# Trilha de Bloqueio

Documentos desta pasta descrevem a evolução do Layer7 para sair do estado atual
de classificação + enforcement parcial e chegar a **bloqueio operacional real**
de aplicações, sites, serviços e funções de produto.

## Documentos

- [`blocking-master-plan.md`](blocking-master-plan.md) — plano mestre de
  implementação, riscos, fases, testes e rollout

## Escopo

Esta trilha é **pós-V1 / V2 orientada**, mas foi aberta porque o objetivo de
produto exige que o pacote bloqueie de forma efetiva no pfSense CE, sem depender
de Squid/SquidGuard.

## Estado atual resumido

- classificação nDPI funcional
- GUI operacional
- eventos em tempo real
- políticas por app/categoria/interface/IP/CIDR
- `Sites/hosts` manuais na GUI
- enforcement atual via PF table do **IP de origem**

## Gap principal

Ainda falta fechar o bloqueio real de:

- domínio/site por destino
- função/serviço com múltiplos domínios e endpoints
- apps modernos com QUIC, DoH, cache DNS e CDNs partilhadas
