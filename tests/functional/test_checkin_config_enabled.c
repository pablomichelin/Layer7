/*
 * test_checkin_config_enabled.c — 30.14: ler check_in_enabled do JSON.
 *
 *   cc -Wall -Wextra -O2 -I src/layer7d -o /tmp/t_ci_cfg \
 *      tests/functional/test_checkin_config_enabled.c \
 *      src/layer7d/license.c src/layer7d/features.c -lssl -lcrypto \
 *   && /tmp/t_ci_cfg
 */
#include "license.h"

#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <unistd.h>

static int g_fail;

static void
check(int cond, const char *name)
{
	if (cond)
		printf("PASS: %s\n", name);
	else {
		printf("FAIL: %s\n", name);
		g_fail = 1;
	}
}

static int
write_cfg(const char *path, const char *body)
{
	FILE *f = fopen(path, "w");
	if (!f)
		return -1;
	fputs(body, f);
	fclose(f);
	return 0;
}

int
main(void)
{
	char dir[] = "/tmp/l7-ci-cfg-XXXXXX";
	char path[256];
	char *made;

	made = mkdtemp(dir);
	if (!made) {
		perror("mkdtemp");
		return 1;
	}
	snprintf(path, sizeof(path), "%s/layer7.json", dir);

	check(write_cfg(path,
	    "{\"layer7\":{\"check_in_enabled\":true}}\n") == 0 &&
	    layer7_checkin_config_enabled(path) == 1,
	    "true => enabled");

	check(write_cfg(path,
	    "{\"layer7\":{\"check_in_enabled\":false}}\n") == 0 &&
	    layer7_checkin_config_enabled(path) == 0,
	    "false => disabled (isolado)");

	check(write_cfg(path, "{\"layer7\":{\"enabled\":false}}\n") == 0 &&
	    layer7_checkin_config_enabled(path) == 0,
	    "ausente => disabled (nao regressivo)");

	unlink(path);
	rmdir(dir);

	if (g_fail) {
		printf("RESULT: FAIL\n");
		return 1;
	}
	printf("RESULT: PASS\n");
	return 0;
}
