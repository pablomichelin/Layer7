/*
 * capture.c — pcap live capture + nDPI flow classification.
 *
 * Fluxo:
 *  1. pcap_open_live na interface
 *  2. Para cada pacote: extrair 5-tuple IPv4 ou IPv6 (passo 12.4)
 *  3. Procurar/criar fluxo na hash table (linear probing)
 *  4. Alimentar ndpi_detection_process_packet
 *  5. Quando classificado → invocar callback com (src_ip, app, cat)
 *  6. Expirar fluxos inativos periodicamente
 *
 * Limitações:
 *  - IPv6: extension headers tratados de forma conservadora (S-06);
 *    DNS hint: A + AAAA (transporte IPv4 ou IPv6)
 *  - Tabela de fluxos com tamanho fixo (hash open-addressing)
 *  - Sem reassembly TCP
 */
#include "capture.h"
#include "capture_flow_key.h"
#include "dns_observe.h"

#include <arpa/inet.h>
#include <net/ethernet.h>
#include <netinet/in.h>
#include <netinet/ip.h>
#include <netinet/ip6.h>
#include <netinet/tcp.h>
#include <netinet/udp.h>
#include <pcap/pcap.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <time.h>

#include <ndpi_api.h>
#include <ndpi_main.h>
#include <ndpi_typedefs.h>

#define L7C_MAX_FLOWS    65536
#define L7C_FLOW_MASK    (L7C_MAX_FLOWS - 1)
#define L7C_IDLE_SEC     120
#define L7C_SNAP_DEFAULT 1536
#define L7C_EXPIRE_INTERVAL 10
#define L7C_MAX_PKTS_PER_FLOW 48
#define L7C_FLOW_PROBE_SLOTS 64
#define L7C_DNS_HINTS       1024
#define L7C_DNS_HOST_MAX    256
#define L7C_DNS_PEND        256
#define L7C_DNS_PEND_TTL_SEC 10
#define L7C_IPV6_EXTHDR_MAX 8

struct l7c_flow {
	uint8_t  ip_ver; /* 4 ou 6 */
	uint8_t  classified;
	uint8_t  in_use;
	uint8_t  proto;
	uint16_t src_port;
	uint16_t dst_port;
	uint32_t src_ip; /* host order se ip_ver==4 */
	uint32_t dst_ip;
	uint8_t  src_ip6[16]; /* network order se ip_ver==6 */
	uint8_t  dst_ip6[16];
	time_t   last_seen;
	uint32_t pkt_count;
	struct ndpi_flow_struct *ndpi_flow;
	ndpi_protocol           detected;
};

struct layer7_capture {
	pcap_t                              *pcap;
	struct ndpi_detection_module_struct  *ndpi;
	struct l7c_flow                      flows[L7C_MAX_FLOWS];
	layer7_flow_cb                       cb;
	layer7_dns_cb                        dns_cb;
	layer7_dns_query_cb                  dns_query_cb;
	char                                 ifname[32];
	unsigned long long                   stat_pkts;
	unsigned long long                   stat_pkts_v4;
	unsigned long long                   stat_pkts_v6;
	unsigned long long                   stat_flows_classified;
	unsigned long long                   stat_flows_classified_v4;
	unsigned long long                   stat_flows_classified_v6;
	unsigned long long                   stat_flows_expired;
	unsigned long long                   stat_flows_active;
	unsigned long long                   stat_flows_active_v4;
	unsigned long long                   stat_flows_active_v6;
	unsigned long long                   stat_flows_evicted;
	unsigned long long                   stat_flows_dropped;
	time_t                               last_expire;
	int                                  datalink;
	int                                  protos_loaded;
	int                                  use_sni; /* A3: usar SNI/Host do nDPI */
};

struct l7c_dns_hint {
	uint32_t ip;
	time_t   expires;
	char     host[L7C_DNS_HOST_MAX];
};

struct l7c_dns_hint6 {
	uint8_t  ip6[16];
	uint8_t  in_use;
	time_t   expires;
	char     host[L7C_DNS_HOST_MAX];
};

static struct l7c_dns_hint s_dns_hints[L7C_DNS_HINTS];
static struct l7c_dns_hint6 s_dns_hints6[L7C_DNS_HINTS];

/* Correlação query→resposta (anti-spoof LAN): txid + cliente. */
struct l7c_dns_pend {
	uint8_t  in_use;
	uint16_t id;
	time_t   expires;
	char     client[INET6_ADDRSTRLEN];
};

static struct l7c_dns_pend s_dns_pend[L7C_DNS_PEND];
static unsigned int s_dns_pend_next;

