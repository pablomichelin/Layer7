# Release Signing — F1.2

## Finalidade

Este documento fecha a subfase **F1.2** da cadeia de confianca de release.
Ele define:

- o formato oficial do manifesto;
- quem gera, quem assina, quem publica e quem valida;
- quais artefactos compoem uma release oficial assinada;
- como verificar checksum e assinatura de forma simples e auditavel.

## Escopo desta subfase

F1.2 cobre apenas a trust chain de release:

- manifesto versionado de release;
- checksums oficiais dos assets publicados;
- assinatura destacada Ed25519 do manifesto;
- separacao builder vs signer;
- validacao minima antes da publicacao.

Nao cobre:

- blacklists, mirrors ou cache;
- fallback por componente;
- hardening do license server;
- alteracoes funcionais do pacote Layer7.

## Artefactos oficiais de uma release F1.2

Uma release oficial assinada passa a publicar:

- `pfSense-pkg-layer7-<versao>.pkg`
- `pfSense-pkg-layer7-<versao>.pkg.sha256`
- `install.sh`
- `uninstall.sh`
- `release-manifest.v1.txt`
- `release-manifest.v1.txt.sig`
- `release-signing-public-key.pem`

## Formato oficial do manifesto

Nome do ficheiro:

```text
release-manifest.v1.txt
```

Formato:

- texto plano UTF-8;
- uma chave por linha em `campo=valor`;
- bloco final de assets em linhas `asset|...`;
- ordem de campos estavel;
- assinatura sobre os bytes exactos do ficheiro.

Cabecalho minimo:

```text
manifest_version=1
release_version=1.8.0
release_tag=v1.8.0
source_repo=https://github.com/pablomichelin/pfsense-layer7
source_commit=<commit>
distribution_repo=https://github.com/pablomichelin/Layer7
builder_role=builder
builder_hostname=<hostname>
builder_generated_at_utc=2026-04-01T12:34:56Z
checksum_algorithm=sha256
signing_scheme=ed25519-openssl-pkeyutl-v1
signature_asset=release-manifest.v1.txt.sig
public_key_asset=release-signing-public-key.pem
signer_role=signer
signer_generated_at_utc=2026-04-01T12:40:00Z
public_key_fingerprint_sha256=<fingerprint>
```

Cada asset oficial entra como:

```text
asset|name=install.sh|role=installer|size=1234|sha256=<hash>
```

Regras:

- o manifesto lista checksum e tamanho dos assets oficiais publicados;
- o manifesto inclui o hash da **public key** publicada;
- o manifesto nao tenta hash de si proprio;
- a integridade do manifesto e garantida pela assinatura destacada.

## Assinatura oficial

Contrato criptografico:

- algoritmo: **Ed25519**
- formato: **assinatura destacada**
- mecanismo operacional: `openssl pkeyutl -sign -rawin`

Ficheiro de assinatura:

```text
release-manifest.v1.txt.sig
```

Public key distribuida:

```text
release-signing-public-key.pem
```

## Separacao de papeis

### Builder

Responsavel por:

- compilar o `.pkg` candidato;
- gerar `install.sh` e `uninstall.sh` versionados;
- gerar `.pkg.sha256`;
- montar o `release-manifest.v1.txt` ainda nao assinado.

Nao faz:

- assinatura do manifesto;
- custodia da private key;
- promocao automatica da release como oficial.

### Signer

Responsavel por:

- manter a **private key** de release fora do builder;
- gerar ou fornecer a public key correspondente;
- completar o manifesto com metadados do signer;
- carimbar o `install.sh` versionado com a public key oficial e o fingerprint
  esperado da release;
- assinar o manifesto;
- validar localmente assinatura e fingerprint antes de promover.

### Publisher

Responsavel por:

- publicar no GitHub Releases apenas o stage dir ja assinado;
- recusar stage dir sem manifesto, assinatura ou public key;
- manter changelog/manual/release notes sincronizados.

### Validador

Pode ser o maintainer, operador ou processo de release.
Responsavel por:

- verificar assinatura do manifesto;
- verificar fingerprint da public key;
- verificar hash e tamanho de cada asset listado.

## Fluxo operacional oficial

### 1. Builder prepara o stage dir

```sh
sh scripts/release/deployz.sh \
  --repo-owner pablomichelin \
  --repo-name Layer7 \
  --version 1.8.0
```

Saida:

- stage dir com `.pkg`, `.pkg.sha256`, `install.sh`, `uninstall.sh` e
  `release-manifest.v1.txt`

### 2. Signer assina fora do builder

```sh
sh scripts/release/sign-release.sh \
  --stage-dir /tmp/layer7-release-v1.8.0 \
  --private-key /caminho/seguro/layer7-release-ed25519.pem
```

Saida:

- `release-manifest.v1.txt.sig`
- `release-signing-public-key.pem`
- manifesto finalizado com fingerprint da public key

### 3. Validacao minima

```sh
sh scripts/release/verify-release.sh \
  --stage-dir /tmp/layer7-release-v1.8.0
```

O stage dir so pode ser promovido se:

- a assinatura validar;
- o fingerprint da public key bater com o manifesto;
- os hashes dos assets baterem com o manifesto;
- o `.pkg.sha256` bater com o hash do `.pkg`.
- o `install.sh` staged estiver coerente com a public key/fingerprint da
  release que acabou de ser assinada.

### 4. Publicacao

```sh
sh scripts/release/publish-release.sh \
  --stage-dir /tmp/layer7-release-v1.8.0 \
  --repo-owner pablomichelin \
  --repo-name Layer7 \
  --version 1.8.0
```

## Geração da chave de release

Ferramenta auxiliar:

```sh
sh scripts/release/generate-release-signing-key.sh --out-dir /caminho/seguro
```

Regras:

- executar apenas no signer;
- nunca gerar a private key no builder;
- nunca commitar a private key;
- distribuir a public key por documentação canónica, repositório e canal
  público de release.

## Limitação operacional relevante

Esta implementacao **nao gera nem commita chave de producao automaticamente**.
Isto e intencional:

- evita criar segredo operacional dentro do repositório ou do builder;
- respeita a separacao entre implementacao da trust chain e custodia humana
  da chave de release;
- deixa claro que a provisao da chave oficial e um acto operacional fora do
  builder e fora do commit.

## Comportamento em falha da F1.4

- `install.sh` publicado por tag passa a validar `release-manifest.v1.txt`,
  `release-manifest.v1.txt.sig` e o hash do `.pkg` antes da instalacao;
- se assinatura, fingerprint ou checksum divergirem, o instalador entra em
  **fail-closed** e nao chama `pkg add`;
- falhas pos-instalacao local (PF, Unbound, arranque do servico) passam a ser
  emitidas como `DEGRADED`, nunca como sucesso silencioso.

## Validacao manual com OpenSSL

Se for necessario validar sem usar `verify-release.sh`:

```sh
openssl pkeyutl -verify -rawin \
  -pubin \
  -inkey release-signing-public-key.pem \
  -sigfile release-manifest.v1.txt.sig \
  -in release-manifest.v1.txt
```

Depois conferir os `sha256` listados no manifesto contra os ficheiros
publicados no stage dir ou na release.
