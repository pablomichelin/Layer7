#!/bin/sh
# Lab checklist GI7 / passo 20.26 — Identity políticas user/grupo.
# Uso no appliance (root), com Identity entitled + fontes (RADIUS/DC/LDAP) activas.
#
# Critérios:
#   GI7.1 — grupo AD bloqueia só IPs do grupo
#   GI7.2 — troca de IP → remap daemon → política segue o user
#   GI7.3 — políticas só IP/MAC inalteradas (também coberto por unit)
#   GI7.4 — precedência ad_* vs IP (unit PASS em 20.25)
#   GI7.5 — LDAP down → ad_* não-match; base intacta
#
# Não altera config automaticamente — só imprime passos e comandos de evidência.

set -eu

echo "=== Layer7 GI7 lab checklist (20.26) ==="
echo "Versão: $(layer7d -V 2>/dev/null || echo '?')"
echo

echo "-- Pré-condições --"
echo "1. Licença com entitlement Identity; GUI Identity enabled."
echo "2. Fonte activa (RADIUS e/ou DC agent e/ou LDAP groups cache)."
echo "3. mode=monitor ou enforce de lab; anotar enabled/mode:"
grep -E '"enabled"|"mode"' /usr/local/etc/layer7.json 2>/dev/null | head -5 || true
echo

echo "-- GI7.1 (grupo AD) --"
echo "1. Criar política block hosts=youtube.com + ad_groups=<GRUPO> priority alta."
echo "2. Upsert/lab: user IN grupo no IP A; user OUT grupo no IP B."
echo "3. DNS/fluxo A → block esperado; B → allow/default."
echo "4. Evidência: status identity_sessions + eventos decide."
echo

echo "-- GI7.2 (remap IP) --"
echo "1. User U no IP A (mapa)."
echo "2. Simular troca: remover A / upsert U no IP C (RADIUS Stop+Start ou DC)."
echo "3. Política ad_users=U deve deixar de casar A e passar a casar C."
echo "4. Evidência: dump identity map / status JSON."
echo

echo "-- GI7.3 (IP/MAC base) --"
echo "1. Política só src_hosts sem ad_* — comportamento idêntico a 1.9.8."
echo "2. Unit: test_policy_decide (suite sem Identity) PASS."
echo

echo "-- GI7.4 (precedência) --"
echo "Unit PASS: test_ad_priority_beats_static_ip (20.25)."
echo "Opcional lab: ad_group pri=90 block vs src_hosts pri=10 allow no mesmo IP."
echo

echo "-- GI7.5 (LDAP down) --"
echo "1. Com LDAP ON e grupos em cache, confirmar match ad_groups."
echo "2. Parar LDAP / firewall ao DC; esperar TTL do mapa/grupos."
echo "3. ad_* → não-match; políticas IP/MAC e VIP intactas."
echo "4. NÃO deve haver fail-closed da LAN."
echo

echo "-- Comandos úteis --"
echo "  service layer7d onestatus"
echo "  # status JSON / identity counters via GUI Status ou ficheiro status"
echo "  sockstat -4l | grep -E '8743|1813|layer7' || true"
echo
echo "Registar PASS/FAIL por critério em docs/09-blocking/plano-gates-identity-mitm.md"
echo "=== fim checklist ==="
