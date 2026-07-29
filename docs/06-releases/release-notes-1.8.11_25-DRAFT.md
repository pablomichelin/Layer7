# Release Notes — `pfSense-pkg-layer7` `1.8.11_25` (DRAFT)

**Estado:** candidato interno, não publicado, não aprovado para produção.

**Build interno:** PASS no FreeBSD 15; artefacto local
`pfSense-pkg-layer7-1.8.11_25.pkg`,
`SHA256=c4e9c197f79ad00d7ddb68f8ececcd391455e86011e558596102877c325d388d`.

## Objectivo

Estabilizar ciclo de vida, captura e enforcement escopado antes do gate real
com dois clientes. A release pública continua a ser `1.8.11_24`.

## Correcções

- PID sem newline do `daemon(8)` deixa de quebrar `status`/`reload`.
- IDs pfSense `lan`/`optN` são migrados para interfaces reais libpcap/PF.
- Política app com origem estática passa a aplicar `layer7_psrc_N`.
- `quarantine_origin` passa a gerar regra PF `psrc` completa.
- Política app+host escolhe `psrc` no match app e `pdst` no match host.
- GUI scoped recusa block sem escopo executável e não transforma toggle de
  um clique em bloqueio global implícito.

## Compatibilidade observada

O appliance alvo observado em `2026-07-29` executa pfSense Plus `26.03.1`
sobre FreeBSD `16.0-CURRENT`; o builder continua FreeBSD `15.0-RELEASE`.
`IGNORE_OSVERSION=yes` não constitui prova de compatibilidade. O candidato só
pode avançar após build, instalação controlada e gates no appliance real.

## Gates obrigatórios

1. `sh tests/run-local.sh`
2. lint PHP e testes PHP no builder
3. build FreeBSD com versão `1.8.11_25` — **PASS**
4. instalação em monitor/disabled e verificação:
   - uma única árvore `daemon + layer7d`;
   - `service layer7d onestatus` reconhece o PID;
   - JSON contém interfaces reais;
   - monitor não contém regra PF `block` Layer7.
5. captura ativa em interface real com `cap_pkts > 0`
6. gate two-client da secção 12 de `docs/04-package/validacao-lab.md`
7. regressões `smoke-monitor-mode.sh` e `smoke-caminho-a.sh`

## Rollback

1. voltar a `enabled=false` e `mode=monitor`;
2. confirmar ausência de regras Layer7 de bloqueio;
3. reinstalar `1.8.11_24`;
4. manter tabelas dinâmicas vazias e confirmar serviço/GUI.

Não criar tag, GitHub Release ou promover `scoped_hybrid` a default antes de
todos os gates acima.
