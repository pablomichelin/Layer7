# F3.10 — Runbook da Proxima Campanha Real

## Finalidade

Este documento fecha a parte canónica da **F3.10** sem abrir F4/F5/F6/F7.

Objectivo desta subfase:

- transformar a proxima campanha real da F3 numa execucao sequencial,
  previsivel e auditavel;
- impedir que a F3.11 comece sem preflight suficiente;
- definir criterios objectivos para **abortar antes de gerar falso `FAIL`**;
- fixar a ordem minima de execucao dos cenarios e a evidencia obrigatoria
  antes e durante a campanha.

Leitura complementar obrigatoria:

- [`f3-matriz-prerequisitos-campanha.md`](f3-matriz-prerequisitos-campanha.md)
  para decidir se a campanha pode abrir;
- [`f3-matriz-drift-operacional.md`](f3-matriz-drift-operacional.md)
  para classificar desvios ainda presentes;
- [`f3-pack-operacional-validacao.md`](f3-pack-operacional-validacao.md)
  para a estrutura de evidencias por `run_id`;
- [`f3-gate-fechamento-validacao.md`](f3-gate-fechamento-validacao.md)
  para a leitura final `PASS` / `FAIL` / `INCONCLUSIVE` / `BLOCKED`.

---

## 1. Condicao de entrada da F3.11

A F3.11 so pode ser aberta como campanha real se, antes do primeiro cenario,
todos os itens abaixo estiverem em verde:

1. deploy escolhido com referencia de repo e revisao do ambiente observadas;
2. schema coerente com o contrato canónico necessario para os cenarios;
3. credencial administrativa autorizada e testada;
4. appliance pfSense autenticavel e com baseline recolhivel;
5. inventario minimo de licencas preparado por cenario;
6. janela legitima para relogio, offline, revoke e drift de NIC/UUID quando
   esses cenarios forem executados.

Se um item obrigatorio falhar no preflight, a campanha **nao** entra em modo
de fechamento da F3.

---

## 2. Evidencias minimas antes de qualquer cenario

No directorio raiz do `run_id`, recolher antes de S01:

- `00-campaign-manifest.txt`
- `10-preflight-deploy.txt`
- `20-preflight-schema.txt`
- `30-preflight-admin.txt`
- `40-preflight-appliance.txt`
- `50-preflight-inventory.md`

Conteudo minimo desses artefactos:

| Ficheiro | Conteudo minimo obrigatorio |
|----------|-----------------------------|
| `00-campaign-manifest.txt` | `run_id`, operadores, data UTC, docs F3.6/F3.7/F3.8/F3.10 usadas, URL publica, origin observado, objectivo da campanha |
| `10-preflight-deploy.txt` | repo/commit de referencia da campanha, identificacao do deploy observado, host/origin, prova de que o ambiente nao e "desconhecido" |
| `20-preflight-schema.txt` | presenca/ausencia de `licenses`, `activations_log`, `admin_audit_log`, `admin_sessions`, `admin_login_guards` |
| `30-preflight-admin.txt` | resultado do login administrativo, escopo autorizado e limites de uso |
| `40-preflight-appliance.txt` | `layer7d --fingerprint`, `service layer7d status`, `date -u`, estado do `.lic` local, stats JSON inicial |
| `50-preflight-inventory.md` | mapeamento `LIC-A` a `LIC-F`, `license_id`, `license_key`, appliance alvo e estado esperado |

Sem estes seis artefactos, a campanha nao deve avancar para S01.

---

## 3. Ordem operacional minima da campanha

### 3.1 Checagens iniciais

1. Gerar `run_id` e directoria da campanha com o helper da F3.7.
2. Registar operador, ambiente, appliance(s), URL publica e origin observado.
3. Congelar a referencia documental da campanha: F3.6, F3.7, F3.8 e F3.10.

### 3.2 Validacao de deploy vs repo

1. Registar o commit/referencia canónica da campanha.
2. Capturar a identificacao do deploy efectivamente observado no host/origin.
3. Confirmar que a campanha nao esta a assumir, sem prova, que o live e
   igual ao repositório.
4. Testar o comportamento minimo de `POST /api/activate` que distingue `409`
   de `403` para o cenario de hardware divergente.

### 3.3 Validacao de schema live

1. Consultar o banco do deploy observado.
2. Confirmar a existencia de `admin_sessions`, `admin_audit_log` e
   `admin_login_guards`.
3. Confirmar que `licenses` e `activations_log` estao legiveis para recolha
   objectiva de evidencias.

### 3.4 Validacao de credenciais admin

1. Executar o login no fluxo oficial de sessao.
2. Confirmar que a credencial esta autorizada para os cenarios
   administrativos desta campanha.
3. Registar explicitamente qualquer limite de escopo.

### 3.5 Validacao do appliance pfSense

1. Confirmar SSH funcional.
2. Recolher baseline: fingerprint, `.lic` local, `date -u`, stats JSON,
   estado do servico.
3. Confirmar snapshot/rollback antes de relogio, offline, revoke, NIC ou
   UUID.

### 3.6 Validacao do inventario de licencas

1. Mapear `LIC-A` a `LIC-F` aos cenarios.
2. Confirmar estado actual de cada licenca no backend.
3. Confirmar em que appliance cada licenca sera exercitada.

### 3.7 Decisao de avancar ou abortar

- so avancar para cenario quando os itens 3.2 a 3.6 estiverem em verde;
- se algum item obrigatorio falhar, abortar a campanha de fechamento da F3
  antes de executar cenarios obrigatorios fora de contexto.

---

## 4. Ordem recomendada de execucao dos cenarios

Ordem minima para reduzir contaminação entre cenarios:

