# ADR-0021 — Check-in online e revogação remota de licença

## Status

**Proposto** (`2026-08-04`) — aguarda implementação (BG-077).

## Contexto

O modelo actual (F3.3) valida o `.lic` **apenas offline**:

- assinatura Ed25519;
- `hardware_id` local;
- data `expiry` + grace de 14 dias.

O daemon revalida o ficheiro local de **hora em hora** (`L7_LIC_CHECK_INTERVAL = 3600`),
mas **nunca** consulta o license server para `revoked`, `expired` efectivo ou
cancelamento comercial.

Consequência operacional (validada em S09):

- revogar no painel/API corta activações futuras;
- o appliance com `.lic` já instalado **continua em enforce** até `expiry + grace`.

Para contratos anuais com cancelamento antecipado, isto é **inaceitável** para o
negócio.

## Problema

Como garantir que **revogação administrativa** (cancelamento, incumprimento,
fraude) produz efeito no appliance **sem depender** de acesso físico ao pfSense
do cliente, mantendo operação previsível em falhas de rede?

## Decisão (proposta)

### 1. Novo contrato online — `POST /api/license/check-in`

Endpoint **público** (mesma superfície que `/api/activate`), rate-limited:

**Request:**

```json
{
  "key": "<license_key>",
  "hardware_id": "<fingerprint>"
}
```

**Response `200` (licença operacional):**

```json
{
  "status": "active",
  "expiry": "2027-12-31",
  "customer": "Cliente",
  "check_in_interval_hours": 168,
  "max_offline_hours": 336
}
```

**Response `409` (não operar):**

```json
{
  "status": "revoked",
  "error": "Licenca revogada."
}
```

Estados efectivos espelham `license-state.js`: `active`, `expired`, `revoked`.
Respostas `404` para chave inexistente; `409` para hardware mismatch.

### 2. Comportamento do daemon (`layer7d`)

| Evento | Acção |
|--------|--------|
| Check-in `200` + `status=active` | Mantém/reafirma licença local; regista `last_check_in_ok` |
| Check-in `409` revoked | **Invalida imediatamente** — remove `.lic`, enforce → monitor-only |
| Check-in `409` expired | Mesmo que revogação para enforce (sem grace online) |
| Rede indisponível | **Degradação controlada** (ver §3) |
| `.lic` local inválido por assinatura/hw/data | Continua a regra actual (independente de check-in) |

**Intervalo de check-in (defaults propostos):**

| Parâmetro | Default | Notas |
|-----------|---------|-------|
| `check_in_interval` | **168 h (7 dias)** | Alinhado ao requisito comercial discutido |
| `max_offline_without_check_in` | **336 h (14 dias)** | Após isto: monitor-only mesmo com `.lic` assinado |
| Intervalo mínimo configurável | 24 h | Para clientes que exigem revogação mais rápida |

O servidor pode devolver `check_in_interval_hours` / `max_offline_hours` por
licença (override futuro); o daemon usa o menor entre default embutido e valor
do servidor.

### 3. Falha de rede (fail-safe comercial)

Ordem de precedência local:

1. **Revogação confirmada online** → corte imediato.
2. **Dentro de `max_offline_without_check_in`** desde último check-in OK →
   continua com `.lic` local (disponibilidade).
3. **Ultrapassado `max_offline_without_check_in`** → monitor-only + aviso operacional.

Isto separa **grace de expiração por data** (14 dias após `expiry` no `.lic`)
de **grace de conectividade** (tempo máximo sem falar com o servidor).

### 4. O que não muda nesta ADR

- Formato do `.lic` (sem campo `revoked` embutido).
- Activacao inicial (`/api/activate`).
- Rebind administrativo (continua bloqueado).

### 5. Superfície administrativa

- Revogar no painel continua a ser o acto canónico de cancelamento.
- `admin_audit_log` regista revogação; `activations_log` ou tabela nova
  `check_ins_log` regista check-ins (decisão de schema na implementação).

## Alternativas consideradas

| Alternativa | Rejeitada porque |
|-------------|------------------|
| CRL assinada offline | mais complexa; exige rotação e distribuição de listas |
| TTL curto no `.lic` + re-download obrigatório | quebra operação offline legítima |
| Só confiar em `expiry` no contrato | não cobre cancelamento a meio do período |
| Check-in só semanal sem teto offline | revogação demoraria até 7 dias |

## Consequências

### Positivas

- Cancelamento comercial passa a ter efeito remoto verificável.
- Operador pode auditar último check-in por licença.
- Cenário S09 passa a ter semântica de produto alinhada ao negócio.

### Negativas / riscos

- Appliance precisa de conectividade periódica HTTPS ao license server.
- Ambientes air-gapped exigem excepção contratual ou intervalo/offline máximos
  negociados.
- Novo vector de abuso → rate limit obrigatório no endpoint.

## Implementação (BG-077)

Componentes:

1. `license-server`: rota `check-in`, logs, testes.
2. `layer7d`: scheduler, persistência `last_check_in_ok`, invalidação.
3. GUI (opcional fase 2): último check-in em Definições.
4. Cenários F3: **S09b** / **S14** (revogação + check-in ≤ intervalo).
5. Docs: `MANUAL-USO-LICENCAS.md`, matriz de testes.

**Gate mínimo antes de declarar pronto:**

- revogar no servidor → appliance com rede → enforce cai em ≤ `check_in_interval`;
- offline prolongado > `max_offline` → monitor-only;
- regressão: activação, expiração local, grace por data intactos.

## Rollback

- Feature flag `layer7.check_in_enabled` (default OFF na primeira release).
- Desactivar flag restaura comportamento F3.3 puro (só validação local).

## Referências

- `docs/01-architecture/f3-expiracao-revogacao-grace.md`
- `docs/01-architecture/f3-plano-check-in-online-revogacao-remota.md`
- BG-077 em `docs/02-roadmap/backlog.md`
- Evidência S09: `docs/tests/evidence/20260804T232600Z-ondaC-dr05/S09/`
