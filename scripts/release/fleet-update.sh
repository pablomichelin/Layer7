#!/bin/sh
# fleet-update.sh — Distribui pacote Layer7 para múltiplos firewalls pfSense.
#
# Compila UMA VEZ no builder, depois distribui o .pkg para N firewalls via SSH.
#
# Uso:
#   ./fleet-update.sh -i inventory.txt -p pfSense-pkg-layer7-0.1.0.pkg
#   ./fleet-update.sh -i inventory.txt -p pfSense-pkg-layer7-0.1.0.pkg --dry-run
#   ./fleet-update.sh -i inventory.txt -p pfSense-pkg-layer7-0.1.0.pkg --parallel 4
#
# Ficheiro inventory.txt (um firewall por linha):
#   192.168.0.195   # escritório principal
#   10.0.1.1        # filial SP
#   10.0.2.1        # filial RJ
#   # linhas com # são ignoradas
#
# Pré-requisitos:
#   - SSH key-based auth configurado para todos os firewalls (ssh-copy-id root@IP)
#   - O ficheiro .pkg já compilado (via deployz.sh ou update-ndpi.sh no builder)
#
# O que o script faz em cada firewall:
#   1. Copia o .pkg via scp
#   2. Para o daemon
#   3. Instala o pacote (pkg add -f)
#   4. Inicia o daemon
#   5. Verifica que está a correr (SIGUSR1 stats)
#   6. Reporta sucesso/falha

set -eu

INVENTORY=""
PKG_FILE=""
DRY_RUN=0
PARALLEL=1
SSH_OPTS="-o ConnectTimeout=10 -o StrictHostKeyChecking=accept-new -o BatchMode=yes"
SSH_USER="root"
LOG_DIR="/tmp/layer7-fleet-$(date +%Y%m%d-%H%M%S)"

usage() {
    echo "Usage: $0 -i inventory.txt -p package.pkg [--dry-run] [--parallel N] [--user USER]"
    echo ""
    echo "  -i FILE       Ficheiro com IPs dos firewalls (um por linha)"
    echo "  -p FILE       Pacote .pkg a instalar"
    echo "  --dry-run     Mostrar o que seria feito sem executar"
    echo "  --parallel N  Número de firewalls simultâneos (default: 1)"
    echo "  --user USER   Utilizador SSH (default: root)"
    exit 1
}

while [ $# -gt 0 ]; do
    case "$1" in
        -i) INVENTORY="$2"; shift 2 ;;
        -p) PKG_FILE="$2"; shift 2 ;;
        --dry-run) DRY_RUN=1; shift ;;
        --parallel) PARALLEL="$2"; shift 2 ;;
        --user) SSH_USER="$2"; shift 2 ;;
        -h|--help) usage ;;
        *) echo "Argumento desconhecido: $1"; usage ;;
    esac
done

if [ -z "$INVENTORY" ] || [ -z "$PKG_FILE" ]; then
    echo "ERRO: -i e -p são obrigatórios"
    usage
fi

if [ ! -f "$INVENTORY" ]; then
    echo "ERRO: ficheiro inventário não encontrado: $INVENTORY"
    exit 1
fi

if [ ! -f "$PKG_FILE" ]; then
    echo "ERRO: pacote não encontrado: $PKG_FILE"
    exit 1
fi

PKG_NAME=$(basename "$PKG_FILE")

HOSTS=""
HOST_COUNT=0
while IFS= read -r line || [ -n "$line" ]; do
    line=$(echo "$line" | sed 's/#.*//' | tr -d '[:space:]')
    [ -z "$line" ] && continue
    HOSTS="$HOSTS $line"
    HOST_COUNT=$((HOST_COUNT + 1))
done < "$INVENTORY"

if [ "$HOST_COUNT" -eq 0 ]; then
    echo "ERRO: nenhum host encontrado em $INVENTORY"
    exit 1
fi

echo "=== Layer7 Fleet Update ==="
echo "Pacote:     $PKG_NAME"
echo "Firewalls:  $HOST_COUNT"
echo "Paralelo:   $PARALLEL"
echo "Dry-run:    $([ "$DRY_RUN" -eq 1 ] && echo 'SIM' || echo 'NÃO')"
echo ""

