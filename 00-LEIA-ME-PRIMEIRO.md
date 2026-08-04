# Layer7 para pfSense CE — Leitura inicial

> **Este ficheiro e um ponto de entrada rapido.** A fonte de verdade operacional
> e **`CORTEX.md`** + **`docs/`**. Documentos `00-`…`16-` na raiz sao **legado
> preservado** — consultar [`docs/00-overview/document-equivalence-map.md`](docs/00-overview/document-equivalence-map.md)
> antes de os tratar como normativos.

---

## Estado actual (checkpoint)

| Item | Valor |
|------|-------|
| **Canal público `latest`** | `1.8.11_65` — tag `v1.8.11_65` em `pablomichelin/Layer7` |
| **SHA256 (`latest`)** | `e7c8ca44f34e19da3a2958eacfd09fce5c77c77d5acd6d8633e9ca9d42cdd48e` |
| **Produção enforce (referência)** | `1.8.11_24` — **até gates G2–G7 + GO humano** (Onda F do plano mestre) |
| **SHA256 (enforce ref)** | `1d5573f0a0c7803a87d8cb536ad9eee43e85daa9bf98bf7edc84ef554e2c7818` |
| **Candidato lab (gates)** | `1.8.11_65` (confirmado passo 1.1 — 2026-08-04) |
| **Produto** | Pacote proprietario Layer7 para **pfSense CE** (Systemup) |
| **Fase roadmap** | F4 aberta; F3 aberta; plano de fecho **ACTIVO** |
| **Trilha activa** | **Caminho B** — Enforcement escopado (E0–E3 no codigo; E4–E8 planeados) |
| **Gate pendente** | **G2–G7** no appliance (`plano-gates-producao.md`) — **nao avancar E4** sem G5 PASS |

> **`latest` ≠ produção enforce.** O botao *Verificar actualizacao* aponta para `_65`;
> a referencia enforce continua `_24` ate veredicto GO.

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

**Plano fecho producao + consolidacao (colar no chat novo):**
[`docs/00-overview/START-HERE-fecho-producao.md`](docs/00-overview/START-HERE-fecho-producao.md)
→ plano completo:
[`docs/02-roadmap/plano-fecho-producao-e-consolidacao.md`](docs/02-roadmap/plano-fecho-producao-e-consolidacao.md)

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

**Trilha activa:** plano mestre de fecho — [`START-HERE-fecho-producao.md`](docs/00-overview/START-HERE-fecho-producao.md).

1. **Passo actual:** P1 passo 1.2 — snapshot appliance lab (`192.168.100.254`); ver `CORTEX.md`.
2. **Gate B1 (Onda A):** install passivo `_65` no appliance lab (`192.168.100.254`):
   - `enabled=false`, `mode=monitor`
   - Roteiro: [`plano-gates-producao.md`](docs/09-blocking/plano-gates-producao.md) G2–G4
3. **Gate two-client (Onda B):** só após G2–G4 PASS — [`validacao-lab.md` sec. 12](docs/04-package/validacao-lab.md)
4. Diagnostico rapido: [`scripts/diagnose-layer7-appliance.sh`](scripts/diagnose-layer7-appliance.sh)
5. Testes locais: `./tests/run-local.sh` (nao substituem appliance)
6. Apos GO (Onda F): avancar E4–E8 conforme Caminho B

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
