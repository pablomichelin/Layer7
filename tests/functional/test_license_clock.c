/*
 * test_license_clock.c — anti-rollback temporal (30.6 / ADR-0033 / GA3).
 *
 *   cc -Wall -Wextra -O2 -I src/layer7d -c -o /tmp/license_clock.o \
 *      -DL7_TEST_CLOCK_ONLY src/layer7d/license.c
 *   (preferir: linkar só layer7_clock_eval via object do builder, ou:)
 *   cc -Wall -Wextra -O2 -I src/layer7d -o /tmp/t_clock \
 *      tests/functional/test_license_clock.c src/layer7d/license.c \
 *      src/layer7d/features.c -lssl -lcrypto && /tmp/t_clock
 *
 * Em FreeBSD (builder). macOS não é ambiente de validação.
 */
#include "license.h"

#include <stdio.h>
#include <string.h>
#include <time.h>

static int g_fail;

static void
check(int cond, const char *name)
{
	if (cond) {
		printf("PASS: %s\n", name);
	} else {
		printf("FAIL: %s\n", name);
		g_fail = 1;
	}
}

int
main(void)
{
	time_t new_max;
	long delta;
	int suspect;
	const time_t base = 1700000000; /* âncora estável */

	/* Primeira observação */
	suspect = layer7_clock_eval(base, 0, &new_max, &delta);
	check(suspect == 0 && new_max == base && delta == 0,
	    "primeira marca = now");

	/* Relógio a avançar */
	suspect = layer7_clock_eval(base + 3600, base, &new_max, &delta);
	check(suspect == 0 && new_max == base + 3600 && delta == 0,
	    "avanco normal actualiza marca");

	/* Retrocesso pequeno (1h) — tolerado */
	suspect = layer7_clock_eval(base - 3600, base, &new_max, &delta);
	check(suspect == 0 && new_max == base && delta == 3600,
	    "retrocesso 1h tolerado; marca intacta");

	/* Retrocesso no limiar exacto — ainda tolerado (só > SUSPECT) */
	suspect = layer7_clock_eval(base - L7_CLOCK_SUSPECT_SEC, base,
	    &new_max, &delta);
	check(suspect == 0 && new_max == base &&
	    delta == L7_CLOCK_SUSPECT_SEC,
	    "retrocesso == limiar tolerado");

	/* Retrocesso grande — suspeito */
	suspect = layer7_clock_eval(base - L7_CLOCK_SUSPECT_SEC - 1, base,
	    &new_max, &delta);
	check(suspect == 1 && new_max == base &&
	    delta == L7_CLOCK_SUSPECT_SEC + 1,
	    "retrocesso >1d => suspeito");

	/* Recuperação: hora volta a >= marca */
	suspect = layer7_clock_eval(base + 10, base, &new_max, &delta);
	check(suspect == 0 && new_max == base + 10 && delta == 0,
	    "recuperacao apos correcao de hora");

	/* now inválido */
	suspect = layer7_clock_eval(0, base, &new_max, &delta);
	check(suspect == 0 && new_max == base,
	    "now<=0 nao altera marca");

	if (g_fail) {
		printf("RESULT: FAIL\n");
		return 1;
	}
	printf("RESULT: PASS\n");
	return 0;
}
