#include <stdint.h>
#include <stdio.h>

#include "../../src/layer7d/capture_flow_key.h"

#define FLOW_MASK 65535U

static int g_fail;

static void
check(int cond, const char *name)
{
	if (cond)
		printf("PASS: %s\n", name);
	else {
		printf("FAIL: %s\n", name);
		g_fail = 1;
	}
}

int
main(void)
{
	uint32_t a = 0x0a000005U;
	uint32_t b = 0x8efa4a0eU;
	uint32_t forward, reverse, other;
	struct layer7_capture_probe probe;
	uint32_t slot;
	int found, evict;
	unsigned int i;

	forward = layer7_capture_flow_hash(a, b, 53124, 443, 6, FLOW_MASK);
	reverse = layer7_capture_flow_hash(b, a, 443, 53124, 6, FLOW_MASK);
	other = layer7_capture_flow_hash(a, b, 53125, 443, 6, FLOW_MASK);

	check(forward == reverse, "TCP ida/volta usa o mesmo bucket");
	check(forward != other, "porta diferente altera a chave");
	check(layer7_capture_flow_hash(a, b, 53000, 53, 17, FLOW_MASK) ==
	    layer7_capture_flow_hash(b, a, 53, 53000, 17, FLOW_MASK),
	    "UDP ida/volta usa o mesmo bucket");

	/* IPv6 — passo 12.4 */
	{
		uint8_t s6[16] = {
			0x20, 0x01, 0x0d, 0xb8, 0, 0, 0, 0,
			0, 0, 0, 0, 0, 0, 0, 0x0a
		};
		uint8_t d6[16] = {
			0x20, 0x01, 0x0d, 0xb8, 0, 0, 0, 0,
			0, 0, 0, 0, 0, 0, 0, 0x01
		};
		uint32_t f6, r6, o6;

		f6 = layer7_capture_flow_hash_v6(s6, d6, 53124, 443, 6,
		    FLOW_MASK);
		r6 = layer7_capture_flow_hash_v6(d6, s6, 443, 53124, 6,
		    FLOW_MASK);
		o6 = layer7_capture_flow_hash_v6(s6, d6, 53125, 443, 6,
		    FLOW_MASK);
		check(f6 == r6, "IPv6 TCP ida/volta usa o mesmo bucket");
		check(f6 != o6, "IPv6 porta diferente altera a chave");
		check(layer7_capture_flow_hash_v6(s6, d6, 53000, 53, 17,
		    FLOW_MASK) ==
		    layer7_capture_flow_hash_v6(d6, s6, 53, 53000, 17,
		    FLOW_MASK),
		    "IPv6 UDP ida/volta usa o mesmo bucket");
	}

	/*
	 * Um slot expirado antes do match não pode provocar uma segunda
	 * alocação para o mesmo fluxo.
	 */
	layer7_capture_probe_init(&probe);
	layer7_capture_probe_observe(&probe, 100, 0, 0, 0);
	layer7_capture_probe_observe(&probe, 101, 1, 1, 50);
	slot = layer7_capture_probe_select(&probe, 1, &found, &evict);
	check(slot == 101 && found == 1 && evict == 0,
	    "probe encontra fluxo depois de buraco expirado");

	layer7_capture_probe_init(&probe);
	layer7_capture_probe_observe(&probe, 200, 1, 0, 30);
	layer7_capture_probe_observe(&probe, 201, 0, 0, 0);
	slot = layer7_capture_probe_select(&probe, 1, &found, &evict);
	check(slot == 201 && found == 0 && evict == 0,
	    "probe usa primeiro slot livre após procurar match");

	layer7_capture_probe_init(&probe);
	for (i = 0; i < 64; i++)
		layer7_capture_probe_observe(&probe, 300 + i, 1, 0,
		    i == 17 ? 1 : 100 + i);
	slot = layer7_capture_probe_select(&probe, 1, &found, &evict);
	check(slot == 317 && found == 0 && evict == 1,
	    "janela cheia selecciona fluxo mais antigo para evicção");

	slot = layer7_capture_probe_select(&probe, 0, &found, &evict);
	check(slot == LAYER7_CAPTURE_NO_SLOT && found == 0 && evict == 0,
	    "lookup sem create não remove fluxo sob pressão");

	check(!layer7_capture_should_finalize(0, 3, 48),
	    "classificação parcial não encerra DPI cedo");
	check(layer7_capture_should_finalize(1, 3, 48),
	    "estado nDPI final encerra DPI");
	check(layer7_capture_should_finalize(0, 48, 48),
	    "orçamento esgotado encerra DPI para giveup");

	if (g_fail) {
		printf("RESULT: FAIL\n");
		return 1;
	}
	printf("RESULT: PASS\n");
	return 0;
}
