#!/bin/sh
# GV6 / passo 12.12 — smoke dual-stack IPv6 (BG-084).
#
# Modos:
#   static     — artefactos do repo (default sem L7_RUN_LAB=1)
#   lab        — coordenador Mac/host: SSH appliance + clientes A/B
#
# Uso lab (validacao-lab.md §21):
#   L7_RUN_LAB=1 sh tests/lab/run-ipv6-dualstack.sh
#
# Variáveis:
#   L7_APPLIANCE   default root@192.168.100.254
#   L7_CLIENT_A    default 192.168.100.234
#   L7_CLIENT_B    default 192.168.100.235
#   L7_CLIENT_PASS default pablo (sshpass)
#   L7_DNS         default 192.168.100.254
#   L7_BLOCK_HOST  default www.youtube.com
#   L7_OK_HOST     default www.google.com
#   L7_EVID_DIR    opcional; auto se vazio
#   L7_KEEP_CFG=1  não restaurar layer7.json após teste
#
# Exit 0 = PASS no modo actual. Não promove produção enforce.

set -u
RC=0
ROOT=$(cd "$(dirname "$0")/../.." && pwd)
cd "$ROOT" || exit 1

APPLIANCE=${L7_APPLIANCE:-root@192.168.100.254}
CLI_A=${L7_CLIENT_A:-192.168.100.234}
CLI_B=${L7_CLIENT_B:-192.168.100.235}
PASS=${L7_CLIENT_PASS:-pablo}
DNS=${L7_DNS:-192.168.100.254}
BLOCK_HOST=${L7_BLOCK_HOST:-www.youtube.com}
OK_HOST=${L7_OK_HOST:-www.google.com}
KEEP=${L7_KEEP_CFG:-0}
RUN_LAB=${L7_RUN_LAB:-0}

RUN_ID=$(date -u +%Y%m%dT%H%M%SZ)-gv6-dualstack
EVID=${L7_EVID_DIR:-$ROOT/docs/tests/evidence/$RUN_ID}
LOG="$EVID/smoke-output.txt"

mkdir -p "$EVID"
echo "$RUN_ID" > "$EVID/run_id.txt"

pass() { printf "PASS: %s\n" "$1" | tee -a "$LOG"; }
fail() { printf "FAIL: %s\n" "$1" | tee -a "$LOG"; RC=1; }
skip() { printf "SKIP: %s\n" "$1" | tee -a "$LOG"; }
hdr()  { printf "\n=== %s ===\n" "$1" | tee -a "$LOG"; }

ssh_fw() {
	ssh -o BatchMode=yes -o ConnectTimeout=12 "$APPLIANCE" "$@"
}

ssh_cli() {
	host=$1
	shift
	if command -v sshpass >/dev/null 2>&1; then
		sshpass -p "$PASS" ssh -o StrictHostKeyChecking=no -o BatchMode=yes \
			-o ConnectTimeout=12 "root@$host" "$@"
	else
		ssh -o BatchMode=yes -o ConnectTimeout=12 "root@$host" "$@"
	fi
}

curl_code() {
	# args: host family(-4|-6) url_host — só o http_code curl (000 se falhar)
	cli=$1
	fam=$2
	uhost=$3
	code=$(ssh_cli "$cli" "curl $fam -sS -o /dev/null -w '%{http_code}' --connect-timeout 5 --max-time 12 https://$uhost/ 2>/dev/null" || true)
	case "$code" in
		[0-9][0-9][0-9]) printf '%s\n' "$code" ;;
		*) printf '000\n' ;;
	esac
}

run_static() {
	hdr "GV6 static checks"
	for f in \
		docs/04-package/validacao-lab.md \
		docs/09-blocking/plano-gates-ipv6.md \
		docs/01-architecture/f4-ipv6-mapa-rastreabilidade.md \
		tests/lab/run-ipv6-dualstack.sh
	do
		if [ -f "$ROOT/$f" ]; then
			pass "artefacto: $f"
		else
			fail "artefacto ausente: $f"
		fi
	done
	if grep -q '## 21\.' "$ROOT/docs/04-package/validacao-lab.md" 2>/dev/null; then
		pass "validacao-lab.md §21 presente"
	else
		fail "validacao-lab.md §21 ausente"
	fi
	if grep -q 'run-ipv6-dualstack' \
		"$ROOT/docs/01-architecture/f4-ipv6-mapa-rastreabilidade.md" 2>/dev/null; then
		pass "mapa M-22 referencia run-ipv6-dualstack"
	else
		fail "mapa sem referência ao script"
	fi
	INC="$ROOT/package/pfSense-pkg-layer7/files/usr/local/pkg/layer7.inc"
	if grep -q 'inet6' "$INC" 2>/dev/null; then
		pass "layer7.inc emite inet6"
	else
		fail "layer7.inc sem inet6"
	fi
}

