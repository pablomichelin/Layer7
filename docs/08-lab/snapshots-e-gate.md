# Snapshots e gate da Fase 1

## Snapshots recomendados

| Momento | Nome sugerido |
|---------|----------------|
| pfSense instalado, WAN/LAN básico, sem Layer7 | `lab-pfsense-base` |
| Após syslog remoto validado | `lab-pfsense-syslog-ok` |
| Antes de instalar primeiro `.txz` Layer7 | `lab-pre-layer7-pkg` |

Plataforma (Proxmox, VMware, Hyper-V, etc.): usar snapshot **da VM pfSense** no hipervisor; no CE também é possível backup XML, mas snapshot de VM é mais rápido para iterar.

## Gate Fase 1 (checklist)

Marque quando for **verdade na sua bancada**:

- [ ] **Build host:** VM FreeBSD instalada, `git` e ferramentas base conforme `builder-freebsd.md`.
- [ ] **pfSense lab:** VM com WAN+LAN, cliente na LAN com internet ou cenário fechado documentado.
- [ ] **SSH:** acesso SSH ao pfSense (e ao builder) confiável a partir da sua estação.
- [ ] **Rede:** pings e rota conforme `lab-topology.md`; IPs anotados.
- [ ] **Syslog:** evento do pfSense visível no coletor remoto.
- [ ] **Snapshot:** pelo menos um snapshot “bom estado” antes de pacotes experimentais.

Quando todos estiverem OK, avançar para **Bloco 3 (PoC nDPI)** no código.
