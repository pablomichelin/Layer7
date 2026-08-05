#!/bin/sh
# Diagnostico rapido Layer7 no pfSense (shell apos opcao 8, ou root SSH directo).
# Saida legivel para colar no chat/issue ou ficheiro.
#
# Uso no appliance:
#   sh diagnose-layer7-appliance.sh 2>&1 | tee /tmp/l7-diag.txt
#
# Copiar para o pfSense (exemplo a partir do workspace):
#   scp scripts/diagnose-layer7-appliance.sh root@192.168.100.254:/tmp/
#   ssh root@192.168.100.254 'sh /tmp/diagnose-layer7-appliance.sh'

L7D=/usr/local/sbin/layer7d
CFG=/usr/local/etc/layer7.json
LOG=/var/log/system.log
MAX_TBL=15

echo "========== l7-diag: host / OS =========="
hostname 2>/dev/null || true
uname -a 2>/dev/null || true
date 2>/dev/null || true

echo
echo "========== l7-diag: pacote / binario =========="
pkg info -x pfSense-pkg-layer7 2>/dev/null || echo "(pkg: sem registo pfSense-pkg-layer7?)"
if [ -x "$L7D" ]; then
	echo "--- layer7d -V ---"
	"$L7D" -V 2>/dev/null || echo "(layer7d -V falhou)"
else
	echo "layer7d ausente: $L7D"
fi

echo
echo "========== l7-diag: licenca (--license-status) =========="
if [ -x "$L7D" ]; then
	"$L7D" --license-status 2>&1 || echo "(exit $? — licenca invalida/ausente e esperado sem .lic)"
else
	echo "layer7d nao executavel — skip --license-status"
fi
if [ -f /usr/local/etc/layer7.lic ]; then
	echo "ficheiro .lic: existe"
	ls -la /usr/local/etc/layer7.lic 2>/dev/null || true
else
	echo "sem /usr/local/etc/layer7.lic"
fi

echo
echo "========== l7-diag: layer7.json (mode / enabled / enforcement_model) =========="
if [ -f "$CFG" ]; then
	grep -E '"mode"|"enabled"|"enforcement_model"' "$CFG" 2>/dev/null | head -20 || true
	if command -v php >/dev/null 2>&1; then
		/usr/local/bin/php -r '
			$c = @json_decode(@file_get_contents("'"$CFG"'"), true);
			$l = $c["layer7"] ?? [];
			printf("parsed: mode=%s enabled=%s enforcement_model=%s\n",
				$l["mode"] ?? "(default monitor)",
				isset($l["enabled"]) ? ($l["enabled"] ? "true" : "false") : "(unset)",
				$l["enforcement_model"] ?? "legacy_global (implicito)");
		' 2>/dev/null || true
	fi
else
	echo "falta $CFG"
fi

echo
echo "========== l7-diag: layer7d (pid / stats) =========="
if [ -f /var/run/layer7d.pid ]; then
	odpid=$(tr -d ' \n\r' < /var/run/layer7d.pid)
	echo "pidfile: ${odpid}"
	if [ -n "$odpid" ] && kill -0 "$odpid" 2>/dev/null; then
		echo "processo: vivo"
		if [ "${L7_DIAG_REFRESH_STATS:-0}" = "1" ]; then
			echo "stats: refresh SIGUSR1 solicitado explicitamente"
			kill -USR1 "$odpid" 2>/dev/null || true
			sleep 1
		else
			echo "stats: leitura passiva (sem sinal; use L7_DIAG_REFRESH_STATS=1 apenas em lab)"
		fi
	else
		echo "processo: morto ou pid invalido"
	fi
else
	echo "sem /var/run/layer7d.pid (servico parado?)"
fi

echo "--- stats JSON (inicio) ---"
if [ -f /var/db/layer7/layer7-stats.json ]; then
	head -c 4000 /var/db/layer7/layer7-stats.json 2>/dev/null
	echo
	[ "$(wc -c < /var/db/layer7/layer7-stats.json)" -gt 4000 ] && echo "... (JSON truncado)"
elif [ -f /tmp/layer7-stats.json ]; then
	head -c 4000 /tmp/layer7-stats.json 2>/dev/null
	echo
	[ "$(wc -c < /tmp/layer7-stats.json)" -gt 4000 ] && echo "... (JSON truncado, legado /tmp)"
else
	echo "sem layer7-stats.json (SIGHUP/USR1 no daemon?)"
fi

echo
echo "========== l7-diag: pf regras (layer7) =========="
pfctl -sr 2>/dev/null | grep -i layer7 | head -50 || \
	echo "(nenhuma linha com layer7 em pfctl -sr)"

echo
echo "========== l7-diag: tabelas PF =========="
for t in layer7_block layer7_block_dst layer7_tagged layer7_allow_dst; do
	echo "--- $t ---"
	pfctl -t "$t" -T show 2>&1 | head -"$MAX_TBL"
	n=$(pfctl -t "$t" -T show 2>/dev/null | wc -l | tr -d ' ')
	[ "${n:-0}" -gt "$MAX_TBL" ] && echo "... (+$((n - MAX_TBL)) entradas)"
	echo
done

echo "--- layer7_pdst_* / layer7_psrc_* (scoped) ---"
pfctl -s Tables 2>/dev/null | grep -E 'layer7_p(dst|src)_' | while read -r tbl rest; do
	[ -z "$tbl" ] && continue
	echo "=== $tbl ==="
	pfctl -t "$tbl" -T show 2>&1 | head -"$MAX_TBL"
	echo
done
if ! pfctl -s Tables 2>/dev/null | grep -q 'layer7_p'; then
	echo "(nenhuma tabela layer7_pdst_* / layer7_psrc_* — legacy_global ou sem reload scoped?)"
fi

echo "--- layer7_bld_* (blacklists) ---"
pfctl -s Tables 2>/dev/null | grep 'layer7_bld_' | while read -r tbl rest; do
	[ -z "$tbl" ] && continue
	echo "=== $tbl ==="
	pfctl -t "$tbl" -T show 2>&1 | head -"$MAX_TBL"
	echo
done

echo
echo "========== l7-diag: logs layer7d (flow_decide / dns_block / enforce_block) =========="
if [ -f "$LOG" ]; then
	grep layer7d "$LOG" 2>/dev/null | \
		grep -E 'flow_decide|dns_block|enforce_block|flow_block|allowlist_' | \
		tail -25 || echo "(sem linhas recentes com esses marcadores)"
else
	echo "log $LOG ausente"
fi

echo
echo "========== l7-diag: servico rc =========="
if [ -f /usr/local/etc/rc.d/layer7d ]; then
	service layer7d onestatus 2>&1 || true
else
	echo "rc.d layer7d em falta?"
fi

echo
echo "========== l7-diag: fim =========="
