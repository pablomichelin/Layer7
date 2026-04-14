# F3 - Fecho Operacional Restante

## Finalidade

O que ainda falta para fechar a F3 com evidencia real.

Actualizado em `2026-04-14` com base no alinhamento do `license-server` live
e no inventario real ja obtido.

---

## 1. O que ja esta provado

### 1.1 Inventario real (obtido em 2026-04-03)

4 licencas reais no live, obtidas via `GET /api/licenses` com Bearer JWT:

| ID | Cliente | Status | Expiry | Cenario |
|----|---------|--------|--------|---------|
| 8 | Compasi | `active` | 2026-12-31 | activacao valida |
| 7 | Systemup | `active` | 2033-10-24 | longo prazo, mesmo hw que ID 5 |
| 6 | Lasalle Agro | `revoked` | 2026-04-30 | revogacao real |
| 5 | Lasalle | `active` (expirada por data) | 2026-03-31 | expiracao hibrida |

### 1.2 Auth no live

- login funciona com `pablo@systemup.inf.br`;
- Bearer bridge administrativa funciona para endpoints autenticados;
- `/api/auth/session` existe no live e devolve sessao stateful + bridge de
  compatibilidade;
- `Origin` externo em `/api/auth/login` volta a falhar fechado com `403
  Origem administrativa nao autorizada.`.

### 1.3 pfSense / appliance `192.168.100.254`

- IP, hostname, pfSense Plus 25.11.1 confirmados;
- SSH funcional no utilizador temporario `codex`;
- Layer7 instalado, daemon running;
- UUID/VM hints recolhidos;
- ficheiros principais presentes;
- baseline canónico exportado em
  `/tmp/layer7-f3-evidence/20260414T111500Z-appliance-baseline/S13` e
  `/tmp/layer7-f3-evidence/20260414T113500Z-appliance-runtime/S07`;
- baseline read-only adicional exportado em
  `${TMPDIR:-/tmp}/layer7-f3-evidence/20260414T123526Z-appliance254-permissions/S07`,
  confirmando usuario efectivo, permissoes de ficheiros e processo real do
  `layer7d`;
- appliance actualmente coerente com a licenca `ID 7 / Systemup /
  2033-10-24`, fingerprint
  `e31560f5bc9894e92b9007d3e2e897a374f3d0b493b803c929d54acf51f8f826`.

### 1.4 Host live `192.168.100.244`

- SSH confirmado;
- stack viva com 4 containers;
- directorio activo observado em `/opt/layer7-license`;
- directorio `/opt/license-server` permanece apenas como legado, fora da stack
  actual.

### 1.5 PostgreSQL live

- base `layer7_license`, user `layer7`;
- tabelas `licenses`, `activations_log`, `admin_sessions`,
  `admin_audit_log` e `admin_login_guards` presentes;
- drift administrativo da F2 anteriormente observado no live deixa de
  existir neste ambiente.

---

## 2. O que falta para fechar a F3

### 2.1 DR-02 — RESOLVIDO (2026-04-03)

Testes executados no live (`POST /api/activate`):

| Cenario | Licenca | Repo | Live | Logica |
|---------|---------|------|------|--------|
| hw diferente do binding | ID 8 (Compasi) | 409 | 403 | correcta (rejeita) |
| licenca revogada | ID 6 (Lasalle Agro) | 409 | 403 | correcta (rejeita) |
| licenca expirada | ID 5 (Lasalle) | 409 | 403 | correcta (rejeita) |
| reactivacao legitima | ID 7 (Systemup) | 200 | 200 | correcta (aceita) |

**Veredicto:** drift cosmetico de codigo HTTP (`403` vs `409`). A logica de
negocio esta correcta em todos os cenarios. O alinhamento de codigo HTTP
permanece pendente num bloco proprio. Nao bloqueia a F3.

### 2.2 Cenarios locais do appliance (DR-05) — PENDENTE