if [ "$DRY_RUN" -eq 1 ]; then
    echo "--- Modo dry-run: apenas mostrando o plano ---"
    for host in $HOSTS; do
        echo "  [$host] scp $PKG_NAME -> /tmp/"
        echo "  [$host] service layer7d onestop"
        echo "  [$host] IGNORE_OSVERSION=yes pkg add -f /tmp/$PKG_NAME"
        echo "  [$host] service layer7d onestart"
        echo "  [$host] kill -USR1 \$(pgrep layer7d) + verificar"
        echo ""
    done
    echo "Total: $HOST_COUNT firewalls"
    exit 0
fi

mkdir -p "$LOG_DIR"
echo "Logs: $LOG_DIR"
echo ""

update_host() {
    host="$1"
    log="$LOG_DIR/${host}.log"
    echo "[$host] Iniciando..." | tee -a "$log"

    if ! ssh $SSH_OPTS "${SSH_USER}@${host}" "echo ok" > /dev/null 2>&1; then
        echo "[$host] FALHA: SSH não conectou" | tee -a "$log"
        return 1
    fi

    echo "[$host] Copiando pacote..." | tee -a "$log"
    if ! scp $SSH_OPTS "$PKG_FILE" "${SSH_USER}@${host}:/tmp/${PKG_NAME}" >> "$log" 2>&1; then
        echo "[$host] FALHA: scp falhou" | tee -a "$log"
        return 1
    fi

    echo "[$host] Parando daemon..." | tee -a "$log"
    ssh $SSH_OPTS "${SSH_USER}@${host}" "service layer7d onestop 2>/dev/null; true" >> "$log" 2>&1

    echo "[$host] Instalando $PKG_NAME..." | tee -a "$log"
    if ! ssh $SSH_OPTS "${SSH_USER}@${host}" \
        "IGNORE_OSVERSION=yes pkg add -f /tmp/${PKG_NAME} 2>&1" >> "$log" 2>&1; then
        echo "[$host] FALHA: pkg add falhou" | tee -a "$log"
        return 1
    fi

    echo "[$host] Iniciando daemon..." | tee -a "$log"
    ssh $SSH_OPTS "${SSH_USER}@${host}" "service layer7d onestart" >> "$log" 2>&1

    sleep 2

    echo "[$host] Verificando..." | tee -a "$log"
    VER=$(ssh $SSH_OPTS "${SSH_USER}@${host}" \
        "/usr/local/sbin/layer7d -V 2>/dev/null || echo '?'" 2>/dev/null)
    PID=$(ssh $SSH_OPTS "${SSH_USER}@${host}" \
        "pgrep layer7d 2>/dev/null || echo ''" 2>/dev/null)

    if [ -n "$PID" ]; then
        echo "[$host] OK: version=$VER pid=$PID" | tee -a "$log"
    else
        echo "[$host] AVISO: daemon não está a correr (verificar logs)" | tee -a "$log"
    fi

    ssh $SSH_OPTS "${SSH_USER}@${host}" "rm -f /tmp/${PKG_NAME}" >> "$log" 2>&1
    return 0
}

OK_COUNT=0
FAIL_COUNT=0
RUNNING=0

for host in $HOSTS; do
    if [ "$PARALLEL" -le 1 ]; then
        if update_host "$host"; then
            OK_COUNT=$((OK_COUNT + 1))
        else
            FAIL_COUNT=$((FAIL_COUNT + 1))
        fi
    else
        update_host "$host" &
        RUNNING=$((RUNNING + 1))
        if [ "$RUNNING" -ge "$PARALLEL" ]; then
            wait -n 2>/dev/null || wait
            RUNNING=$((RUNNING - 1))
        fi
    fi
done

if [ "$PARALLEL" -gt 1 ]; then
    wait
fi

echo ""
echo "=== Resultado ==="
echo "Total:    $HOST_COUNT"
if [ "$PARALLEL" -le 1 ]; then
    echo "Sucesso:  $OK_COUNT"
    echo "Falha:    $FAIL_COUNT"
fi
echo "Logs:     $LOG_DIR/"
echo ""
echo "Verificar todos:"
echo "  for ip in \$(cat $INVENTORY | grep -v '#'); do"
echo "    ssh root@\$ip '/usr/local/sbin/layer7d -V'"
echo "  done"
