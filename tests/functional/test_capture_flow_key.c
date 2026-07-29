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

	forward = layer7_capture_flow_hash(a, b, 53124, 443, 6, FLOW_MASK);
	reverse = layer7_capture_flow_hash(b, a, 443, 53124, 6, FLOW_MASK);
	other = layer7_capture_flow_hash(a, b, 53125, 443, 6, FLOW_MASK);

	check(forward == reverse, "TCP ida/volta usa o mesmo bucket");
	check(forward != other, "porta diferente altera a chave");
	check(layer7_capture_flow_hash(a, b, 53000, 53, 17, FLOW_MASK) ==
	    layer7_capture_flow_hash(b, a, 53, 53000, 17, FLOW_MASK),
	    "UDP ida/volta usa o mesmo bucket");

	if (g_fail) {
		printf("RESULT: FAIL\n");
		return 1;
	}
	printf("RESULT: PASS\n");
	return 0;
}
