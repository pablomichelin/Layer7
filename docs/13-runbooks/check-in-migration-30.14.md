# Runbook — Check-in default ON e migração (`30.14` / BG-118)

**Estado:** activo com GO humano `2026-08-12`  
**ADR:** [ADR-0032](../03-adr/ADR-0032-check-in-obrigatorio-e-assinado.md) (emenda ADR-0021)  
**Gates:** GA5.7–5.11  
**GO literal:** ficha [`../09-blocking/decisoes-humanas-30.1.md`](../09-blocking/decisoes-humanas-30.1.md) (addendum `30.14`)

---

## 0. Objectivo / impacto / risco / teste / rollback

| Campo | Valor |
|-------|--------|
| **Objectivo** | Novas instalações com `check_in_enabled: true`; upgrade sem regressão; caminho isolado documentado; **N3** intacto. |
| **Impacto** | Revogação remota passa a aplicar em instalações novas/opt-in; dependência periódica de HTTPS ao license server. |
| **Risco** | Alto em suporte se upgrade forçasse ON — **mitigado**: upgrade **não** altera valor já gravado. |
| **Teste** | Sample/bare = true; config existente com `false`/ausente permanece OFF; rede down ≠ invalidate. |
| **Rollback** | GUI/JSON `check_in_enabled: false` ou `.pkg` anterior. |

---

## 1. Política (canónica)

| Caso | `check_in_enabled` | Notas |
|------|--------------------|-------|
| Instalação **nova** (sem `layer7.json`) | **`true`** (via `layer7.json.sample`) | Default anti-pirataria |
| **Upgrade** com chave já presente | **Preserva** `true` ou `false` | Não regressivo |
| Upgrade com chave **ausente** | Continua **OFF** no daemon | Opt-in anunciado — não injecta `true` |
| Appliance **isolado / air-gap** | **`false`** explícito | Opt-out **R-J**; documentar no ticket |

**N3 / R-C:** falha de rede, timeout ou resposta não assinada ⇒ check-in falhou;
**não** invalida `.lic` nem reduz enforce enquanto dentro de `max_offline_hours`
(só resposta assinada `revoked`/`expired`, ou teto offline com check-in **activo**,
altera o estado — ADR-0021).

---

## 2. Operador — activar / desactivar

### GUI

Services → Layer 7 → Sistema / Licença → **Check-in periódico** → Guardar check-in.

### JSON

```json
"check_in_enabled": true
```

ou, para isolado:

```json
"check_in_enabled": false
```

Ficheiro: `/usr/local/etc/layer7.json`. Depois: `service layer7d onerestart`.

### Forçar um check-in (lab)

```sh
/usr/local/sbin/layer7d --check-in
```

(CLI ignora a flag de config; requer servidor dual-mode `30.13` e licença
activa. O scheduler periódico só corre com `check_in_enabled: true`.)

---

## 3. Appliance isolado (caminho de excepção)

1. Confirmar necessidade air-gap (sem HTTPS estável ao license server).
2. Definir `check_in_enabled: false` (GUI ou JSON).
3. Registar no ticket de suporte: motivo, data, responsável.
4. Aceitar limite: **revogação remota não corta** esse appliance até expiry+grace
   offline do `.lic` (A-04 residual consciente — R-J).
5. Quando voltar a ter rede fiável: reactivar check-in e validar check-in OK.

**Proibido:** falhar-fechado (derrubar enforce só porque a rede caiu).

---

## 4. Migração anunciada (base instalada)

1. Comunicação ops: novas = ON; existentes preservam OFF até opt-in.
2. Priorizar clientes com risco de cancelamento / T2.
3. Opt-in: activar na GUI; confirmar `license_check_in_enabled: true` no status JSON
   do daemon (se exposto) ou leitura do JSON.
4. Isolados: seguir §3 — não forçar.

---

## 5. Suporte — sintomas

| Sintoma | Acção |
|---------|--------|
| Check-in falha mas enforce OK | Esperado (N3) dentro de `max_offline_hours` |
| Licença invalidada após offline longo com check-in ON | Esperado (ADR-0021 teto); restaurar rede + check-in OK ou reactivar licença |
| Isolado a cair por offline | Verificar se check-in ficou ON por engano → pôr `false` |
| Servidor antigo sem envelope | Deploy license-server `30.13` **antes** de clientes novos com check-in ON |

---

## 6. Verificação mínima (lab / builder)

```sh
# Sample novo = true
grep -n 'check_in_enabled' package/pfSense-pkg-layer7/files/usr/local/etc/layer7.json.sample

# Teste unitário PHP da política + cadeado P2-9 (install não migra)
php tests/functional/test_check_in_default_30.14.php
```

---

## 7. P2-9 / BG-154 — upgrade **não** injecta `true` (`2026-08-14`)

**AVALIADO neste bloco** (opção A). O GO `30.14` e a ADR-0032
permanecem: novas = ON; existentes = opt-in anunciado; isolados =
`false` explícito (R-J). **Não** é correção injectar `true` no
upgrade — isso invertaria o GO `30.14` e arriscaria air-gap.

| Caminho | Contrato |
|---------|----------|
| `layer7_load_or_default()` | Devolve o JSON existente; **não** chama a migration |
| `pkg-install.in` POST-INSTALL | `load_or_default` + `save_json`; **não** chama `layer7_check_in_apply_migration_policy` |
| Chave ausente em config existente | Efectivo **OFF** (`layer7_check_in_effective_enabled`) |
| Injectar `true` / migração forçada | **Fora** — só com GO novo que emende o `30.14` |

Cadeado: `tests/functional/test_check_in_default_30.14.php` (GA5.8 +
asserts do `pkg-install.in`). Runtime `layer7.inc` / `pkg-install.in`
**intacto**.
