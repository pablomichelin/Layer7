# ADR-0030 — Postura anti-tamper do `layer7d` e remoção do modo dev de produção

**Estado:** Aceito  
**Data:** 2026-08-10  
**Aceite:** `2026-08-10` — passo **`30.1b`**; GO humano («concordo com tudo» / recomendações do plano); ficha [`../09-blocking/decisoes-humanas-30.1.md`](../09-blocking/decisoes-humanas-30.1.md)  
**Trilha:** Anti-pirataria / Anti-tamper (AP0 → AP1)  
**Plano:** [`../02-roadmap/plano-antipirataria-anti-tamper.md`](../02-roadmap/plano-antipirataria-anti-tamper.md)  
**Diagnóstico:** [`../01-architecture/modelo-ameacas-antipirataria.md`](../01-architecture/modelo-ameacas-antipirataria.md) (A-01, A-02 parcial, A-07)  
**Gates:** GA2 (passos `30.4`, `30.5`, `30.7`)  
**Backlog:** BG-114, BG-115, BG-120  
**RR obrigatórios neste texto:** R-A, R-G, RR-3

---

## Contexto

O modelo de ameaças aceite (`2026-08-10`) documenta que o caminho mais curto
para bypass total é o modo de desenvolvimento embutido no binário de produção:

- se a pubkey Ed25519 embutida for all-zeros, `is_dev_key()` salta a verificação
  e assume licença válida permanente (achado **A-01**);
- o binário de release **não** é strippado — símbolos de licença aparecem em
  `nm`/Ghidra;
- a decisão de enforce concentra-se num ponto único (**A-02** — tratado em
  AP4/`30.16`, fora do núcleo deste ADR);
- a GUI pode desbloquear UX de add-ons a partir de ficheiros forjáveis (**A-07**).

A criptografia das licenças (Ed25519, binding 1:1) está correcta. O problema é
higiene do artefacto e honestidade sobre o que um binário root-controlável
consegue garantir.

---

## Decisão

### 1. Remover o modo dev do binário de produção (`30.4`)

1. `is_dev_key()` e o bypass associado existem **apenas** sob `#ifdef L7_DEV_BUILD`.
2. A flag **não** é definida no `Makefile` do port de produção.
3. Pubkey inválida/all-zeros num build de produção ⇒ licença **inválida**
   (monitor), nunca válida.

### 2. Strip e endurecimento de build (`30.5`)

1. Strip no `INSTALL_PROGRAM` do port; avaliar `-fvisibility=hidden` onde seguro.
2. Objectivo: remover o mapa de símbolos que aponta às funções de licença.
3. **Não** introduzir ofuscação, packers, VM de código ou anti-debug (**R-G**).

### 3. Entitlements da GUI derivados de material assinado (`30.7`)

1. O estado consumido por `layer7_entitlements()` deriva de material **já
   assinado pelo servidor** (`.lic` e/ou resposta de check-in).
2. O daemon **não** assina localmente com chave própria embutida (seria circular
   sob root — **R-A**).
3. Coordenação obrigatória com ADR-0025; bloco separado da trilha MITM (**R-I**).

### 4. Fora de escopo por deliberação

Ofuscação pesada · packers · anti-debug · fail-closed por rede · kill-switch
remoto · CRL offline (já rejeitada na ADR-0021) · telemetria.

### 5. Limite teórico e riscos residuais (obrigatório)

| Regra / RR | Declaração |
|------------|------------|
| **R-A** | Root no appliance **pode** contornar qualquer verificação local. Este ADR encarece e remove o bypass *trivial*; não torna o produto “impossível de crackar”. |
| **R-G** | Ofuscação/anti-debug ficam fora de escopo por decisão, não por esquecimento. |
| **RR-3** | Remover `is_dev_key` só afecta **builds futuros**. Os `.pkg` já publicados (incl. `1.9.48` e anteriores) com o caminho de bypass intacto permanecem descarregáveis. Inventário em `30.3`; decisão comercial n.º 8 do §5 (despublicar/limitar) em `30.19`. A desvalorização no tempo vem de AP2, não deste ADR. |

---

## Consequências

### Positivas

- Custo de ataque sobe de minutos (patch de 32 bytes) para horas com ferramentas.
- Add-ons na GUI deixam de desbloquear-se com ficheiros forjados.
- Postura alinhada a R-A / R-G sem overclaim.

### Negativas / riscos

- Core dumps de produção menos legíveis após strip (limite aceite, a registar no
  gate GA2.11).
- Releases antigas continuam exploráveis (**RR-3**) até decisão comercial.
- `30.7` toca superfície partilhada com Identity+MITM — risco de regressão se
  misturado (proibido por R-I).

---

## Alternativas consideradas

| Alternativa | Rejeitada porque |
|-------------|------------------|
| Manter `is_dev_key` “só em lab” no mesmo binário | O artefacto público é o de produção; lab não justifica bypass no `.pkg` |
| Ofuscação / packer | **R-G** — custo/risco num daemon root em firewall |
| Declarar “crack impossível” | Viola **R-A** e a honestidade da trilha |

---

## Implementação prevista

- Passos `30.4` → `30.5` → `30.7` (após `30.2` FECHADO — bloqueio duro em
  `license.c`).
- Gates validam o **`.pkg` publicado**, não só o git (**R-H**).
- Rollback: `.pkg` anterior.

## Referências

- Plano §0.0 (R-A…R-L), §0.1 (RR-3), §2 AP1
- ADR-0025 (entitlements Identity/MITM)
- Ficha [`../09-blocking/decisoes-humanas-30.1.md`](../09-blocking/decisoes-humanas-30.1.md) (decisão 8 / RR-3)