static void
dns_pend_remember(const char *client_ip, uint16_t id, time_t now)
{
	struct l7c_dns_pend *p;
	unsigned int i;

	if (!client_ip || !*client_ip)
		return;
	for (i = 0; i < L7C_DNS_PEND; i++) {
		p = &s_dns_pend[i];
		if (p->in_use && p->id == id &&
		    strcmp(p->client, client_ip) == 0) {
			p->expires = now + L7C_DNS_PEND_TTL_SEC;
			return;
		}
	}
	p = &s_dns_pend[s_dns_pend_next++ % L7C_DNS_PEND];
	p->in_use = 1;
	p->id = id;
	p->expires = now + L7C_DNS_PEND_TTL_SEC;
	snprintf(p->client, sizeof(p->client), "%s", client_ip);
}

static int
dns_pend_consume(const char *client_ip, uint16_t id, time_t now)
{
	unsigned int i;

	if (!client_ip || !*client_ip)
		return 0;
	for (i = 0; i < L7C_DNS_PEND; i++) {
		struct l7c_dns_pend *p = &s_dns_pend[i];
		if (!p->in_use)
			continue;
		if (p->expires < now) {
			p->in_use = 0;
			continue;
		}
		if (p->id == id && strcmp(p->client, client_ip) == 0) {
			p->in_use = 0;
			return 1;
		}
	}
	return 0;
}

static uint32_t
ip6_hint_hash(const uint8_t ip6[16])
{
	uint32_t h = 2166136261u;
	unsigned int i;

	for (i = 0; i < 16; i++) {
		h ^= ip6[i];
		h *= 16777619u;
	}
	return h;
}

static int
ip_is_private(uint32_t ip)
{
	if ((ip & 0xff000000U) == 0x0a000000U)
		return 1;
	if ((ip & 0xfff00000U) == 0xac100000U)
		return 1;
	if ((ip & 0xffff0000U) == 0xc0a80000U)
		return 1;
	return 0;
}

/* ULA fc00::/7 — heurística de “cliente local” para orientação do log. */
static int
ip6_is_ula(const uint8_t a[16])
{
	return (a[0] & 0xfe) == 0xfc;
}

/*
 * Avança past extension headers IPv6 até TCP/UDP/ICMPv6 ou fim.
 * Fragmentos não-iniciais (offset != 0) são rejeitados para DPI (S-06).
 * Retorna 0 em sucesso; -1 se o pacote deve ser ignorado.
 */
static int
ipv6_l4_offset(const u_char *ip_data, uint16_t ip_len, uint8_t *proto_out,
    uint16_t *l4_off_out)
{
	const struct ip6_hdr *ip6h;
	uint8_t nh;
	uint16_t off;
	int guard;

	if (ip_len < sizeof(struct ip6_hdr))
		return -1;
	ip6h = (const struct ip6_hdr *)ip_data;
	if (((ip_data[0] >> 4) & 0x0f) != 6)
		return -1;

	nh = ip6h->ip6_nxt;
	off = (uint16_t)sizeof(struct ip6_hdr);

	for (guard = 0; guard < L7C_IPV6_EXTHDR_MAX; guard++) {
		uint16_t hdrlen;

		if (nh == IPPROTO_TCP || nh == IPPROTO_UDP ||
		    nh == IPPROTO_ICMPV6 || nh == IPPROTO_NONE) {
			*proto_out = nh;
			*l4_off_out = off;
			return 0;
		}

		if (off + 2 > ip_len)
			return -1;

		if (nh == IPPROTO_FRAGMENT) {
			uint16_t frag_off;

			if (off + 8 > ip_len)
				return -1;
			frag_off = (uint16_t)((ip_data[off + 2] << 8) |
			    ip_data[off + 3]);
			/* offset em unidades de 8 octetos; bits 0-2 = flags */
			if ((frag_off & 0xfff8U) != 0)
				return -1; /* não-inicial: sem L4 completo */
			nh = ip_data[off];
			off = (uint16_t)(off + 8);
			continue;
		}

		if (nh == IPPROTO_AH) {
			/* AH: comprimento em unidades de 4 octetos - 2 */
			hdrlen = (uint16_t)((ip_data[off + 1] + 2) * 4);
			if (hdrlen < 8 || off + hdrlen > ip_len)
				return -1;
			nh = ip_data[off];
			off = (uint16_t)(off + hdrlen);
			continue;
		}

		if (nh == IPPROTO_HOPOPTS || nh == IPPROTO_ROUTING ||
		    nh == IPPROTO_DSTOPTS) {
			/* hdrlen em unidades de 8 octetos, exclui os primeiros 8 */
			hdrlen = (uint16_t)((ip_data[off + 1] + 1) * 8);
			if (hdrlen < 8 || off + hdrlen > ip_len)
				return -1;
			nh = ip_data[off];
			off = (uint16_t)(off + hdrlen);
			continue;
		}

		/* ESP / desconhecido: não avançar para L4 genérico */
		return -1;
	}
	return -1;
}

static void flow_free(struct layer7_capture *cap, struct l7c_flow *f);

