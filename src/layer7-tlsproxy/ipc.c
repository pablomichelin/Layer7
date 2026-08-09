/*
 * PoC-1 IPC framing for layer7-tlsproxy.
 *
 * Frame: uint32 BE length + UTF-8 JSON body (max 4096).
 * Ops: PING → reply with ok/ts/mitm_effective:false
 *      STATUS → same honesty fields
 *
 * Socket only under /tmp/ when LAYER7_TLSPROXY_LAB=1.
 * Production path /var/run/layer7/mitm.sock is refused in PoC.
 */

#include "ipc.h"

#include <errno.h>
#include <fcntl.h>
#include <signal.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <sys/socket.h>
#include <sys/stat.h>
#include <sys/un.h>
#include <time.h>
#include <unistd.h>

static volatile sig_atomic_t l7_ipc_stop;

static void
l7_ipc_on_signal(int sig)
{
	(void)sig;
	l7_ipc_stop = 1;
}

int
l7_ipc_lab_ok(void)
{
	const char *lab = getenv("LAYER7_TLSPROXY_LAB");

	return (lab != NULL && strcmp(lab, "1") == 0);
}

int
l7_ipc_path_allowed(const char *path)
{
	size_t len;

	if (path == NULL || path[0] == '\0')
		return 0;
	len = strlen(path);
	if (len >= sizeof(((struct sockaddr_un *)0)->sun_path))
		return 0;
	/* Never production mitm socket. */
	if (strstr(path, "/var/run/layer7") != NULL)
		return 0;
	if (strncmp(path, "/var/", 5) == 0)
		return 0;
	/* Lab: /tmp/... or relative path without .. (make test / cwd). */
	if (strncmp(path, "/tmp/", 5) == 0)
		return 1;
	if (path[0] != '/' && strstr(path, "..") == NULL)
		return 1;
	return 0;
}

static int
write_full(int fd, const void *buf, size_t n)
{
	const unsigned char *p = buf;
	size_t off = 0;

	while (off < n) {
		ssize_t w = write(fd, p + off, n - off);

		if (w < 0) {
			if (errno == EINTR)
				continue;
			return -1;
		}
		if (w == 0)
			return -1;
		off += (size_t)w;
	}
	return 0;
}

static int
read_full(int fd, void *buf, size_t n)
{
	unsigned char *p = buf;
	size_t off = 0;

	while (off < n) {
		ssize_t r = read(fd, p + off, n - off);

		if (r < 0) {
			if (errno == EINTR)
				continue;
			return -1;
		}
		if (r == 0)
			return -1;
		off += (size_t)r;
	}
	return 0;
}

static int
send_frame(int fd, const char *json)
{
	unsigned char hdr[4];
	size_t n = strlen(json);

	if (n == 0 || n > L7_IPC_MAX_BODY)
		return -1;
	hdr[0] = (unsigned char)((n >> 24) & 0xff);
	hdr[1] = (unsigned char)((n >> 16) & 0xff);
	hdr[2] = (unsigned char)((n >> 8) & 0xff);
	hdr[3] = (unsigned char)(n & 0xff);
	if (write_full(fd, hdr, 4) != 0)
		return -1;
	return write_full(fd, json, n);
}

static int
recv_frame(int fd, char *body, size_t body_cap, size_t *out_len)
{
	unsigned char hdr[4];
	size_t n;

	if (read_full(fd, hdr, 4) != 0)
		return -1;
	n = ((size_t)hdr[0] << 24) | ((size_t)hdr[1] << 16) |
	    ((size_t)hdr[2] << 8) | (size_t)hdr[3];
	if (n == 0 || n > L7_IPC_MAX_BODY || n >= body_cap)
		return -1;
	if (read_full(fd, body, n) != 0)
		return -1;
	body[n] = '\0';
	*out_len = n;
	return 0;
}

static int
json_has_op(const char *json, const char *op)
{
	char needle[64];
	int n;

	n = snprintf(needle, sizeof(needle), "\"op\":\"%s\"", op);
	if (n <= 0 || (size_t)n >= sizeof(needle))
		return 0;
	return strstr(json, needle) != NULL;
}

static int
handle_request(int fd, const char *req)
{
	char reply[512];
	time_t now = time(NULL);

	/*
	 * Fail-closed honesty: PoC never asserts mitm_effective.
	 * Contract PING reply shape: ok + ts (+ explicit effective false).
	 */
	if (json_has_op(req, "PING") || strstr(req, "\"op\"") == NULL) {
		snprintf(reply, sizeof(reply),
		    "{\"op\":\"PING\",\"ok\":true,\"ts\":%lld,"
		    "\"mitm_effective\":false,\"runtime\":\"poc1\"}",
		    (long long)now);
		return send_frame(fd, reply);
	}
	if (json_has_op(req, "STATUS")) {
		snprintf(reply, sizeof(reply),
		    "{\"op\":\"STATUS\",\"ok\":true,\"ts\":%lld,"
		    "\"mitm_effective\":false,\"mitm_entitled\":false,"
		    "\"bind\":false,\"intercept\":false}",
		    (long long)now);
		return send_frame(fd, reply);
	}
	snprintf(reply, sizeof(reply),
	    "{\"op\":\"ERROR\",\"ok\":false,\"error\":\"unknown_op\","
	    "\"mitm_effective\":false}");
	return send_frame(fd, reply);
}

