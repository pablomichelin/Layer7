# F3.11 - Matriz Canonica de Aceite dos Insumos Externos

## Finalidade

Esta matriz transforma os cinco insumos externos da F3.11 em gate formal de
aceite/rejeicao.

Objectivo:

- decidir rapidamente se cada entrega e suficiente ou insuficiente;
- separar `nao entregue`, `entregue invalido`, `entregue parcial` e
  `entregue valido`;
- impedir repeticao da readiness com dependencias em estado ambiguuo;
- deixar clara a diferenca entre o que libera readiness e o que libera
  campanha.

Estados operacionais usados nesta matriz:

- `nao entregue`
- `entregue invalido`
- `entregue parcial`
- `entregue valido`

Nota de actualizacao em `2026-04-14`:

- esta matriz fica preservada como historico/compatibilidade do pacote de
  cinco insumos;
- host live, PostgreSQL, credencial admin e inventario ja nao bloqueiam a F3;
- o gate corrente e `DR-05` no appliance, nao `5/5` insumos.
- quando esta matriz precisar ser reusada por drift novo no appliance, o
  insumo pfSense deve ser lido como baseline + controlos + control plane
  legitimo da GUI autenticada do pacote para cenarios mutaveis.

---

## 1. Matriz de aceite/rejeicao

| Insumo | Obrigatorio para readiness? | Obrigatorio para campanha? | Evidencia minima | Validacao objectiva | Quem valida | Status possivel | Acao imediata associada a cada status | Efeito no gate da F3.11 |
|--------|------------------------------|-----------------------------|------------------|---------------------|-------------|-----------------|----------------------------------------|--------------------------|
| acesso read-only ao host live `192.168.100.244` | sim | sim | SSH read-only funcional + output de hostname, directorio real, `git rev-parse HEAD`, `docker compose ps` e `docker compose config --services` | executar os comandos do runbook e confirmar host, directorio e stack efectivos | responsavel da rodada F3.11 com owner operacional do host | `nao entregue` / `entregue invalido` / `entregue parcial` / `entregue valido` | `nao entregue`: manter blocker e pedir acesso; `entregue invalido`: rejeitar e pedir nova entrega com output bruto; `entregue parcial`: registar lacuna exacta e nao prosseguir; `entregue valido`: registar aceite e liberar apenas o subgate de host | sem `entregue valido`, readiness = `NO-GO`; campanha = `NO-GO` |
| query read-only ao PostgreSQL live | sim | sim | identidade da base + listagem de `licenses`, `activations_log`, `admin_sessions`, `admin_audit_log`, `admin_login_guards` + `count(*)` nas tres tabelas administrativas | executar queries read-only e confirmar schema/tabelas no ambiente observado | responsavel da rodada F3.11 com owner operacional do banco/stack | `nao entregue` / `entregue invalido` / `entregue parcial` / `entregue valido` | `nao entregue`: manter blocker e pedir meio read-only; `entregue invalido`: rejeitar por falta de SQL/base/prova; `entregue parcial`: registar exactamente o que faltou e manter bloqueio; `entregue valido`: registar aceite e fechar o subgate de schema | sem `entregue valido`, readiness = `NO-GO`; campanha = `NO-GO` |
| credencial admin autorizada com escopo formal | sim | sim | credencial valida + nota de escopo + login em `/api/auth/login` + sessao em `/api/auth/session` | testar credencial no fluxo oficial e confirmar owner/janela de uso | responsavel da rodada F3.11 com owner administrativo do painel | `nao entregue` / `entregue invalido` / `entregue parcial` / `entregue valido` | `nao entregue`: manter blocker administrativo; `entregue invalido`: rejeitar por falta de owner, escopo ou sessao valida; `entregue parcial`: registar a parte ausente e nao liberar checks admin; `entregue valido`: registar aceite e liberar a metade administrativa da readiness | sem `entregue valido`, readiness = `NO-GO`; campanha = `NO-GO` |
| appliance pfSense com SSH, baseline, controlos legitimos e control plane mutavel | sim | sim | SSH funcional + baseline completa + prova de snapshot/restore, relogio, offline/online e NIC/UUID/clone/restore + prova da GUI autenticada do pacote quando o cenario for mutavel | executar baseline do appliance, conferir artefacto real de controlos do lab e validar `PHPSESSID`, `__csrf_magic` e `layer7_settings.php` autenticado quando aplicavel | responsavel da rodada F3.11 com owner do lab/appliance | `nao entregue` / `entregue invalido` / `entregue parcial` / `entregue valido` | `nao entregue`: manter blocker local; `entregue invalido`: rejeitar por faltar SSH/baseline/controlos/control plane; `entregue parcial`: manter bloqueio dos cenarios locais e pedir complemento; `entregue valido`: registar aceite e liberar os subgates locais | sem `entregue valido`, readiness = `NO-GO`; campanha = `NO-GO` |
| inventario real `LIC-A` a `LIC-F` | sim | sim | documento de inventario preenchido + prova objectiva em backend live + mapeamento `licenca -> cliente -> appliance -> cenario` | comparar inventario entregue com query read-only ou leitura administrativa comprovada | responsavel da rodada F3.11 com owner administrativo do licenciamento | `nao entregue` / `entregue invalido` / `entregue parcial` / `entregue valido` | `nao entregue`: manter blocker de inventario; `entregue invalido`: rejeitar por placeholder, inconsistencia ou falta de prova; `entregue parcial`: nao liberar readiness e pedir regularizacao do pool; `entregue valido`: registar aceite e liberar o subgate de inventario | sem `entregue valido`, readiness = `NO-GO`; campanha = `NO-GO` |

---

## 2. Regra objectiva de status

| Status | Definicao canonica | Efeito imediato |
|--------|--------------------|-----------------|
| `nao entregue` | nada utilizavel foi recebido | blocker continua integralmente aberto |
| `entregue invalido` | houve entrega, mas a prova nao sustenta o uso operacional | rejeitar formalmente e pedir nova entrega |
| `entregue parcial` | parte da prova existe, mas continua a faltar um elemento critico | nao repetir readiness; manter bloqueio ate complemento |
| `entregue valido` | acesso/evidencia satisfaz o minimo canónico sem ambiguidade | registar aceite e actualizar gate |

---

## 3. Gate por insumo

1. Historicamente, a readiness da F3.11 so podia ser repetida quando os
   cinco insumos estivessem em `entregue valido`.
2. A campanha so pode ser aberta depois de uma readiness repetida com
   resultado `GO` e sem blocker nao negociavel.
3. No estado corrente de `2026-04-14`, a matriz nao reabre o gate de cinco
   insumos; o blocker real remanescente e `DR-05`.
4. `entregue invalido` nunca conta como progresso.

---

## 4. Leitura formal desta matriz

- esta matriz governa a leitura historica de aceite/rejeicao dos cinco
  insumos;
- nao deve ser usada para reabrir blockers ja saneados no live em
  `2026-04-14`;
- para a rodada corrente, usar scorecard, registro mestre, drift registry e
  `f3-fecho-operacional-restante.md`.
