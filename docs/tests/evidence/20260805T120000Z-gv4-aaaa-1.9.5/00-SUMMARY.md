# Evidência GV4 AAAA — candidato `1.9.5` (2026-08-05)

## Objectivo

Fechar gap A3: DNS AAAA → `dns_cb` → `pdst` sem `pfctl` manual.

## Pacote

- Versão: `1.9.5`
- SHA256: `9278d5d61b55aad1a4b158cf8fa49b39ed6b4d4c7ab7be36f663e2547386da6f`

## Veredicto

Código A+AAAA em `dns_observe.h` publicado. Revalidação appliance e
endurecimento seguiram em **`1.9.6`** (ver evidência
`20260805T121500Z-gv4-aaaa-1.9.6`).

## Artefactos

`01-install.txt` … `06-retest-1.9.6.txt` (raw lab).
