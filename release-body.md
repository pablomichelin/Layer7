# Layer7 v1.8.3 — Bloqueio de QUIC por interface seleccionável

## Resumo

O bloqueio de QUIC (UDP 443) deixa de ser um checkbox global e passa a ser uma lista de interfaces seleccionáveis em `Layer7 → Configurações Gerais`. Cada interface pode ser activada ou desactivada independentemente.

## O que mudou na GUI

Em `Layer7 → Configurações Gerais`, a opção "Bloquear QUIC" agora mostra a lista de todas as interfaces do pfSense com checkboxes individuais:

```
Bloquear QUIC (UDP 443)
  [x] LAN (em0)
  [ ] WAN (igb0)
  [x] VLAN_Alunos (em1.46)
  [ ] ADM (em1.10)

  Selecione as interfaces onde QUIC deve ser bloqueado.
  Vazio = desativado.
```

## Regras PF geradas

Para cada interface seleccionada, são geradas regras específicas:

```
block drop quick inet on em0 proto udp to !<localsubnets> port 443 label "layer7:anti-quic:em0"
block drop quick inet6 on em0 proto udp to !<localsubnets> port 443 label "layer7:anti-quic6:em0"
block drop quick inet on em1.46 proto udp to !<localsubnets> port 443 label "layer7:anti-quic:em1.46"
block drop quick inet6 on em1.46 proto udp to !<localsubnets> port 443 label "layer7:anti-quic6:em1.46"
```

O `to !<localsubnets>` mantém-se — tráfego interno nunca é bloqueado.

## Retrocompatibilidade

Instalações existentes com `block_quic: true` (checkbox antigo activado) continuam a funcionar com regra global **até o utilizador abrir e gravar** as Configurações Gerais. Após gravar, o campo legado é limpo e passa a usar a nova configuração por interface.

## Novo campo no config JSON

```json
{
  "layer7": {
    "block_quic_interfaces": ["em0", "em1.46"],
    "block_quic": false
  }
}
```

## Instalação / Upgrade

```sh
fetch -o /tmp/install.sh https://github.com/pablomichelin/Layer7/releases/download/v1.8.3/install.sh
sh /tmp/install.sh --version 1.8.3
```

## Verificação

```sh
# Confirmar regras geradas por interface
pfctl -a layer7 -sr 2>/dev/null | grep anti-quic
```

## Rollback

```sh
fetch -o /tmp/install.sh https://github.com/pablomichelin/Layer7/releases/download/v1.8.2/install.sh
sh /tmp/install.sh --version 1.8.2
```

## Compatibilidade

- pfSense CE 2.7.x e 2.8.x
- FreeBSD 14 e 15
- Zero migração de configuração necessária
