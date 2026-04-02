# F3.5 — Emissao, Reemissao e Rastreabilidade do `.lic`

## Finalidade

Este documento fecha a **F3.5** de forma conservadora e canónica.

Objectivo desta subfase:

- mapear onde e como o artefacto `.lic` e emitido hoje;
- distinguir emissao inicial, reemissao legitima e reemissao sensivel;
- declarar o risco real de coexistencia de multiplos artefactos validos;
- melhorar a rastreabilidade administrativa sem mudar o contrato externo;
- aplicar apenas um endurecimento minimo e seguro de auditoria/governanca.

Escopo deliberadamente limitado:

- sem mudar o formato do `.lic`;
- sem mudar o algoritmo de fingerprint;
- sem mudar o daemon;
- sem revogacao offline pesada;
- sem workflow novo de rebind;
- sem schema novo;
- sem frontend/UX.

---

## 1. Leitura factual do comportamento actual

### 1.1 Onde o `.lic` e emitido hoje

O artefacto e emitido em dois caminhos:

- `POST /api/activate` em
  `license-server/backend/src/routes/activate.js`
- `GET /api/licenses/:id/download` em
  `license-server/backend/src/routes/licenses.js`

Ambos usam o mesmo helper:

- `generateSignedLicense()` em
  `license-server/backend/src/crypto.js`

### 1.2 Dados usados na assinatura

`generateSignedLicense()` assina um JSON com:

- `hardware_id`
- `expiry`
- `customer`
- `features`
- `issued`

Observacao factual:

- `issued` entra apenas como data `YYYY-MM-DD`;
- nao existe no `.lic` actual:
  - contador;
  - versao de artefacto;
  - reason;
  - flow de emissao;
  - marcador de "primeira emissao" vs "reemissao".

### 1.3 Como `expiry`, `hardware_id` e estado efectivo entram na emissao

Em `POST /api/activate`:

- o backend exige licenca visivel, nao revogada, nao expirada e nao
  arquivada;
- usa `hardware_id` do pedido quando ainda nao existe bind;
- usa `hardware_id` persistido quando o bind ja existe;
- reemite no mesmo hardware sem trocar o bind.

Em `GET /api/licenses/:id/download`:

- o backend exige licenca visivel;
- exige `hardware_id` persistido;
- exige estado efectivo `active`;
- reconstroi o `.lic` a partir do estado actual do banco.

### 1.4 Diferenca real entre activacao publica e download administrativo

Activacao publica:

- pode ser a **primeira emissao** do `.lic`;
- pode ser **re-activacao legitima** do mesmo hardware;
- deixa rasto em `activations_log` com `license_id`, `hardware_id`, `ip`,
  `user_agent`, `result` e `error_message`.

Download administrativo:

- so acontece depois de existir bind;
- e sempre uma **reemissao administrativa** do artefacto;
- antes da F3.5 deixava apenas um evento administrativo generico de download.

### 1.5 Distincao entre primeira emissao e reemissao

Antes da F3.5:

- o sistema nao registava de forma explicita se o artefacto emitido em
  `POST /api/activate` era:
  - `initial_issue`; ou
  - `reactivation_reissue`;
- o download administrativo tambem nao gravava contexto suficiente do
  artefacto emitido.

Na F3.5:

- a activacao publica passa a auditar `license_artifact_issued`;
- o backend passa a distinguir:
  - `initial_issue`
  - `reactivation_reissue`
  - `admin_download_reissue`

### 1.6 Auditoria e rastreabilidade observadas

Antes da F3.5:

- `activations_log` dizia **que houve activacao** com sucesso/falha;
- `admin_audit_log` dizia **que houve download administrativo**;
- mas nao havia trilha barata e explicita do artefacto emitido com:
  - hash do payload;
  - hash da assinatura;
  - hash do envelope devolvido;
  - flow de emissao;
  - tipo de emissao;
  - `customer_id`;
  - estado efectivo no momento da emissao.

Depois da F3.5:

- `POST /api/activate` passa a emitir evento auditavel com contexto do
  artefacto entregue;
- `GET /api/licenses/:id/download` passa a enriquecer o evento
  `license_downloaded` com contexto do artefacto.

### 1.7 O que continua ambíguo hoje

Persistem ambiguidades estruturais do modelo actual:

- o daemon nao sabe qual e o artefacto "mais recente";
- o `.lic` nao carrega contador/versionamento;
- multiplos artefactos da mesma licenca podem coexistir em campo;
- revogacao/expiracao online nao apagam retroactivamente o ficheiro local;
- a trilha auditada melhora a investigacao, mas nao cria enforcement de
  "latest only".

Inferencia conservadora importante:

- como o `.lic` actual nao carrega identidade propria de reemissao alem dos
  campos assinados, uma reemissao com o mesmo conteudo funcional continua sem
  versao intrinseca consumida pelo daemon;
- por isso, a governanca do artefacto continua dependente do rasto auditado,
  nao do ficheiro por si so.

---

## 2. Matriz de cenarios de emissao/reemissao

