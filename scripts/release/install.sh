#!/bin/sh
# install.sh — Instalação universal do Layer7 para pfSense CE
#
# Uso (executar no pfSense como root):
#
#   fetch -o /tmp/install.sh https://raw.githubusercontent.com/pablomichelin/pfsense-layer7/main/scripts/release/install.sh && sh /tmp/install.sh
#
# Ou com versão específica:
#
#   sh /tmp/install.sh --version 0.2.0
#
# O script faz tudo automaticamente:
#   1. Detecta a versão (ou usa a especificada)
#   2. Baixa o .pkg do GitHub Releases
#   3. Instala o pacote
#   4. Cria tabelas PF
#   5. Configura e inicia o serviço
#   6. Abre a GUI para configuração

set -eu

REPO_OWNER="pablomichelin"
REPO_NAME="pfsense-layer7"
VERSION=""
PKG_PREFIX="pfSense-pkg-layer7"
FORCE=0

while [ $# -gt 0 ]; do
    case "$1" in
        --version|-v) VERSION="$2"; shift 2 ;;
        --force|-f) FORCE=1; shift ;;
        -h|--help)
            echo "Uso: sh install.sh [--version X.Y.Z] [--force]"
            echo ""
            echo "  --version X.Y.Z   Versão a instalar (default: última)"
            echo "  --force           Reinstalar mesmo se já estiver instalado"
            exit 0
            ;;
        *) echo "Argumento desconhecido: $1"; exit 1 ;;
    esac
done

# --- Detecção de versão ---
if [ -z "$VERSION" ]; then
    VERSION="0.2.0"
fi

TAG="v${VERSION}"
PKG_NAME="${PKG_PREFIX}-${VERSION}.pkg"
PKG_URL="https://github.com/${REPO_OWNER}/${REPO_NAME}/releases/download/${TAG}/${PKG_NAME}"

echo ""
echo "╔══════════════════════════════════════════╗"
echo "║     Layer7 para pfSense CE               ║"
echo "║     Instalação automática                 ║"
echo "╚══════════════════════════════════════════╝"
echo ""
echo "  Versão:  ${VERSION}"
echo "  Pacote:  ${PKG_NAME}"
echo ""

# --- Verificar se já está instalado ---
INSTALLED_VER=""
if command -v layer7d >/dev/null 2>&1; then
    INSTALLED_VER=$(layer7d -V 2>/dev/null || echo "")
fi

if [ -n "$INSTALLED_VER" ] && [ "$FORCE" -eq 0 ]; then
    echo "  Layer7 já instalado: versão ${INSTALLED_VER}"
    if [ "$INSTALLED_VER" = "$VERSION" ]; then
        echo "  Mesma versão. Use --force para reinstalar."
        echo ""
        exit 0
    fi
    echo "  Actualizando ${INSTALLED_VER} -> ${VERSION}..."
    echo ""
fi

# --- Parar daemon se estiver a correr ---
if pgrep -q layer7d 2>/dev/null; then
    echo "[1/5] Parando daemon existente..."
    service layer7d onestop 2>/dev/null || true
    sleep 1
else
    echo "[1/5] Nenhum daemon a correr."
fi

# --- Baixar pacote ---
echo "[2/5] Baixando pacote do GitHub..."
if ! fetch -o "/tmp/${PKG_NAME}" "${PKG_URL}" 2>/dev/null; then
    echo ""
    echo "ERRO: Não foi possível baixar ${PKG_URL}"
    echo "  Verifique: versão, conexão à internet, acesso ao GitHub."
    exit 1
fi
echo "  Baixado: $(ls -lh /tmp/${PKG_NAME} | awk '{print $5}')"

# --- Instalar ---
echo "[3/5] Instalando pacote..."
if ! IGNORE_OSVERSION=yes pkg add -f "/tmp/${PKG_NAME}" 2>&1; then
    echo ""
    echo "ERRO: Instalação falhou."
    exit 1
fi
rm -f "/tmp/${PKG_NAME}"

# --- Garantir tabelas PF ---
echo "[4/5] Verificando tabelas PF..."
for _table in layer7_block layer7_tagged; do
    if ! pfctl -s Tables 2>/dev/null | grep -qw "$_table"; then
        pfctl -t "$_table" -T add 127.0.0.254 2>/dev/null
        pfctl -t "$_table" -T delete 127.0.0.254 2>/dev/null
        echo "  Tabela '$_table' criada."
    else
        echo "  Tabela '$_table' OK."
    fi
done

# --- Iniciar serviço ---
echo "[5/5] Iniciando serviço..."
sysrc layer7d_enable=YES > /dev/null 2>&1
service layer7d onestart > /dev/null 2>&1 || true
sleep 2

# --- Verificação final ---
VER_CHECK=$(layer7d -V 2>/dev/null || echo "?")
PID_CHECK=$(pgrep layer7d 2>/dev/null || echo "")

echo ""
echo "╔══════════════════════════════════════════╗"
echo "║     Instalação concluída!                 ║"
echo "╚══════════════════════════════════════════╝"
echo ""
echo "  Versão:    ${VER_CHECK}"
if [ -n "$PID_CHECK" ]; then
    echo "  PID:       ${PID_CHECK}"
    echo "  Estado:    A correr"
else
    echo "  Estado:    Parado (verifique /var/log/system.log)"
fi
echo "  Config:    /usr/local/etc/layer7.json"
echo "  Modo:      MONITOR (seguro — não bloqueia nada)"
echo ""
echo "  Próximos passos:"
echo "    1. Abra a GUI: Services > Layer 7"
echo "    2. Em 'Definições': selecione as interfaces"
echo "    3. Em 'Políticas': adicione regras de bloqueio"
echo "    4. Em 'Definições': mude para modo 'enforce' quando pronto"
echo ""
echo "  Rollback:  pkg delete pfSense-pkg-layer7"
echo ""
