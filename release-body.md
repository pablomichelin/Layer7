# Layer7 v1.8.2 — Bloqueio restrito a destinos externos

## Resumo

Correcção arquitectural: todas as regras de bloqueio PF geradas pelo Layer7 passam a aplicar `to !<localsubnets>`, garantindo que **apenas tráfego com destino à internet é bloqueado**. Tráfego interno (impressoras, serviços de rede local, bancos via VPN corporativa) não é afectado.

## Problema corrigido

O Layer7 v1.8.0 gerava regras `from any to any`, o que causava:

- Impressoras locais com acesso a serviços cloud (UDP 443 / QUIC) deixavam de responder
- Serviços bancários que usam HTTP/3 apresentavam lentidão ou falha de conexão
- Qualquer serviço interno usando UDP 443 era bloqueado mesmo sem estar em nenhuma blacklist

## O que mudou (regras PF geradas)

**Antes:**
```
block drop quick inet proto udp to port 443 label "layer7:anti-quic"
block drop quick inet proto tcp to port 853 label "layer7:anti-dot"
block drop quick inet from <layer7_block> to any label "layer7:block:src"
```

**Depois:**
```
block drop quick inet proto udp to !<localsubnets> port 443 label "layer7:anti-quic"
block drop quick inet proto tcp to !<localsubnets> port 853 label "layer7:anti-dot"
block drop quick inet from <layer7_block> to !<localsubnets> label "layer7:block:src"
```

`<localsubnets>` é o alias nativo do pfSense contendo todas as sub-redes directamente conectadas.

## Ficheiros alterados

- `package/pfSense-pkg-layer7/files/usr/local/pkg/layer7.inc`
- `package/pfSense-pkg-layer7/files/usr/local/libexec/layer7-pfctl`
- `package/pfSense-pkg-layer7/files/usr/local/etc/layer7/pf.conf.sample`

## Instalação / Upgrade

```sh
# Upgrade directo
fetch -o /tmp/install.sh https://github.com/pablomichelin/Layer7/releases/download/v1.8.2/install.sh
sh /tmp/install.sh --version 1.8.2

# Após instalar: forçar reload das regras PF
pfctl -f /tmp/rules.debug 2>/dev/null || true
# Ou via GUI: Firewall > Rules > Apply
```

## Verificação

```sh
# Confirmar que as regras incluem !<localsubnets>
pfctl -a layer7 -sr 2>/dev/null | grep localsubnets

# Confirmar que impressora local passa (exemplo: 192.168.1.100)
# pfctl -a layer7 -sr não deve mostrar nenhuma regra bloqueando esse IP
```

## Rollback

```sh
fetch -o /tmp/install.sh https://github.com/pablomichelin/Layer7/releases/download/v1.8.0/install.sh
sh /tmp/install.sh --version 1.8.0
```

## Compatibilidade

- pfSense CE 2.7.x e 2.8.x
- FreeBSD 14 e 15
- Sem alteração no schema de configuração (zero migração necessária)
