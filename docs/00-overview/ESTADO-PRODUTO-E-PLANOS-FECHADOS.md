# 【FILAS FECHADAS】 Estado do produto e planos fechados — consolidação `2026-08-05`

**Classificação:** Canónico (fecho de filas + mapa de navegação)  
**SSOT de estado vivo:** [`CORTEX.md`](../../CORTEX.md)  
**Arranque de chat:** [`START-HERE-fecho-producao.md`](START-HERE-fecho-producao.md)

Este ficheiro **congela** o resultado das filas concluídas em `2026-08-05` e
define como navegar a documentação **sem** reabrir planos fechados. Não
substitui o `CORTEX.md` (que continua a mudar em manutenção); fixa o
**veredicto de fecho** e o inventário de provas.

> **F6 H5 (`2026-08-05`):** raiz `00-`…`16-` → [`docs/archive/raiz-legado/`](../archive/raiz-legado/);
> planos fecho+IPv6 → [`docs/archive/planos-fechados/`](../archive/planos-fechados/).
> Stubs nos caminhos antigos; banners `【ARQUIVO】` / `【FECHADO】`.

---

## 1. Veredicto executivo

| Item | Valor |
|------|-------|
| Produto | Layer7 para pfSense CE (Systemup) |
| Pacote / `latest` / produção enforce | **`1.9.8`** (alinhados) |
| SHA256 | `229639243fc31333251fa286690bf87db9f20b644039b857ca283d16501a99ec` |
| Release | https://github.com/pablomichelin/Layer7/releases/tag/v1.9.8 |
| Rollback enforce | **`1.9.0`** |
| Pronto para enforce | **SIM** (com ressalvas secção 5) |
| Filas START-HERE (fecho + IPv6) | **100% FECHADAS** |
| Modo documental | Manutenção contínua; **novos planos** só com GO + entrada no backlog |

---

## 2. Planos / filas — estado final

| Fila | SSOT de execução | Estado | Evidência-chave |
|------|------------------|--------|-----------------|
| Fecho produção P0–J | [`【FECHADO】 arquivo`](../archive/planos-fechados/plano-fecho-producao-e-consolidacao.md) ([stub](../02-roadmap/plano-fecho-producao-e-consolidacao.md)) | **FECHADO** (`1.9.0` → `1.9.8`) | Onda J + GV7.4 |
| IPv6 V0–V6 (passos 12.1–12.13) | [`【FECHADO】 arquivo`](../archive/planos-fechados/plano-ipv6-completo.md) ([stub](../02-roadmap/plano-ipv6-completo.md)) | **FECHADA** | GV0–GV7 PASS |
| DNS force dual-stack (12.10) | ADR-0024 Opção A | **CONCLUÍDO** (`1.9.7`) | `20260805T140400Z-gv5-12.10-smoke-1.9.7` |
| HTTP/VIP dual-stack (12.11) | ADR-0024 / BG-083 | **CONCLUÍDO** (`1.9.8`) | `20260805T143000Z-gv5-12.11-smoke-1.9.8` |
| Promoção enforce (GV7.4) | gates IPv6 + CORTEX | **PASS** | `20260805T150500Z-gv7.4-promocao-1.9.8` |
| Validação two-client lab | `validacao-lab` + campanha | **PASS** | `20260805T162500Z-prod-align-two-client-1.9.8` |

**BG IPv6:** BG-078 … BG-084 **concluídos**.

---

## 3. Estado real do produto (runtime)

### 3.1 Capacidades em produção (`1.9.8`)

- Daemon `layer7d` + GUI pfSense; classificação **nDPI**
- Modos `monitor` / `enforce`; modelos `legacy_global` (default lab) e `scoped_hybrid` (experimental, validado em gate)
- Políticas por host/app/categoria; allowlist; blacklists UT1 assinadas
- DNS forçado + anti-DoH/DoT; página de bloqueio HTTP (ADR-0017); sinkhole A+AAAA
- Dual-stack IPv4/IPv6: captura, PF scoped, policy/enforce, GUI, DNS `rdr inet6`, HTTP portal inet6, VIP ACL v6
- Licenciamento Ed25519; check-in online (flag); updater GitHub Releases
- Syslog remoto UDP; logs locais com contenção L1 (ADR-0015); relatórios GUI locais

