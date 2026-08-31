/*
 * test_checkin_config_enabled.c — BG-170: check-in always on.
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
#include <time.h>
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
	    layer7_checkin_config_enabled(path) == 1,
	    "BG-170 false JSON nao desliga");

	check(write_cfg(path, "{\"layer7\":{\"enabled\":false}}\n") == 0 &&
	    layer7_checkin_config_enabled(path) == 1,
	    "BG-170 ausente => enabled");

	{
		char state[256];
		time_t now = time(NULL);

		snprintf(state, sizeof(state), "%s/checkin.json", dir);
		unsetenv("L7_CHECK_IN_FORCE");
		if (setenv("L7_CHECKIN_STATE_PATH", state, 1) != 0) {
			perror("setenv");
			return 1;
		}
		unlink(state);

		check(write_cfg(path,
		    "{\"layer7\":{\"check_in_enabled\":true}}\n") == 0 &&
		    layer7_checkin_enforce_ready(path) == 0,
		    "P1-5 enabled + sem estado => enforce_ready=0");
		check(layer7_checkin_due(now) == 0,
		    "P1-5 sem chave => checkin_due=0");
		check(layer7_checkin_offline_expired(now) == 0,
		    "P1-5 sem chave => offline_expired=0");

		check(write_cfg(path,
		    "{\"layer7\":{\"check_in_enabled\":false}}\n") == 0 &&
		    layer7_checkin_enforce_ready(path) == 0,
		    "BG-170 false JSON sem chave => enforce_ready=0");

		check(write_cfg(path, "{\"layer7\":{\"enabled\":false}}\n") == 0 &&
		    layer7_checkin_enforce_ready(path) == 0,
		    "BG-170 chave ausente no JSON => enforce_ready=0");

		check(write_cfg(state,
		    "{\"license_key\":\"ABC123\",\"last_check_in_ok\":1}\n") == 0 &&
		    write_cfg(path,
		    "{\"layer7\":{\"check_in_enabled\":false}}\n") == 0 &&
		    layer7_checkin_enforce_ready(path) == 1,
		    "BG-170 false JSON + chave => enforce_ready=1");

		check(write_cfg(state, "{\"license_key\":\"\"}\n") == 0 &&
		    layer7_checkin_enforce_ready(path) == 0,
		    "P1-5 enabled + chave vazia => enforce_ready=0");

		unsetenv("L7_CHECKIN_STATE_PATH");
		unlink(state);
	}

	unlink(path);
	rmdir(dir);

	if (g_fail) {
		printf("RESULT: FAIL\n");
		return 1;
	}
	printf("RESULT: PASS\n");
	return 0;
}
