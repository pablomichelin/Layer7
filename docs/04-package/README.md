# Pacote pfSense (`pfSense-pkg-layer7`)

Indice local da area; instalacao, upgrade e rollback canónicos:
[`MANUAL-INSTALL`](../10-license-server/MANUAL-INSTALL.md). Para **laboratorio**
— indice, quick-start, topologia, builder — ver
[`../08-lab/README.md`](../08-lab/README.md) e, para a sequencia minima
builder → **`.pkg`** → pfSense,
[`../08-lab/quick-start-lab.md`](../08-lab/quick-start-lab.md).

- [`package-skeleton.md`](package-skeleton.md) — o que existe no repositório.
- [`validacao-lab.md`](validacao-lab.md) — procedimento e evidências; no
  início, *Gates oficiais F4* (ligação a `checklist-mestre`, `test-matrix`,
  secções **10a/10b/11**, `CORTEX` e rascunho de release do port em branch);
  na **11**, anti-QUIC opcional e cenário opcional multi-interface / VLAN para
  `force_dns`.
- [`checklist-validacao-lab.md`](checklist-validacao-lab.md) — checklist rápido.
- [`deploy-github-lab.md`](deploy-github-lab.md) — cadeia builder → release (`.pkg`, `install.sh`); suplementar a [`scripts/release/README.md`](../../scripts/release/README.md).
