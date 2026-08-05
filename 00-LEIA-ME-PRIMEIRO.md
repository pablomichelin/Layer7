# Layer7 para pfSense CE — Leitura inicial

> **Este ficheiro e um ponto de entrada rapido.** A fonte de verdade operacional
> e **`CORTEX.md`** + **`docs/`**. Documentos `00-`…`16-` na raiz sao **legado
> preservado** — consultar [`docs/00-overview/document-equivalence-map.md`](docs/00-overview/document-equivalence-map.md)
> antes de os tratar como normativos.

---

## Estado actual (checkpoint)

| Item | Valor |
|------|-------|
| **Canal público `latest`** | `1.9.6` — tag `v1.9.6` em `pablomichelin/Layer7` (lab IPv6) |
| **SHA256 (`latest`)** | `fc2d7fce624f8ac0afaf68ee9b2c0850b1e956767baeb16dfc11498517e3c6e4` |
| **Produção enforce (referência)** | **`1.9.0`** — fecho plano mestre (`2026-08-05`); promoção `1.9.6` = GV7.4 PENDENTE |
| **Rollback imediato (a partir de `1.9.6`)** | `1.9.5` |
| **Produto** | Pacote proprietario Layer7 para **pfSense CE** (Systemup) |
| **Fase roadmap** | F4 aberta; F3 **fechada** |
| **CE** | LIMITAÇÃO — ADR-0022 aceite; validação física CE pendente |
| **Plano fecho** | **FECHADO** (Ondas A–J; excepções R7/CE documentadas) |
| **Trilha IPv6** | **FECHADA (núcleo)** — V5 residual com GO; [`START-HERE-fecho-producao.md`](docs/00-overview/START-HERE-fecho-producao.md) |

> **Versão actual (`latest`):** `1.9.6`. **Enforce:** `1.9.0`. Núcleo dual-stack fechado (GV7); V5 DNS/portal v6 **adiado** (ADR-0024) — **voltar a fazer bem**.

Instalacao, upgrade e rollback: [`docs/10-license-server/MANUAL-INSTALL.md`](docs/10-license-server/MANUAL-INSTALL.md).

---

## Leitura obrigatoria (ordem)

1. [`CORTEX.md`](CORTEX.md) — SSOT operacional, fase, gates, checkpoint
2. [`AGENTS.md`](AGENTS.md) — regras para agentes e mantenedores
3. [`docs/README.md`](docs/README.md) — indice documental canónico
4. [`docs/02-roadmap/backlog.md`](docs/02-roadmap/backlog.md) — prioridades
5. [`docs/02-roadmap/checklist-mestre.md`](docs/02-roadmap/checklist-mestre.md) — gates
6. Area em causa (ex.: enforcement → [`docs/09-blocking/plano-enforcement-100-porcento.md`](docs/09-blocking/plano-enforcement-100-porcento.md))

**Handoff chat longo:** [`docs/00-overview/handoff-chat-novo.md`](docs/00-overview/handoff-chat-novo.md)

**Arranque único (fecho P0–J FECHADO + trilha IPv6 núcleo FECHADO; residual V5/promoção):**
[`docs/00-overview/START-HERE-fecho-producao.md`](docs/00-overview/START-HERE-fecho-producao.md)
→ plano: [`docs/02-roadmap/plano-ipv6-completo.md`](docs/02-roadmap/plano-ipv6-completo.md)

---

## O que o produto faz hoje

- Pacote pfSense com daemon `layer7d`, GUI integrada, classificacao **nDPI**
- Politicas por interface, IP/CIDR, grupo, horario, host, app e categoria
- **Monitor** (passivo), **enforce** (bloqueio PF), allowlist, blacklists UT1
- Licenciamento via ficheiro `.lic` Ed25519; **enforce ao vivo exige licenca valida**
- Caminho A concluido (`1.8.11_23`): inventario dispositivos, MAC→IP, SNI opt-in, UX perfis
- Caminho B E0–E3 (`1.8.11_24`): fundacao `enforcement_model`, decisao unificada, PF escopado

