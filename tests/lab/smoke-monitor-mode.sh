#!/bin/sh
# F5 smoke (Bloco 6 / Fase 1) — "monitor e monitor de verdade".
#
# Para correr no appliance pfSense apos instalar a build candidata:
#
#   1. Activar pacote (enabled=true) e gravar com mode=monitor (default OK).
#   2. SSH/console ao appliance.
#   3. sh /caminho/para/smoke-monitor-mode.sh
#
# Saida: linha PASS/FAIL por cenario; exit 0 = tudo PASS.

set -u
RC=0

pass() { printf "PASS: %s\n" "$1"; }
fail() { printf "FAIL: %s\n" "$1"; RC=1; }

CFG="/usr/local/etc/layer7.json"

if [ ! -f "$CFG" ]; then
	fail "config absent: $CFG"
	exit 1
fi

MODE=$(/usr/local/bin/php -r '
	$c = @json_decode(@file_get_contents("/usr/local/etc/layer7.json"), true);
	echo isset($c["layer7"]["mode"]) ? $c["layer7"]["mode"] : "monitor";
' 2>/dev/null)
EN=$(/usr/local/bin/php -r '
	$c = @json_decode(@file_get_contents("/usr/local/etc/layer7.json"), true);
	echo !empty($c["layer7"]["enabled"]) ? "1" : "0";
' 2>/dev/null)

printf "INFO: mode=%s enabled=%s\n" "$MODE" "$EN"

if [ "$MODE" != "monitor" ] || [ "$EN" != "1" ]; then
	fail "pre-condicao: pacote tem de estar enabled=true e mode=monitor"
	exit 1
fi

# 1) PF: nao deve ter `block drop quick` com label layer7
BLOCKS=$(pfctl -sr 2>/dev/null | grep -E 'block drop.*"layer7:' || true)
if [ -z "$BLOCKS" ]; then
	pass "PF sem block drop layer7 em modo monitor"
else
	printf "%s\n" "$BLOCKS"
	fail "PF tem block drop em monitor: $(echo "$BLOCKS" | wc -l) regra(s)"
fi

# 2) Tabelas persist tem de existir (mesmo em monitor)
for T in layer7_block layer7_block_dst layer7_tagged layer7_allow_dst; do
	if pfctl -t "$T" -T show >/dev/null 2>&1; then
		pass "tabela $T presente"
	else
		fail "tabela $T ausente"
	fi
done

# 3) layer7_block_dst tem de estar VAZIA em monitor
N=$(pfctl -t layer7_block_dst -T show 2>/dev/null | wc -l | tr -d ' ')
if [ "${N:-0}" -eq 0 ]; then
	pass "layer7_block_dst vazia em monitor"
else
	fail "layer7_block_dst tem $N IPs em monitor (esperado 0)"
fi

# 4) Daemon vivo
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

# 5) layer7-pfctl ensure idempotente em monitor (nao deve falhar)
if /usr/local/libexec/layer7-pfctl ensure >/dev/null 2>&1; then
	pass "layer7-pfctl ensure ok em monitor"
else
	fail "layer7-pfctl ensure falhou em monitor"
fi

exit "$RC"
