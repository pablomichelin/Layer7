# F3.3 — Expiracao, Revogacao, Grace e Validade Offline

## Finalidade

Este documento fecha a leitura canónica da **F3.3** para:

- semantica real de expiracao;
- diferenca entre estado persistido e estado derivado;
- efeitos praticos de revogacao no servidor;
- validade offline do `.lic` ja emitido;
- papel exacto do grace local do daemon;
- limites reais do que o produto garante hoje.

Objectivo desta subfase:

- mapear o comportamento real observado no codigo;
- declarar sem ambiguidade onde servidor e daemon convergem ou divergem;
- reduzir drift operacional com uma melhoria minima e segura;
- **nao** redesenhar o modelo de licenciamento, **nao** abrir revogacao
  offline pesada e **nao** abrir F4/F5/F6/F7.

---

## 1. Leitura factual do comportamento actual

### 1.1 Estado persistido hoje

Observado em `license-server/backend/migrations/001-init.sql` e nas rotas do
backend:

- `licenses.status` persiste apenas:
  - `active`
  - `revoked`
  - `expired`
- `licenses.expiry` guarda a data contratual de fim da licenca;
- `licenses.hardware_id` guarda o bind exacto do appliance apos a primeira
  activacao valida;
- `licenses.activated_at` regista a primeira activacao bem-sucedida;
- `licenses.revoked_at` regista a revogacao administrativa;
- `licenses.archived_at` retira a licenca das listagens e da operacao normal
  do painel, mas nao e um status textual;
- `activations_log` guarda tentativas de activacao, com `result='success'`
  ou `result='fail'`.

Leitura importante:

- o backend **nao** promove automaticamente uma licenca de `active` para
  `expired` quando a data passa;
- por isso, `status='expired'` continua a ser um estado persistido
  **opcional/legado**, nao uma transicao automatica universal.

### 1.2 Estado efectivo derivado hoje

Observado em `activate.js`, `licenses.js`, `customers.js`, `dashboard.js` e,
na F3.3, centralizado em `license-server/backend/src/license-state.js`:

- `revoked` continua a vir do estado persistido;
- `expired` passa a ser tratado como efectivo quando:
  - `status='expired'`; ou
  - `status='active'` com `expiry < CURRENT_DATE`;
- `active` efectivo fica restrito a:
  - `status='active'`; e
  - `expiry >= CURRENT_DATE`.

Ordem de precedencia efectiva:

1. `revoked`
2. `expired`
3. `active`

Conclusao factual:

- o estado que o banco guarda e o estado que o produto **usa** nao sao
  exactamente a mesma coisa;
- o servidor opera com uma combinacao de:
  - **persistencia** para revogacao;
  - **derivacao por data** para expiracao.

### 1.3 Onde a expiracao e decidida no backend

Observado no codigo:

- `crud-validation.js:isLicenseExpired()` compara `license.expiry` com a data
  UTC actual (`YYYY-MM-DD`);
- `POST /api/activate` recusa activacao se a licenca estiver efectivamente
  expirada;
- `GET /api/licenses/:id/download` recusa download se a licenca nao estiver
  efectivamente `active`;
- `GET /api/licenses`, `GET /api/licenses/:id`, `GET /api/customers/:id` e
  `GET /api/dashboard` exibem expirada a licenca `active` cuja data ja passou;
- `DELETE /api/customers/:id` considera "activa" apenas a licenca com
  `status='active'` e `expiry >= CURRENT_DATE`.

Conclusao factual:

- a expiracao **online** do servidor e strict;
- depois da data, a licenca deixa de ser activavel e deixa de ser
  descarregavel pelo painel, mesmo que o campo persistido ainda esteja
  `active`.

### 1.4 Onde a revogacao e decidida no backend

Observado em `license-server/backend/src/routes/licenses.js` e
`activate.js`:

- `POST /api/licenses/:id/revoke` persiste `status='revoked'` e `revoked_at`;
- `POST /api/activate` recusa licenca revogada com `409`;
- `GET /api/licenses/:id/download` recusa licenca revogada porque o estado
  efectivo deixa de ser `active`;
- dashboard e listagens passam a mostrar a licenca como revogada;
- nao existe qualquer chamada do daemon ao servidor para verificar revogacao
  apos o `.lic` estar no appliance.

Conclusao factual:

- a revogacao actual e **forte para activacao e operacao online do painel**;
- a revogacao actual **nao invalida por si so** um `.lic` que ja foi emitido
  e que continua apenas em uso local.

### 1.5 Regras reais de emissao e download do `.lic`

Observado em `activate.js`, `licenses.js` e `crypto.js`:

