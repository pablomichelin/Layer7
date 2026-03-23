/*
 * capture.c — pcap live capture + nDPI flow classification.
 *
 * Fluxo V1:
 *  1. pcap_open_live na interface
 *  2. Para cada pacote: extrair 5-tuple IPv4
 *  3. Procurar/criar fluxo na hash table (linear probing)
 *  4. Alimentar ndpi_detection_process_packet
 *  5. Quando classificado → invocar callback com (src_ip, app, cat)
 *  6. Expirar fluxos inativos periodicamente
 *
 * Limitações V1:
 *  - Apenas IPv4
 *  - Tabela de fluxos com tamanho fixo (hash open-addressing)
 *  - Sem reassembly TCP
 */
#include "capture.h"

#include <arpa/inet.h>
#include <net/ethernet.h>
#include <netinet/in.h>
#include <netinet/ip.h>
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
#define L7C_DNS_HINTS       1024
#define L7C_DNS_HOST_MAX    256

struct l7c_flow {
	uint32_t src_ip;
	uint32_t dst_ip;
	uint16_t src_port;
	uint16_t dst_port;
	uint8_t  proto;
	uint8_t  classified;
	uint8_t  in_use;
	uint8_t  _pad;
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
	char                                 ifname[32];
	unsigned long long                   stat_pkts;
	unsigned long long                   stat_flows_classified;
	unsigned long long                   stat_flows_expired;
	unsigned long long                   stat_flows_active;
	time_t                               last_expire;
	int                                  datalink;
	int                                  protos_loaded;
};

struct l7c_dns_hint {
	uint32_t ip;
	time_t   expires;
	char     host[L7C_DNS_HOST_MAX];
};

static struct l7c_dns_hint s_dns_hints[L7C_DNS_HINTS];

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

static int
dns_read_name(const uint8_t *msg, size_t msg_len, size_t *off, char *out,
    size_t out_len, int depth)
{
	size_t pos, nout = 0;
	int jumped = 0;

	if (!msg || !off || !out || out_len < 2 || depth > 8 || *off >= msg_len)
		return -1;

	pos = *off;
	out[0] = '\0';

	while (pos < msg_len) {
		uint8_t len = msg[pos++];

		if (len == 0) {
			if (!jumped)
				*off = pos;
			if (nout == 0) {
				out[0] = '.';
				out[1] = '\0';
			}
			return 0;
		}

		if ((len & 0xc0U) == 0xc0U) {
			size_t ptr;

			if (pos >= msg_len)
				return -1;
			ptr = ((size_t)(len & 0x3fU) << 8) | msg[pos++];
			if (ptr >= msg_len)
				return -1;
			if (!jumped) {
				*off = pos;
				jumped = 1;
			}
			pos = ptr;
			if (++depth > 8)
				return -1;
			continue;
		}

		if (len > 63 || pos + len > msg_len)
			return -1;
		if (nout != 0) {
			if (nout + 1 >= out_len)
				return -1;
			out[nout++] = '.';
		}
		if (nout + len >= out_len)
			return -1;
		memcpy(out + nout, msg + pos, len);
		nout += len;
		out[nout] = '\0';
		pos += len;
	}

	return -1;
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
observe_dns_response(struct layer7_capture *cap, uint32_t sa, uint32_t da,
    uint16_t sp, uint16_t dp, const uint8_t *payload, uint16_t payload_len,
    time_t now)
{
	size_t off;
	uint16_t qd, an, i;
	char qname[L7C_DNS_HOST_MAX];

	if (sp != 53 || dp == 53 || !payload || payload_len < 12)
		return;

	qd = (uint16_t)((payload[4] << 8) | payload[5]);
	an = (uint16_t)((payload[6] << 8) | payload[7]);
	if (qd == 0 || an == 0)
		return;

	off = 12;
	if (dns_read_name(payload, payload_len, &off, qname, sizeof(qname), 0) != 0)
		return;
	if (off + 4 > payload_len)
		return;
	off += 4; /* qtype + qclass */

	for (i = 0; i < an && off < payload_len; i++) {
		uint16_t type, class_, rdlen;
		uint32_t ttl;

		if (dns_read_name(payload, payload_len, &off, qname, sizeof(qname), 0) != 0)
			return;
		if (off + 10 > payload_len)
			return;
		type = (uint16_t)((payload[off] << 8) | payload[off + 1]);
		class_ = (uint16_t)((payload[off + 2] << 8) | payload[off + 3]);
		ttl = ((uint32_t)payload[off + 4] << 24) |
		    ((uint32_t)payload[off + 5] << 16) |
		    ((uint32_t)payload[off + 6] << 8) |
		    (uint32_t)payload[off + 7];
		rdlen = (uint16_t)((payload[off + 8] << 8) | payload[off + 9]);
		off += 10;
		if (off + rdlen > payload_len)
			return;
		if (type == 1 && class_ == 1 && rdlen == 4) {
			uint32_t ip = ((uint32_t)payload[off] << 24) |
			    ((uint32_t)payload[off + 1] << 16) |
			    ((uint32_t)payload[off + 2] << 8) |
			    (uint32_t)payload[off + 3];
			(void)sa;
			(void)da;
			dns_hint_store(ip, qname, now);

			if (cap->dns_cb) {
				char ip_str[INET_ADDRSTRLEN];
				struct in_addr addr;
				addr.s_addr = htonl(ip);
				inet_ntop(AF_INET, &addr, ip_str,
				    sizeof(ip_str));
				cap->dns_cb(qname, ip_str, ttl);
			}
		}
		off += rdlen;
	}
}

static uint32_t
flow_hash(uint32_t sa, uint32_t da, uint16_t sp, uint16_t dp, uint8_t p)
{
	uint32_t h = sa ^ da ^ ((uint32_t)sp << 16 | dp) ^ p;
	h ^= h >> 16;
	h *= 0x45d9f3b;
	h ^= h >> 16;
	return h & L7C_FLOW_MASK;
}

static struct l7c_flow *
flow_lookup(struct layer7_capture *cap, uint32_t sa, uint32_t da,
    uint16_t sp, uint16_t dp, uint8_t proto, int create)
{
	uint32_t idx = flow_hash(sa, da, sp, dp, proto);
	uint32_t i;

	for (i = 0; i < 64; i++) {
		uint32_t slot = (idx + i) & L7C_FLOW_MASK;
		struct l7c_flow *f = &cap->flows[slot];

		if (f->in_use &&
		    f->src_ip == sa && f->dst_ip == da &&
		    f->src_port == sp && f->dst_port == dp &&
		    f->proto == proto)
			return f;

		if (f->in_use &&
		    f->src_ip == da && f->dst_ip == sa &&
		    f->src_port == dp && f->dst_port == sp &&
		    f->proto == proto)
			return f;

		if (!f->in_use && create) {
			memset(f, 0, sizeof(*f));
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
				return NULL;
			}
			memset(f->ndpi_flow, 0, SIZEOF_FLOW_STRUCT);
			cap->stat_flows_active++;
			return f;
		}
	}
	return NULL;
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
    layer7_dns_cb dns_cb, const char *protos_file, char *errbuf, int errbuflen)
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
	snprintf(cap->ifname, sizeof(cap->ifname), "%s", ifname);
	cap->last_expire = time(NULL);
	return cap;
}

