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

static int
pfctl_kill_states(const char *first, const char *second)
{
	pid_t pid;
	int st;
	char first_buf[16], second_buf[16];
	char *argv[7];
	char path_pfctl[] = "/sbin/pfctl";

	if (!layer7_pf_ipv4_host_ok(first))
		return -1;
	if (second && !layer7_pf_ipv4_host_ok(second))
		return -1;
	memcpy(first_buf, first, strlen(first) + 1);
	if (second)
		memcpy(second_buf, second, strlen(second) + 1);

	argv[0] = path_pfctl;
	argv[1] = "-k";
	argv[2] = first_buf;
	if (second) {
		argv[3] = "-k";
		argv[4] = second_buf;
		argv[5] = NULL;
	} else
		argv[3] = NULL;

	pid = fork();
	if (pid == (pid_t)-1)
		return -1;
	if (pid == 0) {
		execv(path_pfctl, argv);
		_exit(127);
	}
	if (waitpid(pid, &st, 0) != pid)
		return -1;
	return (WIFEXITED(st) && WEXITSTATUS(st) == 0) ? 0 : -1;
}

int
layer7_pf_exec_kill_state_pair(const char *src_ip, const char *dst_ip)
{
	return pfctl_kill_states(src_ip, dst_ip);
}

int
layer7_pf_exec_kill_states_host(const char *ip)
{
	return pfctl_kill_states(ip, NULL);
}

int
layer7_pf_exec_kill_states_to(const char *dst_ip)
{
	pid_t pid;
	int st;
	char dst_buf[16];
	char *argv[7];
	char path_pfctl[] = "/sbin/pfctl";
	char any_ipv4[] = "0.0.0.0/0";

	if (!layer7_pf_ipv4_host_ok(dst_ip))
		return -1;
	memcpy(dst_buf, dst_ip, strlen(dst_ip) + 1);
	argv[0] = path_pfctl;
	argv[1] = "-k";
	argv[2] = any_ipv4;
	argv[3] = "-k";
	argv[4] = dst_buf;
	argv[5] = NULL;

	pid = fork();
	if (pid == (pid_t)-1)
		return -1;
	if (pid == 0) {
		execv(path_pfctl, argv);
		_exit(127);
	}
	if (waitpid(pid, &st, 0) != pid)
		return -1;
	return (WIFEXITED(st) && WEXITSTATUS(st) == 0) ? 0 : -1;
}

int
layer7_pf_policy_table_name(enum layer7_enforce_kind kind, int idx,
    char *buf, size_t buflen)
{
	if (!buf || buflen < 16 || idx < 0 || idx >= L7_MAX_POLICIES)
		return -1;
	if (kind == L7_ENFORCE_DST_SCOPED)
		return snprintf(buf, buflen, "layer7_pdst_%d", idx);
	if (kind == L7_ENFORCE_SRC_SCOPED)
		return snprintf(buf, buflen, "layer7_psrc_%d", idx);
	return -1;
}

const char *
layer7_enforce_kind_str(enum layer7_enforce_kind kind)
{
	switch (kind) {
	case L7_ENFORCE_DST_SCOPED:
		return "dst_scoped";
	case L7_ENFORCE_SRC_SCOPED:
		return "src_scoped";
	default:
		return "none";
	}
}

int
layer7_pf_resolve_block_target(const struct layer7_decision *dec,
    const char *src_ip, const char *dst_ip, int scoped_hybrid,
    char *out_table, size_t tbl_len, const char **out_ip)
{
	const char *ip;

	if (!dec || !out_table || !out_ip)
		return -1;
	if (!dec->would_enforce_block_or_tag ||
	    dec->action != LAYER7_ACTION_BLOCK)
		return 0;

	if (scoped_hybrid && dec->enforce_kind != L7_ENFORCE_NONE &&
	    dec->policy_table_idx >= 0) {
		if (layer7_pf_policy_table_name(dec->enforce_kind,
		    dec->policy_table_idx, out_table, tbl_len) < 0)
			return -1;
		if (dec->enforce_kind == L7_ENFORCE_DST_SCOPED) {
			ip = (dec->enforce_dst_ip[0] != '\0') ?
			    dec->enforce_dst_ip : dst_ip;
		} else
			ip = src_ip;
	} else {
		if (tbl_len < sizeof(L7_PF_TABLE_BLOCK_DST))
			return -1;
		memcpy(out_table, L7_PF_TABLE_BLOCK_DST,
		    sizeof(L7_PF_TABLE_BLOCK_DST));
		ip = dst_ip;
	}

	if (!ip || !layer7_pf_ipv4_host_ok(ip))
		return 0;
	*out_ip = ip;
	return 1;
}

int
layer7_pf_enforce_decision(const struct layer7_decision *dec,
    const char *src_ipv4, const char *dst_ipv4, int scoped_hybrid,
    int dry_run)
{
	char tbl[64];
	const char *ip;

	if (!dec || !src_ipv4)
		return 0;

	if (dec->action == LAYER7_ACTION_TAG) {
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

	if (dec->action != LAYER7_ACTION_BLOCK)
		return 0;

	switch (layer7_pf_resolve_block_target(dec, src_ipv4, dst_ipv4,
	    scoped_hybrid, tbl, sizeof(tbl), &ip)) {
	case 0:
		return 0;
	case -1:
		return -1;
	default:
		break;
	}

	if (dry_run)
		return 1;
	if (layer7_pf_exec_table_add(tbl, ip) == 0)
		return 1;
	return -1;
}
