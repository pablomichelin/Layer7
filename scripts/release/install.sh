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
#   2. Valida manifesto, assinatura e checksum da release
#   3. Instala o pacote
#   4. Verifica tabelas PF
#   5. Configura Unbound anti-DoH
#   6. Inicia o serviço
#   7. Mostra próximos passos

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
RELEASE_MANIFEST_NAME="release-manifest.v1.txt"
RELEASE_SIGNATURE_NAME="${RELEASE_MANIFEST_NAME}.sig"
RELEASE_PUBLIC_KEY_ASSET="release-signing-public-key.pem"
RELEASE_SIGNING_SCHEME="ed25519-openssl-pkeyutl-v1"
# O signer carimba o install.sh publicado com a public key oficial e o
# fingerprint esperado. O source tree permanece vazio de propósito.
EMBEDDED_RELEASE_PUBKEY_B64=""
EXPECTED_RELEASE_PUBKEY_FINGERPRINT_SHA256=""
TMP_DIR="/tmp/layer7-install.$$"

log() {
    echo "$*"
    logger -t layer7-install -- "$*" 2>/dev/null || true
}

fail_closed() {
    log "FAIL-CLOSED: $*"
    exit 1
}

degraded() {
    log "DEGRADED: $*"
}

cleanup() {
    rm -rf "$TMP_DIR" 2>/dev/null || true
}
trap cleanup EXIT

sha256_hex() {
    _file="$1"
    if command -v sha256 >/dev/null 2>&1; then
        sha256 -q "$_file"
    elif command -v sha256sum >/dev/null 2>&1; then
        sha256sum "$_file" | awk '{print $1}'
    else
        openssl dgst -sha256 "$_file" | awk '{print $2}'
    fi
}

file_size_bytes() {
    _file="$1"
    if stat -f '%z' "$_file" >/dev/null 2>&1; then
        stat -f '%z' "$_file"
    elif stat -c '%s' "$_file" >/dev/null 2>&1; then
        stat -c '%s' "$_file"
    else
        wc -c < "$_file" | tr -d ' '
    fi
}

manifest_value() {
    _key="$1"
    _manifest="$2"
    sed -n "s/^${_key}=//p" "$_manifest" | head -1
}

manifest_asset_field_by_role() {
    _role="$1"
    _field_name="$2"
    _manifest="$3"

    grep '^asset|' "$_manifest" | while IFS= read -r _line; do
        _name=""
        _role_value=""
        _size=""
        _sha=""

        OLD_IFS="$IFS"
        IFS='|'
        set -- ${_line#asset|}
        IFS="$OLD_IFS"

        for _field in "$@"; do
            _key="${_field%%=*}"
            _value="${_field#*=}"
            case "$_key" in
                name) _name="$_value" ;;
                role) _role_value="$_value" ;;
                size) _size="$_value" ;;
                sha256) _sha="$_value" ;;
            esac
        done

        [ "$_role_value" = "$_role" ] || continue
        case "$_field_name" in
            name) printf '%s\n' "$_name" ;;
            size) printf '%s\n' "$_size" ;;
            sha256) printf '%s\n' "$_sha" ;;
        esac
        break
    done
}

fingerprint_public_key_sha256() {
    _pubkey="$1"
    openssl pkey -pubin -in "$_pubkey" -outform DER 2>/dev/null \
        | openssl dgst -sha256 \
        | awk '{print $2}'
}

fetch_release_asset() {
    _out="$1"
    _url="$2"
    if ! fetch -o "$_out" "$_url" 2>/dev/null; then
        fail_closed "nao foi possivel baixar $_url"
    fi
}

write_embedded_pubkey() {
    _out="$1"

    [ -n "$EMBEDDED_RELEASE_PUBKEY_B64" ] \
        || fail_closed "install.sh oficial nao foi carimbado com a public key de release"
    [ -n "$EXPECTED_RELEASE_PUBKEY_FINGERPRINT_SHA256" ] \
        || fail_closed "install.sh oficial nao foi carimbado com o fingerprint da release"

    if printf '%s' "$EMBEDDED_RELEASE_PUBKEY_B64" \
        | openssl base64 -A -d -out "$_out" 2>/dev/null; then
        :
    else
        fail_closed "nao foi possivel reconstruir a public key embutida no install.sh"
    fi
}

