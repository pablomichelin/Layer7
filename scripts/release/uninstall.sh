#!/bin/sh
# uninstall.sh — Remoção completa do Layer7 para pfSense CE (v1.4.13+)
#
# Uso (executar no pfSense como root):
#
#   fetch -o /tmp/uninstall.sh https://github.com/pablomichelin/Layer7/releases/download/vX.Y.Z/uninstall.sh && sh /tmp/uninstall.sh
#
# Opcoes:
#   --keep-config   Preserva layer7.json, layer7.lic, CA MITM, secrets Identity
#                   e estado de check-in (/var/db)
#   --keep-license  Preserva layer7.lic e estado de check-in (/var/db)
#   --clean-unbound Remove overrides anti-DoH do Unbound custom_options (config.xml)
#   --yes           Nao pedir confirmacao
#
# O script faz:
#   1. Para o servico layer7d
#   2. Remove o pacote .pkg
#   3. Limpa ficheiros residuais
#   4. Limpa tabelas PF
#   5. (Opcional) Limpa custom_options do Unbound

set -eu

REPO_OWNER="pablomichelin"
REPO_NAME="Layer7"
RELEASE_VERSION_HINT=""

KEEP_CONFIG=0
KEEP_LICENSE=0
CLEAN_UNBOUND=0
AUTO_YES=0

while [ $# -gt 0 ]; do
    case "$1" in
        --keep-config)  KEEP_CONFIG=1; shift ;;
        --keep-license) KEEP_LICENSE=1; shift ;;
        --clean-unbound) CLEAN_UNBOUND=1; shift ;;
        --yes|-y)       AUTO_YES=1; shift ;;
        --help|-h)
            echo "Uso: sh uninstall.sh [--keep-config] [--keep-license] [--clean-unbound] [--yes]"
            echo ""
            echo "  --keep-config    Preserva json, .lic, CA MITM, secrets e check-in"
            echo "  --keep-license   Preserva .lic e estado de check-in"
            echo "  --clean-unbound  Remove overrides anti-DoH do Unbound (config.xml)"
            echo "  --yes            Nao pedir confirmacao"
            exit 0
            ;;
        *) echo "Opcao desconhecida: $1"; exit 1 ;;
    esac
done

# A1/A2: mesma função que pkg-deinstall.in (script standalone no release).
layer7_deinstall_init_paths() {
	_l7_etc="${LAYER7_ETC_DIR:-/usr/local/etc}"
	_l7_var_db="${LAYER7_VAR_DB_DIR:-/var/db}"
	_l7_stage="${LAYER7_PRESERVE_DIR:-${_l7_var_db}/layer7/deinstall-preserve}"
}

layer7_deinstall_secure_rm() {
	_p="$1"
	[ -n "${_p}" ] || return 0
	if [ -e "${_p}" ]; then
		chmod -R u+w "${_p}" 2>/dev/null || true
		/bin/rm -rf "${_p}"
	fi
}

layer7_deinstall_mandatory_present() {
	_etc_l7="${_l7_etc}/layer7"
	[ -d "${_etc_l7}/mitm" ] && return 0
	[ -f "${_etc_l7}/identity-ldap.secret" ] && return 0
	[ -f "${_etc_l7}/identity-radius.secret" ] && return 0
	[ -f "${_etc_l7}/identity-dc.secret" ] && return 0
	return 1
}