static struct l7c_flow *
flow_lookup_v4(struct layer7_capture *cap, uint32_t sa, uint32_t da,
    uint16_t sp, uint16_t dp, uint8_t proto, int create)
{
	uint32_t idx = layer7_capture_flow_hash(sa, da, sp, dp, proto,
	    L7C_FLOW_MASK);
	uint32_t i;
	uint32_t selected;
	struct layer7_capture_probe probe;
	struct l7c_flow *f;
	int found, evict;

	layer7_capture_probe_init(&probe);
	for (i = 0; i < L7C_FLOW_PROBE_SLOTS; i++) {
		uint32_t slot = (idx + i) & L7C_FLOW_MASK;
		int matches;

		f = &cap->flows[slot];
		matches = f->in_use && f->ip_ver == 4 && f->proto == proto &&
		    ((f->src_ip == sa && f->dst_ip == da &&
		    f->src_port == sp && f->dst_port == dp) ||
		    (f->src_ip == da && f->dst_ip == sa &&
		    f->src_port == dp && f->dst_port == sp));
		layer7_capture_probe_observe(&probe, slot, f->in_use, matches,
		    f->last_seen > 0 ? (uint64_t)f->last_seen : 0);
	}

	selected = layer7_capture_probe_select(&probe, create, &found, &evict);
	if (selected == LAYER7_CAPTURE_NO_SLOT)
		return NULL;
	f = &cap->flows[selected];
	if (found)
		return f;

	if (evict) {
		flow_free(cap, f);
		cap->stat_flows_evicted++;
	}

	memset(f, 0, sizeof(*f));
	f->ip_ver = 4;
	f->src_ip = sa;
	f->dst_ip = da;
	f->src_port = sp;
	f->dst_port = dp;
	f->proto = proto;
	f->in_use = 1;
	f->ndpi_flow = (struct ndpi_flow_struct *)
	    ndpi_flow_malloc(SIZEOF_FLOW_STRUCT);
	if (!f->ndpi_flow) {
		memset(f, 0, sizeof(*f));
		cap->stat_flows_dropped++;
		return NULL;
	}
	memset(f->ndpi_flow, 0, SIZEOF_FLOW_STRUCT);
	cap->stat_flows_active++;
	cap->stat_flows_active_v4++;
	return f;
}

static struct l7c_flow *
flow_lookup_v6(struct layer7_capture *cap, const uint8_t sa[16],
    const uint8_t da[16], uint16_t sp, uint16_t dp, uint8_t proto, int create)
{
	uint32_t idx = layer7_capture_flow_hash_v6(sa, da, sp, dp, proto,
	    L7C_FLOW_MASK);
	uint32_t i;
	uint32_t selected;
	struct layer7_capture_probe probe;
	struct l7c_flow *f;
	int found, evict;

	layer7_capture_probe_init(&probe);
	for (i = 0; i < L7C_FLOW_PROBE_SLOTS; i++) {
		uint32_t slot = (idx + i) & L7C_FLOW_MASK;
		int matches;

		f = &cap->flows[slot];
		matches = 0;
		if (f->in_use && f->ip_ver == 6 && f->proto == proto) {
			if ((memcmp(f->src_ip6, sa, 16) == 0 &&
			    memcmp(f->dst_ip6, da, 16) == 0 &&
			    f->src_port == sp && f->dst_port == dp) ||
			    (memcmp(f->src_ip6, da, 16) == 0 &&
			    memcmp(f->dst_ip6, sa, 16) == 0 &&
			    f->src_port == dp && f->dst_port == sp))
				matches = 1;
		}
		layer7_capture_probe_observe(&probe, slot, f->in_use, matches,
		    f->last_seen > 0 ? (uint64_t)f->last_seen : 0);
	}

	selected = layer7_capture_probe_select(&probe, create, &found, &evict);
	if (selected == LAYER7_CAPTURE_NO_SLOT)
		return NULL;
	f = &cap->flows[selected];
	if (found)
		return f;

	if (evict) {
		flow_free(cap, f);
		cap->stat_flows_evicted++;
	}

	memset(f, 0, sizeof(*f));
	f->ip_ver = 6;
	memcpy(f->src_ip6, sa, 16);
	memcpy(f->dst_ip6, da, 16);
	f->src_port = sp;
	f->dst_port = dp;
	f->proto = proto;
	f->in_use = 1;
	f->ndpi_flow = (struct ndpi_flow_struct *)
	    ndpi_flow_malloc(SIZEOF_FLOW_STRUCT);
	if (!f->ndpi_flow) {
		memset(f, 0, sizeof(*f));
		cap->stat_flows_dropped++;
		return NULL;
	}
	memset(f->ndpi_flow, 0, SIZEOF_FLOW_STRUCT);
	cap->stat_flows_active++;
	cap->stat_flows_active_v6++;
	return f;
}

static void
dns_hint_store(uint32_t ip, const char *host, time_t now)
{
	unsigned int i, slot;

	if (!ip || !host || host[0] == '\0' || strcmp(host, ".") == 0)
		return;

	slot = (unsigned int)(ip % L7C_DNS_HINTS);
	for (i = 0; i < 16; i++) {
		struct l7c_dns_hint *h = &s_dns_hints[(slot + i) % L7C_DNS_HINTS];
		if (h->ip == 0 || h->ip == ip || h->expires <= now) {
			h->ip = ip;
			h->expires = now + 600;
			snprintf(h->host, sizeof(h->host), "%s", host);
			return;
		}
	}
}