- `POST /api/activate` emite `.lic` assinado quando:
  - a chave existe;
  - a licenca nao esta arquivada;
  - o cliente associado nao esta arquivado;
  - o estado efectivo e `active`;
  - o bind e inexistente ou corresponde ao mesmo `hardware_id`;
- `GET /api/licenses/:id/download` so emite `.lic` quando:
  - a licenca e visivel;
  - existe `hardware_id` persistido;
  - o estado efectivo e `active`;
- o payload assinado contem:
  - `hardware_id`
  - `expiry`
  - `customer`
  - `features`
  - `issued`

Conclusao factual:

- o painel **nao** faz download administrativo de licenca expirada;
- o painel **nao** faz download administrativo de licenca revogada;
- o servidor **nao** injecta no `.lic` nenhum carimbo de revogacao nem
  obrigacao de contacto futuro com o servidor.

### 1.6 Como o daemon decide aceitar ou rejeitar um `.lic` local

Observado em `src/layer7d/license.c`:

1. recalcula o fingerprint local:
   - `SHA256(kern.hostuuid + ":" + primeira MAC Ethernet nao-loopback)`;
2. le `/usr/local/etc/layer7.lic`;
3. extrai `data` e `sig`;
4. valida a assinatura Ed25519 com a public key embutida;
5. extrai `hardware_id` e `expiry` do JSON assinado;
6. compara `hardware_id` do ficheiro com o fingerprint local via `strcmp()`;
7. valida a data de expiracao;
8. se valida, permite enforce;
9. se invalida, cai para monitor-only.

O daemon **nao consulta**:

- `licenses.status` no servidor;
- `revoked_at`;
- dashboard;
- qualquer heartbeat.

### 1.7 Como o grace de 14 dias funciona de facto

Observado em `src/layer7d/license.c`:

- se `diff_days >= 0`, a licenca e valida sem grace;
- se `diff_days < 0`, o daemon marca:
  - `expired = 1`;
  - `grace = 1` apenas quando `-diff_days <= 14`;
- dentro do grace:
  - `valid = 1`;
  - o daemon continua a permitir enforce;
  - o erro interno passa a ser uma mensagem de expiracao com grace activo;
- apos `14` dias:
  - `valid = 0`;
  - o daemon invalida a licenca;
  - `main.c` desactiva enforce e cai para monitor-only.

Conclusao factual:

- o grace e **local ao daemon**;
- o grace depende apenas de:
  - assinatura valida;
  - `hardware_id` local correspondente;
  - data local do appliance.

### 1.8 Onde servidor e daemon divergem e onde se complementam

Complementam-se:

- o servidor emite e assina;
- o daemon valida assinatura, fingerprint e data local;
- o `hardware_id` assinado liga o `.lic` ao appliance.

Divergem deliberadamente hoje:

- o servidor recusa activacao/download apos expiracao efectiva;
- o daemon ainda aceita o `.lic` ja emitido por ate `14` dias de grace;
- o servidor persiste revogacao;
- o daemon nao conhece revogacao offline.

Leitura oficial da F3.3:

- isto nao e bug escondido; e a semantica real actual do produto;
- a diferenca precisa de ser tratada como **contrato operacional**, nao como
  suposicao.

---

## 2. Matriz de estados e transicoes

### 2.1 Estado persistido vs estado efectivo

| Estado persistido / campos | Estado efectivo no backend | Activacao online | Download administrativo | Validacao local no daemon |
|----------------------------|----------------------------|------------------|-------------------------|---------------------------|
| `status='active'`, `expiry >= hoje`, sem `hardware_id` | `active` | permitido | negado (`licenca ainda nao foi activada`) | nao aplicavel sem `.lic` |
| `status='active'`, `expiry >= hoje`, com `hardware_id` | `active` | permitido no mesmo hardware | permitido | valido ate `expiry` |
| `status='active'`, `expiry < hoje` | `expired` derivado | negado | negado | `.lic` ja emitido ainda pode entrar em grace |
| `status='expired'` | `expired` | negado | negado | `.lic` ja emitido ainda depende apenas de assinatura/data local |
| `status='revoked'` | `revoked` | negado | negado | `.lic` ja emitido continua valido offline ate `expiry + grace` |
| `archived_at IS NOT NULL` | fora da operacao normal | negado por invisibilidade | negado por invisibilidade | o daemon nao sabe que foi arquivada |

### 2.2 Transicoes reais observadas

