# Layer7 para pfSense CE — Leitura inicial

> **Este ficheiro e um ponto de entrada rapido.** A fonte de verdade operacional
> e **`CORTEX.md`** + **`docs/`**. Documentos `00-`…`16-` na raiz sao **legado
> preservado** — consultar [`docs/00-overview/document-equivalence-map.md`](docs/00-overview/document-equivalence-map.md)
> antes de os tratar como normativos.

---

## Estado actual (checkpoint)

| Item | Valor |
|------|-------|
| **Versao publicada** | `1.8.11_24` (`PORTVERSION=1.8.11`, `PORTREVISION=24`) |
| **Release GitHub** | `pablomichelin/Layer7`, tag `v1.8.11_24` |
| **SHA256** | `1d5573f0a0c7803a87d8cb536ad9eee43e85daa9bf98bf7edc84ef554e2c7818` |
| **Produto** | Pacote proprietario Layer7 para **pfSense CE** (Systemup) |
| **Trilha activa** | **Caminho B** — Enforcement escopado (E0–E3 no codigo; E4–E8 planeados) |
| **Gate pendente** | **two-client** no appliance (`validacao-lab.md` sec. 12) — **nao avancar E4** sem PASS |

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

1. **Gate two-client** no appliance lab (`192.168.100.254` ou equivalente):
   - Instalar `1.8.11_24` se ainda nao instalado
   - Configurar `enforcement_model=scoped_hybrid`, politica block YouTube so para cliente A
   - Executar roteiro [`validacao-lab.md` sec. 12](docs/04-package/validacao-lab.md)
   - Smoke: `tests/lab/smoke-enforcement-scoped.sh`
2. Diagnostico rapido no pfSense: [`scripts/diagnose-layer7-appliance.sh`](scripts/diagnose-layer7-appliance.sh)
3. Testes locais antes de build: `./tests/run-local.sh` (unitarios — nao substituem appliance)
4. Apos gate PASS: avancar E4–E8 conforme plano Caminho B (**nao activar `scoped_hybrid` como default** ate E8)

---

## Distribuicao e artefactos

- Canal publico: **`.pkg` via GitHub Releases** (nao instalar directamente do GitHub clone)
- Obter pacote:
  ```sh
  gh release download v1.8.11_24 --repo pablomichelin/Layer7 \
    --pattern 'pfSense-pkg-layer7-1.8.11_24.pkg*' --dir artifacts/
  ```
- Build: builder FreeBSD `192.168.100.12` — ver [`docs/08-lab/builder-freebsd.md`](docs/08-lab/builder-freebsd.md)
- macOS: workspace de edicao/git/docs apenas; validacao tecnica no builder + appliance

---

## Historico deste ficheiro

Este documento na raiz foi criado na fase inicial do projecto (planeamento V1,
referencias a v0.2.0 e `.txz`). Foi **actualizado em 2026-07-29** para reflectir
a V1 comercial publicada, governanca F0 e Caminho B. Para historico de fases
0–11 e motor multi-interface v0.2.0, ver [`docs/changelog/CHANGELOG.md`](docs/changelog/CHANGELOG.md)
e documentos `03-ROADMAP-E-FASES.md` / `14-CHECKLIST-MESTRE.md` na raiz (legado).

**Ponto de verdade operacional:** sempre **`CORTEX.md`**.
