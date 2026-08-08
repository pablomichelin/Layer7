#!/bin/sh
# Regressao: o destino local do portal/sinkhole nao pode voltar ao motor
# de policy. A decisao DNS permanece auditavel; fluxos seguintes sao debug.

set -eu

ROOT=$(cd "$(dirname "$0")/../.." && pwd)
SRC="$ROOT/src/layer7d/main.c"

pass() { printf 'PASS: %s\n' "$1"; }
fail() { printf 'FAIL: %s\n' "$1" >&2; exit 1; }

extract_function() {
    awk -v name="$1" '
        $0 ~ "^" name "\\(" { collecting = 1 }
        collecting {
            print
            opens += gsub(/\{/, "{")
            closes += gsub(/\}/, "}")
            if (opens > 0 && opens == closes) {
                exit
            }
        }
    ' "$SRC"
}

flow=$(extract_function layer7_on_classified_flow)
apply=$(extract_function layer7_apply_block_enforcement)

printf '%s\n' "$flow" | grep -Fq 'if (dst_ip && ip_is_local_iface_addr(dst_ip))' ||
    fail 'fluxo local sem guarda'
printf '%s\n' "$flow" | grep -Fq 'flow_skip: local sinkhole/portal' ||
    fail 'fluxo local sem diagnostico debug'

guard_line=$(printf '%s\n' "$flow" | grep -n -F 'if (dst_ip && ip_is_local_iface_addr(dst_ip))' | head -n 1 | cut -d: -f1)
decide_line=$(printf '%s\n' "$flow" | grep -n -F 'layer7_flow_decide(' | head -n 1 | cut -d: -f1)
[ "$guard_line" -lt "$decide_line" ] || fail 'guarda local ocorre apos policy'

printf '%s\n' "$apply" | grep -Fq 'strcmp(reason, "dns_block") == 0' ||
    fail 'decisao DNS sinkhole nao e diferenciada'
printf '%s\n' "$apply" | grep -Fq 'L7_AUDIT_NOTE' ||
    fail 'decisao DNS de sinkhole nao e auditada'
printf '%s\n' "$apply" | grep -Fq 'outcome=sinkhole' ||
    fail 'sinkhole DNS sem auditoria explicita'
printf '%s\n' "$apply" | grep -Fq 'skip local sinkhole/portal' ||
    fail 'repeticao local nao foi rebaixada para debug'
if grep -Fq 'skip IP local do firewall' "$SRC"; then
    fail 'mensagem informacional ruidosa antiga ainda existe'
fi

pass 'sinkhole local guard and audit contract'