static const char *
dns_hint_lookup(uint32_t ip, time_t now)
{
	unsigned int i, slot;

	if (!ip)
		return NULL;
	slot = (unsigned int)(ip % L7C_DNS_HINTS);
	for (i = 0; i < 16; i++) {
		struct l7c_dns_hint *h = &s_dns_hints[(slot + i) % L7C_DNS_HINTS];
		if (h->ip == ip) {
			if (h->expires > now && h->host[0] != '\0')
				return h->host;
			h->ip = 0;
			h->host[0] = '\0';
			h->expires = 0;
			return NULL;
		}
		if (h->ip == 0)
			return NULL;
	}
	return NULL;
}

static void
dns_hint6_store(const uint8_t ip6[16], const char *host, time_t now)
{
	unsigned int i, slot;
	static const uint8_t zero[16];

	if (!ip6 || !host || host[0] == '\0' || strcmp(host, ".") == 0)
		return;
	if (memcmp(ip6, zero, 16) == 0)
		return;

	slot = (unsigned int)(ip6_hint_hash(ip6) % L7C_DNS_HINTS);
	for (i = 0; i < 16; i++) {
		struct l7c_dns_hint6 *h =
		    &s_dns_hints6[(slot + i) % L7C_DNS_HINTS];
		if (!h->in_use || memcmp(h->ip6, ip6, 16) == 0 ||
		    h->expires <= now) {
			memcpy(h->ip6, ip6, 16);
			h->in_use = 1;
			h->expires = now + 600;
			snprintf(h->host, sizeof(h->host), "%s", host);
			return;
		}
	}
}

static const char *
dns_hint6_lookup(const uint8_t ip6[16], time_t now)
{
	unsigned int i, slot;
	static const uint8_t zero[16];

	if (!ip6 || memcmp(ip6, zero, 16) == 0)
		return NULL;
	slot = (unsigned int)(ip6_hint_hash(ip6) % L7C_DNS_HINTS);
	for (i = 0; i < 16; i++) {
		struct l7c_dns_hint6 *h =
		    &s_dns_hints6[(slot + i) % L7C_DNS_HINTS];
		if (h->in_use && memcmp(h->ip6, ip6, 16) == 0) {
			if (h->expires > now && h->host[0] != '\0')
				return h->host;
			h->in_use = 0;
			h->host[0] = '\0';
			h->expires = 0;
			return NULL;
		}
		if (!h->in_use)
			return NULL;
	}
	return NULL;
}

struct l7c_dns_obs_ctx {
	struct layer7_capture *cap;
	const char *client_ip;
	time_t now;
};

static void
dns_obs_rr_cb(int af, const uint8_t *addr, uint32_t ttl, const char *qname,
    void *ud)
{
	struct l7c_dns_obs_ctx *ctx = ud;
	char ip_str[INET6_ADDRSTRLEN];

	if (!ctx || !ctx->cap || !addr || !qname)
		return;

	if (af == 4) {
		uint32_t ip = ((uint32_t)addr[0] << 24) |
		    ((uint32_t)addr[1] << 16) |
		    ((uint32_t)addr[2] << 8) |
		    (uint32_t)addr[3];
		struct in_addr ina;

		dns_hint_store(ip, qname, ctx->now);
		if (!ctx->cap->dns_cb)
			return;
		ina.s_addr = htonl(ip);
		if (!inet_ntop(AF_INET, &ina, ip_str, sizeof(ip_str)))
			return;
		ctx->cap->dns_cb(ctx->cap->ifname, ctx->client_ip, qname,
		    ip_str, ttl);
		return;
	}

	if (af == 6) {
		dns_hint6_store(addr, qname, ctx->now);
		if (!ctx->cap->dns_cb)
			return;
		if (!inet_ntop(AF_INET6, addr, ip_str, sizeof(ip_str)))
			return;
		ctx->cap->dns_cb(ctx->cap->ifname, ctx->client_ip, qname,
		    ip_str, ttl);
	}
}

static void
observe_dns_response(struct layer7_capture *cap, const char *client_ip,
    uint16_t sp, uint16_t dp, const uint8_t *payload, uint16_t payload_len,
    time_t now)
{
	struct l7c_dns_obs_ctx ctx;
	uint16_t dns_id;

	if (sp != 53 || dp == 53 || !payload || payload_len < 12 || !client_ip)
		return;
	/* QR deve ser 1 (resposta). */
	if ((payload[2] & 0x80U) == 0)
		return;
	dns_id = (uint16_t)((payload[0] << 8) | payload[1]);
	if (!dns_pend_consume(client_ip, dns_id, now))
		return;

	ctx.cap = cap;
	ctx.client_ip = client_ip;
	ctx.now = now;
	(void)layer7_dns_foreach_a_aaaa(payload, payload_len, dns_obs_rr_cb,
	    &ctx);
}

