#!/bin/sh
# Caminho B / E7 — smoke enforcement escopado (BG-052).
#
# Dois modos:
#   1) Estatico (workspace / sem pfSense): verifica artefactos de teste e codigo.
#   2) Appliance (correr NO pfSense com scoped_hybrid activo): gate two-client parcial/completo.
#
# Uso appliance (pre-requisitos em validacao-lab.md sec. 12):
#   enforcement_model=scoped_hybrid, mode=enforce, enabled=true, licenca valida
#   sh /caminho/smoke-enforcement-scoped.sh
#
# Two-client automatizado (opcional — requer SSH dos clientes A/B):
#   L7_CLIENT_A=10.0.0.10 L7_CLIENT_B=10.0.0.20 \
#   L7_TEST_HOST=youtube.com sh smoke-enforcement-scoped.sh
#
# Saida: PASS/FAIL por cenario; exit 0 = tudo PASS no modo actual.

set -u
RC=0
MODE=static

pass() { printf "PASS: %s\n" "$1"; }
fail() { printf "FAIL: %s\n" "$1"; RC=1; }
skip() { printf "SKIP: %s\n" "$1"; }

if [ -x /usr/local/sbin/layer7d ] && [ -f /usr/local/etc/layer7.json ]; then
	MODE=appliance
fi

printf "INFO: smoke mode=%s\n" "$MODE"

