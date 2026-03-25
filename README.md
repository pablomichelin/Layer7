# Layer7 — Distribuicao Publica

Repositório público de distribuição do **Layer7 para pfSense CE**.

Este repositório publica apenas:
- `install.sh`
- `uninstall.sh`
- releases com o pacote `.pkg`

## Instalação

No pfSense, como `root`:

```sh
fetch -o /tmp/install.sh https://raw.githubusercontent.com/pablomichelin/Layer7/main/install.sh && sh /tmp/install.sh
```

Para instalar uma versão específica:

```sh
sh /tmp/install.sh --version 1.4.9
```

## Desinstalação

```sh
fetch -o /tmp/uninstall.sh https://raw.githubusercontent.com/pablomichelin/Layer7/main/uninstall.sh && sh /tmp/uninstall.sh --clean-unbound --yes
```

## Releases

Os releases deste repositório contêm o pacote `.pkg` oficial para instalação e upgrade no pfSense CE.
