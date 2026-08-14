# Builder FreeBSD (requisitos)

## Objetivo

Host onde se compila codigo nativo (nDPI, PoC, futuro pacote) com ABI/libs proximas ao pfSense CE alvo.

## Alinhamento de versao

1. Anotar a versao do pfSense CE do lab (ex.: 2.7.x).
2. Consultar a versao base FreeBSD dessa release (documentacao Netgate/pfSense).
3. Preferir VM FreeBSD com a mesma major (ex.: FreeBSD 14.x se o CE for 14.x).

> Desvio de major aumenta risco de binarios incompativeis no appliance.

## VM / host minimo sugerido

| Recurso | Minimo razoavel |
|---------|-----------------|
| vCPU | 2 |
| RAM | 4 GiB |
| Disco | 32 GiB |
| Rede | 1 NIC (NAT ou bridge) para `pkg` e Git |

## Pacotes iniciais (no builder)

```sh
pkg install -y git ca_root_nss
```

Opcional cedo na PoC:

```sh
pkg install -y cmake pkgconf gcc
```

Arvore `ports` ou `poudriere` entram quando o port `pfSense-pkg-layer7` estiver maduro (Bloco 5+).

## Fluxo de trabalho

1. Clonar: `git clone https://github.com/pablomichelin/pfsense-layer7.git`
2. Desenvolvimento da PoC em `src/` (Bloco 3).
3. Binarios de teste no builder; instalacao no pfSense so via pacote `.pkg`
   quando o empacotamento existir.

### Compilar so o `layer7d` (sem `make package`)

```sh
cd src/layer7d
make
./layer7d -t -c ../../samples/config/layer7-minimal.json
make check   # -V + parse sample (opcional)
make clean
```

Flags alinhadas ao port; `version.str` em `src/layer7d/` define a string de `layer7d -V`. O script `scripts/package/smoke-layer7d.sh` usa o mesmo Makefile com `VSTR_DIR` temporario e binario `layer7d-smoke`.

## Seguranca

- Builder com acesso GitHub (HTTPS ou SSH).
- Nao reutilizar chaves de producao no lab sem isolamento.
- Builder de lab atualmente validado: FreeBSD 15 em `192.168.0.129` com acesso administrativo liberado para blocos de build e validacao.
- Credenciais do builder devem ficar apenas em inventario local ou contexto operacional; nao versionar senha no repositorio.

## Verificacao minima do port (no builder)

Apos `git pull` no clone do repositorio (caminho tipico, ex.: `/root/pfsense-layer7`):

```sh
sh scripts/package/check-port-files.sh
sh scripts/package/smoke-layer7d.sh
cd package/pfSense-pkg-layer7 && make clean 2>/dev/null || true && make package
```

O `smoke-layer7d.sh` recusa propositadamente correr em **macOS**; no portatil
pode correr `check-port-files.sh` local. O smoke de compilacao e testes
leves (Linux) repete no GitHub Actions (`.github/workflows/smoke-layer7d.yml`).

Isto **não** substitui a evidência no **appliance** nem o fecho formal das
subfases **F4**: roteiros **10a**, **10b** e **11** em
[`../04-package/validacao-lab.md`](../04-package/validacao-lab.md) (início:
*Gates oficiais F4*), com a [`test-matrix.md`](../tests/test-matrix.md) e o
[`checklist-mestre.md`](../02-roadmap/checklist-mestre.md) quando o projecto
exigir esse gate.

### Publicar no canal publico (obrigatorio apos cada build de pacote)

O botao **Verificar actualizacao** da GUI so le
`pablomichelin/Layer7/releases/latest`. Apos `make package` e validacao:

```sh
# No Mac (com gh autenticado) ou no builder com token
gh release create "v1.8.11_XX" \
  pfSense-pkg-layer7-1.8.11_XX.pkg \
  pfSense-pkg-layer7-1.8.11_XX.pkg.sha256 \
  --repo pablomichelin/Layer7 \
  --title "Layer7 v1.8.11_XX" \
  --notes "Ver CHANGELOG no repo pfsense-layer7"
```

Confirmar: `curl -s https://api.github.com/repos/pablomichelin/Layer7/releases/latest | grep tag_name`

## Anti-pirataria / passo 30.2 — pubkey fora do git

Builder de produto actual: **`192.168.100.12`** (FreeBSD 15;
`/root/pfsense-layer7`). Credenciais operacionais: ver `AGENTS.md`.

### SoT da pubkey de produção (decisão 4)

| Ficheiro | Papel |
|----------|--------|
| `/root/layer7-build-secrets/l7_ed25519_pubkey.hex` | 64 nibbles hex (32 bytes) — **SoT** |
| `/root/layer7-build-secrets/l7_ed25519_pubkey.inc` | fragmento C de referência |
| `/root/layer7-build-secrets/backup/<RUNID>/` | cópias antes de mudanças |

Antes de cada `make package` de produção:

```sh
cd /root/pfsense-layer7
git pull --ff-only origin main
sh scripts/package/verify-prod-pubkey.sh
cd package/pfSense-pkg-layer7
make clean && DISABLE_LICENSES=yes make package DISABLE_VULNERABILITIES=yes
```

O script **FAIL** se `src/layer7d/license.c` **ou** o PEM do port
(`license-signing-public-key.pem`) divergir do SoT, ou se o PEM
divergir do array C (protege GA1.8 / P3-6). A validação C vs SoT
mantém-se. Selftest local: `sh tests/functional/test_verify_prod_pubkey.sh`
(fixture `L7_PROD_PUBKEY_HEX_FILE`; sem o SoT do builder).

**Não** usar o antigo fluxo `git stash` → `pull` → `checkout stash -- license.c Makefile`
→ `stash drop`. Esse fluxo era a causa do achado A-09 e foi retirado em `30.2`.

Evidência de fecho: `docs/tests/evidence/20260810T231840Z-30.2-builder-pubkey/`.

## Acesso SSH (automacao e scripts)

Sessoes nao interactivas e ferramentas com `BatchMode` necessitam de
**chave publica** autorizada no `authorized_keys` do builder. Se vir
`Permission denied (publickey)`, falta essa chave (ou o host nao e o
builder certo). Isto e independente de ter password para login
interactivo: scripts nao devem depender de password em claro.
