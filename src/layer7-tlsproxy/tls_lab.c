/*
 * PoC-2 — minimal TLS acceptor for lab VM (192.168.100.54).
 *
 * Safety:
 *  - LAYER7_TLSPROXY_LAB=1 required
 *  - default bind 127.0.0.1 only (refuse 0.0.0.0 / :: without --lab-allow-any)
 *  - responses never set mitm_effective=true
 *  - no decrypted payload to disk
 */

#include "tls_lab.h"
#include "ipc.h"

#include <arpa/inet.h>
#include <errno.h>
#include <netinet/in.h>
#include <signal.h>
#include <stdint.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <sys/socket.h>
#include <unistd.h>

#include <openssl/err.h>
#include <openssl/ssl.h>

static volatile sig_atomic_t l7_tls_stop;
static int l7_tls_allow_any;

void
l7_tls_set_allow_any(int v)
{
	l7_tls_allow_any = v;
}

static void
on_sig(int sig)
{
	(void)sig;
	l7_tls_stop = 1;
}

static int
host_allowed(const char *host)
{
	if (host == NULL || host[0] == '\0')
		return 0;
	if (strcmp(host, "127.0.0.1") == 0 || strcmp(host, "::1") == 0)
		return 1;
	if (l7_tls_allow_any)
		return 1;
	fprintf(stderr,
	    "layer7-tlsproxy: refusing bind %s — PoC-2 default is 127.0.0.1 "
	    "(pass --lab-allow-any only on disposable lab .54).\n",
	    host);
	return 0;
}

static void
serve_http_ok(SSL *ssl)
{
	char req[1024];
	const char *body =
	    "{\"service\":\"layer7-tlsproxy\",\"poc\":\"2\",\"ok\":true,"
	    "\"mitm_effective\":false,\"intercept\":false}\n";
	char resp[512];
	int n;

	SSL_read(ssl, req, (int)sizeof(req) - 1);
	n = snprintf(resp, sizeof(resp),
	    "HTTP/1.1 200 OK\r\n"
	    "Content-Type: application/json\r\n"
	    "Connection: close\r\n"
	    "Content-Length: %zu\r\n"
	    "\r\n"
	    "%s",
	    strlen(body), body);
	if (n > 0)
		SSL_write(ssl, resp, n);
}

int
l7_tls_lab_listen(const char *bind_host, int bind_port,
    const char *cert_path, const char *key_path, int oneshot)
{
	SSL_CTX *ctx = NULL;
	int lfd = -1;
	int rc = 1;
	struct sockaddr_in addr4;

	if (!l7_ipc_lab_ok()) {
		fprintf(stderr,
		    "layer7-tlsproxy: refusing TLS listen — "
		    "set LAYER7_TLSPROXY_LAB=1 (lab .54 only).\n");
		return 3;
	}
	if (cert_path == NULL || key_path == NULL ||
	    cert_path[0] == '\0' || key_path[0] == '\0') {
		fprintf(stderr, "layer7-tlsproxy: --cert and --key required\n");
		return 2;
	}
	if (bind_port <= 0 || bind_port > 65535) {
		fprintf(stderr, "layer7-tlsproxy: invalid port\n");
		return 2;
	}
	if (!host_allowed(bind_host))
		return 3;

	l7_tls_stop = 0;
	signal(SIGINT, on_sig);
	signal(SIGTERM, on_sig);

	SSL_library_init();
	SSL_load_error_strings();
	OpenSSL_add_ssl_algorithms();

	ctx = SSL_CTX_new(TLS_server_method());
	if (ctx == NULL) {
		ERR_print_errors_fp(stderr);
		goto out;
	}
	SSL_CTX_set_min_proto_version(ctx, TLS1_2_VERSION);
	if (SSL_CTX_use_certificate_file(ctx, cert_path, SSL_FILETYPE_PEM) != 1 ||
	    SSL_CTX_use_PrivateKey_file(ctx, key_path, SSL_FILETYPE_PEM) != 1 ||
	    SSL_CTX_check_private_key(ctx) != 1) {
		ERR_print_errors_fp(stderr);
		goto out;
	}

	lfd = socket(AF_INET, SOCK_STREAM, 0);
	if (lfd < 0) {
		perror("socket");
		goto out;
	}
	{
		int on = 1;
		setsockopt(lfd, SOL_SOCKET, SO_REUSEADDR, &on, sizeof(on));
	}
	memset(&addr4, 0, sizeof(addr4));
	addr4.sin_family = AF_INET;
	addr4.sin_port = htons((uint16_t)bind_port);
	if (inet_pton(AF_INET, bind_host, &addr4.sin_addr) != 1) {
		fprintf(stderr, "layer7-tlsproxy: inet_pton failed for %s "
		    "(PoC-2 IPv4 only for now)\n", bind_host);
		goto out;
	}
	if (bind(lfd, (struct sockaddr *)&addr4, sizeof(addr4)) != 0) {
		perror("bind");
		goto out;
	}
	if (listen(lfd, 16) != 0) {
		perror("listen");
		goto out;
	}

	fprintf(stderr,
	    "layer7-tlsproxy: TLS lab listen %s:%d (poc2; mitm_effective=false)\n",
	    bind_host, bind_port);

	while (!l7_tls_stop) {
		struct sockaddr_in peer;
		socklen_t plen = sizeof(peer);
		int cfd;
		SSL *ssl;

		cfd = accept(lfd, (struct sockaddr *)&peer, &plen);
		if (cfd < 0) {
			if (errno == EINTR)
				continue;
			perror("accept");
			break;
		}
		ssl = SSL_new(ctx);
		if (ssl == NULL) {
			close(cfd);
			continue;
		}
		SSL_set_fd(ssl, cfd);
		if (SSL_accept(ssl) <= 0) {
			ERR_print_errors_fp(stderr);
		} else {
			serve_http_ok(ssl);
		}
		SSL_shutdown(ssl);
		SSL_free(ssl);
		close(cfd);
		if (oneshot) {
			rc = 0;
			break;
		}
	}
	if (l7_tls_stop)
		rc = 0;

out:
	if (lfd >= 0)
		close(lfd);
	if (ctx != NULL)
		SSL_CTX_free(ctx);
	EVP_cleanup();
	return rc;
}
