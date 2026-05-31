#!/bin/sh
# F5 alargada (Caminho A / A5) — regressao dos blocos A0..A4 no appliance.
#
# READ-ONLY do ponto de vista de enforcement: exercita as funcoes do pacote
# (perfis, inventario de dispositivos, resolucao MAC->IP, contadores por
# perfil) e o parsing do daemon (sni_inspection), sem alterar a config nem
# impor bloqueios. Nao substitui o smoke-monitor-mode.sh (gate de monitor);
# corre os dois para cobertura completa.
#
# Uso no appliance:  sh /caminho/para/smoke-caminho-a.sh
# Saida: PASS/FAIL por cenario; exit 0 = tudo PASS.

set -u
RC=0
PHP=/usr/local/bin/php
INC=/usr/local/pkg/layer7.inc
D=/usr/local/sbin/layer7d

pass() { printf "PASS: %s\n" "$1"; }
fail() { printf "FAIL: %s\n" "$1"; RC=1; }

[ -f "$INC" ] || { fail "layer7.inc ausente: $INC"; exit 1; }

# A0 — perfis carregam e o perfil github existe.
NPROF=$("$PHP" -r 'require_once("'"$INC"'"); echo count(layer7_load_profiles());' 2>/dev/null)
if [ -n "$NPROF" ] && [ "$NPROF" -ge 1 ] 2>/dev/null; then
	pass "A0 perfis carregam (n=$NPROF)"
else
	fail "A0 perfis nao carregam"
fi
HASGH=$("$PHP" -r 'require_once("'"$INC"'"); $f=0; foreach(layer7_load_profiles() as $p){ if(($p["id"]??"")==="github"){$f=1;} } echo $f;' 2>/dev/null)
[ "$HASGH" = "1" ] && pass "A0 perfil github presente" || fail "A0 perfil github ausente"

# A1 — inventario de dispositivos devolve array (DHCP/ARP).
NDEV=$("$PHP" -r 'require_once("'"$INC"'"); $i=function_exists("layer7_device_inventory")?layer7_device_inventory():null; echo is_array($i)?count($i):"-1";' 2>/dev/null)
if [ -n "$NDEV" ] && [ "$NDEV" -ge 0 ] 2>/dev/null; then
	pass "A1 inventario de dispositivos ok (n=$NDEV)"
else
	fail "A1 inventario de dispositivos falhou"
fi

# A2 — helpers de resolucao MAC->IP existem e validam MAC.
OKA2=$("$PHP" -r 'require_once("'"$INC"'");
	$ok = function_exists("layer7_resolve_macs_to_ips")
	   && function_exists("layer7_normalize_macs")
	   && function_exists("layer7_devices_resync")
	   && layer7_device_mac_valid("aa:bb:cc:dd:ee:ff")
	   && !layer7_device_mac_valid("zz:zz");
	echo $ok?"1":"0";' 2>/dev/null)
[ "$OKA2" = "1" ] && pass "A2 helpers MAC->IP presentes/validacao ok" || fail "A2 helpers MAC->IP em falta"

# A3 — daemon parseia sni_inspection nas duas posicoes (antes/depois policies).
if [ -x "$D" ]; then
	T=/tmp/l7_a3_test.json
	printf '%s' '{"layer7":{"enabled":true,"mode":"monitor","policies":[{"id":"p1","enabled":true,"action":"monitor","match":{}}],"sni_inspection":true,"interfaces":["lan"]}}' > "$T"
	OUT=$("$D" -t -c "$T" 2>&1)
	# -t nao falha por causa de sni; aceitamos exit 0 como "config valida".
	if [ $? -eq 0 ]; then
		pass "A3 daemon aceita config com sni_inspection (apos policies)"
	else
		fail "A3 daemon rejeitou config com sni_inspection"
	fi
	rm -f "$T"
else
	printf "SKIP: layer7d ausente, A3 parse nao testado\n"
fi

# A4 — contadores por perfil devolvem um map (mesmo sem stats).
OKA4=$("$PHP" -r 'require_once("'"$INC"'");
	$h = layer7_profile_hit_counts(layer7_load_profiles(), layer7_read_stats());
	echo is_array($h)?"1":"0";' 2>/dev/null)
[ "$OKA4" = "1" ] && pass "A4 contadores por perfil ok" || fail "A4 contadores por perfil falharam"

printf "\n"
if [ "$RC" -eq 0 ]; then
	printf "SMOKE CAMINHO A: ALL PASSED\n"
else
	printf "SMOKE CAMINHO A: FAILED (rc=%d)\n" "$RC"
fi
exit "$RC"
