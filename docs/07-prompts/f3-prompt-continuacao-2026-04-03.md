# Prompt de Continuacao — F3 Layer7 (2026-04-03)

## Projecto

Layer7 para pfSense CE — Systemup Solucao em Tecnologia
Repo local: /Users/pablomichelin/Documents/Layer 7

## Regras obrigatorias

Ler SEMPRE nesta ordem antes de agir:

1. CORTEX.md
2. docs/README.md
3. docs/02-roadmap/roadmap.md
4. docs/02-roadmap/backlog.md
5. docs/02-roadmap/checklist-mestre.md
6. docs/00-overview/document-classification.md
7. docs/00-overview/document-equivalence-map.md

Trabalhar de forma directa, tecnica e conservadora.

## Estado canonico comprovado em 2026-04-03

### Fases

- F0/F1/F2 concluidas — NAO reabrir
- F3 aberta — unica fase activa
- F4/F5/F6/F7 — NAO abrir
- Versao segura do pacote: 1.8.3

### Infraestrutura real (NAO subir Docker local)

| Componente | IP / Host | Estado |
|------------|-----------|--------|
| pfSense appliance | 192.168.100.254 | pfSense Plus 25.11.1, Layer7 instalado, daemon running; ICMP alcancavel a partir da LAN do operador (confirmado 2026-04-03); DR-05 exige SSH ou shell equivalente no appliance |
| License server live | 192.168.100.244 | stack Docker com 4 containers, acessivel via SSH |
| License server publico | https://license.systemup.inf.br | operacional |
| PostgreSQL live | container layer7-license-db no .244 | base layer7_license, user layer7 |
| Builder FreeBSD | 192.168.100.12 | FreeBSD 15.0-RELEASE |

### Auth do live

- Login: pablo@systemup.inf.br / P@blo.147
- POST /api/auth/login => 200 OK com JWT
- GET /api/licenses com Authorization: Bearer <jwt> => 200 OK
- GET /api/auth/session => 404 (endpoint nao existe no live — drift F2.2)
- Cookie jar => 401 (live usa JWT, nao sessao stateful)

### Inventario real obtido do live em 2026-04-03

| ID | Cliente | Status | Expiry | hardware_id |
|----|---------|--------|--------|-------------|
| 8 | Compasi | active | 2026-12-31 | 8c93d3d3... |
| 7 | Systemup | active | 2033-10-24 | e31560f5... |
| 6 | Lasalle Agro | revoked | 2026-04-30 | cdc22935... |
| 5 | Lasalle | active (expirada por data) | 2026-03-31 | e31560f5... |

Notas:
- IDs 7 e 5 partilham o mesmo hardware_id
- ID 5 esta expirada por data mas status continua active (modelo hibrido F3.3)
- ID 6 esta revogada com revoked_at preenchido

### Drifts

| ID | Assunto | Estado | Escopo |
|----|---------|--------|--------|
| DR-01 | Schema live sem admin_sessions/audit_log/login_guards | aberto, nao bloqueante para F3 | F2 |
| DR-02 | activate live responde 403 onde repo usa 409 | RESOLVIDO — drift cosmetico, logica de negocio correcta | F3 |
| DR-03 | Auth live usa JWT em vez de sessao stateful | parcialmente resolvido (Bearer funciona) | F2.2 |
| DR-04 | Inventario LIC-A a LIC-F | RESOLVIDO | — |
| DR-05 | Cenarios locais do appliance incompletos | aberto, unico blocker F3 | F3 |
| DR-06 | CORS wildcard no live | aberto, nao bloqueante para F3 | F2.3 |
| DR-07 | Proveniencia exacta do deploy nao demonstrada | aberto, nao bloqueante para F3 | F7 |

Evidencia DR-02 (obtida em 2026-04-03):

| Cenario | Licenca | Repo | Live | Logica |
|---------|---------|------|------|--------|
| hw diferente do binding | ID 8 (Compasi) | 409 | 403 | correcta (rejeita) |
| licenca revogada | ID 6 (Lasalle Agro) | 409 | 403 | correcta (rejeita) |
| licenca expirada | ID 5 (Lasalle) | 409 | 403 | correcta (rejeita) |
| reactivacao legitima | ID 7 (Systemup) | 200 | 200 | correcta (aceita) |

### Codigo local alterado (shim Bearer — ainda sem commit)

- license-server/backend/src/session.js — suporte a Bearer em paralelo
- license-server/backend/src/auth.js — sem mudanca funcional
- license-server/backend/src/routes/auth.js — login devolve Bearer token
- license-server/frontend/src/api.js — envia Authorization: Bearer
- license-server/frontend/src/auth.jsx — sincroniza token transitório apenas em memoria

Nota: o live ja aceita Bearer nativamente. O shim local e util se/quando
o codigo local for deployado, mas nao e necessario para a campanha actual.

### Git

- Branch: main...origin/main [ahead 23+]
- Sem commit/push nesta rodada

## O que falta para fechar a F3

### 1. DR-02 — RESOLVIDO

Testado em 2026-04-03 no live. O live usa `403` onde o repo usa `409` para
todos os cenarios de rejeicao (hw diferente, revogada, expirada), mas a
logica de negocio esta correcta (rejeita o que deve rejeitar e aceita
reactivacao legitima com `200`). Drift cosmetico que sera alinhado quando
o live for actualizado com o codigo do repo. NAO bloqueia a F3.

### 2. Executar cenarios do appliance (DR-05) — PENDENTE (roteiro)

**O que e:** fechar a metade "appliance" da campanha F3 com evidencia real no
`192.168.100.254`, sem Docker local. O detalhe canónico dos cenarios esta em
[`docs/01-architecture/f3-validacao-manual-evidencias.md`](../01-architecture/f3-validacao-manual-evidencias.md);
a estrutura de pastas e estados em
[`docs/01-architecture/f3-pack-operacional-validacao.md`](../01-architecture/f3-pack-operacional-validacao.md).