### 3.2 Lab observado (`2026-08-05`)

| Nó | Papel |
|----|-------|
| `192.168.100.254` | Appliance pfSense (Plus/FB16) — pacote `1.9.8`, enforce |
| `192.168.100.234` | Cliente A (two-client) |
| `192.168.100.235` | Cliente B (two-client) |
| `192.168.100.12` | Builder FreeBSD 15 |

Config lab pós-campanha: `enforcement_model=legacy_global`, `force_dns=1`,
políticas de produção restauradas após gate scoped temporário.

### 3.3 Provas do dia (índice)

| `run_id` | Tema |
|----------|------|
| [`20260805T133000Z-gv7-fecho`](../tests/evidence/20260805T133000Z-gv7-fecho/) | Fecho documental GV7.1–GV7.3 |
| [`20260805T140400Z-gv5-12.10-smoke-1.9.7`](../tests/evidence/20260805T140400Z-gv5-12.10-smoke-1.9.7/) | Smoke DNS inet6 |
| [`20260805T143000Z-gv5-12.11-smoke-1.9.8`](../tests/evidence/20260805T143000Z-gv5-12.11-smoke-1.9.8/) | Smoke HTTP/VIP inet6 |
| [`20260805T150500Z-gv7.4-promocao-1.9.8`](../tests/evidence/20260805T150500Z-gv7.4-promocao-1.9.8/) | Promoção enforce |
| [`20260805T162500Z-prod-align-two-client-1.9.8`](../tests/evidence/20260805T162500Z-prod-align-two-client-1.9.8/) | Campanha two-client / alinhamento |

Pasta de evidências: [`docs/tests/evidence/`](../tests/evidence/).

---

## 4. Mapa documental — onde está cada coisa

### 4.1 Entrada (ordem obrigatória)

1. [`CORTEX.md`](../../CORTEX.md) — estado vivo  
2. [`AGENTS.md`](../../AGENTS.md) — regras do agente  
3. Este ficheiro — fecho consolidado das filas  
4. [`START-HERE-fecho-producao.md`](START-HERE-fecho-producao.md) — arranque de chat  
5. [`docs/README.md`](../README.md) — índice da árvore `docs/`  
6. Área em causa (tabela abaixo)

### 4.2 Por tema (canónico)

| Tema | Ficheiro(s) |
|------|-------------|
| Instalação / upgrade / rollback | [`MANUAL-INSTALL.md`](../10-license-server/MANUAL-INSTALL.md) |
| Changelog | [`CHANGELOG.md`](../changelog/CHANGELOG.md) |
| Release checklist | [`RELEASE-CHECKLIST.md`](../06-releases/RELEASE-CHECKLIST.md) |
| Roadmap F0–F7 | [`roadmap.md`](../02-roadmap/roadmap.md) |
| Backlog | [`backlog.md`](../02-roadmap/backlog.md) |
| Checklist gates | [`checklist-mestre.md`](../02-roadmap/checklist-mestre.md) |
| ADRs | [`03-adr/README.md`](../03-adr/README.md) |
| IPv6 (histórico da fila) | [`【FECHADO】 plano-ipv6`](../archive/planos-fechados/plano-ipv6-completo.md), [`plano-gates-ipv6.md`](../09-blocking/plano-gates-ipv6.md), [`f4-ipv6-mapa-rastreabilidade.md`](../01-architecture/f4-ipv6-mapa-rastreabilidade.md), [`ADR-0024`](../03-adr/ADR-0024-suporte-ipv6-ativacao-faseada.md) |
| Fecho P0–J (histórico) | [`【FECHADO】 plano-fecho`](../archive/planos-fechados/plano-fecho-producao-e-consolidacao.md) |
| Classificação / equivalência | [`document-classification.md`](document-classification.md), [`document-equivalence-map.md`](document-equivalence-map.md) |
| Limitações DPI / MITM | [`matriz-limitacoes-dpi.md`](../09-blocking/matriz-limitacoes-dpi.md), [`ADR-0017`](../03-adr/ADR-0017-pagina-bloqueio-utilizador-dns-sinkhole.md) |
| Lab | [`08-lab/README.md`](../08-lab/README.md) |
| Testes | [`tests/README.md`](../tests/README.md) |