| Cenario | Comportamento actual provavel | Risco operacional | Rastreabilidade apos F3.5 | Decisao/documentacao |
|--------|-------------------------------|-------------------|---------------------------|----------------------|
| Primeira activacao com emissao inicial do `.lic` | binda `hardware_id`, fixa `activated_at` e devolve `.lic` | Baixo | `activations_log` + `license_artifact_issued` com `initial_issue` | manter |
| Re-activacao legitima do mesmo hardware | reemite `.lic` no mesmo bind | Medio | `activations_log` + `license_artifact_issued` com `reactivation_reissue` | manter e declarar como reemissao legitima |
| Download administrativo da licenca ja bindada | devolve `.lic` assinado com dados correntes do banco | Medio | `license_downloaded` enriquecido com hashes e contexto | manter |
| Renovacao de `expiry` com artefacto antigo em campo | novo `.lic` pode ser emitido; antigo pode continuar a funcionar ate limite local | Medio | auditoria melhora investigacao, mas nao elimina coexistencia | manter e declarar risco |
| Revogacao apos artefacto ja emitido | servidor corta online; artefacto antigo pode continuar offline | Alto | trilha mostra revogacao e historico de emissao, mas nao invalida offline | manter como limite real |
| Multiplos downloads administrativos da mesma licenca | backend pode reemitir varias vezes | Medio | cada acto de download passa a deixar hash/contexto | manter com governanca conservadora |
| Operador baixar `.lic` apos mudanca administrativa permitida | artefacto novo reflecte `expiry`, `features` ou `customer.name` actual | Medio | trilha de download passa a indicar contexto do artefacto entregue | manter com cautela |
| Coexistencia de `.lic` antigo e `.lic` mais recente | pode acontecer sem quebra imediata do antigo | Alto | audit trail permite comparar actos de emissao; daemon continua sem noção de "mais recente" | declarar explicitamente |
| Emissao com servidor online e appliance depois offline | appliance continua com o ficheiro recebido | Medio | emissao fica auditada; uso offline continua local | manter |
| O artefacto mais recente nao ser o unico em campo | situacao real e possivel hoje | Alto | rastreabilidade melhora, exclusividade nao | nao maquilhar; manter fora de escopo tecnico |

---

## 3. Politica conservadora oficial da F3.5

### 3.1 Definicoes oficiais desta fase

- **Emissao inicial:** primeiro `.lic` devolvido por `POST /api/activate`
  quando ainda nao havia bind operacional valido.
- **Reemissao legitima:** novo `.lic` devolvido para a mesma licenca e o
  mesmo hardware bindado.
- **Reemissao administrativa:** `.lic` devolvido por download autenticado do
  painel para licenca activa e bindada.
- **Reemissao sensivel:** qualquer reemissao que ocorra apos mutacao
  administrativa permitida ou em contexto onde um artefacto antigo ainda
  pode permanecer operacional em campo.

### 3.2 Governanca oficial do artefacto

- o sistema passa a tratar emissao/reemissao como evento auditavel explicito;
- o operador nao deve assumir que o artefacto mais recente invalida o antigo;
- antes de reenviar um `.lic`, o operador deve consultar a trilha por
  `license_id`, `flow`, `emission_kind` e hashes do artefacto;
- o download administrativo continua permitido, mas fica assumido como
  **reemissao governada**, nao como operacao neutra.

### 3.3 O que deve ficar auditado

Campos/contextos que passam a ser oficiais para rastreabilidade:

- `flow`
- `emission_kind`
- `license_id`
- `customer_id`
- `license_key_prefix`
- `hardware_id`
- `expiry`
- `effective_status`
- `activated`
- `bound`
- `customer_name`
- `features`
- `issued_on`
- `artifact_payload_sha256`
- `artifact_sig_sha256`
- `artifact_envelope_sha256`
- actor/rota/IP/User-Agent via a infraestrutura de auditoria existente

---

## 4. O que a F3.5 melhora de facto

- passa a existir rasto barato do artefacto devolvido na activacao publica;
- o download administrativo deixa de ser apenas "houve download" e passa a
  carregar contexto do artefacto efectivamente entregue;
- o backend passa a distinguir emissao inicial de reemissao legitima no
  caminho publico;
- a investigacao futura passa a conseguir correlacionar:
  - tentativas em `activations_log`;
  - mutacoes administrativas;
  - eventos de download;
  - hashes do artefacto emitido.

---

## 5. O que continua impossivel sem mudar formato, daemon ou revogacao offline

- impor que apenas o artefacto mais recente seja aceite pelo daemon;
- invalidar imediatamente um `.lic` antigo offline;
- embutir contador/versionamento sem mudar o formato do `.lic`;
- distinguir no appliance qual das reemissoes foi a "ultima" apenas pelo
  contrato actual do daemon;
- resolver de forma forte a coexistencia de multiplos artefactos validos em
  campo.

---

## 6. Fora de escopo

- rebind administrativo completo;
- revogacao offline pesada;
- alteracao do formato `.lic`;
- alteracao do algoritmo de fingerprint;
- schema novo;
- frontend/UX;
- workflow de aprovacao pesado;
- package/runtime/observabilidade ampliada.

---

## 7. Impacto, risco, teste e rollback

### Impacto

- melhora a rastreabilidade de emissao e download;
- preserva `POST /api/activate` e `GET /api/licenses/:id/download`;
- nao muda o payload externo `{ data, sig }`;
- nao muda a semantica de validacao do daemon.

### Risco

- baixo;
- a mudanca fica restrita a auditoria e governanca do backend;
- nao altera assinatura nem criterio de aceite do `.lic`.

### Teste minimo esperado

- `node --check` nos ficheiros JS alterados;
- revisao de diff;
- validacao manual de que:
  - a activacao continua a devolver `{ data, sig }`;
  - o download administrativo continua a devolver `{ data, sig }`;
  - a auditoria passa a carregar `flow`, `emission_kind` e hashes do
    artefacto.

### Rollback

- reverter o commit local da F3.5.

---

## 8. Proximos passos seguros da F3

- F3.6: consolidar evidencias e validacoes manuais/appliance dos cenarios
  pendentes de expiracao, revogacao, grace, renovacao + reactivacao,
  indisponibilidade do servidor e fingerprint real;
- so depois disso avaliar se existe espaco para evolucoes mais invasivas de
  artefacto, revogacao offline ou rebind governado.