**Pre-requisitos operacionais**

- Acesso SSH ao pfSense (ou consola) com permissao para `layer7d`, ficheiros
  em `/usr/local/etc/layer7.lic`, `/tmp/layer7-stats.json`, `service layer7d`.
- Definir um `run_id` novo (ex.: `20260403T...Z-appliance254`).
- Snapshot da VM **antes** de cenarios de relogio (S12) ou mudanca de
  NIC/UUID/clone (S13) — risco alto; rollback = restore do snapshot.
- Separar mentalmente: testes que so mexem no live (ja feitos) vs testes que
  mexem no appliance e podem afectar producao de lab.

**Evidencia SSH ao appliance (2026-04-03)**

- `ssh` alcanca o host; o servidor anuncia
  `Permission denied (publickey,password,keyboard-interactive)` para
  `admin@192.168.100.254` e `root@192.168.100.254` em modo nao-interactivo
  (`BatchMode=yes`) — **normal** quando ainda nao ha chave autorizada no
  pfSense para esta maquina.
- A matriz antiga do repo as vezes exemplifica `ssh root@<PFSENSE_IP>`; neste
  lab ambos os utilizadores pedem o mesmo tipo de credencial; usar o login que
  o teu pfSense tiver configurado para shell (muitas vezes `admin`).
- Para um agente/CI conseguir correr `$PF_SSH` sem password no chat: em
  **System > Advanced > Admin Access** (e/ou chaves SSH do utilizador admin no
  pfSense), instalar a **chave publica** da maquina que executa os testes
  (ex.: conteudo de `~/.ssh/id_ed25519.pub` no Mac do operador). Depois:
  `export PF_SSH='ssh -i ~/.ssh/id_ed25519 admin@192.168.100.254'`.
- Alternativa segura: corre os comandos `$PF_SSH '...'` **localmente** num
  terminal interactivo (password uma vez) e guarda os outputs no pack de
  evidencias; nao coloques passwords no repositorio nem no prompt.

**Convencao de comandos (ajustar host/user)**

```bash
# Exemplo: substituir por o teu alvo real (ssh, usuario, chave)
export PF_SSH='ssh -o StrictHostKeyChecking=accept-new admin@192.168.100.254'

$PF_SSH 'service layer7d status || true'
$PF_SSH 'layer7d --fingerprint'
$PF_SSH '/bin/kill -USR1 "$(cat /var/run/layer7d.pid)" && cat /tmp/layer7-stats.json'
```

**Mapa DR-05 -> cenarios F3.6 (prioridade minima sensata)**

| Foco DR-05 | Cenario F3.6 | O que prova |
|------------|--------------|-------------|
| offline/online + estado do daemon | S07, S08, S09 (trechos appliance) | activacao, grace, revogacao online vs `.lic` local |
| relogio / grace local | S12 | `license_grace`, transicao para monitor-only apos 14 dias |
| NIC / UUID / clone / restore | S13 | `kern.hostuuid`, `layer7d --fingerprint` antes/depois, stats JSON |
| snapshot/restore | transversal | recuperacao segura apos S12/S13; nao e cenario isolado na matriz |

Executar na ordem segura: cenarios que **nao** mudam relogio nem hardware
primeiro; depois S12 ou S13 apenas com snapshot e janela de manutencao.

**Evidencia minima por execucao**

- Guardar outputs em `${TMPDIR:-/tmp}/layer7-f3-evidence/<RUN_ID>/` com
  subpastas `S07`, `S08`, ... conforme pack F3.7.
- Para cada cenario: estado `PASS` / `FAIL` / `INCONCLUSIVE` / `BLOCKED`,
  timestamp, e diff claro entre esperado (doc F3.6) vs observado.
- Se o live devolver `403` em vez de `409` em chamadas curl durante S09/S13,
  tratar como **drift cosmetico ja conhecido (DR-02)**; validar a **logica**
  (rejeita vs aceita), nao so o codigo HTTP.

**Apos concluir DR-05 (checklist documental)**

1. Actualizar
   [`docs/01-architecture/f3-11-drift-registry.md`](../01-architecture/f3-11-drift-registry.md)
   — DR-05 para resolvido ou detalhar o que ficou `BLOCKED` e porque.
2. Actualizar
   [`docs/01-architecture/f3-fecho-operacional-restante.md`](../01-architecture/f3-fecho-operacional-restante.md),
   scorecard, execution master register, `f3-11-start-here`, `CORTEX.md`
   (trilha F3).
3. Preencher
   [`docs/tests/templates/f3-validation-campaign-report.md`](../../tests/templates/f3-validation-campaign-report.md)
   com o veredito binario.

Este e o UNICO blocker restante da F3.

### 3. Decidir fecho da F3

Depois de 2, preencher relatorio final e decidir:
- F3 pode fechar; ou
- F3 nao pode fechar (e porque).

## Regras de trabalho

- NAO criar documentos novos de governanca/burocracia
- NAO exigir "5/5 insumos entregue valido" — os insumos ja estao disponiveis
- NAO tratar drifts da F2 como blockers da F3
- NAO subir Docker local — infraestrutura real ja existe
- NAO declarar F3 fechada sem evidencia real
- Separar SEMPRE: appliance vs host live vs banco vs auth vs inventario
- Manter docs actualizadas no mesmo bloco da alteracao
- Blocos pequenos, claros, auditaveis e reversiveis
- DR-05: seguir matriz F3.6 + pack F3.7; nao improvisar cenarios fora do doc
- Nao commitar credenciais em markdown; usar variaveis de ambiente ou runbook
  privado fora do repo
