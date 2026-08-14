# License Server — Índice da Área

Documentação canónica do **servidor de licenças Layer7** e do **portal
administrativo** (`https://license.systemup.inf.br`).

## Hierarquia (obrigatória)

1. [`../../CORTEX.md`](../../CORTEX.md) — SSOT global Layer7
2. Este índice + manuais da área
3. **Portal (trilha activa de produto UI):** [`portal/README.md`](portal/README.md)

Os manuais de instalação do **pacote pfSense** e de uso de licenças
continuam SSOT operacional. A pasta `portal/` governa o **produto painel**
(versão visual própria, planos, ideias, acções).

## Documentos desta área

| Documento | Papel | Classificação |
|-----------|-------|---------------|
| [`MANUAL-INSTALL.md`](MANUAL-INSTALL.md) | Instalação/upgrade do pacote Layer7 | Canónico (pacote) |
| [`MANUAL-USO-LICENCAS.md`](MANUAL-USO-LICENCAS.md) | Operação de licenças (admin + daemon) | Canónico |
| [`PLANO-LICENSE-SERVER.md`](PLANO-LICENSE-SERVER.md) | Plano histórico de criação do servidor (F2) | Suplementar / histórico F2 |
| [`portal/README.md`](portal/README.md) | **Arranque da trilha do portal admin** | Canónico (portal) |

## Código e deploy

| Item | Caminho / alvo |
|------|----------------|
| Código | `license-server/` (repo) |
| Deploy live | `192.168.100.244:/opt/layer7-license` |
| URL pública | `https://license.systemup.inf.br` |
| Origin privado | `127.0.0.1:8445` (via ISPConfig) |
| Freeze P0-1 | **ACTIVO** — serving `30.11` no git; **proibido** deploy integral HEAD→`.244` |

## Versão do portal (visual / produto UI)

Versão **independente** do `PORTVERSION` do pacote pfSense.

- **Baseline actual:** ver [`portal/VERSION.md`](portal/VERSION.md)
- **Changelog do portal:** [`portal/CHANGELOG.md`](portal/CHANGELOG.md)
