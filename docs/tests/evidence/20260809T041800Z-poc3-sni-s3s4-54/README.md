# Evidência — PoC-3 SNI bypass/block (`192.168.100.54`)

**Data:** `2026-08-09`  
**Host:** `root@192.168.100.54`  
**Binário:** `0.0.3-poc3`  
**Resultado:** **PASS** (S3 + S4 lab)

## Casos

| SNI | Política | Resultado |
|-----|----------|-----------|
| `blocked.test` | `--block-sni` | HTML **403** “Acesso bloqueado” |
| `bank.example` | `--bypass-sni` | JSON `verdict=bypass` |
| `other.test` | default | JSON `verdict=allow` |

Todas as respostas: `mitm_effective=false`.

## Limites honestos

- Bypass é **simulação de política** (ainda sem splice/upstream real).  
- S1 inline / 20.10 / produção **não** feitos.  
- **S6:** ECH não exercitado; SNI em claro no ClientHello lab.

## Isolamento

`.254` / `.234` / `.235` não tocados.