run_lab() {
	hdr "GV6 lab dual-stack — $RUN_ID"
	echo "appliance=$APPLIANCE A=$CLI_A B=$CLI_B block=$BLOCK_HOST ok=$OK_HOST" | tee -a "$LOG"

	# --- pré-condições ---
	hdr "21.0 pré-condições"
	FW_OUT=$(ssh_fw '
		pkg info -x layer7 2>/dev/null | head -1
		php -r '\''$c=json_decode(file_get_contents("/usr/local/etc/layer7.json"),true); $l=$c["layer7"];
			echo "en=".(!empty($l["enabled"])?"1":"0")." mode=".$l["mode"]." model=".$l["enforcement_model"]."\n";'\''
		pfctl -sr 2>/dev/null | grep -c "Layer7 lab GV4 allow IPv6 LAN" || true
		service layer7d onestatus 2>&1 | head -1
	') || { fail "SSH appliance"; return; }
	echo "$FW_OUT" | tee -a "$LOG" | tee "$EVID/00-precond.txt"
	echo "$FW_OUT" | grep -q 'pfSense-pkg-layer7' && pass "pkg layer7 instalado" || fail "pkg layer7"
	echo "$FW_OUT" | grep -q 'Layer7 lab GV4 allow IPv6 LAN\|^[1-9]' || true
	PASS6=$(echo "$FW_OUT" | sed -n '3p' | tr -d '[:space:]')
	case "$PASS6" in
		[1-9]*) pass "pass inet6 LAN lab presente ($PASS6)" ;;
		*) fail "falta regra pass inet6 LAN lab (ver validacao-lab §21.0)" ;;
	esac

	A6=$(ssh_cli "$CLI_A" 'ip -6 -o addr show scope global 2>/dev/null | awk "{print \$4}" | cut -d/ -f1' | tr '\n' ' ')
	B6=$(ssh_cli "$CLI_B" 'ip -6 -o addr show scope global 2>/dev/null | awk "{print \$4}" | cut -d/ -f1' | tr '\n' ' ')
	echo "A_v6=$A6" | tee -a "$LOG" | tee "$EVID/01-client-addrs.txt"
	echo "B_v6=$B6" | tee -a "$LOG"
	A6_1=$(echo "$A6" | awk '{print $1}')
	A6_2=$(echo "$A6" | awk '{print $2}')
	B6_1=$(echo "$B6" | awk '{print $1}')
	[ -n "$A6_1" ] && pass "cliente A tem GUA ($A6_1)" || fail "cliente A sem IPv6 GUA"
	[ -n "$B6_1" ] && pass "cliente B tem GUA ($B6_1)" || fail "cliente B sem IPv6 GUA"

	# --- backup + apply scoped ---
	hdr "21.1 apply scoped_hybrid (YouTube → A only)"
	ssh_fw "cp -f /usr/local/etc/layer7.json /tmp/layer7.json.pre-gv6" || fail "backup json"
	APPLY_PHP=$(mktemp /tmp/gv6-apply.XXXXXX.php)
	cat > "$APPLY_PHP" <<PHPEOF
