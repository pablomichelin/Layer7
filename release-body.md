## Layer7 v1.6.0 — Reorganização do Frontend

Pacote Layer 7 para pfSense CE com classificacao em tempo real via nDPI.

### Principais mudanças

- **Navegação reduzida de 11 para 7 abas** — Grupos, Excepções, Categorias e Teste movidos para links rápidos em Políticas
- **Dashboard simplificado** — removidos bloco de validação de config e contadores PF duplicados
- **Definições reorganizadas** — 3 blocos claros: Serviço, Relatórios, Sistema (licença + backup + update)
- **Eventos limpos** — apenas Monitor ao vivo + Filtro + Todos os logs
- **Relatórios limpos** — alertas consolidados, removido resumo em prosa duplicado
- **Diagnósticos limpos** — secções PF verbose colapsáveis, removidos "Comandos úteis"
- **Blacklists limpos** — form "Nova categoria" agora colapsável
- **Políticas limpos** — barra de links rápidos, zona remover colapsável
- **i18n padronizado** — nomes de abas em português

### Instalacao (um comando)

```sh
fetch -o /tmp/install.sh https://raw.githubusercontent.com/pablomichelin/Layer7/main/install.sh && sh /tmp/install.sh
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
