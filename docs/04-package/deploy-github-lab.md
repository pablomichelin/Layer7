# Deploy lab via GitHub Releases

**Objetivo:** distribuir o pacote Layer7 como artefato em GitHub Releases e permitir instalação no pfSense de lab com um único comando (fetch + sh), sem depender de repositório pkg alternativo do pfSense.

---

## Arquitetura do fluxo

```
┌─────────────────────┐     ┌──────────────────────┐     ┌─────────────────┐
│  Builder FreeBSD    │     │   GitHub Release     │     │  pfSense lab    │
│                     │     │                      │     │                 │
│  deployz.sh         │────>│  .txz                │<────│  fetch + sh     │
│  make package       │     │  .sha256             │     │  install-lab.sh │
│  gh release create  │     │  install-lab.sh      │     │  pkg add -f     │
└─────────────────────┘     └──────────────────────┘     └─────────────────┘
```

1. **Builder FreeBSD:** executa `deployz.sh`; gera `.txz`, `.sha256`, `install-lab.sh`; publica em GitHub Release.
2. **GitHub Release:** armazena os artefatos; URLs públicas para download.
3. **pfSense lab:** baixa `install-lab.sh`, executa; o script baixa `.txz`, valida checksum, instala com `pkg add -f`.

---

## Por que GitHub Release asset e não repo pkg alternativo

- **Simplicidade:** um único comando no pfSense; não exige configurar repositório pkg no firewall.
- **Independência:** o pacote não depende de repo alternativo do pfSense; evita colisão com upgrades do CE.
- **Reprodutibilidade:** artefato versionado; checksum para validação.
- **Escopo:** fluxo para lab/teste; não substitui suporte oficial do Package Manager do pfSense.

---

## Sequência: builder → GitHub Release → pfSense

### 1. No builder FreeBSD

```sh
cd /caminho/para/Layer7
sh scripts/release/deployz.sh \
  --repo-owner pablomichelin \
  --repo-name pfsense-layer7 \
  --version 0.0.31
```

O script:
- valida dependências e working tree limpo;
- executa `make package` no port;
- gera `.sha256`;
- gera `install-lab.sh` a partir do template;
- cria tag `v0.0.31` (se não existir);
- faz `git push` e `git push --tags`;
- cria GitHub Release com `.txz`, `.sha256`, `install-lab.sh`.

### 2. No pfSense (Diagnostics > Command Prompt)

```sh
fetch -o /tmp/install-lab.sh https://github.com/pablomichelin/pfsense-layer7/releases/download/v0.0.31/install-lab.sh && sh /tmp/install-lab.sh
```

O `install-lab.sh`:
- baixa o `.txz` do release;
- baixa o `.sha256` (opcional);
- valida checksum com `sha256 -c` se disponível;
- instala com `pkg add -f`;
- mostra `pkg info` e próximos passos.

### 3. Próximos passos no pfSense

```sh
cp /usr/local/etc/layer7.json.sample /usr/local/etc/layer7.json
service layer7d onestart
service layer7d status
```

---

## Comando único de instalação

Formato genérico:

```sh
fetch -o /tmp/install-lab.sh https://github.com/REPO_OWNER/REPO_NAME/releases/download/TAG/install-lab.sh && sh /tmp/install-lab.sh
```

Exemplo real:

```sh
fetch -o /tmp/install-lab.sh https://github.com/pablomichelin/pfsense-layer7/releases/download/v0.0.31/install-lab.sh && sh /tmp/install-lab.sh
```

---

## Rollback

### Remover pacote

```sh
pkg delete pfSense-pkg-layer7
```

### Reinstalar versão anterior

Usar o `install-lab.sh` do release desejado:

```sh
fetch -o /tmp/install-lab.sh https://github.com/pablomichelin/pfsense-layer7/releases/download/v0.0.30/install-lab.sh && sh /tmp/install-lab.sh
```

---

## Troubleshooting

| Sintoma | Ação |
|---------|------|
| `working tree não está limpo` | `git status`; commit ou stash antes do deploy. |
| `comando obrigatório não encontrado: gh` | Instalar GitHub CLI: `pkg install gh` ou [instalação oficial](https://cli.github.com/manual/installation). |
| `nenhum .txz encontrado` | Verificar `make package` no port; `find package/pfSense-pkg-layer7 -name '*.txz'`. |
| `gh release create` falha (release já existe) | Usar tag diferente ou `gh release delete` antes. |
| `fetch` falha no pfSense | Verificar conectividade; URL correta; release público. |
| `checksum inválido` | Re-baixar; verificar integridade do release. |
| `pkg add -f` falha | Verificar compatibilidade pfSense/FreeBSD; logs em `/var/log/pkg*.log`. |

---

## Referências

- [`scripts/release/README.md`](../../scripts/release/README.md) — uso do deployz.sh
- [`docs/04-package/validacao-lab.md`](validacao-lab.md) — validação do pacote no lab
- [ADR-0002](../03-adr/ADR-0002-distribuicao-artefato-txz.md) — distribuição por artefato `.txz`
