## Layer7 v0.3.1 — anti-bypass DNS (DoH/DoT/iCloud Private Relay)

Pacote Layer 7 para pfSense CE com classificacao em tempo real via nDPI.

### Novidades

- **Anti-bypass DNS multi-camada** — impede que dispositivos contornem o bloqueio usando DNS cifrado (DoH, DoT, DoQ) ou iCloud Private Relay.
- **Bloqueio DoT/DoQ** — regras PF automaticas bloqueiam TCP/UDP porta 853, cortando DNS over TLS e DNS over QUIC.
- **Deteccao nDPI de DoH** — politica built-in `anti-bypass-dns` bloqueia fluxos classificados como `DoH_DoT` e `iCloudPrivateRelay` pelo nDPI, adicionando IPs de destino a tabela de bloqueio.
- **Unbound anti-DoH** — script configura NXDOMAIN para dominios de bypass conhecidos (mask.icloud.com, dns.google, cloudflare-dns.com, etc.). iOS desativa Private Relay automaticamente.
- **Instalacao integrada** — install.sh agora configura Unbound anti-DoH automaticamente.

### Instalacao (um comando)

```sh
fetch -o /tmp/install.sh https://raw.githubusercontent.com/pablomichelin/pfsense-layer7/main/scripts/release/install.sh && sh /tmp/install.sh
```

### Rollback

```sh
pkg delete pfSense-pkg-layer7
```

### Compatibilidade

- pfSense CE 2.7.x / 2.8.x / 25.x
- FreeBSD 14 / 15

### Documentacao

- [Guia Completo](docs/tutorial/guia-completo-layer7.md)
- [CHANGELOG](docs/changelog/CHANGELOG.md)
- [Anti-bypass DNS](docs/05-daemon/pf-enforcement.md)
