# Testes (referência)

## CI (GitHub Actions)

O workflow **[`.github/workflows/smoke-layer7d.yml`](../../.github/workflows/smoke-layer7d.yml)** em **push/PR** para `main` ou `master`:

- **`scripts/package/check-port-files.sh`** — `pkg-plist` alinhado a **`files/`**;
- instala toolchain no **Ubuntu**;
- executa **`scripts/package/smoke-layer7d.sh`** (compilação + `-t` + cenários **`-e -n`**).

**Limitações:** não compila o **port** `.txz`, não corre no **pfSense**, não executa **pfctl**. Gate de pacote: [`../04-package/validacao-lab.md`](../04-package/validacao-lab.md).

## Local

- `sh scripts/package/smoke-layer7d.sh` (requer `cc` + `make`).
- `make -C src/layer7d check` após `make` no mesmo diretório.

## Matriz de testes

[`test-matrix.md`](test-matrix.md) — 58 testes divididos por categoria (build, instalação, daemon, config, policy engine, enforcement, GUI, observabilidade, rollback). 47 OK, 11 pendentes no appliance.
