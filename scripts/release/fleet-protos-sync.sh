#!/bin/sh
# fleet-protos-sync.sh — Sincroniza custom protocols file para múltiplos firewalls.
#
# Este script NÃO requer recompilação. Distribui o ficheiro layer7-protos.txt
# para todos os firewalls e envia SIGHUP para recarregar sem reiniciar.
#
# Uso:
#   ./fleet-protos-sync.sh -i inventory.txt -f layer7-protos.txt
#   ./fleet-protos-sync.sh -i inventory.txt -f layer7-protos.txt --dry-run
#
# Cenários de uso:
#   - Bloquear novo domínio/app detectado
#   - Adicionar regra para aplicação interna
#   - Categorizar IP de parceiro/fornecedor
#   - Atualizar regras sem tocar no pacote ou no nDPI
#
# Formato do protos.txt:
#   host:"dominio.com"@NomeProtocolo
#   tcp:8080@HTTP
#   ip:1.2.3.4@CustomService

set -eu

INVENTORY=""
PROTOS_FILE=""
DRY_RUN=0
SSH_OPTS="-o ConnectTimeout=10 -o StrictHostKeyChecking=accept-new -o BatchMode=yes"
SSH_USER="root"
REMOTE_PATH="/usr/local/etc/layer7-protos.txt"

usage() {
    echo "Usage: $0 -i inventory.txt -f layer7-protos.txt [--dry-run] [--user USER]"
    exit 1
}

while [ $# -gt 0 ]; do
    case "$1" in
        -i) INVENTORY="$2"; shift 2 ;;
        -f) PROTOS_FILE="$2"; shift 2 ;;
        --dry-run) DRY_RUN=1; shift ;;
        --user) SSH_USER="$2"; shift 2 ;;
        -h|--help) usage ;;
        *) echo "Argumento desconhecido: $1"; usage ;;
    esac
done

if [ -z "$INVENTORY" ] || [ -z "$PROTOS_FILE" ]; then
    echo "ERRO: -i e -f são obrigatórios"
    usage
fi

if [ ! -f "$INVENTORY" ]; then
    echo "ERRO: inventário não encontrado: $INVENTORY"
    exit 1
fi

if [ ! -f "$PROTOS_FILE" ]; then
    echo "ERRO: protos file não encontrado: $PROTOS_FILE"
    exit 1
fi

RULES=$(grep -cv '^\s*$\|^\s*#' "$PROTOS_FILE" 2>/dev/null || echo 0)
echo "=== Layer7 Protos Sync ==="
echo "Ficheiro:  $PROTOS_FILE ($RULES regras)"
echo "Destino:   $REMOTE_PATH"
echo ""

HOSTS=""
HOST_COUNT=0
while IFS= read -r line || [ -n "$line" ]; do
    line=$(echo "$line" | sed 's/#.*//' | tr -d '[:space:]')
    [ -z "$line" ] && continue
    HOSTS="$HOSTS $line"
    HOST_COUNT=$((HOST_COUNT + 1))
done < "$INVENTORY"

echo "Firewalls: $HOST_COUNT"
echo ""

OK=0
FAIL=0

for host in $HOSTS; do
    if [ "$DRY_RUN" -eq 1 ]; then
        echo "  [$host] scp $PROTOS_FILE -> $REMOTE_PATH"
        echo "  [$host] kill -HUP \$(pgrep layer7d)"
        continue
    fi

    if ! scp $SSH_OPTS "$PROTOS_FILE" "${SSH_USER}@${host}:${REMOTE_PATH}" > /dev/null 2>&1; then
        echo "  [$host] FALHA: scp"
        FAIL=$((FAIL + 1))
        continue
    fi

    PID=$(ssh $SSH_OPTS "${SSH_USER}@${host}" "pgrep layer7d 2>/dev/null || echo ''" 2>/dev/null)
    if [ -n "$PID" ]; then
        ssh $SSH_OPTS "${SSH_USER}@${host}" "kill -HUP $PID" > /dev/null 2>&1
        echo "  [$host] OK (SIGHUP enviado, pid=$PID)"
    else
        echo "  [$host] OK (copiado, mas daemon não está a correr)"
    fi
    OK=$((OK + 1))
done

if [ "$DRY_RUN" -eq 0 ]; then
    echo ""
    echo "Resultado: $OK OK, $FAIL falhas (de $HOST_COUNT)"
fi