<?php
require_once("/etc/inc/globals.inc");
require_once("/etc/inc/config.inc");
require_once("/etc/inc/filter.inc");
require_once("/usr/local/pkg/layer7.inc");
\$data = layer7_load_or_default();
\$l = &\$data["layer7"];
\$l["enabled"] = true;
\$l["mode"] = "enforce";
\$l["enforcement_model"] = "scoped_hybrid";
\$src = array("$CLI_A");
foreach (array("$A6_1", "$A6_2") as \$ip) {
	if (\$ip !== "" && !in_array(\$ip, \$src, true)) {
		\$src[] = \$ip;
	}
}
\$l["policies"] = array(array(
	"id" => "gv6-yt-a",
	"name" => "GV6 YouTube Client A",
	"enabled" => true,
	"action" => "block",
	"priority" => 10,
	"match" => array(
		"hosts" => array("youtube.com", "www.youtube.com", "googlevideo.com", "ytimg.com"),
		"src_hosts" => \$src,
	),
));
layer7_save_json(\$data);
layer7_pf_config_resync(true);
if (function_exists("filter_configure")) {
	filter_configure();
}
echo "APPLIED src=" . implode(",", \$src) . "\n";
PHPEOF
	scp -o BatchMode=yes -q "$APPLY_PHP" "$APPLIANCE:/tmp/gv6-apply.php"
	rm -f "$APPLY_PHP"
	APPLY_OUT=$(ssh_fw 'php /tmp/gv6-apply.php' 2>&1) || true
	echo "$APPLY_OUT" | tee -a "$LOG" | tee "$EVID/01b-apply.txt"
	echo "$APPLY_OUT" | grep -q APPLIED && pass "scoped_hybrid aplicado + filter_configure" \
		|| fail "apply scoped falhou"

	ssh_fw 'service layer7d onerestart >/dev/null 2>&1; sleep 2; service layer7d onestatus' | tee -a "$LOG"

	# --- DNS learn ---
	hdr "21.2 DNS A/AAAA → pdst"
	learn_dns() {
		ssh_cli "$CLI_A" "dig +time=2 +tries=1 @$DNS A $BLOCK_HOST +short; dig +time=2 +tries=1 @$DNS AAAA $BLOCK_HOST +short"
	}
	learn_dns | tee "$EVID/02-dns-learn.txt" | tee -a "$LOG"
	sleep 2
	has_v4=0
	has_v6=0
	i=0
	while [ "$i" -lt 5 ]; do
		i=$((i + 1))
		PDST=$(ssh_fw 'for t in 0 1 2 3 4 5 6 7 8 9; do
			out=$(pfctl -t layer7_pdst_$t -T show 2>/dev/null) || continue
			n=$(printf "%s\n" "$out" | sed "/^$/d" | wc -l | tr -d " ")
			[ "$n" -gt 0 ] || continue
			echo "pdst_$t count=$n"
			printf "%s\n" "$out"
		done')
		echo "$PDST" | tee "$EVID/03-pdst.txt" >/dev/null
		echo "--- pdst attempt $i ---" | tee -a "$LOG"
		echo "$PDST" | tee -a "$LOG"
		has_v4=0
		has_v6=0
		echo "$PDST" | grep -qE '([0-9]{1,3}\.){3}[0-9]{1,3}' && has_v4=1
		echo "$PDST" | grep -qE '([0-9a-fA-F]{0,4}:){2,}' && has_v6=1
		if [ "$has_v4" -eq 1 ] && [ "$has_v6" -eq 1 ]; then
			break
		fi
		learn_dns >/dev/null
		sleep 2
	done
	cp "$EVID/03-pdst.txt" "$EVID/03-pdst-final.txt" 2>/dev/null || true
	if [ "$has_v4" -eq 1 ] && [ "$has_v6" -eq 1 ]; then
		pass "pdst populado com A e AAAA"
	elif echo "$PDST" | grep -qE 'pdst_[0-9]+ count=[1-9]'; then
		fail "pdst parcial (v4=$has_v4 v6=$has_v6) — dual-stack incompleto"
	else
		fail "pdst vazio após dig local"
	fi

	# --- two-client traffic ---
	hdr "21.3 two-client curl v4/v6"
	AY4=$(curl_code "$CLI_A" -4 "$BLOCK_HOST")
	AY6=$(curl_code "$CLI_A" -6 "$BLOCK_HOST")
	AG4=$(curl_code "$CLI_A" -4 "$OK_HOST")
	AG6=$(curl_code "$CLI_A" -6 "$OK_HOST")
	BY4=$(curl_code "$CLI_B" -4 "$BLOCK_HOST")
	BY6=$(curl_code "$CLI_B" -6 "$BLOCK_HOST")
	BG4=$(curl_code "$CLI_B" -4 "$OK_HOST")
	BG6=$(curl_code "$CLI_B" -6 "$OK_HOST")
	{
		echo "A yt4=$AY4 yt6=$AY6 g4=$AG4 g6=$AG6"
		echo "B yt4=$BY4 yt6=$BY6 g4=$BG4 g6=$BG6"
	} | tee "$EVID/04-curl-results.txt" | tee -a "$LOG"

	# Expect: A blocked on YT (000/timeout), B free (2xx), Google free both
	case "$AY4" in 000) pass "A yt4 bloqueado ($AY4)" ;; *) fail "A yt4 esperado 000 got $AY4" ;; esac
	case "$AY6" in 000) pass "A yt6 bloqueado ($AY6)" ;; *) fail "A yt6 esperado 000 got $AY6" ;; esac
	case "$AG4" in 2??) pass "A google4 OK ($AG4)" ;; *) fail "A google4 ($AG4)" ;; esac
	case "$AG6" in 2??) pass "A google6 OK ($AG6)" ;; *) fail "A google6 ($AG6)" ;; esac
	case "$BY4" in 2??) pass "B yt4 livre ($BY4)" ;; *) fail "B yt4 ($BY4)" ;; esac
	case "$BY6" in 2??) pass "B yt6 livre ($BY6)" ;; *) fail "B yt6 ($BY6)" ;; esac

	# --- IPv4 regression light ---
	hdr "21.4 regressão IPv4 leve"
	case "$BG4" in 2??) pass "B google4 OK ($BG4)" ;; *) fail "B google4 regressão ($BG4)" ;; esac

	# --- NDP sanity ---
	hdr "21.5 NDP / reachability"
	NDP=$(ssh_cli "$CLI_A" 'ping6 -c 1 -W 2 fe80::1%ens160 2>&1 | tail -2; ip -6 neigh | head -5')
	echo "$NDP" | tee "$EVID/05-ndp.txt" | tee -a "$LOG"
	pass "NDP sample capturado (manual review se FAIL alhures)"

	# --- restore ---
	hdr "21.6 rollback config"
	if [ "$KEEP" = "1" ]; then
		skip "KEEP_CFG=1 — não restaurou layer7.json"
	else
		REST_PHP=$(mktemp /tmp/gv6-restore.XXXXXX.php)
		cat > "$REST_PHP" <<'PHPEOF'
