/*
 * layer7-tlsproxy — helper MITM opt-in (PoC lab).
 *
 * PoC-0 idle · PoC-1 IPC · PoC-2 TLS · PoC-3 SNI bypass/block + HTTPS page
 * Lab: root@192.168.100.54 only for destructive TLS.
 * Squid rejected. docs/09-blocking/poc-layer7-tlsproxy-lab.md
 */

#include "ipc.h"
#include "tls_lab.h"

#include <stdio.h>
#include <stdlib.h>
#include <string.h>

#ifndef LAYER7_TLSPROXY_VERSION
#define LAYER7_TLSPROXY_VERSION "0.0.3-poc3"
#endif

static void
usage(const char *argv0)
{
	fprintf(stderr,
	    "usage: %s [--version] [--health] [--help]\n"
	    "       %s --ipc-serve [--sock PATH] [--oneshot]\n"
	    "       %s --ipc-ping  [--sock PATH]\n"
	    "       %s --lab-tls-listen [HOST:PORT] --cert CRT --key KEY \\\n"
	    "          [--bypass-sni HOST]... [--block-sni HOST]... [--oneshot]\n"
	    "\n"
	    "Lab only: LAYER7_TLSPROXY_LAB=1. Default TLS bind 127.0.0.1.\n"
	    "Never claims mitm_effective=true. Never on .254/.234/.235.\n",
	    argv0, argv0, argv0, argv0);
}

static int
parse_host_port(const char *spec, char *host, size_t host_sz, int *port)
{
	const char *colon;
	size_t hlen;

	if (spec == NULL || host == NULL || port == NULL)
		return -1;
	colon = strrchr(spec, ':');
	if (colon == NULL || colon == spec)
		return -1;
	hlen = (size_t)(colon - spec);
	if (hlen == 0 || hlen >= host_sz)
		return -1;
	memcpy(host, spec, hlen);
	host[hlen] = '\0';
	*port = atoi(colon + 1);
	if (*port <= 0 || *port > 65535)
		return -1;
	return 0;
}

static int
cmd_health(void)
{
	printf("{\n");
	printf("  \"service\": \"layer7-tlsproxy\",\n");
	printf("  \"version\": \"%s\",\n", LAYER7_TLSPROXY_VERSION);
	printf("  \"mode\": \"poc3\",\n");
	printf("  \"bind\": false,\n");
	printf("  \"intercept\": false,\n");
	printf("  \"ipc_capable\": true,\n");
	printf("  \"tls_lab_capable\": true,\n");
	printf("  \"sni_policy\": true,\n");
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
	const char *cert = NULL;
	const char *key = NULL;
	const char *listen_spec = "127.0.0.1:8443";
	char host[64];
	int port = 8443;
	int oneshot = 0;
	int i;
	int mode_serve = 0;
	int mode_ping = 0;
	int mode_tls = 0;

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
		if (strcmp(argv[i], "--health") == 0)
			return cmd_health();
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
		if (strcmp(argv[i], "--lab-tls-listen") == 0) {
			mode_tls = 1;
			if (i + 1 < argc && argv[i + 1][0] != '-')
				listen_spec = argv[++i];
			continue;
		}
		if (strcmp(argv[i], "--lab-allow-any") == 0) {
			l7_tls_set_allow_any(1);
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
		if (strcmp(argv[i], "--cert") == 0) {
			if (i + 1 >= argc) {
				fprintf(stderr, "--cert requires PATH\n");
				return 2;
			}
			cert = argv[++i];
			continue;
		}
		if (strcmp(argv[i], "--key") == 0) {
			if (i + 1 >= argc) {
				fprintf(stderr, "--key requires PATH\n");
				return 2;
			}
			key = argv[++i];
			continue;
		}
		if (strcmp(argv[i], "--bypass-sni") == 0) {
			if (i + 1 >= argc) {
				fprintf(stderr, "--bypass-sni requires HOST\n");
				return 2;
			}
			if (l7_tls_policy_add_bypass(argv[++i]) != 0) {
				fprintf(stderr, "invalid/too many --bypass-sni\n");
				return 2;
			}
			continue;
		}
		if (strcmp(argv[i], "--block-sni") == 0) {
			if (i + 1 >= argc) {
				fprintf(stderr, "--block-sni requires HOST\n");
				return 2;
			}
			if (l7_tls_policy_add_block(argv[++i]) != 0) {
				fprintf(stderr, "invalid/too many --block-sni\n");
				return 2;
			}
			continue;
		}
		if (strcmp(argv[i], "--lab-allow-bind") == 0) {
			if (!l7_ipc_lab_ok()) {
				fprintf(stderr,
				    "layer7-tlsproxy: refusing bind — "
				    "set LAYER7_TLSPROXY_LAB=1 "
				    "(never on production .254).\n");
				return 3;
			}
			fprintf(stderr,
			    "layer7-tlsproxy: use --lab-tls-listen.\n");
			return 4;
		}
		fprintf(stderr, "unknown option: %s\n", argv[i]);
		usage(argv[0]);
		return 2;
	}

	if ((mode_serve + mode_ping + mode_tls) > 1) {
		fprintf(stderr, "choose one mode only\n");
		return 2;
	}
	if (mode_serve)
		return l7_ipc_serve(sock, oneshot);
	if (mode_ping)
		return l7_ipc_ping(sock);
	if (mode_tls) {
		if (parse_host_port(listen_spec, host, sizeof(host), &port) != 0) {
			fprintf(stderr, "invalid listen spec (want HOST:PORT)\n");
			return 2;
		}
		return l7_tls_lab_listen(host, port, cert, key, oneshot);
	}

	usage(argv[0]);
	return 2;
}
