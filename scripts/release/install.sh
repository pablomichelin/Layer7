#!/bin/sh
# install.sh — Instalação universal do Layer7 para pfSense CE
#
# Uso (executar no pfSense como root):
#
#   fetch -o /tmp/install.sh https://github.com/pablomichelin/Layer7/releases/download/vX.Y.Z/install.sh && sh /tmp/install.sh
#
# Ou com versão específica:
#
#   sh /tmp/install.sh --version X.Y.Z
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
REPO_NAME="Layer7"
VERSION=""
# O pipeline de release fixa este valor quando publica install.sh como asset
# versionado. No source tree ele permanece vazio e o script detecta a ultima
# release publicada.
DEFAULT_VERSION=""
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
if [ -z "$VERSION" ] && [ -n "$DEFAULT_VERSION" ]; then
    VERSION="$DEFAULT_VERSION"
fi

if [ -z "$VERSION" ]; then
    GH_API="https://api.github.com/repos/${REPO_OWNER}/${REPO_NAME}/releases/latest"
    GH_TMP="/tmp/layer7-gh-latest.json"
    rm -f "$GH_TMP"
    if fetch -qo "$GH_TMP" "$GH_API" 2>/dev/null && [ -f "$GH_TMP" ]; then
        VERSION=$(sed -n 's/.*"tag_name"[[:space:]]*:[[:space:]]*"v\([^"]*\)".*/\1/p' "$GH_TMP" | head -1)
        rm -f "$GH_TMP"
    fi
    if [ -z "$VERSION" ]; then
        echo "ERRO: Nao foi possivel detectar a versao mais recente do GitHub."
        echo "  Use: sh install.sh --version X.Y.Z"
        exit 1
    fi
fi

TAG="v${VERSION}"
PKG_NAME="${PKG_PREFIX}-${VERSION}.pkg"
PKG_URL="https://github.com/${REPO_OWNER}/${REPO_NAME}/releases/download/${TAG}/${PKG_NAME}"

echo ""
echo "╔══════════════════════════════════════════╗"
echo "║     Layer7 para pfSense CE               ║"
echo "║     por Systemup                          ║"
echo "║     www.systemup.inf.br                   ║"
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
    echo "[1/6] Parando daemon existente..."
    service layer7d onestop 2>/dev/null || true
    sleep 1
else
    echo "[1/6] Nenhum daemon a correr."
fi

# --- Baixar pacote ---
echo "[2/6] Baixando pacote do GitHub..."
if ! fetch -o "/tmp/${PKG_NAME}" "${PKG_URL}" 2>/dev/null; then
    echo ""
    echo "ERRO: Não foi possível baixar ${PKG_URL}"
    echo "  Verifique: versão, conexão à internet, acesso ao GitHub."
    exit 1
fi
echo "  Baixado: $(ls -lh /tmp/${PKG_NAME} | awk '{print $5}')"

# --- Instalar ---
echo "[3/6] Instalando pacote..."
if ! IGNORE_OSVERSION=yes pkg add -f "/tmp/${PKG_NAME}" 2>&1; then
    echo ""
    echo "ERRO: Instalação falhou."
    exit 1
fi
rm -f "/tmp/${PKG_NAME}"

# --- Garantir tabelas PF ---
echo "[4/6] Verificando tabelas PF..."
HELPER="/usr/local/libexec/layer7-pfctl"
if [ -x "$HELPER" ]; then
    sh "$HELPER" ensure 2>/dev/null || true
fi
if [ -f /tmp/rules.debug ]; then
    /sbin/pfctl -f /tmp/rules.debug 2>/dev/null || true
fi
for _table in layer7_block layer7_block_dst layer7_tagged layer7_bld_0; do
    if pfctl -s Tables 2>/dev/null | grep -qw "$_table"; then
        echo "  Tabela '$_table' OK."
    else
        echo "  Tabela '$_table' pendente (sera criada no proximo filter reload)."
    fi
done

# --- Configurar Unbound anti-DoH ---
echo "[5/6] Configurando Unbound anti-DoH/Relay..."
ANTI_DOH="/usr/local/libexec/layer7-unbound-anti-doh"
if [ -x "$ANTI_DOH" ]; then
    sh "$ANTI_DOH" 2>/dev/null || echo "  AVISO: script anti-DoH retornou erro (pode ja estar configurado)."
else
    echo "  Script anti-DoH nao encontrado (ignorado)."
fi

# --- Iniciar serviço ---
echo "[6/6] Iniciando serviço..."
sysrc layer7d_enable=YES > /dev/null 2>&1
service layer7d onestart > /dev/null 2>&1 || true
sleep 2

# --- Verificação final ---
VER_CHECK=$(layer7d -V 2>/dev/null || echo "?")
PID_CHECK=$(pgrep layer7d 2>/dev/null || echo "")

echo ""
echo "╔══════════════════════════════════════════╗"
echo "║     Instalação concluída!                 ║"
echo "║     Systemup — www.systemup.inf.br        ║"
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