static void
on_packet(struct layer7_capture *cap, const struct pcap_pkthdr *hdr,
    const u_char *pkt)
{
	const u_char *ip_data;
	uint16_t ip_len;
	const struct ip *iph;
	uint32_t sa, da;
	uint16_t sp = 0, dp = 0;
	uint8_t proto;
	int ip_hdr_len;
	struct l7c_flow *f;
	time_t now;
	ndpi_protocol detected;
	const uint8_t *l4_data = NULL;
	uint16_t l4_len = 0;

	cap->stat_pkts++;

	if (cap->datalink == DLT_EN10MB) {
		if (hdr->caplen < 14)
			return;
		uint16_t etype = ntohs(*(const uint16_t *)(pkt + 12));
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
		if (etype != 0x0800)
			return;
	} else {
		ip_data = pkt;
		ip_len = (uint16_t)hdr->caplen;
	}

	if (ip_len < 20)
		return;

	iph = (const struct ip *)ip_data;
	if (iph->ip_v != 4)
		return;

	ip_hdr_len = iph->ip_hl * 4;
	if (ip_hdr_len < 20 || ip_len < (uint16_t)ip_hdr_len)
		return;

	sa = ntohl(iph->ip_src.s_addr);
	da = ntohl(iph->ip_dst.s_addr);
	proto = iph->ip_p;

	if (proto == IPPROTO_TCP && ip_len >= (uint16_t)(ip_hdr_len + 4)) {
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

	f = flow_lookup(cap, sa, da, sp, dp, proto, 1);
	if (!f)
		return;

	now = hdr->ts.tv_sec;
	f->last_seen = now;
	f->pkt_count++;

	if (proto == IPPROTO_UDP && l4_data && l4_len >= 12 + 8)
		observe_dns_response(cap, sa, da, sp, dp, l4_data + 8,
		    (uint16_t)(l4_len - 8), now);

	if (f->classified)
		return;

	uint64_t time_ms = (uint64_t)hdr->ts.tv_sec * 1000 +
	    (uint64_t)hdr->ts.tv_usec / 1000;

	detected = ndpi_detection_process_packet(cap->ndpi, f->ndpi_flow,
	    ip_data, ip_len, time_ms, NULL);

	if (ndpi_is_protocol_detected(detected) != 0 ||
	    f->pkt_count >= L7C_MAX_PKTS_PER_FLOW) {

		f->classified = 1;
		f->detected = detected;

		char *app_name = ndpi_get_proto_name(cap->ndpi,
		    detected.proto.app_protocol != NDPI_PROTOCOL_UNKNOWN ?
		    detected.proto.app_protocol :
		    detected.proto.master_protocol);

		const char *cat_name = ndpi_category_get_name(cap->ndpi,
		    detected.category);

		char src_ip_str[INET_ADDRSTRLEN];
		char dst_ip_str[INET_ADDRSTRLEN];
		struct in_addr addr;
		uint32_t log_src = f->src_ip;
		uint32_t log_dst = f->dst_ip;
		const char *host_hint;

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

		cap->stat_flows_classified++;
		cap->cb(cap->ifname, src_ip_str, dst_ip_str,
		    app_name ? app_name : "Unknown",
		    cat_name ? cat_name : "Unspecified", host_hint);
	}

	expire_idle(cap, now);
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
    unsigned long long *flows_classified, unsigned long long *flows_expired)
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