verify_release_trust_chain() {
    _manifest_path="$TMP_DIR/$RELEASE_MANIFEST_NAME"
    _sig_path="$TMP_DIR/$RELEASE_SIGNATURE_NAME"
    _pubkey_path="$TMP_DIR/$RELEASE_PUBLIC_KEY_ASSET"
    _pkg_sha_file="$TMP_DIR/${PKG_NAME}.sha256"

    mkdir -p "$TMP_DIR"
    write_embedded_pubkey "$_pubkey_path"

    _actual_fingerprint="$(fingerprint_public_key_sha256 "$_pubkey_path")"
    [ "$_actual_fingerprint" = "$EXPECTED_RELEASE_PUBKEY_FINGERPRINT_SHA256" ] \
        || fail_closed "fingerprint da public key embutida diverge do valor esperado"

    fetch_release_asset "$_manifest_path" "$MANIFEST_URL"
    fetch_release_asset "$_sig_path" "$SIGNATURE_URL"

    _manifest_version="$(manifest_value manifest_version "$_manifest_path")"
    _release_version="$(manifest_value release_version "$_manifest_path")"
    _release_tag="$(manifest_value release_tag "$_manifest_path")"
    _checksum_algo="$(manifest_value checksum_algorithm "$_manifest_path")"
    _signing_scheme="$(manifest_value signing_scheme "$_manifest_path")"
    _signature_asset="$(manifest_value signature_asset "$_manifest_path")"
    _public_key_asset="$(manifest_value public_key_asset "$_manifest_path")"
    _manifest_fingerprint="$(manifest_value public_key_fingerprint_sha256 "$_manifest_path")"

    [ "$_manifest_version" = "1" ] || fail_closed "manifest_version inesperado na release ${TAG}"
    [ "$_release_version" = "$VERSION" ] || fail_closed "release_version do manifesto nao bate com ${VERSION}"
    [ "$_release_tag" = "$TAG" ] || fail_closed "release_tag do manifesto nao bate com ${TAG}"
    [ "$_checksum_algo" = "sha256" ] || fail_closed "checksum_algorithm inesperado no manifesto"
    [ "$_signing_scheme" = "$RELEASE_SIGNING_SCHEME" ] || fail_closed "signing_scheme inesperado no manifesto"
    [ "$_signature_asset" = "$RELEASE_SIGNATURE_NAME" ] || fail_closed "signature_asset inesperado no manifesto"
    [ "$_public_key_asset" = "$RELEASE_PUBLIC_KEY_ASSET" ] || fail_closed "public_key_asset inesperado no manifesto"
    [ "$_manifest_fingerprint" = "$EXPECTED_RELEASE_PUBKEY_FINGERPRINT_SHA256" ] \
        || fail_closed "fingerprint da release nao bate com o trust anchor embutido"

    if ! openssl pkeyutl -verify -rawin \
        -pubin \
        -inkey "$_pubkey_path" \
        -sigfile "$_sig_path" \
        -in "$_manifest_path" >/dev/null 2>&1; then
        fail_closed "assinatura do manifesto da release e invalida"
    fi

    _pkg_asset_name="$(manifest_asset_field_by_role package name "$_manifest_path")"
    _pkg_asset_size="$(manifest_asset_field_by_role package size "$_manifest_path")"
    _pkg_asset_sha="$(manifest_asset_field_by_role package sha256 "$_manifest_path")"
    _pkg_sha_asset_name="$(manifest_asset_field_by_role package-checksum name "$_manifest_path")"

    [ "$_pkg_asset_name" = "$PKG_NAME" ] || fail_closed "manifesto aponta para pacote inesperado: $_pkg_asset_name"
    [ -n "$_pkg_asset_size" ] || fail_closed "manifesto sem size do pacote"
    [ -n "$_pkg_asset_sha" ] || fail_closed "manifesto sem sha256 do pacote"
    [ "$_pkg_sha_asset_name" = "${PKG_NAME}.sha256" ] || fail_closed "manifesto aponta para checksum inesperado: $_pkg_sha_asset_name"

    fetch_release_asset "$_pkg_sha_file" "$PKG_SHA_URL"
    _expected_sha_line="$(printf '%s  %s' "$_pkg_asset_sha" "$PKG_NAME")"
    _actual_sha_line="$(tr -d '\r' < "$_pkg_sha_file" | head -1)"
    [ "$_actual_sha_line" = "$_expected_sha_line" ] \
        || fail_closed "conteudo de ${PKG_NAME}.sha256 nao bate com o manifesto"

    log "[2/7] Validando trust chain da release..."
    fetch_release_asset "/tmp/${PKG_NAME}" "$PKG_URL"

    _downloaded_size="$(file_size_bytes "/tmp/${PKG_NAME}")"
    [ "$_downloaded_size" = "$_pkg_asset_size" ] \
        || fail_closed "tamanho do pacote nao bate com o manifesto"

    _downloaded_sha="$(sha256_hex "/tmp/${PKG_NAME}")"
    [ "$_downloaded_sha" = "$_pkg_asset_sha" ] \
        || fail_closed "sha256 do pacote nao bate com o manifesto"

    log "  Trust chain validada: manifesto, assinatura e checksum conferem."
}

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
PKG_SHA_URL="${PKG_URL}.sha256"
MANIFEST_URL="https://github.com/${REPO_OWNER}/${REPO_NAME}/releases/download/${TAG}/${RELEASE_MANIFEST_NAME}"
SIGNATURE_URL="${MANIFEST_URL}.sig"

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

