/*
 * test_checkin_signed.c — validação payload check-in v2 (30.13 / GA5.2–5.4).
 *
 *   cc -Wall -Wextra -O2 -I src/layer7d -DL7_TEST_CHECKIN_SIGNED \
 *      -o /tmp/t_checkin_signed \
 *      tests/functional/test_checkin_signed.c \
 *      src/layer7d/license.c src/layer7d/features.c -lssl -lcrypto \
 *   && /tmp/t_checkin_signed
 *
 * macOS: pode compilar com LibreSSL/OpenSSL do sistema; builder FreeBSD é
 * o ambiente canónico.
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
	char status[32];
	const time_t now = 1700000000;
	const char *nonce = "AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA"; /* 43 */
	const char *hw = "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa";
	char payload[512];

	/* C1-ish: payload válido */
	snprintf(payload, sizeof(payload),
	    "{\"v\":1,\"status\":\"active\",\"hardware_id\":\"%s\","
	    "\"nonce\":\"%s\",\"expiry\":\"2027-12-31\",\"customer\":\"T\","
	    "\"features\":\"base\",\"check_in_interval_hours\":168,"
	    "\"max_offline_hours\":336,\"iat\":1700000000}",
	    hw, nonce);
	memset(status, 0, sizeof(status));
	check(layer7_checkin_validate_payload_test(payload, nonce, hw, now,
	    status, sizeof(status)) == 0 && strcmp(status, "active") == 0,
	    "C1 campos activos aceites");

	/* C4: nonce diferente */
	check(layer7_checkin_validate_payload_test(payload,
	    "BBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBB", hw, now,
	    status, sizeof(status)) != 0,
	    "C4 replay nonce rejeitado");

	/* hardware mismatch */
	check(layer7_checkin_validate_payload_test(payload, nonce,
	    "bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb",
	    now, status, sizeof(status)) != 0,
	    "hardware_id mismatch rejeitado");

	/* C7: revoked válido */
	snprintf(payload, sizeof(payload),
	    "{\"v\":1,\"status\":\"revoked\",\"hardware_id\":\"%s\","
	    "\"nonce\":\"%s\",\"error\":\"Licenca revogada.\","
	    "\"iat\":1700000000}",
	    hw, nonce);
	check(layer7_checkin_validate_payload_test(payload, nonce, hw, now,
	    status, sizeof(status)) == 0 && strcmp(status, "revoked") == 0,
	    "C7 denied revoked campos OK");

	/* skew > 1 dia */
	check(layer7_checkin_validate_payload_test(payload, nonce, hw,
	    now + L7_CHECKIN_IAT_SKEW_SEC + 1, status, sizeof(status)) != 0,
	    "iat skew >1d rejeitado (N3/falha check-in)");

	/* v incorrecta */
	snprintf(payload, sizeof(payload),
	    "{\"v\":2,\"status\":\"active\",\"hardware_id\":\"%s\","
	    "\"nonce\":\"%s\",\"iat\":1700000000}",
	    hw, nonce);
	check(layer7_checkin_validate_payload_test(payload, nonce, hw, now,
	    status, sizeof(status)) != 0,
	    "v!=1 rejeitado");

	if (g_fail) {
		printf("RESULT: FAIL\n");
		return 1;
	}
	printf("RESULT: PASS\n");
	return 0;
}
