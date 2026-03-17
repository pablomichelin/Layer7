/*
 * Layer7 pfSense — PoC nDPI (Bloco 3)
 * Lê PCAP IPv4 TCP/UDP, classifica com libnDPI, imprime JSONL por fluxo detectado.
 *
 * Build (após compilar nDPI — ver scripts/build/build-poc-freebsd.sh):
 *   cc -O2 -o layer7_ndpi_poc layer7_ndpi_poc.c \
 *     -I "$NDPI_SRC/src/include" \
 *     -L "$NDPI_SRC/src/lib/.libs" -lndpi -lpcap -lm
 */

#define _GNU_SOURCE
#include <errno.h>
#include <inttypes.h>
#include <pcap.h>
#include <stdbool.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <time.h>

#include <netinet/in.h>

#include "ndpi_api.h"

#define NBUCKETS 4096
#define MAX_FLOWS 20000

struct flow_node {
	struct flow_node *next;
	uint32_t ip_a, ip_b;
	uint16_t port_a, port_b;
	uint8_t l4_proto;
	struct ndpi_flow_struct *ndpi_flow;
	int emitted;
};

static struct flow_node *buckets[NBUCKETS];
static struct ndpi_detection_module_struct *ndpi;
static unsigned long flow_count;
static unsigned long pkts;
static unsigned long detected_events;

static uint32_t tuple_hash(uint32_t a, uint32_t b, uint16_t pa, uint16_t pb, uint8_t p)
{
	uint32_t h = a ^ (b << 1) ^ ((uint32_t)pa << 3) ^ ((uint32_t)pb << 5) ^ (uint32_t)p;
	return h % NBUCKETS;
}

static void norm_ipv4_tuple(uint32_t saddr, uint32_t daddr, uint16_t sport, uint16_t dport,
			    uint32_t *a, uint32_t *b, uint16_t *pa, uint16_t *pb)
{
	if (saddr < daddr || (saddr == daddr && sport <= dport)) {
		*a = saddr;
		*b = daddr;
		*pa = sport;
		*pb = dport;
	} else {
		*a = daddr;
		*b = saddr;
		*pa = dport;
		*pb = sport;
	}
}

static struct flow_node *flow_get(uint32_t saddr, uint32_t daddr, uint16_t sport, uint16_t dport,
				  uint8_t l4_proto)
{
	uint32_t a, b;
	uint16_t pa, pb;
	norm_ipv4_tuple(saddr, daddr, sport, dport, &a, &b, &pa, &pb);
	uint32_t h = tuple_hash(a, b, pa, pb, l4_proto);

	for (struct flow_node *n = buckets[h]; n; n = n->next) {
		if (n->ip_a == a && n->ip_b == b && n->port_a == pa && n->port_b == pb &&
		    n->l4_proto == l4_proto)
			return n;
	}

	if (flow_count >= MAX_FLOWS) {
		fprintf(stderr, "layer7_ndpi_poc: limite de fluxos (%d), pacotes seguintes ignorados\n",
			MAX_FLOWS);
		return NULL;
	}

	struct flow_node *n = (struct flow_node *)calloc(1, sizeof(*n));
	if (!n)
		return NULL;
	n->ip_a = a;
	n->ip_b = b;
	n->port_a = pa;
	n->port_b = pb;
	n->l4_proto = l4_proto;
	n->ndpi_flow = (struct ndpi_flow_struct *)ndpi_flow_malloc(sizeof(struct ndpi_flow_struct));
	if (!n->ndpi_flow) {
		free(n);
		return NULL;
	}
	memset(n->ndpi_flow, 0, sizeof(struct ndpi_flow_struct));
	n->next = buckets[h];
	buckets[h] = n;
	flow_count++;
	return n;
}

static void emit_json_flow(uint64_t ts_ms, struct flow_node *fn, ndpi_protocol *proto, const char *confidence)
{
	const char *m = ndpi_get_proto_name(ndpi, proto->proto.master_protocol);
	const char *a = ndpi_get_proto_name(ndpi, proto->proto.app_protocol);
	const char *c = ndpi_category_get_name(ndpi, proto->category);
	/* IPs em decimal para PoC; produção usaria string ou binário normalizado */
	/* endpoint_* = tupla canonizada (menor IP/porta primeiro) */
	printf("{\"v\":1,\"ts_ms\":%" PRIu64 ",\"confidence\":\"%s\",\"master\":\"%s\",\"app\":\"%s\","
	       "\"category\":\"%s\",\"endpoint_a_ip\":%" PRIu32 ",\"endpoint_b_ip\":%" PRIu32 ","
	       "\"endpoint_a_port\":%u,\"endpoint_b_port\":%u,\"l4\":\"%s\"}\n",
	       ts_ms, confidence, m ? m : "?", a ? a : "?", c ? c : "?", fn->ip_a, fn->ip_b, fn->port_a,
	       fn->port_b, fn->l4_proto == IPPROTO_TCP ? "tcp" : "udp");
	fflush(stdout);
	detected_events++;
	fn->emitted = 1;
}