static void
observe_dns_query(struct layer7_capture *cap, const char *src_ip,
    const char *resolver_ip, uint16_t sp, uint16_t dp,
    const uint8_t *payload, uint16_t payload_len, time_t now)
{
	size_t off;
	uint16_t qd;
	uint16_t dns_id;
	char qname[L7C_DNS_HOST_MAX];

	if (dp != 53 || sp == 53 || !payload || payload_len < 12)
		return;
	/* QR deve ser 0 (query). */
	if ((payload[2] & 0x80U) != 0)
		return;
	if (!src_ip)
		return;

	dns_id = (uint16_t)((payload[0] << 8) | payload[1]);
	dns_pend_remember(src_ip, dns_id, now);

	if (!cap || !cap->dns_query_cb)
		return;

	qd = (uint16_t)((payload[4] << 8) | payload[5]);
	if (qd == 0)
		return;

	off = 12;
	if (layer7_dns_read_name(payload, payload_len, &off, qname,
	    sizeof(qname), 0) != 0)
		return;
	if (qname[0] == '\0' || strcmp(qname, ".") == 0)
		return;

	cap->dns_query_cb(cap->ifname, src_ip,
	    resolver_ip ? resolver_ip : "-", qname);
}

/*
 * Caminho A / A3: valida um host (SNI/Host) antes de o usar para matching.
 * Aceita apenas hostnames plausiveis (tem ponto, caracteres de dominio),
 * evitando lixo de parsing. Nao aceita literais IP nem strings vazias.
 */
static int
sni_host_plausible(const char *h)
{
	size_t i, len, dots = 0;

	if (!h || h[0] == '\0')
		return 0;
	len = strlen(h);
	if (len < 4 || len >= 80)
		return 0;
	for (i = 0; i < len; i++) {
		char c = h[i];
		if (c == '.') {
			dots++;
			continue;
		}
		if (!((c >= 'a' && c <= 'z') || (c >= 'A' && c <= 'Z') ||
		    (c >= '0' && c <= '9') || c == '-' || c == '_'))
			return 0;
	}
	return dots >= 1;
}

static void
flow_free(struct layer7_capture *cap, struct l7c_flow *f)
{
	if (!f->in_use)
		return;
	if (f->ndpi_flow) {
		ndpi_flow_free(f->ndpi_flow);
		f->ndpi_flow = NULL;
	}
	if (f->ip_ver == 6) {
		if (cap->stat_flows_active_v6 > 0)
			cap->stat_flows_active_v6--;
	} else if (cap->stat_flows_active_v4 > 0) {
		cap->stat_flows_active_v4--;
	}
	f->in_use = 0;
	if (cap->stat_flows_active > 0)
		cap->stat_flows_active--;
}

static void
expire_idle(struct layer7_capture *cap, time_t now)
{
	uint32_t i;

	if (now - cap->last_expire < L7C_EXPIRE_INTERVAL)
		return;
	cap->last_expire = now;
	for (i = 0; i < L7C_MAX_FLOWS; i++) {
		struct l7c_flow *f = &cap->flows[i];
		if (f->in_use && (now - f->last_seen) > L7C_IDLE_SEC) {
			flow_free(cap, f);
			cap->stat_flows_expired++;
		}
	}
}

struct layer7_capture *
layer7_capture_open(const char *ifname, int snaplen, layer7_flow_cb cb,
    layer7_dns_cb dns_cb, layer7_dns_query_cb dns_query_cb,
    const char *protos_file, char *errbuf, int errbuflen)
{
	struct layer7_capture *cap;
	char pcap_errbuf[PCAP_ERRBUF_SIZE];
	const char *pfile;

	if (!ifname || !cb) {
		if (errbuf)
			snprintf(errbuf, (size_t)errbuflen,
			    "ifname and cb required");
		return NULL;
	}

	cap = calloc(1, sizeof(*cap));
	if (!cap)
		return NULL;

	if (snaplen <= 0)
		snaplen = L7C_SNAP_DEFAULT;

	cap->pcap = pcap_open_live(ifname, snaplen, 1, 100, pcap_errbuf);
	if (!cap->pcap) {
		if (errbuf)
			snprintf(errbuf, (size_t)errbuflen, "pcap: %s",
			    pcap_errbuf);
		free(cap);
		return NULL;
	}

	cap->datalink = pcap_datalink(cap->pcap);
	if (cap->datalink != DLT_EN10MB && cap->datalink != DLT_RAW) {
		if (errbuf)
			snprintf(errbuf, (size_t)errbuflen,
			    "unsupported datalink: %d", cap->datalink);
		pcap_close(cap->pcap);
		free(cap);
		return NULL;
	}

	cap->ndpi = ndpi_init_detection_module(NULL);
	if (!cap->ndpi) {
		if (errbuf)
			snprintf(errbuf, (size_t)errbuflen,
			    "ndpi_init_detection_module failed");
		pcap_close(cap->pcap);
		free(cap);
		return NULL;
	}

	pfile = (protos_file && protos_file[0]) ?
	    protos_file : "/usr/local/etc/layer7-protos.txt";
	{
		FILE *pf = fopen(pfile, "r");
		if (pf) {
			fclose(pf);
			ndpi_load_protocols_file(cap->ndpi, pfile);
			cap->protos_loaded = 1;
		}
	}

	if (ndpi_finalize_initialization(cap->ndpi) != 0) {
		if (errbuf)
			snprintf(errbuf, (size_t)errbuflen,
			    "ndpi_finalize_initialization failed");
		ndpi_exit_detection_module(cap->ndpi);
		pcap_close(cap->pcap);
		free(cap);
		return NULL;
	}

	cap->cb = cb;
	cap->dns_cb = dns_cb;
	cap->dns_query_cb = dns_query_cb;
	cap->use_sni = 0;
	snprintf(cap->ifname, sizeof(cap->ifname), "%s", ifname);
	cap->last_expire = time(NULL);
	return cap;
}

