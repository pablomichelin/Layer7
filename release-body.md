## Layer7 v0.3.0 — bloqueio por destino (sites/apps)

Pacote Layer 7 para pfSense CE com classificacao em tempo real via nDPI.

### Novidades

- **Bloqueio por destino** — o daemon agora bloqueia IPs de DESTINO em vez de quarentenar o cliente inteiro. Sites/apps bloqueados ficam inacessiveis; o resto funciona normalmente.
- **Bloqueio DNS** — quando um dominio em `Sites/hosts` de uma politica `block` e resolvido, o IP vai automaticamente para `layer7_block_dst` e o PF bloqueia trafego para esse IP.
- **Bloqueio nDPI por destino** — classificacoes nDPI com `action=block` adicionam o IP de destino do fluxo (nao mais de origem) a tabela de bloqueio.
- **Expiracao automatica** — entradas na tabela de destino expiram com base no TTL DNS (minimo 5 min) para evitar bloqueios permanentes de IPs dinamicos.
- **Nova tabela PF** — `layer7_block_dst` com regras `block drop quick to` no snippet do pacote.
- **Diagnostics actualizado** — GUI mostra contadores da tabela de destino.

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