| Evento | Persistencia | Efeito real |
|--------|--------------|-------------|
| Criacao de licenca | `status='active'`, `hardware_id=NULL` | licenca nasce activa e sem bind |
| Primeira activacao valida | fixa `hardware_id` e `activated_at` | emite `.lic` assinado |
| Re-activacao no mesmo hardware | preserva bind | reemite `.lic` |
| Expiracao por data | nao muda automaticamente o `status` | backend passa a tratar como expirada |
| Revogacao administrativa | `status='revoked'`, `revoked_at=NOW()` | backend corta activacao e download |
| Uso offline do `.lic` | sem persistencia adicional | daemon segue com assinatura + fingerprint + data + grace |

---

## 3. Matriz real de cenarios

| Cenario | Comportamento actual provavel | Risco operacional | O que o produto garante hoje | Decisao/documentacao recomendada |
|---------|-------------------------------|-------------------|------------------------------|----------------------------------|
| Licenca activa ainda nao activada | servidor trata como `active` sem bind; activacao valida fixa `hardware_id`; download admin negado | Baixo | primeira activacao fixa bind uma unica vez | manter contrato actual e documentar que download exige bind previo |
| Licenca activa ja activada e `.lic` emitido | reactivacao no mesmo hardware reemite `.lic`; daemon aceita localmente | Baixo | bind exacto + reemissao compativel | manter |
| Licenca expirada no servidor sem `.lic` local | activacao negada; download negado | Baixo | servidor falha fechado online | manter |
| Licenca expirada no servidor com `.lic` local ja emitido | activacao/download negados; daemon aceita ate `expiry + 14 dias` | Medio | expiracao online nao mata imediatamente o `.lic` ja emitido | declarar isto explicitamente em docs e runbook |
| Licenca revogada no servidor sem `.lic` local | activacao negada; download negado | Baixo | revogacao corta uso online futuro | manter |
| Licenca revogada no servidor com `.lic` local ja emitido | servidor bloqueia tudo online; daemon continua a aceitar offline enquanto assinatura/fingerprint/data local permitirem | Alto | revogacao actual nao revoga offline um `.lic` ja distribuido | declarar como limite real, sem maquilhagem |
| Licenca activa com servidor indisponivel | activacao nova falha; `.lic` existente continua local | Medio | operacao offline depende do ficheiro ja emitido | manter e documentar |
| Appliance offline durante o grace | daemon continua em enforce com `expired=1`, `grace=1` | Medio | grace local de `14` dias | manter |
| Appliance offline apos o grace | daemon invalida licenca e cai para monitor-only | Medio | grace termina localmente sem consulta ao servidor | manter |
| Download administrativo de `.lic` para licenca ja expirada | painel responde `409` | Baixo | backend nao reemite `.lic` efectivo-expirado | manter |
| Download administrativo de `.lic` para licenca revogada | painel responde `409` | Baixo | backend nao reemite `.lic` revogado | manter |
| Risco de rebind enquanto existe `.lic` antigo em campo | um eventual rebind emitiria novo `.lic` para novo hardware, mas o `.lic` antigo continuaria valido offline no hardware antigo ate `expiry + grace` | Alto | o sistema actual nao tem revogacao offline para cortar o artefacto antigo | **nao abrir rebind administrativo nesta fase** |

---

## 4. Limite real da expiracao e da revogacao actuais

### 4.1 Limite real da expiracao

O produto garante hoje:

- depois da data, o servidor deixa de activar e deixa de emitir `.lic`;
- o daemon continua a aceitar o `.lic` ja emitido por ate `14` dias;
- depois do grace, o daemon corta enforce localmente.

O produto **nao** garante hoje:

- invalidez imediata do `.lic` exactamente na viragem de expiracao, se o
  ficheiro ja estava no appliance;
- sincronizacao em tempo real entre servidor e appliance para expirar antes do
  grace;
- proteccao contra clock local errado ou adulterado.

### 4.2 Limite real da revogacao

O produto garante hoje:

- revogacao persistida no servidor;
- activacao futura negada;
- download administrativo futuro negado;
- visibilidade administrativa coerente de licenca revogada.

O produto **nao** garante hoje:

- revogacao offline de `.lic` ja emitido;
- invalidacao remota imediata do appliance ja activado;
- corte automatico de enforce local sem remover o `.lic` ou esperar a data.

---

## 5. Validade offline do `.lic` ja emitido

### 5.1 Quando um `.lic` antigo continua operacional offline

Continua operacional quando:

- a assinatura Ed25519 continua valida;
- o `hardware_id` do ficheiro continua a bater com o fingerprint local;
- a data ainda nao passou; ou
- a data passou, mas ainda esta dentro dos `14` dias de grace.

### 5.2 Quando deixa de operar offline

Deixa de operar quando:

- a assinatura falha;
- o hardware deixa de bater;
- o ficheiro nao existe;
- a expiracao passou e o grace local foi esgotado.

