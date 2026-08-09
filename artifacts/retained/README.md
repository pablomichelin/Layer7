# Pacotes locais retidos (rede de segurança)

Cópias locais **fora** do GitHub Releases. Os `.pkg` estão no `.gitignore`.
Fonte canónica de download: `https://github.com/pablomichelin/Layer7/releases`.

| Artefacto | Papel |
|-----------|--------|
| `1.9.41` | lab / `latest` (20.10b correctivo + 20.11 GI2/GI3) |
| `1.9.40` | NO-GO histórico (auditoria F1–F6) — não promover |
| `1.9.39` | rollback 20.10a |
| `1.9.38` | rollback intenção/IPC (20.9) |
| `1.9.37` | rollback scaffolding (20.8) |
| `1.9.8` | referência enforce produção |
| `1.9.0` | rollback enforce |
| `1.8.11_69` | rollback histórico |
| `1.8.11_24` | referência enforce histórica (G2–G7) |

Backup builder (`license.c` sensível): `../Bkp Freebsd 15/` — **não** commitar.

Limpeza `2026-08-09`: removidos `.pkg` intermediários de `artifacts/` e `tmp-release/`.
