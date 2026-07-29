/*
 * Rotacao limitada do log local sem depender do daemon ou do pfSense.
 */
#include "log_store.h"

#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <sys/stat.h>
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
contains(const char *path, const char *needle)
{
	char buf[512];
	FILE *f;
	size_t n;

	f = fopen(path, "r");
	if (!f)
		return 0;
	n = fread(buf, 1, sizeof(buf) - 1, f);
	fclose(f);
	buf[n] = '\0';
	return strstr(buf, needle) != NULL;
}

int
main(void)
{
	char dir[] = "/tmp/layer7-log-store.XXXXXX";
	char path[512], p1[516], p2[516], p3[516], p4[516];
	struct stat st;
	int i;

	check(mkdtemp(dir) != NULL, "mkdtemp");
	snprintf(path, sizeof(path), "%s/events.log", dir);
	snprintf(p1, sizeof(p1), "%s.1", path);
	snprintf(p2, sizeof(p2), "%s.2", path);
	snprintf(p3, sizeof(p3), "%s.3", path);
	snprintf(p4, sizeof(p4), "%s.4", path);

	check(layer7_log_store_append(path, "event=one payload=1234567890",
	    64, 3) == 0, "append inicial");
	check(layer7_log_store_append(path, "event=two payload=1234567890",
	    64, 3) == 0, "append sem rotacao");
	check(layer7_log_store_append(path, "event=three payload=1234567890",
	    64, 3) == 0, "append com rotacao");
	check(access(p1, F_OK) == 0, "primeira copia criada");
	check(contains(p1, "event=one") && contains(p1, "event=two"),
	    "copia preserva linhas anteriores");
	check(contains(path, "event=three"), "activo recebe linha nova");

	for (i = 0; i < 12; i++) {
		char line[80];
		snprintf(line, sizeof(line),
		    "event=bulk seq=%d payload=abcdefghijklmnopqrstuvwxyz", i);
		check(layer7_log_store_append(path, line, 64, 3) == 0,
		    "append repetido");
	}
	check(access(p2, F_OK) == 0, "segunda copia criada");
	check(access(p3, F_OK) == 0, "terceira copia criada");
	check(access(p4, F_OK) != 0, "numero de copias limitado");
	check(stat(path, &st) == 0 && st.st_size < 128,
	    "ficheiro activo permanece limitado");

	unlink(path);
	unlink(p1);
	unlink(p2);
	unlink(p3);
	rmdir(dir);

	if (g_fail) {
		printf("\nTEST LOG_STORE: FAILED\n");
		return 1;
	}
	printf("\nTEST LOG_STORE: ALL PASSED\n");
	return 0;
}