### 5.3 Conclusao operacional

- expiracao e revogacao no servidor **nao** sao equivalentes a invalidacao
  offline imediata;
- o `.lic` em campo tem autonomia local limitada pela propria assinatura,
  fingerprint e data.

---

## 6. Avaliacao explicita sobre rebind administrativo

### 6.1 Pergunta da fase

Seria seguro abrir rebind administrativo agora?

### 6.2 Resposta canónica da F3.3

**Nao. Seria perigoso abrir rebind administrativo nesta fase.**

Motivo factual:

- um rebind administrativo resolveria o servidor para um novo hardware;
- mas o `.lic` antigo ja distribuido continuaria valido offline no hardware
  antigo enquanto a assinatura, o fingerprint antigo e a janela
  `expiry + grace` permitissem;
- como nao existe revogacao offline pesada, heartbeat obrigatorio ou formato
  novo de `.lic`, o produto actual nao consegue garantir corte imediato do
  artefacto antigo em campo.

Decisao conservadora oficial:

- **nao implementar rebind administrativo na F3.3**;
- qualquer futura trilha de rebind fica bloqueada ate existir politica
  explicita para o risco do `.lic` antigo ainda valido offline.

---

## 7. Melhoria minima e segura materializada na F3.3

### 7.1 Mudanca

Foi materializada uma unica melhoria tecnica pequena no backend:

- criacao de `license-server/backend/src/license-state.js`;
- centralizacao da derivacao de estado efectivo (`active`, `expired`,
  `revoked`);
- reutilizacao desse helper em:
  - `POST /api/activate`
  - `GET /api/licenses`
  - `GET /api/licenses/:id`
  - `GET /api/licenses/:id/download`
  - `GET /api/customers/:id`
  - `GET /api/dashboard`
  - contagem de licencas activas no arquivo de cliente

### 7.2 Objectivo

- reduzir ambiguidade entre estado persistido e estado efectivo;
- evitar criterios ligeiramente diferentes entre rotas administrativas;
- manter o modelo actual sem mudar schema, payloads nem formato `.lic`.

### 7.3 Impacto e risco

- impacto baixo e localizado;
- sem mudanca de contrato publico de sucesso;
- sem mudanca no algoritmo de fingerprint;
- sem mudanca no daemon;
- sem revogacao offline nova;
- sem mudanca de auth/admin/TLS/CORS/limiter.

### 7.4 Rollback

- reverter o commit da F3.3 restaura o criterio anterior espalhado nas rotas.

---

## 8. Politica conservadora oficial desta fase

1. Revogacao actual vale fortemente para o **servidor**, nao para invalidacao
   offline imediata do `.lic` ja emitido.
2. Expiracao online continua strict no backend.
3. Grace local continua apenas no daemon, por `14` dias.
4. O `.lic` antigo em campo pode continuar operativo offline dentro da sua
   propria janela local.
5. Rebind administrativo continua fora de escopo e bloqueado por risco real.
6. Nenhuma promessa comercial ou operacional deve dizer que a revogacao actual
   corta imediatamente o appliance offline.

---

## 9. Itens fora de escopo nesta subfase

- revogacao offline pesada;
- heartbeat ou chamada home obrigatoria;
- rebind administrativo;
- novo schema amplo de licencas;
- novo formato `.lic`;
- mudanca do algoritmo de assinatura;
- mudanca do algoritmo de fingerprint;
- alteracoes em package/runtime/observabilidade.

---

## 10. Proximos passos seguros dentro da F3

### F3.4

- transformar a matriz desta F3.3 em evidencia repetivel de lab/appliance;
- validar manualmente os cenarios pendentes:
  - expiracao com `.lic` ja emitido;
  - revogacao com `.lic` ja emitido;
  - grace local;
  - renovacao + reactivacao;
  - indisponibilidade do servidor com `.lic` local;
- so depois reavaliar qualquer trilha de renovacao operacional ampliada ou
  rebind futuro.

---

## 11. Objectivo, impacto, risco, teste e rollback deste bloco

- **Objectivo:** explicitar a semantica real de expiracao, revogacao e
  validade offline, sem quebrar compatibilidade.
- **Impacto:** documental forte; tecnico pequeno e localizado no backend.
- **Risco:** baixo para o codigo alterado; alto apenas para o risco ja
  existente e agora declarado do `.lic` antigo continuar valido offline.
- **Teste minimo:** `node --check` nos ficheiros JS alterados, diff objectivo
  e revisao cruzada das docs canónicas.
- **Rollback:** `git revert <commit-da-f3.3>` ou restaurar os ficheiros
  alterados desta subfase.
