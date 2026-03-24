/*
 * bl_config.h — Parse do config.json das blacklists.
 *
 * Ficheiro separado do layer7.json; nao altera config_parse.c.
 */
#ifndef LAYER7_BL_CONFIG_H
#define LAYER7_BL_CONFIG_H

#include "blacklist.h"

struct l7_bl_config {
	int enabled;
	char categories[L7_BL_MAX_CATS][L7_BL_CAT_LEN];
	int n_categories;
	char whitelist[L7_BL_WL_MAX][L7_BL_DOMAIN_MAX];
	int n_whitelist;
	char except_ips[64][48];
	int n_except_ips;
};

/*
 * Le config.json das blacklists. Retorna 0 se OK, -1 se erro.
 * Se o ficheiro nao existir, cfg->enabled = 0.
 */
int l7_bl_config_load(const char *path, struct l7_bl_config *cfg);

#endif /* LAYER7_BL_CONFIG_H */
