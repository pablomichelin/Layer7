# ADR-0025 — Entitlements de add-on: Identity + MITM (SKU X/Y)

**Estado:** Proposto (rev. `c` — contrato técnico de parse + precedência check-in)  
**Data:** 2026-08-05  
**Decisores:** Operador (GO aceitação no passo 20.2)  
**Plano:** [`../02-roadmap/plano-identity-mitm-addon.md`](../02-roadmap/plano-identity-mitm-addon.md)  
**Arranque:** [`../00-overview/START-HERE-identity-mitm.md`](../00-overview/START-HERE-identity-mitm.md)

---

## Contexto

- O campo `features` já existe no `.lic`, na DB do license-server e em `l7_license_info`, mas na prática usa-se `"full"` **sem gate de módulo**.
- O produto quer comercializar **licença X (base)** e **licença Y (add-on)** para Identity e/ou MITM.
- Add-on no **mesmo** pacote `pfSense-pkg-layer7` (não segundo `.pkg` nesta trilha).

---

## Decisão (proposta)

1. Tratar `features` como **lista CSV** de tokens normalizados em minúsculas:  
   `base`, `identity`, `mitm` (extensível depois).
2. **Standard (X):** `base` — capacidades do produto em `1.9.8`.
3. **Add-on Identity:** requer token `identity`.
4. **Add-on MITM:** requer token `mitm` (mesmo que MITM esteja DEFER na implementação — o token só activa quando houver código + spike GO).
5. Combinações válidas: `base`, `base,identity`, `base,mitm`, `base,identity,mitm`.
6. **Autoridade:** o **daemon** recusa activar módulos sem token; a GUI apenas reflecte.
7. **Um pacote:** código pode estar presente; runtime gated.
8. **Transição de legado** — escolher no GO 20.2:

### Opção T1 (recomendada)

| Valor legado | Interpretação |
|--------------|---------------|
| `full`, vazio, desconhecido antigo | `base` apenas |
| Novos tokens | só se emitidos explicitamente |

Upsell claro; clientes Identity/MITM precisam reemissão.

### Opção T2 (generosa)

| Valor legado | Interpretação |
|--------------|---------------|
| `full` | `base,identity,mitm` |
| Outros | conforme CSV |

**Default de proposta até GO:** **T1**.

### Contrato técnico de parse (rev. `c` — obrigatório em 20.3 / GI1)

O daemon baseline guarda `features` em **`char features[64]`**
(`license.h`); o license-server e o parse devem respeitar este contrato:

| # | Regra | Consequência |
|---|-------|--------------|
| P1 | CSV máximo **63 bytes úteis** | License-server **valida na emissão** e recusa payload maior; daemon trunca com log `license_features_truncated` (nunca overflow) |
| P2 | Normalização | tokens `trim` + minúsculas antes de comparar |
| P3 | Tokens desconhecidos | **Ignorados** (forward-compatible): pacote antigo com `.lic` novo mantém base a funcionar |
| P4 | Erro de parse / campo ausente | Tratar como **`base` apenas** — *fail-closed no add-on, fail-open no base* (produto base nunca cai por causa do campo `features`) |
| P5 | Grace period / expiry | Comportamento actual de `valid/grace` **inalterado**; entitlements add-on seguem a mesma validade da licença (sem grace especial próprio) |
| P6 | Testes C obrigatórios | parse normal, truncamento, token desconhecido, CSV vazio, espaços, maiúsculas, duplicados |

Se no futuro forem precisos mais tokens do que cabem em 63 bytes, aumentar o
buffer é mudança de contrato: exige emenda a este ADR + verificação de compat
da struct `l7_license_info` em todos os consumidores.

### Check-in vs artefacto `.lic` (rev. `b`, precisada na rev. `c`)

- Remoção comercial de `identity`/`mitm` deve **desligar módulos em runtime** (alinhar ADR-0021).
- Reemissão de `.lic` continua o caminho canónico para mudança permanente assinada.
- **Precedência canónica (decidida aqui, não deixada para IM1):**

| Situação | Entitlements efectivos |
|----------|------------------------|
| Check-in recente OK (dentro da janela ADR-0021) | **Interseção** `.lic` ∩ resposta do check-in — o check-in pode **retirar** entitlements, nunca acrescentar além do `.lic` assinado |
| Check-in indisponível (offline dentro da tolerância ADR-0021) | Payload do `.lic` em disco (comportamento offline actual) |
| Check-in devolve revogação | Regras ADR-0021 (licença toda, não só add-on) |

- Racional: o `.lic` assinado é o tecto de confiança; o check-in é canal de
  **redução** rápida (downgrade comercial, abuso). Isto elimina a ambiguidade
  que estava adiada para IM1.
- GI1 testa: retirar `identity` via check-in → módulo desliga sem reinstalar
  `.lic`.

---

## Consequências

- License-server admin deve permitir editar tokens com validação.
- Testes de parse e gate obrigatórios (GI1).
- `MANUAL-USO-LICENCAS.md` na IM1.

---

## Alternativas rejeitadas

| Alternativa | Motivo |
|-------------|--------|
| Segundo pacote `.pkg` só Identity | Complexidade de deps/updater |
| Só flag na GUI sem `.lic` | Bypass trivial |
| `features` string livre sem gramática | Ambiguidade operacional |

---

## Rollback

- Reemitir `.lic` com `base` / check-in sem tokens add-on.
- Pacote anterior ignora tokens novos com segurança (forward-compatible parse).

---

## Referências

- [`../10-license-server/MANUAL-USO-LICENCAS.md`](../10-license-server/MANUAL-USO-LICENCAS.md)
- [`../01-architecture/f3-arquitetura-licenciamento-ativacao.md`](../01-architecture/f3-arquitetura-licenciamento-ativacao.md)
- ADR-0021 (check-in)
