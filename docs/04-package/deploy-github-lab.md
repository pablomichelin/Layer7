# Deploy lab via GitHub Releases

**Papel:** documento **suplementar** — descreve a cadeia **builder → stage →
release** alinhada ao [`scripts/release/README.md`](../../scripts/release/README.md)
e ao [`MANUAL-INSTALL`](../10-license-server/MANUAL-INSTALL.md). A **fonte
operacional** para instalação a partir de release continua a ser o próprio
`README` de `scripts/release/` e o manual de instalação.

**Artefacto canónico:** **`.pkg`** (ver [ADR-0003](../03-adr/ADR-0003-hierarquia-oficial-de-distribuicao.md)).
Referências antigas a **`.txz`** são só histórico ([ADR-0002](../03-adr/ADR-0002-distribuicao-artefato-txz.md)).

**Objetivo:** permitir instalação no pfSense de lab com **um comando** no
appliance (`fetch` + `install.sh`), sem repositório pkg alternativo do pfSense.

---

## Arquitetura do fluxo (estado actual)

```
┌─────────────────────┐     ┌──────────────────────┐     ┌─────────────────┐
│  Builder FreeBSD    │     │   GitHub Release     │     │  pfSense lab    │
│                     │     │                      │     │                 │
│  deployz.sh         │────>│  .pkg                │<────│  fetch + sh     │
│  make package       │     │  .pkg.sha256         │     │  install.sh     │
│  sign / publish     │     │  install.sh          │     │  pkg add -f     │
│                     │     │  uninstall.sh        │     │                 │
│                     │     │  manifest + assinat. │     │                 │
└─────────────────────┘     └──────────────────────┘     └─────────────────┘
```

1. **Builder FreeBSD:** `sh scripts/release/deployz.sh --repo-owner … --repo-name … --version …` — corre `make package` no port, localiza o **`.pkg`**, gera checksum, `install.sh` / `uninstall.sh` e manifesto no **stage dir** (ver saída do script). A assinatura e a publicação seguem [`RELEASE-SIGNING.md`](../06-releases/RELEASE-SIGNING.md) e `publish-release.sh`.
2. **GitHub Release:** armazena os assets; URLs públicas para download.
3. **pfSense lab:** tal como em [`scripts/release/README.md`](../../scripts/release/README.md) — `fetch` do **`install.sh`** do release; o script obtém o **`.pkg`**, valida checksum quando aplicável e instala com `pkg add -f`.

---

## Por que GitHub Release como asset e não repo pkg alternativo

- **Simplicidade:** um comando no pfSense; não exige configurar repositório pkg no firewall.
- **Independência:** o pacote não depende de repo alternativo do pfSense; evita colisão com upgrades do CE.
- **Reprodutibilidade:** artefacto versionado; checksum / manifesto na trilha F1.
- **Escopo:** fluxo para lab/teste; não substitui o suporte oficial do Package Manager do pfSense.

---

## Sequência: builder → stage → publicar

### 1. No builder FreeBSD

```sh
cd /caminho/para/Layer7
sh scripts/release/deployz.sh \
  --repo-owner pablomichelin \
  --repo-name pfsense-layer7 \
  --version 1.8.11
```

O script (resumo):

- valida árvore git e dependências;
- executa `make package` no port;
- localiza o **`.pkg`** mais recente (`find … -name '*.pkg'`);
- grava **`.pkg.sha256`**, gera **`install.sh`** / **`uninstall.sh`** e manifesto no stage;
- indica os passos seguintes: assinar, verificar, **publicar** com `publish-release.sh`.

Seguir a saída do script e [`scripts/release/README.md`](../../scripts/release/README.md) para o caminho completo até ao release.

### 2. No pfSense (SSH ou Diagnostics > Command Prompt)

Alinhado ao README de release — usar o **`install.sh`** do asset publicado (versão/tag conforme o teu release):

```sh
fetch -o /tmp/install.sh https://github.com/pablomichelin/Layer7/releases/download/v1.8.3/install.sh && sh /tmp/install.sh
```

(Substituir owner/repo/tag pela combinação do teu fork e versão.)

### 3. Próximos passos no pfSense

Conforme o próprio `install.sh` e [`validacao-lab.md`](validacao-lab.md) (configuração, serviço, GUI).

---

## Comando único de instalação (formato genérico)

```sh
fetch -o /tmp/install.sh https://github.com/REPO_OWNER/REPO_NAME/releases/download/TAG/install.sh && sh /tmp/install.sh
```

---

## Rollback

### Remover pacote

```sh
pkg delete pfSense-pkg-layer7
```

### Reinstalar versão anterior

Usar o **`install.sh`** (e tag) do release desejado, como no passo 2.

---

## Troubleshooting

| Sintoma | Ação |
|---------|------|
| `working tree não está limpo` | `git status`; commit ou stash antes do `deployz.sh`. |
| `nenhum .pkg encontrado` após `make package` | No port: `find package/pfSense-pkg-layer7 -name '*.pkg'`; corrigir build. |
| `gh` / publicação falha | Ver [`scripts/release/README.md`](../../scripts/release/README.md) e credenciais GitHub. |
| `fetch` falha no pfSense | Conectividade; URL do release; asset `install.sh` publicado. |
| `checksum inválido` | Re-baixar assets; verificar manifesto / integridade do release. |
| `pkg add -f` falha | Compatibilidade pfSense/FreeBSD; logs em `/var/log/pkg*.log`. |

---

## Legado: `install-lab.sh.template`

Existe [`install-lab.sh.template`](../../scripts/release/install-lab.sh.template) —
modelo antigo de instalação por `fetch` direto ao **`.pkg`**; o fluxo suportado
para releases oficiais passa pelo **`install.sh`** gerado a partir de
[`scripts/release/install.sh`](../../scripts/release/install.sh). Manter o
template apenas como referência histórica até decisão da F6.

---

## Referências

- [`scripts/release/README.md`](../../scripts/release/README.md) — instalação, frota, deploy
- [`docs/04-package/validacao-lab.md`](validacao-lab.md) — validação no lab
  (gates F4: secções **10a** / **10b** / **11**; na **11**, cenário opcional
  multi-interface / VLAN para **BG-011** / teste **6.7**)
- [`docs/10-license-server/MANUAL-INSTALL.md`](../10-license-server/MANUAL-INSTALL.md) — instalação canónica
- [ADR-0003](../03-adr/ADR-0003-hierarquia-oficial-de-distribuicao.md) — hierarquia `.pkg`
- [ADR-0002](../03-adr/ADR-0002-distribuicao-artefato-txz.md) — histórico `.txz`
