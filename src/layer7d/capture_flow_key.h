/*
 * Chave/hash bidireccional de fluxo IPv4.
 *
 * Os dois sentidos da mesma conversa precisam cair no mesmo bucket para que
 * o nDPI receba ida e volta no mesmo ndpi_flow_struct.
 */
#ifndef LAYER7_CAPTURE_FLOW_KEY_H
#define LAYER7_CAPTURE_FLOW_KEY_H

#include <stdint.h>

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

#endif /* LAYER7_CAPTURE_FLOW_KEY_H */