static void process_pcap(const char *path)
{
	char err[PCAP_ERRBUF_SIZE];
	pcap_t *ph = pcap_open_offline(path, err);
	if (!ph) {
		fprintf(stderr, "pcap_open_offline: %s\n", err);
		exit(1);
	}

	int dlt = pcap_datalink(ph);
	if (dlt != DLT_EN10MB && dlt != DLT_LINUX_SLL && dlt != DLT_LINUX_SLL2) {
		fprintf(stderr, "DLT %d não suportado neste PoC (use Ethernet ou LINUX_SLL)\n", dlt);
		pcap_close(ph);
		exit(1);
	}

	struct timespec t0, t1;
	clock_gettime(CLOCK_MONOTONIC, &t0);

	struct pcap_pkthdr *hdr;
	const u_char *pkt;
	int r;

	while ((r = pcap_next_ex(ph, &hdr, &pkt)) >= 0) {
		if (r == 0)
			continue;
		pkts++;

		unsigned off = 0;
		if (dlt == DLT_EN10MB)
			off = 14;
		else if (dlt == DLT_LINUX_SLL)
			off = 16;
		else if (dlt == DLT_LINUX_SLL2)
			off = 20;

		if (hdr->caplen < off + (unsigned)sizeof(struct ndpi_iphdr))
			continue;

		const uint8_t *l3 = pkt + off;
		const struct ndpi_iphdr *ip = (const struct ndpi_iphdr *)l3;
		if (ip->version != 4)
			continue;

		unsigned ihl = (unsigned)ip->ihl * 4U;
		if (ihl < sizeof(struct ndpi_iphdr) || hdr->caplen < off + ihl)
			continue;

		uint16_t tot_len = ntohs(ip->tot_len);
		if (tot_len < ihl)
			continue;
		unsigned ip_len = tot_len;
		if (off + ip_len > hdr->caplen)
			ip_len = hdr->caplen - off;

		const uint8_t *l4_ptr;
		uint32_t l4_len;
		uint8_t l4_proto;

		if (ndpi_detection_get_l4((uint8_t *)ip, (uint16_t)ip_len, (const uint8_t **)&l4_ptr, &l4_len,
					  &l4_proto, NDPI_DETECTION_ONLY_IPV4) != 0)
			continue;

		if (l4_proto != IPPROTO_TCP && l4_proto != IPPROTO_UDP)
			continue;

		uint16_t sport, dport;
		if (l4_proto == IPPROTO_TCP) {
			if (l4_len < sizeof(struct ndpi_tcphdr))
				continue;
			const struct ndpi_tcphdr *tcp = (const struct ndpi_tcphdr *)l4_ptr;
			sport = ntohs(tcp->source);
			dport = ntohs(tcp->dest);
		} else {
			if (l4_len < sizeof(struct ndpi_udphdr))
				continue;
			const struct ndpi_udphdr *udp = (const struct ndpi_udphdr *)l4_ptr;
			sport = ntohs(udp->source);
			dport = ntohs(udp->dest);
		}

		uint32_t saddr = ip->saddr;
		uint32_t daddr = ip->daddr;

		struct flow_node *fn = flow_get(saddr, daddr, sport, dport, l4_proto);
		if (!fn)
			continue;

		uint64_t ts_ms =
		    (uint64_t)hdr->ts.tv_sec * 1000ULL + (uint64_t)hdr->ts.tv_usec / 1000ULL;

		ndpi_protocol p = ndpi_detection_process_packet(ndpi, fn->ndpi_flow, (uint8_t *)ip,
								(uint16_t)ip_len, ts_ms, NULL);

		if (!fn->emitted && ndpi_is_protocol_detected(p) != 0 &&
		    (p.proto.master_protocol != NDPI_PROTOCOL_UNKNOWN ||
		     p.proto.app_protocol != NDPI_PROTOCOL_UNKNOWN)) {
			emit_json_flow(ts_ms, fn, &p, "detected");
		}
	}

	pcap_close(ph);
	clock_gettime(CLOCK_MONOTONIC, &t1);
	double sec = (t1.tv_sec - t0.tv_sec) + (t1.tv_nsec - t0.tv_nsec) / 1e9;
	fprintf(stderr,
		"--- layer7_ndpi_poc: pkts=%lu flows=%lu events=%lu tempo_s=%.3f pkts_per_s=%.0f ---\n",
		pkts, flow_count, detected_events, sec, sec > 0 ? (double)pkts / sec : 0);
}

static void free_flows(void)
{
	for (unsigned i = 0; i < NBUCKETS; i++) {
		struct flow_node *n = buckets[i];
		while (n) {
			struct flow_node *nx = n->next;
			if (n->ndpi_flow)
				ndpi_flow_free(n->ndpi_flow);
			free(n);
			n = nx;
		}
		buckets[i] = NULL;
	}
}

int main(int argc, char **argv)
{
	if (argc != 2) {
		fprintf(stderr, "Uso: %s <arquivo.pcap>\n", argv[0]);
		return 2;
	}

	ndpi = ndpi_init_detection_module(NULL);
	if (!ndpi) {
		fprintf(stderr, "ndpi_init_detection_module falhou\n");
		return 1;
	}
	if (ndpi_finalize_initialization(ndpi) != 0) {
		fprintf(stderr, "ndpi_finalize_initialization falhou\n");
		ndpi_exit_detection_module(ndpi);
		return 1;
	}

	process_pcap(argv[1]);

	ndpi_exit_detection_module(ndpi);
	free_flows();
	return 0;
}
