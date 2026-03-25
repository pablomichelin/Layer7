#!/bin/sh
# uninstall.sh — Remoção completa do Layer7 para pfSense CE (v1.4.12+)
#
# Uso (executar no pfSense como root):
#
#   fetch -o /tmp/uninstall.sh https://raw.githubusercontent.com/pablomichelin/Layer7/main/uninstall.sh && sh /tmp/uninstall.sh
#
# Opcoes:
#   --keep-config   Preserva layer7.json e layer7.lic para reinstalacao futura
#   --keep-license  Preserva apenas layer7.lic
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
            echo "  --keep-config    Preserva layer7.json e layer7.lic"
            echo "  --keep-license   Preserva apenas layer7.lic"
            echo "  --clean-unbound  Remove overrides anti-DoH do Unbound (config.xml)"
            echo "  --yes            Nao pedir confirmacao"
            exit 0
            ;;
        *) echo "Opcao desconhecida: $1"; exit 1 ;;
    esac
done

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
rm -f /var/log/layer7-bl-update.log
rm -f /tmp/layer7-stats.json
rm -f /usr/local/etc/layer7.json.sample
rm -f /usr/local/etc/layer7-protos.txt.sample
rm -f /usr/local/etc/layer7-protos.txt

if [ "$KEEP_CONFIG" -eq 1 ]; then
    echo "      --keep-config: preservando layer7.json e layer7.lic"
elif [ "$KEEP_LICENSE" -eq 1 ]; then
    rm -f /usr/local/etc/layer7.json
    echo "      --keep-license: preservando layer7.lic, removendo layer7.json"
else
    rm -f /usr/local/etc/layer7.json
    rm -f /usr/local/etc/layer7.lic
    echo "      Configuracao e licenca removidas."
fi

rm -rf /usr/local/etc/layer7
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
echo "  fetch -o /tmp/install.sh https://raw.githubusercontent.com/pablomichelin/Layer7/main/install.sh && sh /tmp/install.sh"
echo ""
