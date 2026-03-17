# Builder — ordem sugerida

1. Criar VM FreeBSD alinhada ao CE (ver `docs/08-lab/builder-freebsd.md`).
2. `pkg install -y git ca_root_nss`
3. Clonar o repo e trabalhar na branch `main`.
4. **Bloco 3:** compilar PoC nDPI a partir de `src/` (instruções virão no próximo bloco).

Não executar `make` em `package/pfSense-pkg-layer7/` até o port estar completo.