---

## Enforcement: monitor vs enforce vs licenca vs modelo

### Modos de servico (`layer7.mode`)

| Modo | Comportamento |
|------|----------------|
| `monitor` | Classifica e regista; **sem** `block drop` PF injectado pelo pacote |
| `enforce` | Aplica bloqueios PF quando licenca valida e `enabled=true` |

### Licenca

- Sem `.lic` valida (ou fora de grace): o daemon **nao impoe** bloqueios — comportamento equivalente a monitor para enforcement.
- Auditoria: `layer7d --license-status` (formato `chave=valor`).

### Modelo de enforcement (`layer7.enforcement_model`)

| Valor | Default | Comportamento |
|-------|---------|---------------|
| `legacy_global` | **Sim** | Decisao considera cliente na policy engine, mas bloqueio PF vai para **`layer7_block_dst`** — **efeito global por destino** (qualquer cliente afectado pelo IP bloqueado). Este e o comportamento historico V1 / REV-001 by design. |
| `scoped_hybrid` | Nao (experimental) | Bloqueio escopado: `layer7_pdst_N` / `layer7_psrc_N` por politica; **per-client real**. Requer activacao manual + **gate two-client PASS** antes de producao. |

**Importante:** bloqueio “so para o filho” **nao funciona** com o default `legacy_global`. Para per-client, activar `scoped_hybrid` **e** passar o gate do appliance (sec. 12).

Plano SSOT: [`docs/09-blocking/plano-enforcement-100-porcento.md`](docs/09-blocking/plano-enforcement-100-porcento.md).

---

## Proximos passos operacionais

**Trilha IPv6:** **FECHADA (núcleo)** — residual V5 (12.10 / BG-083) ou promoção
enforce (`1.9.6`) com GO —
[`START-HERE-fecho-producao.md`](docs/00-overview/START-HERE-fecho-producao.md).

1. **Chat limpo:** colar só o caminho do START-HERE-fecho-producao.md.
2. Residual: **12.10/V5** com GO Opção A **ou** **promoção enforce** com GO; ver `CORTEX.md`.
3. Produção enforce permanece **`1.9.0`** até GO de promoção (GV7.4).
4. Diagnostico rapido: [`scripts/diagnose-layer7-appliance.sh`](scripts/diagnose-layer7-appliance.sh)
5. Testes locais: `./tests/run-local.sh` (nao substituem appliance; obrigatorio em ondas com codigo)

---

## Distribuicao e artefactos

- Canal publico: **`.pkg` via GitHub Releases** (nao instalar directamente do GitHub clone)
- Obter pacote lab (`latest`):
  ```sh
  gh release download v1.8.11_65 --repo pablomichelin/Layer7 \
    --pattern 'pfSense-pkg-layer7-1.8.11_65.pkg*' --dir artifacts/
  ```
- Obter pacote producao enforce (referencia ate GO):
  ```sh
  gh release download v1.8.11_24 --repo pablomichelin/Layer7 \
    --pattern 'pfSense-pkg-layer7-1.8.11_24.pkg*' --dir artifacts/
  ```
- Build: builder FreeBSD `192.168.100.12` — ver [`docs/08-lab/builder-freebsd.md`](docs/08-lab/builder-freebsd.md)
- macOS: workspace de edicao/git/docs apenas; validacao tecnica no builder + appliance

---

## Historico deste ficheiro

Este documento na raiz foi criado na fase inicial do projecto (planeamento V1,
referencias a v0.2.0 e `.txz`). Foi **actualizado em 2026-08-04** para reflectir
dual-canal (`latest` `_65` vs enforce `_24`), governanca F0–F4 e plano de fecho.
Para historico de fases 0–11 e motor multi-interface v0.2.0, ver [`docs/changelog/CHANGELOG.md`](docs/changelog/CHANGELOG.md)
e documentos `03-ROADMAP-E-FASES.md` / `14-CHECKLIST-MESTRE.md` na raiz (legado).

**Ponto de verdade operacional:** sempre **`CORTEX.md`**.