# --- Modo estatico (repo / CI / Mac sem SSH appliance) ---
run_static_checks() {
	REPO_ROOT=""
	case "$0" in
	*/*) REPO_ROOT=$(cd "$(dirname "$0")/../.." && pwd) ;;
	esac

	if [ -z "$REPO_ROOT" ] || [ ! -d "$REPO_ROOT/tests/functional" ]; then
		skip "estatico: repo root nao detectado (correr a partir do clone Layer7)"
		return
	fi

	for f in test_policy_decide.c test_enforce_scoped.c test_scoped_pf_inc.php; do
		if [ -f "$REPO_ROOT/tests/functional/$f" ]; then
			pass "estatico: tests/functional/$f"
		else
			fail "estatico: tests/functional/$f ausente"
		fi
	done

	INC="$REPO_ROOT/package/pfSense-pkg-layer7/files/usr/local/pkg/layer7.inc"
	if [ -f "$INC" ] && grep -q 'layer7_policy_enforcement_rules_text' "$INC"; then
		pass "estatico: layer7_policy_enforcement_rules_text em layer7.inc"
	else
		fail "estatico: layer7_policy_enforcement_rules_text em falta"
	fi
	if grep -q 'layer7_pallow_' "$INC" &&
	    grep -q 'tag L7ALLOW' "$INC" &&
	    ! grep -q 'pass quick inet to <layer7_allow_dst>' "$INC"; then
		pass "estatico: allow usa tag interna, sem pass quick no pfSense"
	else
		fail "estatico: allow PF escopado/seguro ausente"
	fi

	MC="$REPO_ROOT/src/layer7d/main.c"
	if [ -f "$MC" ] && grep -q 'layer7_decide_for_client' "$MC"; then
		pass "estatico: layer7_decide_for_client em main.c"
	else
		fail "estatico: layer7_decide_for_client nao encontrado"
	fi

	DIAG="$REPO_ROOT/scripts/diagnose-layer7-appliance.sh"
	[ -f "$DIAG" ] && pass "estatico: diagnose-layer7-appliance.sh" || \
		fail "estatico: diagnose-layer7-appliance.sh ausente"

	skip "gate two-client appliance — executar no pfSense (modo appliance)"
}

# --- Modo appliance ---
run_appliance_checks() {
	CFG=/usr/local/etc/layer7.json
	PHP=/usr/local/bin/php

	read_cfg() {
		"$PHP" -r '
			$c = @json_decode(@file_get_contents("'"$CFG"'"), true);
			$l = $c["layer7"] ?? [];
			echo ($l["enforcement_model"] ?? "legacy_global") . "\n";
			echo ($l["mode"] ?? "monitor") . "\n";
			echo !empty($l["enabled"]) ? "1" : "0";
		' 2>/dev/null
	}

	OUT=$(read_cfg) || OUT=""
	EM=$(echo "$OUT" | sed -n '1p')
	MODE_CFG=$(echo "$OUT" | sed -n '2p')
	EN=$(echo "$OUT" | sed -n '3p')

	printf "INFO: enforcement_model=%s mode=%s enabled=%s\n" \
		"${EM:-?}" "${MODE_CFG:-?}" "${EN:-?}"

	if [ "$EM" != "scoped_hybrid" ]; then
		fail "pre-condicao: enforcement_model=scoped_hybrid (actual: ${EM:-unset})"
		printf "HINT: legacy_global bloqueia globalmente por destino; gate scoped requer scoped_hybrid\n"
	else
		pass "enforcement_model=scoped_hybrid"
	fi

	if [ "$MODE_CFG" != "enforce" ] || [ "$EN" != "1" ]; then
		fail "pre-condicao: enabled=true e mode=enforce"
	else
		pass "mode=enforce enabled=true"
	fi

	# Licenca
	if /usr/local/sbin/layer7d --license-status >/dev/null 2>&1; then
		pass "licenca valida (--license-status exit 0)"
	else
		fail "licenca invalida/ausente — enforce ao vivo exige licenca"
	fi

	# Daemon vivo
	if [ -f /var/run/layer7d.pid ]; then
		PID=$(tr -d ' \n\r' < /var/run/layer7d.pid)
		if [ -n "$PID" ] && kill -0 "$PID" 2>/dev/null; then
			pass "layer7d vivo pid=$PID"
		else
			fail "pidfile presente mas processo morto"
		fi
	else
		fail "/var/run/layer7d.pid ausente"
	fi

	# Regras PF scoped
	PDST_RULES=$(pfctl -sr 2>/dev/null | grep -E 'layer7_pdst_|layer7:pdst:' || true)
	if [ -n "$PDST_RULES" ]; then
		pass "regras layer7_pdst presentes no ruleset"
	else
		fail "regras layer7_pdst ausentes — Filter reload / Resync Layer7?"
	fi

	L7_RULESET=$(pfctl -sr 2>/dev/null | grep 'layer7:' || true)
	if echo "$L7_RULESET" | grep -q 'layer7:allow:dst' &&
	    echo "$L7_RULESET" | grep 'layer7:allow:dst' | grep -q 'match' &&
	    ! echo "$L7_RULESET" | grep 'layer7:allow:dst' | grep -q 'pass'; then
		pass "allowlist marca L7ALLOW sem autorizar perante regras pfSense"
	else
		fail "allowlist deve usar match/tag, nunca pass quick"
	fi
	if echo "$L7_RULESET" | grep -E 'layer7:(block|pdst|psrc|bl:)' |
	    grep -q 'tagged.*L7ALLOW'; then
		pass "blocks Layer7 respeitam a marca interna L7ALLOW"
	else
		fail "blocks Layer7 sem exclusao tagged L7ALLOW"
	fi

	# Global legacy deve estar vazia em scoped activo (pos-trafego de teste pode ter entradas stale — aviso)
	N_GLOBAL=$(pfctl -t layer7_block_dst -T show 2>/dev/null | wc -l | tr -d ' ')
	if [ "${N_GLOBAL:-0}" -eq 0 ]; then
		pass "layer7_block_dst vazia (scoped)"
	else
		fail "layer7_block_dst tem ${N_GLOBAL} entradas com scoped_hybrid (esperado vazio)"
	fi

	# Tabela pdst_0 existe (politica indice 0 — setup padrao sec. 12)
	if pfctl -t layer7_pdst_0 -T show >/dev/null 2>&1; then
		pass "tabela layer7_pdst_0 existe"
	else
		fail "tabela layer7_pdst_0 ausente"
	fi

	# Two-client automatizado (opcional)
	CLIENT_A=${L7_CLIENT_A:-}
	CLIENT_B=${L7_CLIENT_B:-}
	TEST_HOST=${L7_TEST_HOST:-youtube.com}

	if [ -n "$CLIENT_A" ] && [ -n "$CLIENT_B" ]; then
		printf "INFO: two-client A=%s B=%s host=%s\n" "$CLIENT_A" "$CLIENT_B" "$TEST_HOST"
		if command -v ssh >/dev/null 2>&1; then
			# Cliente A dispara resolucao/acesso
			if ssh -o BatchMode=yes -o ConnectTimeout=5 "$CLIENT_A" \
				"nslookup $TEST_HOST >/dev/null 2>&1 || getent hosts $TEST_HOST >/dev/null 2>&1"; then
				pass "cliente A resolve $TEST_HOST"
			else
				fail "cliente A nao resolve $TEST_HOST (SSH BatchMode)"
			fi
			sleep 2
			N_PDST=$(pfctl -t layer7_pdst_0 -T show 2>/dev/null | wc -l | tr -d ' ')
			if [ "${N_PDST:-0}" -gt 0 ]; then
				pass "layer7_pdst_0 populada apos trafego de A ($N_PDST IPs)"
			else
				fail "layer7_pdst_0 vazia apos trafego de A"
			fi
			if ssh -o BatchMode=yes -o ConnectTimeout=5 "$CLIENT_B" \
				"curl -m 8 -sS -o /dev/null -w '%{http_code}' https://www.$TEST_HOST/ 2>/dev/null | grep -qE '^[23]'"; then
				pass "cliente B acede $TEST_HOST (nao bloqueado)"
			else
				fail "cliente B bloqueado ou inacessivel — gate two-client FAIL"
			fi
		else
			skip "two-client: ssh ausente no appliance para testar A/B"
		fi
	else
		skip "two-client automatizado: definir L7_CLIENT_A e L7_CLIENT_B"
	fi
}

if [ "$MODE" = "appliance" ]; then
	run_appliance_checks
else
	run_static_checks
fi

printf "\n"
if [ "$RC" -eq 0 ]; then
	printf "SMOKE ENFORCEMENT SCOPED (%s): ALL PASSED\n" "$MODE"
else
	printf "SMOKE ENFORCEMENT SCOPED (%s): FAILED (rc=%d)\n" "$MODE" "$RC"
fi
exit "$RC"