<?php
require_once("/etc/inc/globals.inc");
require_once("/etc/inc/config.inc");
require_once("/etc/inc/filter.inc");
require_once("/usr/local/pkg/layer7.inc");
layer7_pf_config_resync(true);
if (function_exists("filter_configure")) {
	filter_configure();
}
echo "RESTORED\n";
PHPEOF
		scp -o BatchMode=yes -q "$REST_PHP" "$APPLIANCE:/tmp/gv6-restore.php"
		rm -f "$REST_PHP"
		ssh_fw '
			if [ -f /tmp/layer7.json.pre-gv6 ]; then
				cp -f /tmp/layer7.json.pre-gv6 /usr/local/etc/layer7.json
			elif [ -f /tmp/layer7.json.pre-gv-ipv6 ]; then
				cp -f /tmp/layer7.json.pre-gv-ipv6 /usr/local/etc/layer7.json
			fi
			php /tmp/gv6-restore.php
			service layer7d onerestart >/dev/null 2>&1
			php -r '\''$c=json_decode(file_get_contents("/usr/local/etc/layer7.json"),true); $l=$c["layer7"];
				echo "model=".$l["enforcement_model"]." pols=".count($l["policies"])."\n";'\''
		' | tee "$EVID/06-restore.txt" | tee -a "$LOG"
		grep -q RESTORED "$EVID/06-restore.txt" && pass "config restaurada + filter_configure" \
			|| fail "restore falhou"
	fi

	# summary
	{
		echo "run_id=$RUN_ID"
		echo "verdict=$([ "$RC" -eq 0 ] && echo PASS || echo FAIL)"
		cat "$EVID/04-curl-results.txt" 2>/dev/null
	} > "$EVID/00-SUMMARY.txt"
}

# --- main ---
: > "$LOG"
hdr "run-ipv6-dualstack"
run_static
if [ "$RUN_LAB" = "1" ]; then
	run_lab
else
	skip "lab: defina L7_RUN_LAB=1 para campanha appliance (validacao-lab §21)"
fi

hdr "resultado"
if [ "$RC" -eq 0 ]; then
	pass "run-ipv6-dualstack overall"
else
	fail "run-ipv6-dualstack overall"
fi
exit "$RC"
