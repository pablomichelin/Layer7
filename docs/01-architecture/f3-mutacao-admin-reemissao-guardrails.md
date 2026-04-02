# F3.4 — Mutacao Administrativa, Reemissao e Guardrails

## Finalidade

Este documento fecha a **F3.4** de forma conservadora e canónica.

Objectivo da subfase:

- mapear a superficie administrativa real de mutacao da licenca;
- distinguir mutacoes seguras de mutacoes perigosas apos bind/activacao;
- explicitar o risco de reemissao e de dois artefactos validos em campo;
- bloquear apenas o minimo necessario para impedir rebind/transferencia
  silenciosa por CRUD normal, sem abrir workflow novo nem quebrar
  compatibilidade.

Escopo deliberadamente limitado:

- sem rebind administrativo completo;
- sem desrevogacao com governanca dedicada;
- sem mudanca de schema;
- sem mudanca do formato `.lic`;
- sem mudanca do algoritmo de fingerprint;
- sem mexer em daemon/package/runtime/frontend.

---

## 1. Leitura factual do codigo actual

### 1.1 Rotas administrativas observadas

Em `license-server/backend/src/routes/licenses.js`:

- `POST /api/licenses` cria licenca
- `PUT /api/licenses/:id` edita licenca
- `POST /api/licenses/:id/revoke` revoga licenca
- `DELETE /api/licenses/:id` arquiva licenca
- `GET /api/licenses/:id/download` reemite/download do `.lic`

Em `license-server/backend/src/routes/customers.js`:

- `POST /api/customers` cria cliente
- `PUT /api/customers/:id` edita cliente
- `DELETE /api/customers/:id` arquiva cliente

Nao existe na F3.4:

- rota normal de rebind;
- rota normal de desrevogacao;
- rota normal para editar `hardware_id`, `status`, `revoked_at` ou
  `license_key`;
- trilha dedicada para mover licenca bindada entre clientes.

### 1.2 Campos aceites hoje em create/update

Validacao observada em `license-server/backend/src/crud-validation.js`:

- `POST /api/licenses` aceita apenas:
  - `customer_id`
  - `expiry`
  - `features`
  - `notes`
- `PUT /api/licenses/:id` aceita apenas:
  - `customer_id`
  - `expiry`
  - `features`
  - `notes`

Logo, via CRUD normal:

- `hardware_id` nao e editavel;
- `status` nao e editavel;
- `revoked_at` nao e editavel;
- `license_key` nao e editavel.

### 1.3 Diferenca entre licenca nao bindada e bindada

Licenca nao bindada:

- nasce `active`;
- nasce sem `hardware_id`;
- ainda nao emitiu bind operacional;
- pode ser corrigida administrativamente com risco reduzido.

Licenca bindada/activada:

- ja tem `hardware_id` persistido;
- pode gerar `.lic` assinado via activacao ou download administrativo;
- qualquer mutacao administrativa passa a coexistir com a possibilidade real
  de um `.lic` antigo continuar valido offline no hardware antigo.

### 1.4 Reemissao/download observado no codigo

`GET /api/licenses/:id/download`:

- exige licenca efectivamente `active`;
- exige `hardware_id` ja persistido;
- reconstroi o `.lic` com os valores actuais do banco:
  - `customer` derivado de `customers.name`
  - `hardware_id`
  - `expiry`
  - `features`
- assina novamente o artefacto com Ed25519.

Conclusao factual:

- qualquer mutacao administrativa permitida no banco que altere campos
  assinados pode produzir novo `.lic` diferente do antigo;
- como o daemon nao consulta o servidor em tempo real, o artefacto antigo
  pode continuar operacional offline ate falhar por data, grace, assinatura
  ou hardware mismatch.

### 1.5 Auditoria actual

Existe trilha minima em `admin_audit_log` via `auditAdminEvent()` para:

- create
- update
- revoke
- archive
- download

Antes da F3.4, `license_updated` registava apenas o `license_id`.
Na F3.4, a auditoria passa a registar tambem:

- `changed_fields`
- `activated`
- `bound`

Isto nao cria workflow novo, mas melhora previsibilidade do que foi alterado.

---

## 2. Matriz canónica de campos e mutacoes

| Campo | Licenca nao bindada | Licenca bindada | Risco actual | Politica F3.4 |
|------|----------------------|-----------------|--------------|---------------|
| `customer_id` | mutavel | sensivel | pode transferir silenciosamente a licenca para outro cliente enquanto `.lic` antigo continua em campo | **permitido antes do bind; bloqueado apos bind/activacao** |
| `expiry` | mutavel | mutavel | reemissao pode coexistir com `.lic` antigo, mas faz parte da renovacao legitima da mesma licenca | permitido |
| `features` | mutavel | mutavel | reemissao pode gerar artefacto novo, mas nao altera ownership/bind | permitido |
| `notes` | mutavel | mutavel | apenas metadado administrativo | permitido |
| `hardware_id` | fora do CRUD normal | fora do CRUD normal | rebind de facto | reservado para trilha futura dedicada |
| `status` | fora do CRUD normal | fora do CRUD normal | contradicao com semantica efectiva e fluxo oficial | reservado; sem edicao manual directa |
| `revoked_at` | fora do CRUD normal | fora do CRUD normal | desrevogacao silenciosa e historico incoerente | reservado |
| `license_key` | imutavel | imutavel | mudaria identidade da licenca | bloqueado pelo schema actual |
| `customer.name` | mutavel no cliente | mutavel no cliente | muda o texto emitido em `.lic` futuro, sem mudar ownership da licenca | permitido com cautela operacional |

---

## 3. Matriz de cenarios reais

