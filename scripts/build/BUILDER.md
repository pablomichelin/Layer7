# Builder — ordem sugerida

1. Criar VM FreeBSD alinhada ao CE (ver `docs/08-lab/builder-freebsd.md`).
2. `pkg install -y git ca_root_nss`
3. Clonar o repo e trabalhar na branch `main`.
4. **PoC nDPI:** compilar a partir de `src/poc_ndpi/` (ver `scripts/build/build-poc-freebsd.sh`).
5. **Pacote Layer7:** `sh scripts/package/check-port-files.sh`, depois
   `sh scripts/package/smoke-layer7d.sh`, depois `make package` em
   `package/pfSense-pkg-layer7/` — ver `docs/04-package/validacao-lab.md`,
   `docs/08-lab/quick-start-lab.md` e a seção *Verificação mínima do port* em
   `docs/08-lab/builder-freebsd.md`.
