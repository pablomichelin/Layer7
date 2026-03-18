#include "enforce.h"
#include "policy.h"
#include <ctype.h>
#include <stdio.h>
#include <string.h>
#include <sys/wait.h>
#include <unistd.h>

int
layer7_pf_table_name_ok(const char *name)
{
	const char *p;

	if (!name || !*name || strlen(name) > 63)
		return 0;
	for (p = name; *p; p++) {
		if (!isalnum((unsigned char)*p) && *p != '_')
			return 0;
	}
	return 1;
}

int
layer7_pf_ipv4_host_ok(const char *ip)
{
	int i;
	unsigned val;
	int digits;

	if (!ip || !*ip)
		return 0;
	for (i = 0; i < 4; i++) {
		val = 0;
		digits = 0;
		while (*ip >= '0' && *ip <= '9' && digits < 4) {
			val = val * 10U + (unsigned)(*ip - '0');
			digits++;
			ip++;
		}
		if (digits == 0 || val > 255U)
			return 0;
		if (i < 3) {
			if (*ip != '.')
				return 0;
			ip++;
		}
	}
	return *ip == '\0';
}

int
layer7_pf_snprint_add(char *buf, size_t buflen, const char *table,
    const char *ip)
{
	if (!buf || buflen < 16)
		return -1;
	if (!layer7_pf_table_name_ok(table) || !layer7_pf_ipv4_host_ok(ip))
		return -1;
	return snprintf(buf, buflen, "pfctl -t %s -T add %s", table, ip);
}

static int
pfctl_table_op(const char *table, const char *ip, const char *op)
{
	pid_t pid;
	int st;
	char tb[64], ipb[16];
	char *argv[8];
	char path_pfctl[] = "/sbin/pfctl";

	if (strcmp(op, "add") != 0 && strcmp(op, "delete") != 0)
		return -1;
	if (!layer7_pf_table_name_ok(table) || !layer7_pf_ipv4_host_ok(ip))
		return -1;
	if (strlen(table) >= sizeof(tb) || strlen(ip) >= sizeof(ipb))
		return -1;
	memcpy(tb, table, strlen(table) + 1);
	memcpy(ipb, ip, strlen(ip) + 1);

	argv[0] = path_pfctl;
	argv[1] = "-t";
	argv[2] = tb;
	argv[3] = "-T";
	argv[4] = (char *)op;
	argv[5] = ipb;
	argv[6] = NULL;

	pid = fork();
	if (pid == (pid_t)-1)
		return -1;
	if (pid == 0) {
		execv(path_pfctl, argv);
		_exit(127);
	}
	if (waitpid(pid, &st, 0) != pid)
		return -1;
	if (WIFEXITED(st) && WEXITSTATUS(st) == 0)
		return 0;
	return -1;
}

int
layer7_pf_exec_table_add(const char *table, const char *ip)
{
	return pfctl_table_op(table, ip, "add");
}

int
layer7_pf_exec_table_delete(const char *table, const char *ip)
{
	return pfctl_table_op(table, ip, "delete");
}

int
layer7_pf_enforce_decision(const struct layer7_decision *dec,
    const char *src_ipv4, int dry_run)
{
	if (!dec || !src_ipv4)
		return 0;
	if (!dec->would_enforce_block_or_tag || !dec->pf_table[0])
		return 0;
	if (!layer7_pf_ipv4_host_ok(src_ipv4))
		return 0;
	if (dry_run)
		return 1;
	if (layer7_pf_exec_table_add(dec->pf_table, src_ipv4) == 0)
		return 1;
	return -1;
}