void
layer7_capture_set_sni(struct layer7_capture *cap, int on)
{
	if (cap)
		cap->use_sni = on ? 1 : 0;
}

static void
on_packet(struct layer7_capture *cap, const struct pcap_pkthdr *hdr,
    const u_char *pkt)
{
	const u_char *ip_data;
	uint16_t ip_len;
	uint16_t etype = 0;
	uint8_t ip_ver;
	uint32_t sa4 = 0, da4 = 0;
	uint8_t sa6[16], da6[16];
	uint16_t sp = 0, dp = 0;
	uint8_t proto = 0;
	uint16_t l3_hdr_len = 0;
	struct l7c_flow *f;
	time_t now;
	ndpi_protocol detected;
	const uint8_t *l4_data = NULL;
	uint16_t l4_len = 0;

	cap->stat_pkts++;
	memset(sa6, 0, sizeof(sa6));
	memset(da6, 0, sizeof(da6));

	if (cap->datalink == DLT_EN10MB) {
		if (hdr->caplen < 14)
			return;
		etype = ntohs(*(const uint16_t *)(pkt + 12));
		if (etype == 0x8100) {
			if (hdr->caplen < 18)
				return;
			etype = ntohs(*(const uint16_t *)(pkt + 16));
			ip_data = pkt + 18;
			ip_len = (uint16_t)(hdr->caplen - 18);
		} else {
			ip_data = pkt + 14;
			ip_len = (uint16_t)(hdr->caplen - 14);
		}
		if (etype != 0x0800 && etype != 0x86DD)
			return;
	} else {
		ip_data = pkt;
		ip_len = (uint16_t)hdr->caplen;
		if (ip_len < 1)
			return;
		etype = (((ip_data[0] >> 4) & 0x0f) == 6) ? 0x86DD : 0x0800;
	}

	if (etype == 0x86DD ||
	    (cap->datalink != DLT_EN10MB &&
	    ip_len >= 1 && ((ip_data[0] >> 4) & 0x0f) == 6)) {
		const struct ip6_hdr *ip6h;
		uint16_t l4_off = 0;

		ip_ver = 6;
		if (ipv6_l4_offset(ip_data, ip_len, &proto, &l4_off) != 0)
			return;
		ip6h = (const struct ip6_hdr *)ip_data;
		memcpy(sa6, &ip6h->ip6_src, 16);
		memcpy(da6, &ip6h->ip6_dst, 16);
		l3_hdr_len = l4_off;

		if (proto == IPPROTO_TCP && ip_len >= (uint16_t)(l4_off + 4)) {
			const struct tcphdr *th =
			    (const struct tcphdr *)(ip_data + l4_off);
			sp = ntohs(th->th_sport);
			dp = ntohs(th->th_dport);
			l4_data = ip_data + l4_off;
			l4_len = (uint16_t)(ip_len - l4_off);
		} else if (proto == IPPROTO_UDP &&
		    ip_len >= (uint16_t)(l4_off + 4)) {
			const struct udphdr *uh =
			    (const struct udphdr *)(ip_data + l4_off);
			sp = ntohs(uh->uh_sport);
			dp = ntohs(uh->uh_dport);
			l4_data = ip_data + l4_off;
			l4_len = (uint16_t)(ip_len - l4_off);
		}

		f = flow_lookup_v6(cap, sa6, da6, sp, dp, proto, 1);
	} else {
		const struct ip *iph;
		int ip_hdr_len;

		ip_ver = 4;
		if (ip_len < 20)
			return;
		iph = (const struct ip *)ip_data;
		if (iph->ip_v != 4)
			return;
		ip_hdr_len = iph->ip_hl * 4;
		if (ip_hdr_len < 20 || ip_len < (uint16_t)ip_hdr_len)
			return;
		sa4 = ntohl(iph->ip_src.s_addr);
		da4 = ntohl(iph->ip_dst.s_addr);
		proto = iph->ip_p;
		l3_hdr_len = (uint16_t)ip_hdr_len;
		{
			uint16_t ip_total = ntohs(iph->ip_len);

			/* Preferir comprimento do cabeçalho IP (ignora padding Ethernet). */
			if (ip_total >= (uint16_t)ip_hdr_len &&
			    ip_total <= ip_len)
				ip_len = ip_total;
		}

		if (proto == IPPROTO_TCP &&
		    ip_len >= (uint16_t)(ip_hdr_len + 4)) {
			const struct tcphdr *th =
			    (const struct tcphdr *)(ip_data + ip_hdr_len);
			sp = ntohs(th->th_sport);
			dp = ntohs(th->th_dport);
			l4_data = ip_data + ip_hdr_len;
			l4_len = (uint16_t)(ip_len - ip_hdr_len);
		} else if (proto == IPPROTO_UDP &&
		    ip_len >= (uint16_t)(ip_hdr_len + 4)) {
			const struct udphdr *uh =
			    (const struct udphdr *)(ip_data + ip_hdr_len);
			sp = ntohs(uh->uh_sport);
			dp = ntohs(uh->uh_dport);
			l4_data = ip_data + ip_hdr_len;
			l4_len = (uint16_t)(ip_len - ip_hdr_len);
		}

		f = flow_lookup_v4(cap, sa4, da4, sp, dp, proto, 1);
	}

	(void)l3_hdr_len;
	if (ip_ver == 6)
		cap->stat_pkts_v6++;
	else
		cap->stat_pkts_v4++;

	now = hdr->ts.tv_sec;

	/*
	 * DNS observe independente do fluxo: sob pressão da hash table
	 * flow_lookup pode falhar e não pode silenciar aprendizagem A/AAAA.
	 */
	if (proto == IPPROTO_UDP && l4_data && l4_len >= 12 + 8) {
		char client_str[INET6_ADDRSTRLEN];
		char src_str[INET6_ADDRSTRLEN];
		char dst_str[INET6_ADDRSTRLEN];

		if (ip_ver == 6) {
			inet_ntop(AF_INET6, da6, client_str, sizeof(client_str));
			inet_ntop(AF_INET6, sa6, src_str, sizeof(src_str));
			inet_ntop(AF_INET6, da6, dst_str, sizeof(dst_str));
			observe_dns_query(cap, src_str, dst_str, sp, dp,
			    l4_data + 8, (uint16_t)(l4_len - 8), now);
			observe_dns_response(cap, client_str, sp, dp,
			    l4_data + 8, (uint16_t)(l4_len - 8), now);
		} else if (sa4 || da4) {
			struct in_addr addr;

			addr.s_addr = htonl(da4);
			inet_ntop(AF_INET, &addr, client_str, sizeof(client_str));
			addr.s_addr = htonl(sa4);
			inet_ntop(AF_INET, &addr, src_str, sizeof(src_str));
			addr.s_addr = htonl(da4);
			inet_ntop(AF_INET, &addr, dst_str, sizeof(dst_str));
			observe_dns_query(cap, src_str, dst_str, sp, dp,
			    l4_data + 8, (uint16_t)(l4_len - 8), now);
			observe_dns_response(cap, client_str, sp, dp,
			    l4_data + 8, (uint16_t)(l4_len - 8), now);
		}
	}

	if (!f)
		return;

	f->last_seen = now;
	f->pkt_count++;

	expire_idle(cap, now);

	if (f->classified)
		return;

	{
		uint64_t time_ms = (uint64_t)hdr->ts.tv_sec * 1000 +
		    (uint64_t)hdr->ts.tv_usec / 1000;

		detected = ndpi_detection_process_packet(cap->ndpi, f->ndpi_flow,
		    ip_data, ip_len, time_ms, NULL);
	}

	if (layer7_capture_should_finalize(
	    detected.state == NDPI_STATE_CLASSIFIED,
	    f->pkt_count, L7C_MAX_PKTS_PER_FLOW)) {
		char src_ip_str[INET6_ADDRSTRLEN];
		char dst_ip_str[INET6_ADDRSTRLEN];
		const char *host_hint = NULL;
		char *app_name;
		const char *cat_name;

		if (detected.state != NDPI_STATE_CLASSIFIED)
			detected = ndpi_detection_giveup(cap->ndpi, f->ndpi_flow);

		f->classified = 1;
		f->detected = detected;

		app_name = ndpi_get_proto_name(cap->ndpi,
		    detected.proto.app_protocol != NDPI_PROTOCOL_UNKNOWN ?
		    detected.proto.app_protocol :
		    detected.proto.master_protocol);
		cat_name = ndpi_category_get_name(cap->ndpi, detected.category);

		if (f->ip_ver == 6) {
			uint8_t log_src[16], log_dst[16];

			memcpy(log_src, f->src_ip6, 16);
			memcpy(log_dst, f->dst_ip6, 16);
			if (!ip6_is_ula(log_src) && ip6_is_ula(log_dst)) {
				uint8_t tmp[16];

				memcpy(tmp, log_src, 16);
				memcpy(log_src, log_dst, 16);
				memcpy(log_dst, tmp, 16);
			}
			inet_ntop(AF_INET6, log_src, src_ip_str,
			    sizeof(src_ip_str));
			inet_ntop(AF_INET6, log_dst, dst_ip_str,
			    sizeof(dst_ip_str));
			host_hint = dns_hint6_lookup(log_dst, now);
			if (cap->use_sni && f->ndpi_flow &&
			    f->ndpi_flow->host_server_name[0] != '\0' &&
			    sni_host_plausible(f->ndpi_flow->host_server_name)) {
				host_hint = f->ndpi_flow->host_server_name;
				dns_hint6_store(log_dst, host_hint, now);
			}
		} else {
			struct in_addr addr;
			uint32_t log_src = f->src_ip;
			uint32_t log_dst = f->dst_ip;

			if (!ip_is_private(log_src) && ip_is_private(log_dst)) {
				uint32_t tmp = log_src;

				log_src = log_dst;
				log_dst = tmp;
			}
			addr.s_addr = htonl(log_src);
			inet_ntop(AF_INET, &addr, src_ip_str, sizeof(src_ip_str));
			addr.s_addr = htonl(log_dst);
			inet_ntop(AF_INET, &addr, dst_ip_str, sizeof(dst_ip_str));
			host_hint = dns_hint_lookup(log_dst, now);
			if (cap->use_sni && f->ndpi_flow &&
			    f->ndpi_flow->host_server_name[0] != '\0' &&
			    sni_host_plausible(f->ndpi_flow->host_server_name)) {
				host_hint = f->ndpi_flow->host_server_name;
				dns_hint_store(log_dst, host_hint, now);
			}
		}

		cap->stat_flows_classified++;
		if (f->ip_ver == 6)
			cap->stat_flows_classified_v6++;
		else
			cap->stat_flows_classified_v4++;
		cap->cb(cap->ifname, src_ip_str, dst_ip_str,
		    app_name ? app_name : "Unknown",
		    cat_name ? cat_name : "Unspecified", host_hint);
	}
}