verify_release_trust_chain
echo "  Baixado: $(ls -lh /tmp/${PKG_NAME} | awk '{print $5}')"

# --- Instalar ---
echo "[3/7] Instalando pacote..."
if ! IGNORE_OSVERSION=yes pkg add -f "/tmp/${PKG_NAME}" 2>&1; then
    echo ""
    echo "ERRO: Instalação falhou."
    exit 1
fi
rm -f "/tmp/${PKG_NAME}"

# --- Garantir tabelas PF ---
echo "[4/7] Verificando tabelas PF..."
HELPER="/usr/local/libexec/layer7-pfctl"
if [ -x "$HELPER" ]; then
    if ! sh "$HELPER" ensure 2>/dev/null; then
        degraded "helper PF retornou erro; validar tabelas PF no appliance"
    fi
else
    degraded "helper PF nao encontrado; validar tabelas PF no appliance"
fi
if [ -f /tmp/rules.debug ]; then
    if ! /sbin/pfctl -f /tmp/rules.debug 2>/dev/null; then
        degraded "reload de rules.debug falhou; validar filtro activo manualmente"
    fi
fi
for _table in layer7_block layer7_block_dst layer7_tagged layer7_bld_0; do
    if pfctl -s Tables 2>/dev/null | grep -qw "$_table"; then
        echo "  Tabela '$_table' OK."
    else
        degraded "tabela '$_table' pendente; sera criada no proximo filter reload"
    fi
done

# --- Configurar Unbound anti-DoH ---
echo "[5/7] Configurando Unbound anti-DoH/Relay..."
ANTI_DOH="/usr/local/libexec/layer7-unbound-anti-doh"
if [ -x "$ANTI_DOH" ]; then
    if ! sh "$ANTI_DOH" 2>/dev/null; then
        degraded "script anti-DoH retornou erro; validar Services > DNS Resolver"
    fi
else
    degraded "script anti-DoH nao encontrado; validar empacotamento da release"
fi

# --- Iniciar serviço ---
echo "[6/7] Iniciando serviço..."
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
    degraded "servico nao subiu apos a instalacao; verificar /var/log/system.log"
fi
echo "  Config:    /usr/local/etc/layer7.json"
echo "  Modo:      MONITOR (seguro — não bloqueia nada)"
echo ""
echo "[7/7] Install concluido."
echo ""
echo "  Próximos passos:"
echo "    1. Abra a GUI: Services > Layer 7"
echo "    2. Em 'Definições': selecione as interfaces"
echo "    3. Em 'Políticas': adicione regras de bloqueio"
echo "    4. Em 'Definições': mude para modo 'enforce' quando pronto"
echo ""
echo "  Rollback:  pkg delete pfSense-pkg-layer7"
echo ""