| Cenario | Comportamento actual provavel | Risco operacional | Decisao F3.4 |
|--------|-------------------------------|-------------------|---------------|
| Editar `hardware_id` em licenca nunca activada | nao ha rota normal para isso | alto, porque abriria bind administrativo precoce | manter bloqueado |
| Editar `hardware_id` em licenca bindada | nao ha rota normal para isso | critico; rebind silencioso | manter bloqueado e fora de escopo |
| Editar `expiry` em licenca activa sem `.lic` emitido | permitido por `PUT` | baixo; e a renovacao normal | manter permitido |
| Editar `expiry` em licenca activa com `.lic` ja emitido | permitido por `PUT`; download/reativacao podem emitir `.lic` novo | medio; antigo `.lic` pode coexistir ate expirar/grace | manter permitido, com risco explicitado |
| Revogar licenca ja bindada | permitido por rota dedicada | medio; servidor corta online, mas nao corta offline imediatamente | manter permitido |
| “Desrevogar” licenca | nao existe rota normal | alto se fosse silencioso | manter fora de escopo |
| Alterar `customer_id` de licenca bindada | antes da F3.4 era permitido por `PUT` | alto; transferencia silenciosa + dois artefactos coerentes com clientes diferentes | **bloquear** |
| Alterar `status` manualmente para `active/expired/revoked` | nao existe por CRUD normal | alto; contradicao com semantica efectiva | manter fora de escopo |
| Arquivar licenca bindada | ja falha se estiver activa | medio; historico preservado, mas nao e transferencia | manter comportamento actual |
| Baixar novo `.lic` depois de mutacao administrativa sensivel | possivel se licenca continuar `active` e bindada | alto quando a mutacao muda identidade/ownership | impedir pela raiz a mutacao perigosa |
| Risco de dois artefactos validos em campo | existe sempre que um `.lic` novo e emitido e o antigo ainda esta no hardware correcto | real e conhecido | explicitar e nao abrir rebind/transferencia nesta fase |

---

## 4. Politica conservadora oficial da F3.4

### 4.1 Mutações que continuam permitidas

- corrigir `customer_id` antes do bind/activacao;
- renovar `expiry`;
- ajustar `features`;
- ajustar `notes`;
- revogar licenca pelo fluxo oficial;
- arquivar licenca pelo fluxo oficial quando o estado actual permitir.

### 4.2 Mutacoes que passam a ficar bloqueadas no CRUD normal

- mudar `customer_id` depois de a licenca ja estar activada/bindada.

Racional:

- nao e um mero metadado;
- muda ownership comercial da licenca;
- abre caminho para reemissao coerente para outro cliente sem invalidar o
  artefacto anterior em campo;
- equivale, na pratica, a uma transferencia administrativa insegura.

### 4.3 Mutacoes que ficam reservadas para trilha futura dedicada

- rebind administrativo;
- desrevogacao;
- qualquer edicao manual de `hardware_id`, `status`, `revoked_at` ou
  `license_key`;
- transferencia formal de licenca bindada entre clientes.

---

## 5. Relacao com expiracao, revogacao e validade offline

A F3.3 ja formalizou que:

- expiracao e revogacao cortam o caminho online;
- o daemon nao invalida offline imediatamente um `.lic` ja emitido;
- um artefacto antigo pode continuar valido no mesmo hardware ate falhar por
  data, grace, assinatura ou hardware mismatch.

A F3.4 usa esse contrato como premissa e toma a decisao conservadora minima:

- **nao permitir que o CRUD normal altere ownership de uma licenca ja
  bindada**, porque isso facilitaria a existencia de dois artefactos
  operacionalmente validos em contexto comercial diferente.

Isto nao resolve revogacao offline pesada nem reemissao historica; apenas
evita uma classe concreta e evitavel de mutacao perigosa.

---

## 6. O que o sistema garante agora

- uma licenca bindada continua associada ao mesmo hardware enquanto nao
  houver trilha dedicada de rebind;
- o CRUD normal ja nao pode transferir silenciosamente uma licenca bindada
  para outro cliente via `customer_id`;
- renovacao normal da mesma licenca continua suportada;
- auditoria minima de update passa a registar os campos alterados e o estado
  de bind/activacao no momento da mutacao.

## 7. O que o sistema ainda nao garante

- invalidacao imediata de `.lic` antigo em appliance offline;
- workflow seguro de transferencia entre clientes;
- workflow seguro de rebind administrativo;
- desrevogacao governada;
- historico forte de versoes/reemissoes do `.lic`.

---

## 8. Impacto, risco, teste e rollback

### Impacto

- reduz a superficie de mutacao administrativa perigosa;
- preserva create, renew, revoke, archive e reemissao legitima da mesma
  licenca;
- nao muda contrato externo do daemon nem formato `.lic`.

### Risco

- baixo;
- a unica incompatibilidade intencional e impedir um uso administrativo que
  era tecnicamente possivel, mas operacionalmente inseguro, apos bind.

### Teste minimo esperado

- `node --check` no ficheiro alterado do backend;
- revisao objectiva do diff;
- validacao manual simples por API:
  - mudar `customer_id` antes do bind continua possivel;
  - mudar `customer_id` depois do bind passa a falhar com `409`;
  - mudar `expiry` em licenca bindada continua possivel.

### Rollback

- reverter o commit local da F3.4.

---

## 9. Proximos passos seguros da F3

- F3.5: consolidar evidencias e validacoes manuais/appliance para os cenarios
  pendentes de offline, grace, renovacao + reactivacao e fingerprint real;
- so depois disso avaliar, em trilha propria, se existe espaco para
  transferencia/rebind com governanca dedicada e politica explicita para
  artefactos antigos em campo.
