/*
 * test_features_parse.c — ADR-0025 P1–P6 + transição T1 (passo 20.3).
 *
 *   cc -Wall -Wextra -O2 -I src/layer7d -o /tmp/t_feat \
 *      tests/functional/test_features_parse.c src/layer7d/features.c && /tmp/t_feat
 */
#include "l7_features.h"

#include <stdio.h>
#include <string.h>

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

static void
expect_flags(const char *raw, unsigned want, const char *norm, const char *name)
{
	struct l7_features f;

	(void)layer7_features_parse(raw, &f);
	check(f.flags == want, name);
	if (norm != NULL)
		check(strcmp(f.normalized, norm) == 0, name);
}

int
main(void)
{
	struct l7_features f;
	char longbuf[128];
	int trunc;

	/* P4 / T1: NULL, vazio, espaços */
	expect_flags(NULL, L7_FEAT_BASE, "base", "NULL -> base");
	expect_flags("", L7_FEAT_BASE, "base", "vazio -> base");
	expect_flags("   ", L7_FEAT_BASE, "base", "espacos -> base");

	/* T1: full legado */
	expect_flags("full", L7_FEAT_BASE, "base", "full -> base (T1)");
	expect_flags("FULL", L7_FEAT_BASE, "base", "FULL -> base (T1)");
	expect_flags("full,unknown", L7_FEAT_BASE, "base",
	    "full+unknown -> base");

	/* Parse normal */
	expect_flags("base", L7_FEAT_BASE, "base", "base");
	expect_flags("base,identity", L7_FEAT_BASE | L7_FEAT_IDENTITY,
	    "base,identity", "base,identity");
	expect_flags("base,mitm", L7_FEAT_BASE | L7_FEAT_MITM,
	    "base,mitm", "base,mitm");
	expect_flags("base,identity,mitm",
	    L7_FEAT_BASE | L7_FEAT_IDENTITY | L7_FEAT_MITM,
	    "base,identity,mitm", "base,identity,mitm");

	/* P2: maiúsculas + espaços */
	expect_flags(" Base , Identity ",
	    L7_FEAT_BASE | L7_FEAT_IDENTITY, "base,identity",
	    "espacos+maiusculas");

	/* P3: token desconhecido ignorado */
	expect_flags("base,foo,identity",
	    L7_FEAT_BASE | L7_FEAT_IDENTITY, "base,identity",
	    "desconhecido ignorado");
	expect_flags("legacy_sku", L7_FEAT_BASE, "base",
	    "so desconhecido -> base");

	/* P6: duplicados */
	expect_flags("base,identity,identity,base",
	    L7_FEAT_BASE | L7_FEAT_IDENTITY, "base,identity",
	    "duplicados");

	/* Tokens add-on sem "base" explícito — base implícita (fail-open) */
	expect_flags("identity", L7_FEAT_BASE | L7_FEAT_IDENTITY,
	    "base,identity", "identity implica base");
	expect_flags("mitm", L7_FEAT_BASE | L7_FEAT_MITM,
	    "base,mitm", "mitm implica base");

	/* P1: truncamento >63 bytes, sem overflow */
	memset(longbuf, 'a', sizeof(longbuf) - 1);
	longbuf[sizeof(longbuf) - 1] = '\0';
	/* prefixo válido + lixo longo */
	memcpy(longbuf, "base,identity,", 14);
	trunc = layer7_features_parse(longbuf, &f);
	check(trunc == 1, "truncamento retorna 1");
	check(f.truncated == 1, "truncated flag");
	check(strlen(f.raw) == L7_FEATURES_MAX, "raw len == 63");
	check(f.raw[L7_FEATURES_MAX] == '\0', "raw NUL no [63]");
	check(layer7_features_has(&f, L7_FEAT_BASE) == 1, "trunc ainda base");
	/* identity pode ou não caber conforme corte — flags não crasham */
	check(f.normalized[0] != '\0', "normalized nao vazio apos trunc");

	/* Exact 63 bytes sem truncar */
	{
		char exact[64];
		memset(exact, 'x', 63);
		exact[63] = '\0';
		/* "base" no inicio */
		memcpy(exact, "base", 4);
		exact[4] = ',';
		trunc = layer7_features_parse(exact, &f);
		check(trunc == 0, "exact 63 sem truncar");
		check(f.truncated == 0, "truncated=0 exact 63");
		check(strlen(f.raw) == 63, "raw len 63 exact");
	}

	/* layer7_features_has */
	layer7_features_parse("base,mitm", &f);
	check(layer7_features_has(&f, L7_FEAT_BASE) == 1, "has base");
	check(layer7_features_has(&f, L7_FEAT_MITM) == 1, "has mitm");
	check(layer7_features_has(&f, L7_FEAT_IDENTITY) == 0, "sem identity");
	check(layer7_features_has(NULL, L7_FEAT_BASE) == 0, "has NULL");

	/* full + identity explícito: T1 full nao bloqueia token novo explícito */
	expect_flags("full,identity",
	    L7_FEAT_BASE | L7_FEAT_IDENTITY, "base,identity",
	    "full+identity explicito");

	/* Interseção check-in (P / GI1.8) */
	{
		unsigned lic = L7_FEAT_BASE | L7_FEAT_IDENTITY | L7_FEAT_MITM;
		unsigned ci = L7_FEAT_BASE | L7_FEAT_IDENTITY;
		unsigned eff = layer7_features_intersect(lic, ci);

		check(eff == (L7_FEAT_BASE | L7_FEAT_IDENTITY),
		    "intersect remove mitm");
		check(layer7_features_allows_identity(eff) == 1,
		    "allows identity apos intersect");
		check(layer7_features_allows_mitm(eff) == 0,
		    "bloqueia mitm apos intersect");
		check(layer7_features_allows_mitm(L7_FEAT_BASE) == 0,
		    "sem mitm entitlement");
		check(layer7_features_allows_identity(L7_FEAT_BASE) == 0,
		    "sem identity entitlement");
		check(layer7_features_identity_want(
		    L7_FEAT_BASE | L7_FEAT_IDENTITY, 0) == 0,
		    "token identity + toggle OFF = nao liga");
		check(layer7_features_identity_want(
		    L7_FEAT_BASE | L7_FEAT_IDENTITY, 1) == 1,
		    "token identity + toggle ON = pode ligar");
		check(layer7_features_identity_want(L7_FEAT_BASE, 1) == 0,
		    "toggle ON sem token = nao liga");
		/* check-in nao pode acrescentar alem do .lic */
		eff = layer7_features_intersect(L7_FEAT_BASE,
		    L7_FEAT_BASE | L7_FEAT_IDENTITY | L7_FEAT_MITM);
		check(eff == L7_FEAT_BASE, "intersect nao acrescenta");
	}

	if (g_fail) {
		printf("RESULT: FAIL\n");
		return 1;
	}
	printf("RESULT: PASS\n");
	return 0;
}