### 4.3 Raiz `00-`…`16-` (arquivada H5)

**【ARQUIVO · LEGADO】** — texto em [`docs/archive/raiz-legado/`](../archive/raiz-legado/);
stubs na raiz do repo. Ver equivalência. Onboarding:
[`00-LEIA-ME-PRIMEIRO.md`](../../00-LEIA-ME-PRIMEIRO.md) (stub; vence CORTEX).

---

## 5. Ressalvas e exclusões (não são “plano incompleto”)

| Tema | Estado | Nota |
|------|--------|------|
| CE físico pfSense CE | LIMITAÇÃO ADR-0022 | Lab = Plus/FB16 |
| Trust chain pacote (BG-028) | Fase 0 ADR-0023 | Caminho manual vigente |
| F6 H5 (raiz legado + planos fechados) | **EXECUTADA** `2026-08-05` | `docs/archive/raiz-legado/` + `planos-fechados/` + stubs |
| `scoped_hybrid` default produção | Não | Validado; lab usa `legacy_global` |
| Analytics / SIEM pesado | Fora de fila | Só telemetria mínima F7 (BG-018) se GO |
| MITM / Identity add-on | **Novo plano ABERTO** | [`START-HERE-identity-mitm.md`](START-HERE-identity-mitm.md); ADR-0017 mantém-se com MITM OFF |
| Console multi-firewall | Fora de fila | — |
| Rebind automático de licença | Fora de fila | — |

---

## 6. Como abrir um **novo** plano (futuro)

1. **GO humano** explícito no chat.  
2. Criar/actualizar item no [`backlog.md`](../02-roadmap/backlog.md) (fase, risco, teste, rollback).  
3. Se for fila longa: plano em `docs/02-roadmap/` + ADR se decisão arquitectural.  
4. **Não** reutilizar este START-HERE para reabrir P0–J ou V0–V6 sem GO.  
5. Opcional: novo `START-HERE-<tema>.md` **só** se for fila distinta e registada no CORTEX + `docs/README.md` (evitar proliferação).  
6. Actualizar `CORTEX.md` checkpoint no mesmo bloco.

Prompt modelo:

```text
Contexto: planos fecho+IPv6 FECHADOS (1.9.8). Ler:
docs/00-overview/ESTADO-PRODUTO-E-PLANOS-FECHADOS.md
CORTEX.md, AGENTS.md, backlog.md

Novo plano proposto: <nome>
Objectivo / impacto / risco / teste / rollback:
...
Não reabrir filas fechadas sem GO.
Responder em português.
```

---

## 7. Checklist de fecho documental (este bloco)

- [x] Produção enforce = `latest` = `1.9.8`
- [x] Trilha IPv6 FECHADA (inclui GV7.4)
- [x] Fecho P0–J FECHADO
- [x] Evidências do dia indexadas
- [x] Exclusões (SIEM, MITM, CE) declaradas
- [x] Porta de entrada para novos planos definida
- [x] F6 H5: raiz legado + planos fechados arquivados com stubs e banners

---

## 8. Histórico deste documento

| Data | Evento |
|------|--------|
| 2026-08-05 | Criação — consolidação pós GV7.4 + campanha two-client `1.9.8` |
| 2026-08-05 | Nota: aberto plano **Identity + MITM Add-on** (não reabre P0–J/IPv6); arranque `START-HERE-identity-mitm.md` |
| 2026-08-05 | F6 H5 — arquivo físico raiz `00-`…`16-` + planos fecho/IPv6 |