layer7_deinstall_stage_secrets() {
	_etc_l7="${_l7_etc}/layer7"
	_old_umask=$(umask)
	umask 077
	if ! /bin/mkdir -p "${_l7_stage}" 2>/dev/null; then
		umask "${_old_umask}"
		if layer7_deinstall_mandatory_present; then
			return 1
		fi
		return 0
	fi
	if ! chmod 0700 "${_l7_stage}"; then
		umask "${_old_umask}"
		return 1
	fi
	if [ -d "${_etc_l7}/mitm" ]; then
		/bin/rm -rf "${_l7_stage}/mitm"
		if ! /bin/cp -a "${_etc_l7}/mitm" "${_l7_stage}/mitm"; then
			umask "${_old_umask}"
			return 1
		fi
		[ -d "${_l7_stage}/mitm" ] || {
			umask "${_old_umask}"
			return 1
		}
		if [ -f "${_l7_stage}/mitm/ca.key" ]; then
			if ! chmod 0600 "${_l7_stage}/mitm/ca.key"; then
				umask "${_old_umask}"
				return 1
			fi
		fi
	fi
	for _s in identity-ldap.secret identity-radius.secret identity-dc.secret; do
		if [ -f "${_etc_l7}/${_s}" ]; then
			if ! /bin/cp -p "${_etc_l7}/${_s}" "${_l7_stage}/${_s}"; then
				umask "${_old_umask}"
				return 1
			fi
			if ! chmod 0600 "${_l7_stage}/${_s}"; then
				umask "${_old_umask}"
				return 1
			fi
			[ -f "${_l7_stage}/${_s}" ] || {
				umask "${_old_umask}"
				return 1
			}
		fi
	done
	if [ -f "${_etc_l7}/profiles-custom.json" ]; then
		/bin/cp -f "${_etc_l7}/profiles-custom.json" \
			"${_l7_stage}/profiles-custom.json" 2>/dev/null || true
	fi
	umask "${_old_umask}"
	return 0
}

layer7_deinstall_restore_secrets() {
	_etc_l7="${_l7_etc}/layer7"
	/bin/mkdir -p "${_etc_l7}" || return 1
	if [ -d "${_l7_stage}/mitm" ]; then
		/bin/rm -rf "${_etc_l7}/mitm"
		/bin/mv -f "${_l7_stage}/mitm" "${_etc_l7}/mitm" || return 1
		if [ -f "${_etc_l7}/mitm/ca.key" ]; then
			chmod 0600 "${_etc_l7}/mitm/ca.key" || return 1
		fi
	fi
	for _s in identity-ldap.secret identity-radius.secret identity-dc.secret; do
		if [ -f "${_l7_stage}/${_s}" ]; then
			/bin/mv -f "${_l7_stage}/${_s}" "${_etc_l7}/${_s}" || return 1
			chmod 0600 "${_etc_l7}/${_s}" || return 1
		fi
	done
	if [ -f "${_l7_stage}/profiles-custom.json" ]; then
		/bin/mv -f "${_l7_stage}/profiles-custom.json" \
			"${_etc_l7}/profiles-custom.json" 2>/dev/null || true
		chown www:wheel "${_etc_l7}/profiles-custom.json" \
			2>/dev/null || true
		chmod 0664 "${_etc_l7}/profiles-custom.json" 2>/dev/null || true
	fi
	return 0
}

layer7_deinstall_etc_lifecycle() {
	layer7_deinstall_init_paths
	_preserve_runtime=0
	if [ "${_keep_config}" -eq 1 ] || [ "${_is_upgrade}" -eq 1 ]; then
		_preserve_runtime=1
	fi
	if [ "${_is_upgrade}" -eq 0 ]; then
		if [ "${_keep_config}" -eq 1 ]; then
			:
		elif [ "${_keep_license}" -eq 1 ]; then
			/bin/rm -f "${_l7_etc}/layer7.json" >/dev/null 2>&1 || true
		else
			/bin/rm -f "${_l7_etc}/layer7.json" \
				"${_l7_etc}/layer7.lic" >/dev/null 2>&1 || true
		fi
	fi
	if [ "${_preserve_runtime}" -eq 1 ]; then
		if ! layer7_deinstall_stage_secrets; then
			layer7_deinstall_secure_rm "${_l7_stage}"
			return 0
		fi
		/bin/rm -rf "${_l7_etc}/layer7"
		if ! layer7_deinstall_restore_secrets; then
			return 0
		fi
		layer7_deinstall_secure_rm "${_l7_stage}"
	else
		/bin/rm -rf "${_l7_etc}/layer7"
		layer7_deinstall_secure_rm "${_l7_stage}"
	fi
	if [ "${_is_upgrade}" -eq 0 ] && [ "${_keep_config}" -eq 0 ] && \
	    [ "${_keep_license}" -eq 0 ]; then
		/bin/rm -f "${_l7_var_db}/layer7-checkin.json" \
			"${_l7_var_db}/layer7/clock-mark.json" \
			"${_l7_var_db}/layer7/content-subscription.json" \
			>/dev/null 2>&1 || true
	fi
	return 0
}

