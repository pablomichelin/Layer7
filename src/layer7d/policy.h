/*
 * Policy engine V1: policies[], exceptions[], match app+categoria, decisão.
 * Ver docs/core/precedence.md e policy-matrix.md.
 */
#ifndef LAYER7_POLICY_H
#define LAYER7_POLICY_H

#include "../common/layer7_types.h"
#include <stddef.h>
#include <stdint.h>

#define L7_MAX_POLICIES 24
#define L7_MAX_EXCEPTIONS 16
#define L7_POLICY_ID_LEN 80
#define L7_POLICY_NAME_LEN 160
#define L7_POLICY_APP_LEN 64
#define L7_MAX_APPS_PER_POLICY 12
#define L7_POLICY_CAT_LEN 64
#define L7_MAX_CATS_PER_POLICY 8
#define L7_POLICY_HOST_LEN 128
#define L7_MAX_HOSTS_PER_POLICY 32
#define L7_EXC_HOST_LEN 48
#define L7_TAG_TABLE_LEN 64
#ifndef L7_IFACE_NAME_LEN
#define L7_IFACE_NAME_LEN 32
#endif
#define L7_MAX_IFACES_PER_RULE 8
#define L7_MAX_SRC_HOSTS 16
#define L7_MAX_SRC_CIDRS 16

#define L7_MAX_GROUPS 16
#define L7_GROUP_ID_LEN 80
#define L7_GROUP_NAME_LEN 160
#define L7_MAX_GROUP_CIDRS 16
#define L7_MAX_GROUP_HOSTS 16
#define L7_MAX_GROUPS_PER_POLICY 8

struct l7_cidr {
	uint32_t net;
	int prefix;
};

/*
 * Schedule bitmask: bit 0=sun, 1=mon, 2=tue, 3=wed, 4=thu, 5=fri, 6=sat.
 * Times in minutes from midnight (0-1439).
 * has_schedule=0 means always active.
 */
struct l7_schedule {
	int has_schedule;
	uint8_t days;
	int start_min;
	int end_min;
};

struct layer7_policy_rule {
	char id[L7_POLICY_ID_LEN];
	char name[L7_POLICY_NAME_LEN];
	int enabled;
	enum layer7_action action;
	int priority;
	char tag_table[L7_TAG_TABLE_LEN];
	struct l7_schedule schedule;
	int n_ndpi_apps;
	char ndpi_apps[L7_MAX_APPS_PER_POLICY][L7_POLICY_APP_LEN];
	int n_ndpi_cats;
	char ndpi_cats[L7_MAX_CATS_PER_POLICY][L7_POLICY_CAT_LEN];
	int n_hosts;
	char hosts[L7_MAX_HOSTS_PER_POLICY][L7_POLICY_HOST_LEN];
	int n_ifaces;
	char ifaces[L7_MAX_IFACES_PER_RULE][L7_IFACE_NAME_LEN];
	int n_src_hosts;
	char src_hosts[L7_MAX_SRC_HOSTS][L7_EXC_HOST_LEN];
	int n_src_cidrs;
	struct l7_cidr src_cidrs[L7_MAX_SRC_CIDRS];
	int n_groups;
	char groups[L7_MAX_GROUPS_PER_POLICY][L7_GROUP_ID_LEN];
};

struct layer7_group {
	char id[L7_GROUP_ID_LEN];
	char name[L7_GROUP_NAME_LEN];
	int n_cidrs;
	struct l7_cidr cidrs[L7_MAX_GROUP_CIDRS];
	int n_hosts;
	char hosts[L7_MAX_GROUP_HOSTS][L7_EXC_HOST_LEN];
};

int layer7_schedule_active(const struct l7_schedule *s);

#define L7_EXC_MAX_HOSTS 8
#define L7_EXC_MAX_CIDRS 8

struct layer7_exception {
	char id[L7_POLICY_ID_LEN];
	int enabled;
	int n_hosts;
	char hosts[L7_EXC_MAX_HOSTS][L7_EXC_HOST_LEN];
	int n_cidrs;
	struct l7_cidr cidrs[L7_EXC_MAX_CIDRS];
	int n_ifaces;
	char ifaces[L7_MAX_IFACES_PER_RULE][L7_IFACE_NAME_LEN];
	int priority;
	enum layer7_action action;
};

enum layer7_decide_reason {
	L7_DECIDE_EXCEPTION = 1,
	L7_DECIDE_POLICY_MATCH = 2,
	L7_DECIDE_DEFAULT_MONITOR = 3,
	L7_DECIDE_DEFAULT_ALLOW = 4,
};

struct layer7_decision {
	enum layer7_action action;
	enum layer7_decide_reason reason;
	char matched_policy_id[L7_POLICY_ID_LEN];
	char matched_exception_id[L7_POLICY_ID_LEN];
	int would_enforce_block_or_tag;
	char pf_table[L7_TAG_TABLE_LEN]; /* sugerida se would_enforce block/tag */
};

int layer7_policies_parse(const char *json, size_t len,
    struct layer7_policy_rule *out, int *n_out, int max_out);
void layer7_policies_sort(struct layer7_policy_rule *rules, int n);

int layer7_groups_parse(const char *json, size_t len,
    struct layer7_group *out, int *n_out, int max_out);
void layer7_policies_expand_groups(struct layer7_policy_rule *rules,
    int n_rules, const struct layer7_group *groups, int n_groups);

int layer7_exceptions_parse(const char *json, size_t len,
    struct layer7_exception *out, int *n_out, int max_out);
void layer7_exceptions_sort(struct layer7_exception *exc, int n);

/*
 * Avalia exceções (por prioridade desc.) depois políticas.
 * iface: nome da interface onde o fluxo foi capturado (e.g. "em0"); NULL = ignora
 * src_ip: IPv4 dotted ou NULL (exceções não casam sem IP).
 */
void layer7_flow_decide(const struct layer7_exception *exc, int n_exc,
    const struct layer7_policy_rule *rules, int n_rules, int global_enforce,
    const char *iface, const char *src_ip,
    const char *ndpi_app, const char *ndpi_category, const char *host,
    struct layer7_decision *dec);

const char *layer7_action_str(enum layer7_action a);
const char *layer7_decide_reason_str(enum layer7_decide_reason r);

/*
 * Verifica se um dominio DNS casa com alguma politica de bloqueio activa.
 * Retorna 1 se bloqueado, 0 se nao.
 */
int layer7_domain_is_blocked(const struct layer7_policy_rule *rules,
    int n_rules, const char *domain);

#endif