Alvo: appliance `192.168.100.254` (pfSense Plus, Layer7 activo). Conectividade
ICMP e SSH confirmadas. Em `2026-04-14`, o utilizador temporario `codex`
passou a dar acesso shell e permitiu exportar baseline real do appliance.
O bloqueio do DR-05 deixou de ser "entrar no host" e passou a ser
**executar cenarios mutaveis com permissao suficiente para reescrever
`/usr/local/etc/layer7.lic` e controlar o daemon sem ambiguidade**.

Hoje ja esta provado que:

- `pfSsh.php playback svc restart layer7d` funciona com `codex`;
- `layer7d --fingerprint` funciona;
- `kern.hostuuid` e stats JSON sao observaveis;
- o `.lic` actual e legivel, mas **nao e escrevivel** por `codex`.
- `service layer7d status` via `codex` reporta falso negativo por falta de
  leitura do pidfile `0600 root:wheel`, mas `pgrep -fl layer7d` confirma o
  daemon vivo (`/usr/local/sbin/layer7d`) e o stats JSON confirma runtime
  activo.

Portanto, a metade read-only do DR-05 ja esta desbloqueada e parcialmente
executada; os cenarios que exigem nova activacao, limpeza do `.lic`, grace
ou troca de artefacto ainda pedem permissao de escrita/control plane no
appliance. Detalhe em
[`docs/07-prompts/f3-prompt-continuacao-2026-04-03.md`](../07-prompts/f3-prompt-continuacao-2026-04-03.md)
(secao "Evidencia SSH ao appliance").

**Roteiro operacional unificado** (comandos completos, criterios `PASS`/`FAIL`
e evidencia minima): ver
[`f3-validacao-manual-evidencias.md`](f3-validacao-manual-evidencias.md)
(cenarios `S07` a `S09` onde aplicavel ao appliance, `S12` relogio/grace,
`S13` NIC/UUID/clone) e
[`f3-pack-operacional-validacao.md`](f3-pack-operacional-validacao.md)
(`run_id`, directoria de evidencias).

**Mapa rapido DR-05:**

| Tema | Cenario F3.6 | Notas |
|------|--------------|-------|
| offline/online, daemon vs backend | S07, S08, S09 | S07 ja tem baseline/export; S08-S09 ainda dependem de mutacao controlada |
| relogio / fim de grace | S12 | snapshot obrigatorio antes; rollback = restore |
| NIC / UUID / clone / restore | S13 | baseline ja exportado; cenarios mutaveis continuam pendentes |
| seguranca | — | snapshot/restore da VM como rede de seguranca, nao como "cenario" isolado |

**Apos executar:** actualizar drift registry (DR-05), este documento, scorecard,
execution master register, `f3-11-start-here`, `CORTEX.md` e o relatorio em
`docs/tests/templates/f3-validation-campaign-report.md`.

**Prompt de continuidade** com o mesmo roteiro em formato copiavel:
[`docs/07-prompts/f3-prompt-continuacao-2026-04-03.md`](../07-prompts/f3-prompt-continuacao-2026-04-03.md).

### 2.3 Relatorio final de campanha

Depois de 2.2:
- consolidar evidencias por `run_id`;
- preencher relatorio final;
- decidir binariamente: `F3 pode fechar` ou `F3 nao pode fechar`.

---

## 3. O que NAO falta para fechar a F3

Itens que o ChatGPT tratava como blockers da F3 mas que sao de outras fases
ou que ja ficaram alinhados no live:

- schema administrativo do live (tabelas `admin_sessions`, etc.) — **ja
  alinhado no live**; nao bloqueia a F3;
- CORS/same-origin — **ja alinhado no live**; nao bloqueia a F3;
- proveniencia exacta do deploy — F7/operacional;
- sessao stateful por cookie — **ja alinhada no live**; nao bloqueia a F3;
- `5/5 insumos entregue valido` com burocracia de intake/triagem/ciclo — overhead desnecessario.

---

## 4. Objectivo, impacto, risco, teste e rollback

- **Objectivo:** simplificar e actualizar o caminho real para fechar a F3.
- **Impacto:** documental.
- **Risco:** baixo.
- **Rollback:** `git revert <commit-deste-bloco>`.