if [ "${LAYER7_DEINSTALL_LIB:-}" = "1" ]; then
	return 0 2>/dev/null || exit 0
fi

echo "============================================"
echo "  Layer7 para pfSense CE — Desinstalacao"
echo "  Systemup Solucao em Tecnologia"
echo "============================================"
echo ""

if [ "$(id -u)" -ne 0 ]; then
    echo "ERRO: Execute como root."
    exit 1
fi

if [ "$AUTO_YES" -eq 0 ]; then
    if [ -t 0 ]; then
        printf "Deseja remover completamente o Layer7? [s/N] "
        read -r resp
        case "$resp" in
            [sS]|[sS][iI][mM]|[yY]|[yY][eE][sS]) ;;
            *) echo "Cancelado."; exit 0 ;;
        esac
    else
        echo "Modo nao-interactivo detectado. Prosseguindo automaticamente."
        echo "(Use --yes para suprimir esta mensagem.)"
    fi
fi

echo ""
echo "[1/5] Parando servico layer7d..."
if service layer7d onestatus >/dev/null 2>&1; then
    service layer7d onestop 2>/dev/null || true
    sleep 1
    echo "      Servico parado."
else
    echo "      Servico nao estava em execucao."
fi

echo "[2/5] Removendo pacote..."
if [ -x /usr/local/libexec/layer7-pfctl ]; then
    echo "      A limpar tabelas PF layer7 (flush-all)..."
    /usr/local/libexec/layer7-pfctl flush-all 2>/dev/null || true
fi
if pkg info pfSense-pkg-layer7 >/dev/null 2>&1; then
    pkg delete -y pfSense-pkg-layer7 2>/dev/null || true
    echo "      Pacote removido."
else
    echo "      Pacote nao estava instalado."
fi

echo "[3/5] Limpando ficheiros residuais..."

rm -f /usr/local/sbin/layer7d
rm -f /usr/local/libexec/layer7-pfctl
rm -f /usr/local/libexec/layer7-unbound-anti-doh
rm -rf /usr/local/www/packages/layer7
rm -f /usr/local/pkg/layer7.xml
rm -f /usr/local/pkg/layer7.inc
rm -f /etc/inc/priv/layer7.priv.inc
rm -f /usr/local/etc/rc.d/layer7d
rm -f /usr/local/share/pfSense-pkg-layer7/info.xml
rmdir /usr/local/share/pfSense-pkg-layer7 2>/dev/null || true
rm -f /var/run/layer7d.pid
rm -f /var/log/layer7d.log
rm -f /var/log/layer7-events.log
_l7_log_idx=1
while [ "$_l7_log_idx" -le 10 ]; do
    rm -f "/var/log/layer7d.log.${_l7_log_idx}"
    rm -f "/var/log/layer7-events.log.${_l7_log_idx}"
    _l7_log_idx=$((_l7_log_idx + 1))
done
rm -f /var/log/layer7-bl-update.log
rm -f /tmp/layer7-stats.json /var/db/layer7/layer7-stats.json \
	/var/db/layer7/layer7-stats.json.tmp
rm -f /usr/local/etc/layer7.json.sample
rm -f /usr/local/etc/layer7-protos.txt.sample
rm -f /usr/local/etc/layer7-protos.txt

_keep_config="$KEEP_CONFIG"
_keep_license="$KEEP_LICENSE"
_is_upgrade=0
if [ "$KEEP_CONFIG" -eq 1 ]; then
    echo "      --keep-config: preservando layer7.json, layer7.lic, CA MITM e secrets"
elif [ "$KEEP_LICENSE" -eq 1 ]; then
    echo "      --keep-license: preservando layer7.lic e estado de check-in"
else
    echo "      Configuracao, licenca e estado local removidos."
fi
# A1: wipe de /usr/local/etc/layer7 só após staging obrigatório OK.
layer7_deinstall_etc_lifecycle || true
echo "      Ficheiros residuais limpos."

