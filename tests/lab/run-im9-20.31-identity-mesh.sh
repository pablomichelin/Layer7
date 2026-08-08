#!/bin/sh
# IM9 / passo 20.31 — malha lab Identity (rede) + evidências.
#
# Não activa Identity nem MITM. Não altera layer7.json.
# Foco: não-regressão OFF + inventário honesto do que falta em lab AD/DC/RADIUS.
#
# Uso (appliance root):
#   sh tests/lab/run-im9-20.31-identity-mesh.sh
#   sh tests/lab/run-im9-20.31-identity-mesh.sh /caminho/para/outdir
#
# Critérios cobertos aqui:
#   IM9-NR — Identity OFF / sem .lic → sem portos Identity; daemon vivo
#   IM9-GUI — texto ADR-0029 presente na página Identity
#   IM9-MITM — DEFER (não exercitar intercept)
# Residuais (checklist only): GI5.1, GI6.*, GI7 lab AD — ver README da evidência

set -eu

OUTDIR="${1:-}"
CFG="/usr/local/etc/layer7.json"
RC=0

pass() { printf "PASS: %s\n" "$1"; }
fail() { printf "FAIL: %s\n" "$1"; RC=1; }
info() { printf "INFO: %s\n" "$1"; }
skip() { printf "SKIP: %s\n" "$1"; }

if [ -n "$OUTDIR" ]; then
	mkdir -p "$OUTDIR"
	# shellcheck disable=SC2094
	exec >"$OUTDIR/01-mesh-output.txt" 2>&1
fi

echo "=== Layer7 IM9 / 20.31 identity mesh ==="
echo "host=$(hostname 2>/dev/null || echo '?')"
echo "date_utc=$(date -u '+%Y-%m-%dT%H:%M:%SZ' 2>/dev/null || date)"
echo

VER="$(layer7d -V 2>/dev/null || echo '?')"
PKG="$(pkg query %v pfSense-pkg-layer7 2>/dev/null || echo '?')"
info "layer7d=$VER pkg=$PKG"

case "$VER" in
*1.9.29*) pass "versão daemon 1.9.29" ;;
*) fail "versão daemon esperada 1.9.29 (got $VER)" ;;
esac
case "$PKG" in
1.9.29) pass "pkg 1.9.29" ;;
*) fail "pkg esperado 1.9.29 (got $PKG)" ;;
esac

if [ ! -f "$CFG" ]; then
	fail "config ausente: $CFG"
	exit 1
fi

php -r '
$j=@json_decode(@file_get_contents("/usr/local/etc/layer7.json"), true);
$l7=isset($j["layer7"])?$j["layer7"]:$j;
$iden=isset($l7["identity"])?$l7["identity"]:null;
echo "enabled=".(!empty($l7["enabled"])?"true":"false")."\n";
echo "mode=".($l7["mode"]??"")."\n";
echo "identity_key=".(array_key_exists("identity",$l7)?"present":"absent")."\n";
echo "identity_enabled=";
if (!is_array($iden)) { echo "null\n"; }
else { echo (!empty($iden["enabled"])?"true":"false")."\n"; }
' 2>/dev/null || fail "php parse layer7.json"

EN=$(php -r '$c=@json_decode(@file_get_contents("/usr/local/etc/layer7.json"),true);$l7=isset($c["layer7"])?$c["layer7"]:$c;echo !empty($l7["enabled"])?"1":"0";' 2>/dev/null || echo "?")
IDEN=$(php -r '$c=@json_decode(@file_get_contents("/usr/local/etc/layer7.json"),true);$l7=isset($c["layer7"])?$c["layer7"]:$c;$i=$l7["identity"]??null;if(!is_array($i)){echo "null";}else{echo !empty($i["enabled"])?"1":"0";}' 2>/dev/null || echo "?")

if [ "$IDEN" = "1" ]; then
	fail "Identity enabled=true neste appliance — 20.31 mesh OFF espera Identity OFF"
else
	pass "Identity OFF ou ausente (inerte)"
fi

if [ -f /usr/local/etc/layer7.lic ]; then
	info "layer7.lic presente (features a inspeccionar manualmente)"
	# não falhar — produção pode ter licença base
else
	pass "sem layer7.lic (gate Identity inerte por desenho)"
fi

if service layer7d onestatus >/dev/null 2>&1; then
	pass "layer7d running"
else
	fail "layer7d not running"
fi

PORTS=$(sockstat -4l 2>/dev/null | grep -E ':(8743|1813)\b' || true)
if [ -z "$PORTS" ]; then
	pass "sem listener Identity 8743/1813 (OFF)"
else
	fail "portos Identity abertos com módulo OFF: $PORTS"
fi

GUI="/usr/local/www/packages/layer7/layer7_identity.php"
if [ -f "$GUI" ] && grep -q "ADR-0029" "$GUI"; then
	pass "GUI Identity contém ADR-0029 (H*)"
else
	fail "GUI Identity sem texto ADR-0029"
fi

if [ -f /usr/local/www/packages/layer7/layer7_mitm.php ]; then
	info "MITM page presente (UI); runtime DEFER — não activar"
	skip "MITM intercept (DEFER 20.7a)"
else
	info "MITM page ausente"
fi

# Não-regressão PF mínima com enabled=false: sem block layer7
BLOCKS=$(pfctl -sr 2>/dev/null | grep -E 'block drop.*"layer7:' || true)
if [ -z "$BLOCKS" ]; then
	pass "PF sem block drop layer7 (estado actual)"
else
	# se enabled=false não deveria haver; se enabled monitor pode haver? smoke exige monitor+enabled
	if [ "$EN" = "0" ]; then
		fail "PF tem block layer7 com enabled=false"
	else
		info "PF tem regras layer7 com enabled=true — fora do escopo mesh OFF"
	fi
fi

THR=$(ps -H -p "$(cat /var/run/layer7d.pid 2>/dev/null)" 2>/dev/null | wc -l | tr -d ' ' || echo 0)
info "process_lines_around_pid≈$THR (ADR-0028: sem threads Identity OFF)"

echo
echo "-- Residuais lab (humano / AD+DC+RADIUS) — não executados aqui --"
echo "  GI5.1 expand grupo LDAP em lab"
echo "  GI5.3 NAS físico RADIUS accounting"
echo "  GI6.1–6.5 DC agent + conflito + logout em lab"
echo "  GI7.1–7.5 checklist: tests/lab/run-gi7-identity-policies.sh"
echo "  Unitário GI7: tests/run-local.sh (builder/dev)"
echo
echo "=== fim mesh; exit=$RC ==="
exit "$RC"
