# Testes (referência)

## CI (GitHub Actions)

O workflow **[`.github/workflows/smoke-layer7d.yml`](../../.github/workflows/smoke-layer7d.yml)** em **push/PR** para `main` ou `master`:

- **`scripts/package/check-port-files.sh`** — `pkg-plist` alinhado a **`files/`**;
- instala toolchain no **Ubuntu**;
- executa **`scripts/package/smoke-layer7d.sh`** (compilação + `-t` + cenários **`-e -n`**).

**Limitações:** não compila o **port** `.pkg`, não corre no **pfSense**, não executa **pfctl**. Gate de pacote: [`../04-package/validacao-lab.md`](../04-package/validacao-lab.md).

## Local

- `sh scripts/package/smoke-layer7d.sh` (requer `cc` + `make`).
- `make -C src/layer7d check` após `make` no mesmo diretório.

## Matriz de testes

[`test-matrix.md`](test-matrix.md) — 70 testes divididos por categoria
(build, instalação, daemon, config, policy engine, enforcement, GUI,
observabilidade, rollback e addendum de licenciamento/activação da F3).
Estado actual: 66 OK e 4 pendentes em appliance/lab para fechar a validacao
manual de grace/offline, expiracao/revogacao com `.lic` ja emitido e da
matriz real de fingerprint em reinstall, troca de NIC, clone de VM, restore
e migracao.
