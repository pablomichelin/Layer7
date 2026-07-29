/*
 * Chave/hash bidireccional de fluxo IPv4.
 *
 * Os dois sentidos da mesma conversa precisam cair no mesmo bucket para que
 * o nDPI receba ida e volta no mesmo ndpi_flow_struct.
 */
#ifndef LAYER7_CAPTURE_FLOW_KEY_H
#define LAYER7_CAPTURE_FLOW_KEY_H

#include <limits.h>
#include <stdint.h>

#define LAYER7_CAPTURE_NO_SLOT UINT32_MAX

/*
 * Estado do probe open-addressing.
 *
 * Um slot livre não encerra a procura: expiração cria buracos dentro da
 * janela e o mesmo fluxo pode existir depois deles. Primeiro examinamos a
 * janela inteira; só depois escolhemos o primeiro slot livre ou, sob
 * pressão, o slot em uso menos recentemente visto.
 */
struct layer7_capture_probe {
	uint32_t match_slot;
	uint32_t first_free_slot;
	uint32_t oldest_slot;
	uint64_t oldest_seen;
};

static inline void
layer7_capture_probe_init(struct layer7_capture_probe *probe)
{
	probe->match_slot = LAYER7_CAPTURE_NO_SLOT;
	probe->first_free_slot = LAYER7_CAPTURE_NO_SLOT;
	probe->oldest_slot = LAYER7_CAPTURE_NO_SLOT;
	probe->oldest_seen = UINT64_MAX;
}

static inline void
layer7_capture_probe_observe(struct layer7_capture_probe *probe,
    uint32_t slot, int in_use, int matches, uint64_t last_seen)
{
	if (!in_use) {
		if (probe->first_free_slot == LAYER7_CAPTURE_NO_SLOT)
			probe->first_free_slot = slot;
		return;
	}
	if (matches && probe->match_slot == LAYER7_CAPTURE_NO_SLOT)
		probe->match_slot = slot;
	if (probe->oldest_slot == LAYER7_CAPTURE_NO_SLOT ||
	    last_seen < probe->oldest_seen) {
		probe->oldest_slot = slot;
		probe->oldest_seen = last_seen;
	}
}

static inline uint32_t
layer7_capture_probe_select(const struct layer7_capture_probe *probe,
    int create, int *found, int *evict)
{
	if (found)
		*found = 0;
	if (evict)
		*evict = 0;
	if (probe->match_slot != LAYER7_CAPTURE_NO_SLOT) {
		if (found)
			*found = 1;
		return probe->match_slot;
	}
	if (!create)
		return LAYER7_CAPTURE_NO_SLOT;
	if (probe->first_free_slot != LAYER7_CAPTURE_NO_SLOT)
		return probe->first_free_slot;
	if (probe->oldest_slot != LAYER7_CAPTURE_NO_SLOT) {
		if (evict)
			*evict = 1;
		return probe->oldest_slot;
	}
	return LAYER7_CAPTURE_NO_SLOT;
}

static inline uint32_t
layer7_capture_flow_hash(uint32_t sa, uint32_t da, uint16_t sp, uint16_t dp,
    uint8_t proto, uint32_t mask)
{
	uint32_t h, tmp_ip;
	uint16_t tmp_port;

	/* Canonicaliza os endpoints por ordem lexicografica (IP, porta). */
	if (sa > da || (sa == da && sp > dp)) {
		tmp_ip = sa;
		sa = da;
		da = tmp_ip;
		tmp_port = sp;
		sp = dp;
		dp = tmp_port;
	}

	h = sa ^ da ^ ((uint32_t)sp << 16 | dp) ^ proto;
	h ^= h >> 16;
	h *= 0x45d9f3bU;
	h ^= h >> 16;
	return h & mask;
}

/*
 * nDPI 5.x pode expor um protocolo parcial antes de concluir a
 * classificação. Encerrar nesse ponto perde refinamentos posteriores
 * (por exemplo TLS -> YouTube) e metadados como SNI.
 */
static inline int
layer7_capture_should_finalize(int ndpi_classification_final,
    uint32_t packet_count, uint32_t packet_budget)
{
	if (ndpi_classification_final)
		return 1;
	return packet_budget > 0 && packet_count >= packet_budget;
}

#endif /* LAYER7_CAPTURE_FLOW_KEY_H */