1. `S01`
2. `S02`
3. `S03`
4. `S04`
5. `S06`
6. `S05`
7. `S10` apenas se a janela continuar limpa e sem risco de contaminar o
   restante
8. `S07`
9. `S08`
10. `S12`
11. `S11`
12. `S09`
13. `S13`

Justificacao operacional:

- `S01` e `S02` constroem o baseline real de bind;
- `S03` confirma cedo se o deploy respeita o contrato `409`;
- `S04`, `S06` e `S05` esgotam primeiro a metade administrativa obrigatoria;
- `S07` valida o caminho online de expiracao sem ainda entrar em grace;
- `S08` e `S12` usam o mesmo bloco de controlo de relogio;
- `S11` deve ocorrer antes de `S09`, porque a coexistencia de artefactos
  exige licenca ainda activa antes da revogacao;
- `S13` fica por ultimo porque pode destruir a identidade do appliance.

---

## 5. Criterio para abortar a campanha antes de gerar falso FAIL

Abortar a campanha de fechamento antes do primeiro cenario afectado se
ocorrer qualquer um dos pontos abaixo:

- deploy efectivo sem revisao/prova minimamente observada;
- `activate` ainda a responder fora do contrato esperado para o cenario de
  controlo;
- schema live sem as tabelas canónicas necessarias;
- credencial administrativa ausente, invalida ou sem autorizacao formal;
- appliance sem SSH, sem fingerprint, sem stats JSON ou sem snapshot;
- inventario de licencas incompleto ou em estado diferente do manifesto;
- ausencia de janela legitima para relogio, offline ou drift de NIC/UUID.

**Regra:** nesses casos o resultado correcto e **aborto de campanha com
drift/blocker registado**, nao `FAIL` do produto.

---

## 6. Criterio para marcar BLOCKED antecipadamente

Marcar o cenario como `BLOCKED` sem o executar quando o pre-requisito ja
estiver objectivamente ausente:

| Cenario | Marcar `BLOCKED` antecipadamente quando |
|---------|-----------------------------------------|
| S01 | nao existir LIC-A activa sem bind ou o appliance estiver sem acesso |
| S02 | S01 nao tiver produzido baseline valido no mesmo hardware |
| S03 | nao existir licenca bindada previamente provada |
| S04 | nao houver sessao admin autorizada ou `admin_audit_log` |
| S05 | nao houver sessao admin autorizada, bind valido ou licenca dedicada |
| S06 | nao houver `ALT_CUSTOMER_ID` valido ou sessao admin autorizada |
| S07 | nao houver licenca expirada dedicada ou appliance sem `.lic` local limpo |
| S08 | nao houver artefacto previo valido e controlo de relogio |
| S09 | nao houver artefacto antigo preservado e isolamento offline |
| S10 | nao houver janela limpa para repeticao administrativa |
| S11 | nao houver artefacto antigo guardado antes da reemissao |
| S12 | nao houver snapshot e controlo de data antes/durante/depois da grace |
| S13 | o lab nao permitir drift real e reversivel de NIC/UUID/clone/restore |

---

## 7. Evidencias obrigatorias por cenario

Usar os nomes da F3.7 sempre que aplicavel.

| ID | Evidencias obrigatorias |
|----|-------------------------|
| S01 | `40-http-response.txt` ou saida CLI do `--activate`, `10-backend-license.txt`, `20-backend-activations-log.txt`, `30-backend-admin-audit-log.txt`, `50-appliance-cli.txt` |
| S02 | saida CLI da reactivacao, `10-backend-license.txt`, `20-backend-activations-log.txt`, `30-backend-admin-audit-log.txt` |
| S03 | `40-http-response.txt`, `20-backend-activations-log.txt`, `10-backend-license.txt` |
| S04 | `40-http-response.txt`, artefacto descarregado, `30-backend-admin-audit-log.txt` |
| S05 | resposta do `PUT`, artefacto reemitido, `10-backend-license.txt`, prova de bind preservado, opcionalmente prova no appliance |
| S06 | `40-http-response.txt`, `10-backend-license.txt`, `30-backend-admin-audit-log.txt` |
| S07 | saida CLI do `--activate`, prova de ausencia de `.lic` local, `10-backend-license.txt`, `20-backend-activations-log.txt` |
| S08 | `10-backend-license.txt`, tentativa online negada, `50-appliance-cli.txt` ou `60-appliance-license.json`, `date -u` do appliance |
| S09 | resposta da revogacao, `10-backend-license.txt`, tentativa online negada, stats/offline do appliance |
| S10 | dois downloads ou duas respostas guardadas, `30-backend-admin-audit-log.txt`, `70-local-hashes.txt` como evidencia adicional |
| S11 | copia do artefacto antigo, copia do artefacto novo, hashes dos dois, stats do appliance com cada um, `30-backend-admin-audit-log.txt` |
| S12 | tres capturas de data/estado: antes da expiracao, dentro da grace e apos a grace; stats JSON e estado do servico |
| S13 | `kern.hostuuid` antes/depois, fingerprint antes/depois, stats JSON, descricao exacta da mudanca aplicada e tentativa online se houve drift |

---

## 8. Fecho operativo da campanha

1. Preencher o relatorio final unico da campanha.
2. Contar `PASS`, `FAIL`, `INCONCLUSIVE` e `BLOCKED`.
3. Se qualquer obrigatorio ficar fora de `PASS`, concluir `F3 nao pode
   fechar`.
4. Se todos os obrigatorios ficarem em `PASS`, concluir `F3 pode fechar`.

Nao existe fecho parcial da F3. A F3.11 so cumpre a sua funcao se executar
este runbook sem reaprender blockers ja registados na F3.9/F3.10.
