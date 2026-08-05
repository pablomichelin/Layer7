/*
 * test_bl_limits.c — BG-100: clamp + truncate por max_entries.
 */
#include "blacklist.h"

#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <sys/stat.h>
#include <unistd.h>

static int fails;

static void
expect(int cond, const char *msg)
{
	if (!cond) {
		fprintf(stderr, "FAIL: %s\n", msg);
		fails++;
	}
}

static void
write_domains(const char *path, int n)
{
	FILE *f = fopen(path, "w");
	int i;

	if (!f)
		exit(2);
	for (i = 0; i < n; i++)
		fprintf(f, "d%d.example.test\n", i);
	fclose(f);
}

int
main(void)
{
	char dir[] = "/tmp/l7-bl-limits-XXXXXX";
	char path[256];
	const char *cats[1];
	struct l7_bl_limits lim;
	struct l7_blacklist *bl;

	expect(l7_bl_clamp_max_entries(0) == L7_BL_MAX_TOTAL_DEFAULT,
	    "default max_entries");
	expect(l7_bl_clamp_max_entries(9000000) == L7_BL_MAX_TOTAL_HARD,
	    "hard max");
	expect(l7_bl_clamp_max_entries(500) == 500, "passthrough mid");
	expect(l7_bl_clamp_max_entries(2500000) == 2500000, "passthrough");
	expect(l7_bl_clamp_mem_percent(0) == L7_BL_MEM_PERCENT_DEFAULT,
	    "default percent");
	expect(l7_bl_clamp_mem_percent(90) == L7_BL_MEM_PERCENT_MAX,
	    "cap percent");
	expect(l7_bl_clamp_mem_percent(3) == L7_BL_MEM_PERCENT_MIN,
	    "min percent");
	{
		size_t b = l7_bl_compute_mem_budget(25);
		expect(b >= (size_t)L7_BL_MEM_BUDGET_MIN_MB * 1024ULL * 1024ULL,
		    "budget >= min");
		expect(b <= (size_t)L7_BL_MEM_BUDGET_MAX_MB * 1024ULL * 1024ULL,
		    "budget <= max");
	}

	if (!mkdtemp(dir)) {
		perror("mkdtemp");
		return 2;
	}
	snprintf(path, sizeof(path), "%s/toy", dir);
	mkdir(path, 0755);
	snprintf(path, sizeof(path), "%s/toy/domains", dir);
	write_domains(path, 5000);

	cats[0] = "toy";
	memset(&lim, 0, sizeof(lim));
	lim.max_entries = 100; /* truncate artificial */
	lim.mem_percent = 25;
	bl = l7_blacklist_load(dir, cats, 1, NULL, 0, &lim);
	expect(bl != NULL, "load toy truncated");
	if (bl) {
		expect(l7_blacklist_count(bl) == 100, "truncated to 100");
		expect(l7_blacklist_was_truncated(bl) == 1, "truncated flag");
		expect(l7_blacklist_lookup(bl, "d0.example.test") != NULL,
		    "first domain present");
		expect(l7_blacklist_lookup(bl, "d999.example.test") == NULL,
		    "over-cap domain absent");
		l7_blacklist_free(bl);
	}

	unlink(path);
	snprintf(path, sizeof(path), "%s/toy", dir);
	rmdir(path);
	rmdir(dir);

	if (fails) {
		fprintf(stderr, "%d failure(s)\n", fails);
		return 1;
	}
	printf("test_bl_limits: PASS\n");
	return 0;
}
