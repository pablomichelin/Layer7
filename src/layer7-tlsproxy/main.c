/*
 * layer7-tlsproxy — helper MITM opt-in (PoC lab).
 *
 * PoC-0: idle (--version / --health).
 * PoC-1: IPC PING on unix socket under /tmp with LAYER7_TLSPROXY_LAB=1.
 *
 * No TLS terminate, no PF, no production bind.
 * Squid is rejected. See docs/09-blocking/poc-layer7-tlsproxy-lab.md
 */

#include "ipc.h"

#include <stdio.h>
#include <stdlib.h>
#include <string.h>

#ifndef LAYER7_TLSPROXY_VERSION
#define LAYER7_TLSPROXY_VERSION "0.0.1-poc1"
#endif

static void
usage(const char *argv0)
{
	fprintf(stderr,
	    "usage: %s [--version] [--health] [--help]\n"
	    "       %s --ipc-serve [--sock PATH] [--oneshot]\n"
	    "       %s --ipc-ping  [--sock PATH]\n"
	    "       %s --lab-allow-bind   (REQUIRES LAYER7_TLSPROXY_LAB=1; no TCP yet)\n"
	    "\n"
	    "PoC-1: IPC only under /tmp with LAYER7_TLSPROXY_LAB=1.\n"
	    "Never claims mitm_effective=true. No intercept on production hosts.\n",
	    argv0, argv0, argv0, argv0);
}

static int
cmd_health(void)
{
	printf("{\n");
	printf("  \"service\": \"layer7-tlsproxy\",\n");
	printf("  \"version\": \"%s\",\n", LAYER7_TLSPROXY_VERSION);
	printf("  \"mode\": \"poc1-idle\",\n");
	printf("  \"bind\": false,\n");
	printf("  \"intercept\": false,\n");
	printf("  \"ipc_capable\": true,\n");
	printf("  \"mitm_effective_claim\": false,\n");
	printf("  \"lab_env\": %s,\n",
	    l7_ipc_lab_ok() ? "true" : "false");
	printf("  \"status\": \"ok\"\n");
	printf("}\n");
	return 0;
}

int
main(int argc, char **argv)
{
	const char *sock = L7_IPC_DEFAULT_SOCK;
	int oneshot = 0;
	int i;
	int mode_serve = 0;
	int mode_ping = 0;

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
		if (strcmp(argv[i], "--ipc-serve") == 0) {
			mode_serve = 1;
			continue;
		}
		if (strcmp(argv[i], "--ipc-ping") == 0) {
			mode_ping = 1;
			continue;
		}
		if (strcmp(argv[i], "--oneshot") == 0) {
			oneshot = 1;
			continue;
		}
		if (strcmp(argv[i], "--sock") == 0) {
			if (i + 1 >= argc) {
				fprintf(stderr, "--sock requires PATH\n");
				return 2;
			}
			sock = argv[++i];
			continue;
		}
		if (strcmp(argv[i], "--lab-allow-bind") == 0) {
			if (!l7_ipc_lab_ok()) {
				fprintf(stderr,
				    "layer7-tlsproxy: refusing bind — "
				    "set LAYER7_TLSPROXY_LAB=1 for PoC only "
				    "(never on production .254).\n");
				return 3;
			}
			fprintf(stderr,
			    "layer7-tlsproxy: PoC-1 has no TCP bind yet.\n"
			    "lab flag accepted; intercept still disabled.\n");
			return 4;
		}
		fprintf(stderr, "unknown option: %s\n", argv[i]);
		usage(argv[0]);
		return 2;
	}

	if (mode_serve && mode_ping) {
		fprintf(stderr, "choose --ipc-serve or --ipc-ping, not both\n");
		return 2;
	}
	if (mode_serve)
		return l7_ipc_serve(sock, oneshot);
	if (mode_ping)
		return l7_ipc_ping(sock);

	usage(argv[0]);
	return 2;
}
