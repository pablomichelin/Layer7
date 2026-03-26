## Layer7 v1.6.2 — Fix edição de categorias custom

Pacote Layer 7 para pfSense CE com classificacao em tempo real via nDPI.

### Correção

- Restaurado botão de editar para categorias personalizadas criadas pelo utilizador
- Ao editar, campo ID fica readonly (apenas domínios podem ser alterados)
- Categorias UT1 pré-definidas continuam sem opção de edição

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
