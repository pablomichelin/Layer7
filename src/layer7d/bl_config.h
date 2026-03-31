/*
 * bl_config.h — Parse do config.json das blacklists.
 *
 * v1.2.0: suporta regras por IP/CIDR de origem (rules[]).
 * Retrocompativel com formato v1.1.0 (flat categories[]).
 */
#ifndef LAYER7_BL_CONFIG_H
#define LAYER7_BL_CONFIG_H

#include "blacklist.h"

#define L7_BL_MAX_RULES     8
#define L7_BL_RULE_NAME_LEN 64
#define L7_BL_RULE_CIDRS    16
#define L7_BL_RULE_EXCEPT   16

struct l7_bl_rule {
	char name[L7_BL_RULE_NAME_LEN];
	int enabled;
	int force_dns;	/* redirect all DNS from src_cidrs to local Unbound */
	char categories[L7_BL_MAX_CATS][L7_BL_CAT_LEN];
	int n_categories;
	char src_cidrs[L7_BL_RULE_CIDRS][48];
	int n_src_cidrs;
	char except_ips[L7_BL_RULE_EXCEPT][48];
	int n_except_ips;
};

struct l7_bl_config {
	int enabled;
	char whitelist[L7_BL_WL_MAX][L7_BL_DOMAIN_MAX];
	int n_whitelist;
	struct l7_bl_rule rules[L7_BL_MAX_RULES];
	int n_rules;
};

/*
 * Le config.json das blacklists. Retorna 0 se OK, -1 se erro.
 * Se o ficheiro nao existir, cfg->enabled = 0.
 * Suporta formato novo (rules[]) e antigo (flat categories[]).
 */
int l7_bl_config_load(const char *path, struct l7_bl_config *cfg);

#endif /* LAYER7_BL_CONFIG_H */
