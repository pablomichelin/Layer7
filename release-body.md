## Layer7 v0.2.6 — listas melhores e sites manuais

Pacote Layer 7 para pfSense CE com classificacao em tempo real via nDPI.

### Novidades

- **Selecao em massa nas listas** — politicas e excecoes agora possuem botoes para selecionar tudo ou limpar interfaces; apps/categorias nDPI permitem selecionar os itens visiveis apos o filtro
- **Sites/hosts manuais nas politicas** — novo campo `Sites/hosts` grava `match.hosts[]` no JSON e casa contra o `host=` observado nos eventos
- **Match por dominio e subdominio** — uma regra com `youtube.com` tambem casa `www.youtube.com`, quando o hostname for inferido por DNS
- **Ver listas existentes** — cada politica ganhou acao `Ver listas`, com visualizacao completa dos itens bloqueados/monitorados sem entrar direto em edicao
- **Hostnames e destino nos eventos** — mantido o enriquecimento `dst=` e `host=`, que agora tambem suporta o matching manual por sites

### Instalacao (um comando)

```sh
fetch -o /tmp/install.sh https://raw.githubusercontent.com/pablomichelin/pfsense-layer7/main/scripts/release/install.sh && sh /tmp/install.sh
```

### Rollback

```sh
pkg delete pfSense-pkg-layer7
```

### Compatibilidade

- pfSense CE 2.7.x / 2.8.x
- FreeBSD 14 / 15

### Documentacao

- [Guia Completo](docs/tutorial/guia-completo-layer7.md)
- [CHANGELOG](docs/changelog/CHANGELOG.md)