static int
serve_one_client(int cfd)
{
	char body[L7_IPC_MAX_BODY + 1];
	size_t n;

	if (recv_frame(cfd, body, sizeof(body), &n) != 0)
		return -1;
	return handle_request(cfd, body);
}

int
l7_ipc_serve(const char *sock_path, int oneshot)
{
	struct sockaddr_un addr;
	int lfd = -1;
	int rc = 1;

	if (!l7_ipc_lab_ok()) {
		fprintf(stderr,
		    "layer7-tlsproxy: refusing IPC serve — "
		    "set LAYER7_TLSPROXY_LAB=1 (lab only).\n");
		return 3;
	}
	if (!l7_ipc_path_allowed(sock_path)) {
		fprintf(stderr,
		    "layer7-tlsproxy: refusing socket path '%s' — "
		    "PoC allows /tmp/... or relative lab path "
		    "(never /var/run/layer7).\n",
		    sock_path ? sock_path : "(null)");
		return 3;
	}

	l7_ipc_stop = 0;
	signal(SIGINT, l7_ipc_on_signal);
	signal(SIGTERM, l7_ipc_on_signal);

	unlink(sock_path);
	lfd = socket(AF_UNIX, SOCK_STREAM, 0);
	if (lfd < 0) {
		perror("socket");
		return 1;
	}

	memset(&addr, 0, sizeof(addr));
	addr.sun_family = AF_UNIX;
	strncpy(addr.sun_path, sock_path, sizeof(addr.sun_path) - 1);

	if (bind(lfd, (struct sockaddr *)&addr, sizeof(addr)) != 0) {
		perror("bind");
		goto out;
	}
	(void)chmod(sock_path, 0600);
	if (listen(lfd, 4) != 0) {
		perror("listen");
		goto out;
	}

	fprintf(stderr,
	    "layer7-tlsproxy: IPC serve on %s (lab; mitm_effective=false)\n",
	    sock_path);

	while (!l7_ipc_stop) {
		int cfd = accept(lfd, NULL, NULL);

		if (cfd < 0) {
			if (errno == EINTR)
				continue;
			perror("accept");
			break;
		}
		(void)serve_one_client(cfd);
		close(cfd);
		if (oneshot) {
			rc = 0;
			break;
		}
	}
	if (l7_ipc_stop)
		rc = 0;

out:
	if (lfd >= 0)
		close(lfd);
	unlink(sock_path);
	return rc;
}

int
l7_ipc_ping(const char *sock_path)
{
	struct sockaddr_un addr;
	char body[L7_IPC_MAX_BODY + 1];
	size_t n;
	int fd = -1;
	int rc = 1;

	if (!l7_ipc_lab_ok()) {
		fprintf(stderr,
		    "layer7-tlsproxy: refusing IPC ping — "
		    "set LAYER7_TLSPROXY_LAB=1 (lab only).\n");
		return 3;
	}
	if (!l7_ipc_path_allowed(sock_path)) {
		fprintf(stderr,
		    "layer7-tlsproxy: refusing socket path '%s'.\n",
		    sock_path ? sock_path : "(null)");
		return 3;
	}

	fd = socket(AF_UNIX, SOCK_STREAM, 0);
	if (fd < 0) {
		perror("socket");
		return 1;
	}
	memset(&addr, 0, sizeof(addr));
	addr.sun_family = AF_UNIX;
	strncpy(addr.sun_path, sock_path, sizeof(addr.sun_path) - 1);
	if (connect(fd, (struct sockaddr *)&addr, sizeof(addr)) != 0) {
		perror("connect");
		goto out;
	}
	if (send_frame(fd, "{\"op\":\"PING\"}") != 0) {
		fprintf(stderr, "send PING failed\n");
		goto out;
	}
	if (recv_frame(fd, body, sizeof(body), &n) != 0) {
		fprintf(stderr, "recv PING reply failed\n");
		goto out;
	}
	fputs(body, stdout);
	fputc('\n', stdout);
	if (strstr(body, "\"ok\":true") == NULL) {
		fprintf(stderr, "PING reply missing ok:true\n");
		goto out;
	}
	if (strstr(body, "\"mitm_effective\":true") != NULL) {
		fprintf(stderr, "FAIL: reply claimed mitm_effective=true\n");
		rc = 5;
		goto out;
	}
	if (strstr(body, "\"mitm_effective\":false") == NULL) {
		fprintf(stderr, "FAIL: reply must assert mitm_effective:false\n");
		rc = 5;
		goto out;
	}
	rc = 0;
out:
	if (fd >= 0)
		close(fd);
	return rc;
}
