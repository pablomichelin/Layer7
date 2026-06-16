# ADR-0014 — Enforcement escopado por politica (Caminho B / E0)

## Status

Aceito

## Contexto

O Layer7 classifica trafego e decide politicas **por cliente** (IP, CIDR, grupo,
dispositivo via `device_ips`), excepcao e prioridade. A imposicao PF actual usa
a tabela global `layer7_block_dst`: um IP bloqueado para um cliente afecta todos
os clientes da LAN.

O **Caminho A** (`1.8.11_23`) entregou inventario de dispositivos, politicas por
MAC→IP, SNI opt-in e UX tipo UDM, mas **nao alterou** a semantica PF de
enforcement. O gap foi confirmado por analise estatica em `2026-06-15` (ver
[`docs/09-blocking/plano-enforcement-100-porcento.md`](../09-blocking/plano-enforcement-100-porcento.md)).

Blacklists UT1 ja usam o modelo correcto: `from {cidr} to <layer7_bld_N>`.

## Problema

Como evoluir para enforcement **escopado por politica** (site=destino por origem;
app=quarentena de origem) sem regressao nas instalacoes existentes e sem MITM?

## Decisao

### 1. Dois modelos de enforcement via flag explicita

Novo campo JSON `layer7.enforcement_model`:

| Valor | Semantica |
|-------|-----------|
| `legacy_global` | Comportamento actual: tabela unica `layer7_block_dst` |
| `scoped_hybrid` | Novo modelo (E2–E8): `layer7_pdst_N` / `layer7_psrc_N` por politica |

**Default:** `legacy_global` ate validacao completa no appliance (E8 muda o
default para `scoped_hybrid`).

### 2. Modelo hibrido escopado (implementacao E2–E8)

| Tipo de match | Enforcement PF | Exemplo |
|---------------|------------------|---------|
| Site / host / SNI | `from {cliente} to <layer7_pdst_N>` | YouTube so para 10.0.0.10 |
| App / categoria nDPI (sem host) | `from <layer7_psrc_N> to !<localsubnets>` | BitTorrent: quarentena do cliente |
| Politica sem origem | Global **so** com `scope_global: true` (E4) | Opt-in explicito |
| Blacklists UT1 | Manter `layer7_bld_N` | Sem regressao |

### 3. E0 nao altera runtime

O bloco E0 materializa apenas:

- ADR e backlog BG-045..BG-052;
- parse do campo no daemon (`config_parse`);
- selector na GUI Settings (default `legacy_global`, aviso claro);
- documentacao (CORTEX, plano Caminho B).

Nenhuma regra PF nem logica de `main.c` muda ate E2/E3.

### 4. Transicao e rollback

Operador pode voltar a `legacy_global` a qualquer momento; flush de tabelas
scoped fica para E3/E8. Instalacoes existentes sem o campo continuam em
`legacy_global` implicito.

## Alternativas consideradas

### A. Mudar default para scoped imediatamente

Rejeitada: risco de regressao em producao antes dos gates two-client (E3/E7).

### B. Manter global e documentar como limitacao permanente

Rejeitada: contradiz promessa UX Caminho A (politicas por dispositivo).

### C. Inline/divert MITM

Rejeitada: fora do escopo V1; Caminho B reutiliza PF passivo existente.

## Consequencias

- Caminho B ganha fundacao reversivel sem mudar comportamento default;
- E1–E8 implementam decisao unificada e PF escopado sobre esta flag;
- docs e GUI passam a expor o gap Caminho A vs enforcement real.

## Riscos / limitacoes honestas

- CDN, ECH, DoH hardcoded, IPv6 e delay nDPI continuam limites (E6/E8 doc);
- licenca invalida continua a desactivar enforce ao vivo (nao e bug Caminho B);
- `device_ips` stale exige resync operacional (documentado em E0).

## Referencias

- Plano Caminho B: [`docs/09-blocking/plano-enforcement-100-porcento.md`](../09-blocking/plano-enforcement-100-porcento.md)
- Plano mestre historico (V1): [`docs/09-blocking/blocking-master-plan.md`](../09-blocking/blocking-master-plan.md)
- PF enforcement actual: [`docs/05-daemon/pf-enforcement.md`](../05-daemon/pf-enforcement.md)
