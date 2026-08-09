# Revisão gerencial — S3 / S6 (2026-08-09)

## Achado

O commit documental `8939ddb` classificou **20.11 / GI3 / S3 / S6** como PASS.
Isso **conflita** com:

- **ADR-0026 S3:** «Funcional em ≥ 1 browser Windows corporativo»
- **Desenho S3:** browser Windows + CA + HTML legível
- **Prova real da corrida:** `curl --cacert` em Linux `.54`

`curl` **não** é browser. Limite documentado de ECH **não** é PASS experimental.

## Correcção

| Item | Antes (`8939ddb`) | Depois (esta rev.) |
|------|-------------------|--------------------|
| 20.11 | PASS | **PARCIAL / NO-GO fecho** |
| GI2 | PASS | **PASS** (mantido) |
| GI3 | PASS | **PENDENTE** (S3/GI3.1) |
| S3 | PASS | **PENDENTE** |
| S6 | PASS (limite) | **NA / limite** |

Histórico `8939ddb` **preservado**; esta revisão supersede a classificação.