int
layer7_capture_poll(struct layer7_capture *cap, int batch_size)
{
	struct pcap_pkthdr *hdr;
	const u_char *pkt;
	int n = 0, ret;

	if (!cap)
		return -1;
	if (batch_size <= 0)
		batch_size = 64;

	while (n < batch_size) {
		ret = pcap_next_ex(cap->pcap, &hdr, &pkt);
		if (ret == 1) {
			on_packet(cap, hdr, pkt);
			n++;
		} else if (ret == 0) {
			break;
		} else {
			return -1;
		}
	}
	return n;
}

void
layer7_capture_stats(const struct layer7_capture *cap,
    unsigned long long *pkts_total, unsigned long long *flows_active,
    unsigned long long *flows_classified, unsigned long long *flows_expired,
    unsigned long long *flows_evicted, unsigned long long *flows_dropped)
{
	if (!cap)
		return;
	if (pkts_total)
		*pkts_total = cap->stat_pkts;
	if (flows_active)
		*flows_active = cap->stat_flows_active;
	if (flows_classified)
		*flows_classified = cap->stat_flows_classified;
	if (flows_expired)
		*flows_expired = cap->stat_flows_expired;
	if (flows_evicted)
		*flows_evicted = cap->stat_flows_evicted;
	if (flows_dropped)
		*flows_dropped = cap->stat_flows_dropped;
}

void
layer7_capture_stats_af(const struct layer7_capture *cap,
    unsigned long long *pkts_v4, unsigned long long *pkts_v6,
    unsigned long long *active_v4, unsigned long long *active_v6,
    unsigned long long *classified_v4, unsigned long long *classified_v6)
{
	if (!cap)
		return;
	if (pkts_v4)
		*pkts_v4 = cap->stat_pkts_v4;
	if (pkts_v6)
		*pkts_v6 = cap->stat_pkts_v6;
	if (active_v4)
		*active_v4 = cap->stat_flows_active_v4;
	if (active_v6)
		*active_v6 = cap->stat_flows_active_v6;
	if (classified_v4)
		*classified_v4 = cap->stat_flows_classified_v4;
	if (classified_v6)
		*classified_v6 = cap->stat_flows_classified_v6;
}

void
layer7_capture_close(struct layer7_capture *cap)
{
	uint32_t i;

	if (!cap)
		return;
	for (i = 0; i < L7C_MAX_FLOWS; i++)
		flow_free(cap, &cap->flows[i]);
	if (cap->ndpi)
		ndpi_exit_detection_module(cap->ndpi);
	if (cap->pcap)
		pcap_close(cap->pcap);
	free(cap);
}
