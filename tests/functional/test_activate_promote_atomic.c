/*
 * test_activate_promote_atomic.c — BG-128 P3-5 / BG-142
 *
 * Promoção atómica do .lic em Activate: tmp 0600 no mesmo directório,
 * validar candidato antes do rename, preservar o final anterior em falha.
 * Nunca escreve /usr/local/etc real.
 *
 *   cc -Wall -Wextra -O2 -I src/layer7d -DL7_TEST_ACTIVATE_PROMOTE \
 *      -o /tmp/t_act_promote \
 *      tests/functional/test_activate_promote_atomic.c \
 *      src/layer7d/license.c src/layer7d/features.c -lssl -lcrypto \
 *   && /tmp/t_act_promote
 */
#include "license.h"

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
write_text(const char *path, const char *body)
{
	FILE *f = fopen(path, "w");

	if (!f)
		return -1;
	fputs(body, f);
	return fclose(f) == 0 ? 0 : -1;
}

static char *
read_text(const char *path)
{
	FILE *f;
	long sz;
	char *buf;

	f = fopen(path, "rb");
	if (!f)
		return NULL;
	if (fseek(f, 0, SEEK_END) != 0) {
		fclose(f);
		return NULL;
	}
	sz = ftell(f);
	if (sz < 0) {
		fclose(f);
		return NULL;
	}
	rewind(f);
	buf = malloc((size_t)sz + 1);
	if (!buf) {
		fclose(f);
		return NULL;
	}
	if ((long)fread(buf, 1, (size_t)sz, f) != sz) {
		free(buf);
		fclose(f);
		return NULL;
	}
	buf[sz] = '\0';
	fclose(f);
	return buf;
}

static int
mode_is_0600(const char *path)
{
	struct stat st;

	if (stat(path, &st) != 0)
		return 0;
	return (st.st_mode & 0777) == 0600;
}

static int
path_exists(const char *path)
{
	struct stat st;

	return stat(path, &st) == 0;
}

int
main(void)
{
	char dir[] = "/tmp/l7-act-promote-XXXXXX";
	char dest[320];
	char src[320];
	char tmp[320];
	char *made;
	char *raw;
	const char *old_body = "{\"data\":\"OLD\",\"sig\":\"prev\"}";
	const char *new_body = "{\"data\":\"NEW\",\"sig\":\"next\"}";
	const char *real_lic = "/usr/local/etc/layer7.lic";
	struct stat real_before;
	int real_existed;

	made = mkdtemp(dir);
	if (!made) {
		perror("mkdtemp");
		return 1;
	}
	if (setenv("LAYER7_TEST_ROOT", dir, 1) != 0) {
		perror("setenv LAYER7_TEST_ROOT");
		return 1;
	}
	unsetenv("L7_ACTIVATE_PROMOTE_HOOK");

	snprintf(dest, sizeof(dest), "%s/layer7.lic", dir);
	snprintf(src, sizeof(src), "%s/activate.body", dir);
	snprintf(tmp, sizeof(tmp), "%s/layer7.lic.tmp", dir);

	real_existed = (stat(real_lic, &real_before) == 0);

	check(write_text(dest, old_body) == 0, "P3-5 seed .lic anterior");
	check(write_text(src, new_body) == 0, "P3-5 seed activate.body");

	/* 1) Interrupção após write do tmp, antes do rename. */
	if (setenv("L7_ACTIVATE_PROMOTE_HOOK", "stop-after-write", 1) != 0) {
		perror("setenv hook stop");
		return 1;
	}
	check(layer7_test_promote_license(src, dest) != 0,
	    "P3-5 stop-after-write falha o promote");
	raw = read_text(dest);
	check(raw != NULL && strcmp(raw, old_body) == 0,
	    "P3-5 stop-after-write preserva .lic anterior");
	free(raw);
	raw = read_text(tmp);
	check(raw != NULL && strcmp(raw, new_body) == 0,
	    "P3-5 stop-after-write deixa tmp com candidato");
	free(raw);
	raw = read_text(src);
	check(raw != NULL && strcmp(raw, new_body) == 0,
	    "P3-5 stop-after-write nao renameia activate.body");
	free(raw);
	unlink(tmp);
	unsetenv("L7_ACTIVATE_PROMOTE_HOOK");

	/* 2) Candidato inválido: verify falha, tmp some, final intacto. */
	check(write_text(src, "not-a-license") == 0, "P3-5 seed candidato invalido");
	check(layer7_test_promote_license(src, dest) != 0,
	    "P3-5 candidato invalido falha o promote");
	raw = read_text(dest);
	check(raw != NULL && strcmp(raw, old_body) == 0,
	    "P3-5 verify-fail preserva .lic anterior");
	free(raw);
	check(!path_exists(tmp), "P3-5 verify-fail remove tmp");
	raw = read_text(src);
	check(raw != NULL && strcmp(raw, "not-a-license") == 0,
	    "P3-5 verify-fail nao renameia activate.body");
	free(raw);

	/* 3) Sucesso (hook exclusivo: accept-candidate, sem chave de producao). */
	check(write_text(src, new_body) == 0, "P3-5 seed candidato aceite");
	if (setenv("L7_ACTIVATE_PROMOTE_HOOK", "accept-candidate", 1) != 0) {
		perror("setenv hook accept");
		return 1;
	}
	check(layer7_test_promote_license(src, dest) == 0,
	    "P3-5 sucesso rename atomico");
	raw = read_text(dest);
	check(raw != NULL && strcmp(raw, new_body) == 0,
	    "P3-5 sucesso grava candidato no final");
	free(raw);
	check(mode_is_0600(dest), "P3-5 sucesso final 0600");
	check(!path_exists(tmp), "P3-5 sucesso tmp ausente");
	raw = read_text(src);
	check(raw != NULL && strcmp(raw, new_body) == 0,
	    "P3-5 sucesso nao renameia activate.body");
	free(raw);
	unsetenv("L7_ACTIVATE_PROMOTE_HOOK");

	/* Nunca escrever o path real de producao. */
	if (real_existed) {
		struct stat real_after;

		check(stat(real_lic, &real_after) == 0 &&
		    real_after.st_mtime == real_before.st_mtime &&
		    real_after.st_size == real_before.st_size &&
		    real_after.st_ino == real_before.st_ino,
		    "P3-5 nao toca /usr/local/etc/layer7.lic existente");
	} else {
		check(!path_exists(real_lic),
		    "P3-5 nao cria /usr/local/etc/layer7.lic");
	}
	check(strncmp(dest, "/usr/local/etc/", 15) != 0,
	    "P3-5 dest do harness esta sob mkdtemp");

	unsetenv("LAYER7_TEST_ROOT");
	unsetenv("L7_ACTIVATE_PROMOTE_HOOK");
	unlink(dest);
	unlink(src);
	unlink(tmp);
	rmdir(dir);

	if (g_fail) {
		printf("RESULT: FAIL\n");
		return 1;
	}
	printf("RESULT: PASS\n");
	return 0;
}
