# Release Notes — v__VERSION__

**Data:** __DATA__
**Tag:** `v__VERSION__`
**Artefato:** `pfSense-pkg-layer7-__VERSION__.pkg`
**Checksum:** `pfSense-pkg-layer7-__VERSION__.pkg.sha256`
**Manifesto:** `release-manifest.v1.txt`
**Assinatura:** `release-manifest.v1.txt.sig`
**Public key:** `release-signing-public-key.pem`

---

## Resumo

Descrever em 1–3 frases o que esta release entrega.

---

## Novidades

- item 1
- item 2

## Correções

- item 1

## Alterações

- item 1

---

## Instalação

### Primeira instalação (Diagnostics > Command Prompt)

```sh
fetch -o /tmp/install.sh https://github.com/REPO_OWNER/REPO_NAME/releases/download/v__VERSION__/install.sh && sh /tmp/install.sh
```

### Upgrade de versão anterior

```sh
pkg delete pfSense-pkg-layer7
```

Depois executar o comando de instalação acima com a nova versão.

### Pós-instalação

```sh
cp /usr/local/etc/layer7.json.sample /usr/local/etc/layer7.json
service layer7d onestart
service layer7d status
```

---

## Rollback

```sh
pkg delete pfSense-pkg-layer7
```

Para reinstalar versão anterior, usar `install.sh` versionado do release desejado.

Ver [`docs/05-runbooks/rollback.md`](../05-runbooks/rollback.md).

---

## Compatibilidade

- pfSense CE: __VERSAO_PFSENSE__
- FreeBSD builder: __VERSAO_FREEBSD__

---

## Limitações conhecidas

- Classificação nDPI ainda não integrada no loop do daemon (Fase 13)
- Fluxo de lab/teste; não substitui Package Manager oficial do pfSense
