# S13 — Divergência de fingerprint (NIC) — PASS

| Campo | Valor |
|-------|-------|
| `run_id` | `20260804T212000Z-ondaC-s13-drift-PASS` |
| Appliance | `192.168.100.254` |
| Pacote | `1.8.11_68` |
| Rollback | Veeam disponível; drift **revertido** no mesmo run (`ifconfig em0 ether` restore) |

## Método de drift

Alteração **reversível** da MAC de `em0` (interface sem IP LAN — `vmx0` mantém `192.168.100.254`):

- Antes: `00:50:56:88:e1:33`
- Durante teste: `02:11:22:33:44:55`
- Restauro: MAC original

`kern.hostuuid` **inalterado** — apenas componente MAC do fingerprint mudou (cenário válido F3.2).

## Resultados

| Fase | Fingerprint (prefixo) | `license_valid` |
|------|-------------------------|-----------------|
| Baseline | `e31560f5…` | `true` |
| Pós-drift | `082fba6d…` | `false` — `hardware mismatch` |
| Activate online | novo HW | HTTP **409** `Hardware ID nao corresponde.` |
| Pós-restore | `e31560f5…` | `true` (Systemup) |

## Veredicto

**PASS** — comportamento alinhado com `f3-fingerprint-e-binding.md` e matriz F3.

## Artefactos

- `50-appliance-cli-01.txt` — baseline (referência run `233500Z-veeam`)
- `50-appliance-cli-02.txt` — pós-drift
- `40-appliance-activate-attempt.txt` — 409 online
- `50-appliance-cli-03-restore.txt` — pós-restore
- `S13-transcript.txt` — resumo CLI
