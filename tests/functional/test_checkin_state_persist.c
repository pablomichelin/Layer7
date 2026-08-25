/*
 * test_checkin_state_persist.c — BG-128 P2-7 / P2-8 / P2-10
 *
 * Persistência atómica do estado de check-in, escape JSON, falha de tmp
 * a preservar o ficheiro anterior, troca de SKU (features_set=0) e modo 0600.
 *
 *   cc -Wall -Wextra -O2 -I src/layer7d -DL7_TEST_CHECKIN_STATE \
 *      -o /tmp/t_ci_persist \
 *      tests/functional/test_checkin_state_persist.c \
 *      src/layer7d/license.c src/layer7d/features.c -lssl -lcrypto \
 *   && /tmp/t_ci_persist
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

int
main(void)
{
	char dir[] = "/tmp/l7-ci-persist-XXXXXX";
	char state[320];
	char cfg[320];
	char lic[320];
	char *made;
	char *raw;
	struct l7_checkin_status st;

	made = mkdtemp(dir);
	if (!made) {
		perror("mkdtemp");
		return 1;
	}
	snprintf(state, sizeof(state), "%s/checkin.json", dir);
	snprintf(cfg, sizeof(cfg), "%s/layer7.json", dir);
	snprintf(lic, sizeof(lic), "%s/layer7.lic", dir);

	if (setenv("L7_CHECKIN_STATE_PATH", state, 1) != 0) {
		perror("setenv");
		return 1;
	}
	unsetenv("L7_CHECK_IN_FORCE");
	unsetenv("L7_CHECKIN_STATE_TMP");
	unlink(state);

	/* P2-8 sucesso: store_key atómico, JSON válido, 0600. */
	check(layer7_checkin_store_key("KEYOK123") == 0, "P2-8 store_key sucesso");
	raw = read_text(state);
	check(raw != NULL && strstr(raw, "\"license_key\": \"KEYOK123\"") != NULL,
	    "P2-8 JSON contem chave");
	check(raw != NULL && strstr(raw, "\"features_set\": false") != NULL,
	    "P2-8 features_set false em chave nova");
	check(mode_is_0600(state), "P2-8 estado 0600");
	check(write_text(cfg, "{\"layer7\":{\"check_in_enabled\":true}}\n") == 0 &&
	    layer7_checkin_enforce_ready(cfg) == 1,
	    "P2-8 enforce_ready com chave");
	free(raw);

	/* P2-8 JSON seguro: aspas e barra invertida em last_error. */
	check(layer7_test_checkin_save_error("KEYOK123",
	    "denied: said \"revoked\" path \\tmp") == 0,
	    "P2-8 save last_error com aspas");
	raw = read_text(state);
	check(raw != NULL && strstr(raw, "said \\\"revoked\\\"") != NULL,
	    "P2-8 ficheiro escapa aspas");
	check(raw != NULL && strstr(raw, "path \\\\tmp") != NULL,
	    "P2-8 ficheiro escapa barra");
	check(raw != NULL && strchr(raw, '{') != NULL &&
	    strstr(raw, "\"license_key\": \"KEYOK123\"") != NULL,
	    "P2-8 JSON continua parseavel");
	free(raw);
	memset(&st, 0, sizeof(st));
	check(layer7_checkin_get_status(&st) == 0 &&
	    strcmp(st.last_error, "denied: said \"revoked\" path \\tmp") == 0,
	    "P2-8 load restora last_error");

	/* P2-8 falha de tmp: estado anterior sobrevive. */
	raw = read_text(state);
	check(raw != NULL, "P2-8 snapshot antes da falha tmp");
	if (setenv("L7_CHECKIN_STATE_TMP",
	    "/tmp/l7-ci-persist-missing-dir/x.tmp", 1) != 0) {
		perror("setenv tmp");
		free(raw);
		return 1;
	}
	check(layer7_checkin_store_key("OTHERKEY") != 0,
	    "P2-8 store_key falha se tmp inacessivel");
	{
		char *after = read_text(state);

		check(after != NULL && raw != NULL &&
		    strcmp(after, raw) == 0,
		    "P2-8 falha tmp preserva estado anterior");
		check(after != NULL &&
		    strstr(after, "\"license_key\": \"KEYOK123\"") != NULL,
		    "P2-8 chave anterior intacta");
		free(after);
	}
	free(raw);
	unsetenv("L7_CHECKIN_STATE_TMP");

	/* P2-7 troca de SKU: limpa so o cache de features. */
	check(write_text(state,
	    "{\n"
	    "  \"license_key\": \"OLDKEY\",\n"
	    "  \"last_check_in_ok\": 12345,\n"
	    "  \"last_check_in_attempt\": 12340,\n"
	    "  \"check_in_interval_hours\": 24,\n"
	    "  \"max_offline_hours\": 48,\n"
	    "  \"last_error\": \"\",\n"
	    "  \"features\": \"base,identity,mitm\",\n"
	    "  \"features_set\": true\n"
	    "}\n") == 0, "P2-7 estado previo com SKU pago");
	check(layer7_checkin_store_key("NEWKEYABC") == 0,
	    "P2-7 store_key nova chave");
	raw = read_text(state);
	check(raw != NULL && strstr(raw, "\"license_key\": \"NEWKEYABC\"") != NULL,
	    "P2-7 chave substituida");
	check(raw != NULL && strstr(raw, "\"features_set\": false") != NULL,
	    "P2-7 features_set limpo");
	check(raw != NULL && strstr(raw, "base,identity,mitm") == NULL,
	    "P2-7 CSV antigo ausente");
	check(raw != NULL && strstr(raw, "\"check_in_interval_hours\": 24") != NULL &&
	    strstr(raw, "\"max_offline_hours\": 48") != NULL &&
	    strstr(raw, "\"last_check_in_ok\": 12345") != NULL,
	    "P2-7 intervalos e timestamps preservados");
	free(raw);
	memset(&st, 0, sizeof(st));
	check(layer7_checkin_get_status(&st) == 0 &&
	    st.interval_hours == 24 && st.max_offline_hours == 48 &&
	    st.last_ok == 12345,
	    "P2-7 get_status ve intervalos antigos");

	/* D-5: lock exclusivo ao lado do estado; escritas sequenciais. */
	{
		char lockp[320];

		snprintf(lockp, sizeof(lockp), "%s.lock", state);
		check(access(lockp, F_OK) == 0, "D-5 ficheiro .lock criado");
		check(layer7_checkin_store_key("LOCKKEY1") == 0 &&
		    layer7_checkin_store_key("LOCKKEY2") == 0,
		    "D-5 escritas sequenciais");
		raw = read_text(state);
		check(raw != NULL &&
		    strstr(raw, "\"license_key\": \"LOCKKEY2\"") != NULL,
		    "D-5 ultima escrita prevalece");
		free(raw);
		unlink(lockp);
	}

	/* P2-10: promote/.lic via o mesmo helper 0600. */
	{
		const char *body = "{\"data\":\"x\",\"sig\":\"y\"}";

		unlink(lic);
		check(layer7_test_write_bytes_0600(lic, body, strlen(body)) == 0,
		    "P2-10 write_bytes_0600 sucesso");
		check(mode_is_0600(lic), "P2-10 .lic 0600");
		raw = read_text(lic);
		check(raw != NULL && strcmp(raw, body) == 0,
		    "P2-10 payload .lic intacto");
		free(raw);
	}

	unsetenv("L7_CHECKIN_STATE_PATH");
	unlink(state);
	unlink(cfg);
	unlink(lic);
	rmdir(dir);

	if (g_fail) {
		printf("RESULT: FAIL\n");
		return 1;
	}
	printf("RESULT: PASS\n");
	return 0;
}
