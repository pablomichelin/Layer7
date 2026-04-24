# Laboratório

**Continuidade operacional:** o fluxo real do projecto (ordem de fases,
prioridades, port em desenvolvimento) vive no [`CORTEX.md`](../../CORTEX.md) e
no [roadmap canónico F0–F7](../02-roadmap/roadmap.md). A **F4** (package, daemon,
blacklists, `force_dns`) segue
[`f4-plano-de-implementacao.md`](../02-roadmap/f4-plano-de-implementacao.md);
os roteiros de evidência mínima no pfSense (secções **10a** / **10b** / **11**)
estão no [validacao-lab](../04-package/validacao-lab.md). Build do `.pkg` no
builder **FreeBSD**; validação funcional no **appliance** pfSense — nunca
Windows como gate (ver [guia-windows.md](guia-windows.md) legado).

| Documento | Uso |
|-----------|-----|
| [lab-topology.md](lab-topology.md) | Rede de teste, cliente, interfaces |
| [builder-freebsd.md](builder-freebsd.md) | VM FreeBSD para compilar PoC/pacote |
| [guia-windows.md](guia-windows.md) | Documento legado: Windows nao e fluxo vigente; preservado ate F6 |
| [quick-start-lab.md](quick-start-lab.md) | Fluxo encadeado: builder → pfSense → validação |
| [syslog-remote.md](syslog-remote.md) | Logs remotos no lab |
| [snapshots-e-gate.md](snapshots-e-gate.md) | Snapshots + checklist gate (contexto legado) |
| [lab-inventory.template.md](lab-inventory.template.md) | Template de IPs/versões (cópia local) |
| [../04-package/validacao-lab.md](../04-package/validacao-lab.md) | Build `.pkg`, `pkg add`, serviço, GUI, roteiros F4 (10a, 10b, 11), §6c CLI |
| [../poc/README.md](../poc/README.md) | PoC nDPI e registro de resultados |

**Equivalencia documental (raiz vs `docs/`):** os ficheiros
[`03-ROADMAP-E-FASES.md`](../../03-ROADMAP-E-FASES.md) e
[`07-PLANO-DE-IMPLEMENTACAO-PASSO-A-PASSO.md`](../../07-PLANO-DE-IMPLEMENTACAO-PASSO-A-PASSO.md)
na raiz sao **historico**; a fonte de decisão para fases e execução e o
[`roadmap`](../02-roadmap/roadmap.md) e o
[`../00-overview/document-equivalence-map.md`](../00-overview/document-equivalence-map.md)
— ver tambem
[`../02-roadmap/checklist-mestre.md`](../02-roadmap/checklist-mestre.md) para
gates (incl. F4.1 / F4.2 / F4.3).
