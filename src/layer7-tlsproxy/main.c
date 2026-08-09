/*
 * layer7-tlsproxy — helper MITM opt-in (PoC lab).
 *
 * PoC-0: idle only. No listen, no PF, no TLS terminate.
 * Intercept requires LAYER7_TLSPROXY_LAB=1 and --lab-allow-bind (PoC-1+).
 *
 * Squid is rejected. See docs/09-blocking/poc-layer7-tlsproxy-lab.md
 */

#include <stdio.h>
#include <stdlib.h>
#include <string.h>

#ifndef LAYER7_TLSPROXY_VERSION
#define LAYER7_TLSPROXY_VERSION "0.0.0-poc0"
#endif

static void
usage(const char *argv0)
{
	fprintf(stderr,
	    "usage: %s [--version] [--health] [--help]\n"
	    "       %s --lab-allow-bind   (REQUIRES LAYER7_TLSPROXY_LAB=1; PoC-1+)\n"
	    "\n"
	    "PoC-0: idle helper. Does NOT bind, intercept, or load CA.\n"
	    "Production appliances must not run bind mode.\n",
	    argv0, argv0);
}

static int
cmd_health(void)
{
	printf("{\n");
	printf("  \"service\": \"layer7-tlsproxy\",\n");
	printf("  \"version\": \"%s\",\n", LAYER7_TLSPROXY_VERSION);
	printf("  \"mode\": \"idle\",\n");
	printf("  \"bind\": false,\n");
	printf("  \"intercept\": false,\n");
	printf("  \"mitm_effective_claim\": false,\n");
	printf("  \"lab_env\": %s,\n",
	    getenv("LAYER7_TLSPROXY_LAB") != NULL ? "true" : "false");
	printf("  \"status\": \"ok\"\n");
	printf("}\n");
	return 0;
}

int
main(int argc, char **argv)
{
	int i;

	if (argc < 2) {
		usage(argv[0]);
		return 2;
	}

	for (i = 1; i < argc; i++) {
		if (strcmp(argv[i], "--version") == 0 ||
		    strcmp(argv[i], "-V") == 0) {
			printf("layer7-tlsproxy %s\n", LAYER7_TLSPROXY_VERSION);
			return 0;
		}
		if (strcmp(argv[i], "--health") == 0) {
			return cmd_health();
		}
		if (strcmp(argv[i], "--help") == 0 ||
		    strcmp(argv[i], "-h") == 0) {
			usage(argv[0]);
			return 0;
		}
		if (strcmp(argv[i], "--lab-allow-bind") == 0) {
			const char *lab = getenv("LAYER7_TLSPROXY_LAB");

			if (lab == NULL || strcmp(lab, "1") != 0) {
				fprintf(stderr,
				    "layer7-tlsproxy: refusing bind — "
				    "set LAYER7_TLSPROXY_LAB=1 for PoC only "
				    "(never on production .254).\n");
				return 3;
			}
			fprintf(stderr,
			    "layer7-tlsproxy: PoC-0 has no bind implementation yet.\n"
			    "lab flag accepted; intercept still disabled.\n");
			return 4;
		}
		fprintf(stderr, "unknown option: %s\n", argv[i]);
		usage(argv[0]);
		return 2;
	}

	return 0;
}