echo "[4/5] Limpando tabelas PF..."
for tbl in layer7_block layer7_block_dst layer7_tagged layer7_bl_except \
           layer7_bld_0 layer7_bld_1 layer7_bld_2 layer7_bld_3 \
           layer7_bld_4 layer7_bld_5 layer7_bld_6 layer7_bld_7; do
    pfctl -t "$tbl" -T flush 2>/dev/null || true
done
echo "      Tabelas PF limpas."

if [ "$CLEAN_UNBOUND" -eq 1 ]; then
    echo "[5/5] Limpando overrides anti-DoH do Unbound..."
    MARKER_START="# --- Layer7 anti-DoH/Relay START ---"
    MARKER_END="# --- Layer7 anti-DoH/Relay END ---"

    if [ -f /conf/config.xml ]; then
        if grep -q "custom_options" /conf/config.xml 2>/dev/null; then
            cp /conf/config.xml /conf/config.xml.bak.layer7
            php -r '
                require_once("config.inc");
                require_once("util.inc");
                global $config;
                $ms = "# --- Layer7 anti-DoH/Relay START ---";
                $me = "# --- Layer7 anti-DoH/Relay END ---";
                if (isset($config["unbound"]["custom_options"])) {
                    $raw = $config["unbound"]["custom_options"];
                    $co = @base64_decode($raw, true);
                    if ($co === false) { $co = $raw; }
                    $ps = strpos($co, $ms);
                    if ($ps !== false) {
                        $pe = strpos($co, $me, $ps);
                        if ($pe !== false) {
                            $pe += strlen($me);
                            while ($pe < strlen($co) && ($co[$pe] === "\n" || $co[$pe] === "\r")) $pe++;
                            $co = substr($co, 0, $ps) . substr($co, $pe);
                        }
                        $config["unbound"]["custom_options"] = base64_encode(trim($co));
                        write_config("Layer7 uninstall: anti-DoH overrides removed");
                        echo "OK\n";
                    } else {
                        echo "MARKER_NOT_FOUND\n";
                    }
                } else {
                    echo "NO_CUSTOM_OPTIONS\n";
                }
            ' 2>/dev/null
            RESULT=$?
            if [ "$RESULT" -eq 0 ]; then
                echo "      Overrides anti-DoH removidos do config.xml."
                echo "      Backup em /conf/config.xml.bak.layer7"
            else
                echo "      AVISO: Nao foi possivel limpar automaticamente."
                echo "      Limpe manualmente em Services > DNS Resolver > Custom Options."
            fi
        else
            echo "      Overrides anti-DoH nao encontrados no config.xml."
        fi
    else
        echo "      config.xml nao encontrado (nao e pfSense?)."
    fi
else
    echo "[5/5] Unbound custom_options nao alterado (use --clean-unbound para limpar)."
fi

sysrc -x layer7d_enable 2>/dev/null || true

echo ""
echo "============================================"
echo "  Layer7 removido com sucesso!"
echo "============================================"
echo ""
echo "O pfSense esta a funcionar normalmente."
echo ""
if [ "$KEEP_CONFIG" -eq 1 ] || [ "$KEEP_LICENSE" -eq 1 ]; then
    echo "Ficheiros preservados:"
    [ "$KEEP_CONFIG" -eq 1 ] && ls -la /usr/local/etc/layer7.json 2>/dev/null && ls -la /usr/local/etc/layer7.lic 2>/dev/null
    [ "$KEEP_LICENSE" -eq 1 ] && [ "$KEEP_CONFIG" -eq 0 ] && ls -la /usr/local/etc/layer7.lic 2>/dev/null
    echo ""
fi
echo "Para reinstalar:"
if [ -n "$RELEASE_VERSION_HINT" ]; then
    echo "  fetch -o /tmp/install.sh https://github.com/${REPO_OWNER}/${REPO_NAME}/releases/download/v${RELEASE_VERSION_HINT}/install.sh && sh /tmp/install.sh"
else
    echo "  fetch -o /tmp/install.sh https://github.com/${REPO_OWNER}/${REPO_NAME}/releases/download/vX.Y.Z/install.sh && sh /tmp/install.sh"
fi
echo ""
