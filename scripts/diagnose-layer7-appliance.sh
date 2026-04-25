#!/bin/sh
# Diagnóstico rápido Layer7 no pfSense (shell após opção 8, ou root SSH directo).
# Não requer parâmetros. Copiar via SCP ou colar no shell. Saída: colar no chat ou ficheiro.
# Uso: sh diagnose-layer7-appliance.sh 2>&1 | tee /tmp/l7-diag.txt

echo "========== l7-diag: host / OS =========="
hostname 2>/dev/null || true
uname -a 2>/dev/null || true

echo
echo "========== l7-diag: pacote =========="
pkg info -x pfSense-pkg-layer7 2>/dev/null || echo "(pkg: sem registo pfSense-pkg-layer7?)"

echo
echo "========== l7-diag: layer7d (pid) =========="
if [ -f /var/run/layer7d.pid ]; then
	odpid=$(tr -d ' \n\r' < /var/run/layer7d.pid)
	echo "pidfile: ${odpid}"
	if [ -n "$odpid" ] && kill -0 "$odpid" 2>/dev/null; then
		echo "processo: vivo"
		/bin/kill -USR1 "$odpid" 2>/dev/null || true
		sleep 1
	else
		echo "processo: morto ou pid inválido"
	fi
else
	echo "sem /var/run/layer7d.pid (serviço parado?)"
fi

echo
echo "========== l7-diag: stats (início) =========="
if [ -f /tmp/layer7-stats.json ]; then
	/bin/cat /tmp/layer7-stats.json 2>/dev/null | /usr/bin/head -c 3000
	echo
	[ $(/usr/bin/wc -c < /tmp/layer7-stats.json) -gt 3000 ] && echo "… (JSON truncado na cópia)"
else
	echo "sem /tmp/layer7-stats.json (SIGHUP/USR1 no daemon?)"
fi

echo
echo "========== l7-diag: layer7.json (mode / enabled) =========="
if [ -f /usr/local/etc/layer7.json ]; then
	/bin/grep -E '"mode"|"enabled"' /usr/local/etc/layer7.json 2>/dev/null | /usr/bin/head -30 || true
	# reforço: se mode não for "enforce", pfctl de bloqueio por política não aplica
else
	echo "falta /usr/local/etc/layer7.json"
fi

echo
echo "========== l7-diag: licença (ficheiro) =========="
if [ -f /usr/local/etc/layer7.lic ]; then
	echo "ficheiro .lic: existe"; /bin/ls -la /usr/local/etc/layer7.lic
else
	echo "sem /usr/local/etc/layer7.lic (ou caminho legado) — ver stats license_* acima"
fi

echo
echo "========== l7-diag: pf regras (layer7) =========="
pfctl -sr 2>/dev/null | /usr/bin/grep -i layer7 | /usr/bin/head -40 || echo "(nenhuma linha com 'layer7' em pfctl -sr — filtro ainda não gera?)"

echo
echo "========== l7-diag: tabelas PF (amostra) =========="
for t in layer7_block layer7_block_dst; do
	echo "--- $t ---"
	pfctl -t "$t" -T show 2>&1 | /usr/bin/head -15
	echo
done

echo
echo "========== l7-diag: serviço rc =========="
if [ -f /usr/local/etc/rc.d/layer7d ]; then
	service layer7d onestatus 2>&1 || true
else
	echo "rc.d layer7d em falta?"
fi

echo
echo "========== l7-diag: fim =========="
