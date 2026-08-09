# Comparação por fingerprint — CA tlsproxy / Root `.24` / issuer do leaf

| Campo | Valor |
|-------|--------|
| **Run ID** | `20260809T193500Z` |
| **Objectivo** | Comparar SHA1 da CA carregada pelo tlsproxy, da CA em `LocalMachine\Root` na `.24`, e da emissora do leaf |
| **Mutação** | Nenhuma (RO + demo local builder) |

## Resultado — janela B+D (evidência histórica)

| Elo | Identidade | SHA1 (norm) |
|-----|------------|-------------|
| **A)** CA carregada/exportada (`.254` → `06-mitm-ca.crt`) | `CN=Layer7-PhaseD-Lab-CA` | `25EDD8C084E813F3C5573F15A15CFDC8D35E1B3E` |
| **B)** CA instalada Root `.24` (durante B+D) | mesmo subject | `25EDD8C084E813F3C5573F15A15CFDC8D35E1B3E` |
| **C)** Emissora do peer TLS | peer **era a CA** (D0) | `25EDD8C084E813F3C5573F15A15CFDC8D35E1B3E` |

| Match | Valor |
|-------|--------|
| A ↔ B | **YES** |
| A ↔ C | **YES** (porque peer=CA self-signed) |
| B ↔ C | **YES** |

**Conclusão trust:** a falha Edge **não** foi mismatch de fingerprint CA/Root.  
A CA certa estava no Root; o browser rejeitou o **KU do peer** (`ERR_SSL_KEY_USAGE_INCOMPATIBLE`).

## Contraste Phase C vs Phase D

| CA | SHA1 |
|----|------|
| Phase C (`Layer7-PhaseA-Ephemeral-CA`) | `768AD5B382F2D950DB4273D64E122788732575D8` |
| Phase D (`Layer7-PhaseD-Lab-CA`) | `25EDD8C084E813F3C5573F15A15CFDC8D35E1B3E` |
| Match C↔D | **NO** |

Rollback `.24` removeu **ambas** (`91-rollback-24.txt`).

## Estado actual (RO)

| Host | Estado |
|------|--------|
| `.254` | `1.9.42`; MITM OFF; sem `ca.crt` em disco; sem gate tlsproxy |
| `.24` Root live | **não consultado** neste run (credencial lab `/tmp` inválida/redigida) |

## Demo D1 (builder) — comportamento esperado pós-fix

| Elo | Valor |
|-----|--------|
| CA loaded SHA1 | ≠ peer leaf SHA1 |
| Peer | `CN=mitm-lab.test`, `CA:FALSE`, issuer=`CN=…-CA` |
| `openssl verify -CAfile ca.crt peer.pem` | **OK** |

Ficheiros: `01-compare.txt`, `02-live-tlsproxy-d1.txt`.

## Critério para novo B+D

Antes do Edge: A=B=fingerprint da CA; C=issuer do **leaf** (não do peer-CA); peer ≠ CA.  
Harness: `tests/harness/mitm-activate-hang/compare-ca-fingerprints.sh`.
