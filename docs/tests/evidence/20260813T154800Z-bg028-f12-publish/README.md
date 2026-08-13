# Evidência — BG-028 Fase 1 / primeira publish F1.2 (`v1.9.58`)

**Run ID:** `20260813T154800Z-bg028-f12-publish`  
**Data:** `2026-08-13`  
**Objectivo:** activar ADR-0023 Fase 1 (custódia humana + 1ª release assinada).  
**Fora de escopo:** produção `.254` / GA5.9; license-server; Cloudflare/DNS; tags antigas.

## Gates ADR-0023 Fase 1

| # | Critério | Resultado |
|---|----------|-----------|
| 1 | Par Ed25519 em custódia humana (fora do git e do builder) | **PASS** |
| 2 | `sign-release.sh` na release de transição | **PASS** |
| 3 | `verify-release.sh` no stage dir | **PASS** |
| 4 | `MANUAL-INSTALL.md` reactiva `install.sh` / `uninstall.sh` | neste bloco |
| 5 | PORTVERSION publicada com manifesto + `.sig` no GitHub Release | neste bloco |

## Artefacto

| Campo | Valor |
|-------|--------|
| Pacote | `pfSense-pkg-layer7-1.9.58.pkg` |
| SHA256 | `8b4a2dc6ecd62c126222186112ea80ee75407d35c35049f94631980092108d3d` |
| Tamanho | 3305770 bytes |
| Source commit | `1ce0ba3c98e28f9e7c12e226aa8d3c2099daba7d` |
| Builder | `192.168.100.12` (`freebsd`) |
| Fingerprint SHA256 da chave pública | `d26e3f007e81298bad910f99dd62a22e2109740158b3b3c7f4e79490bdc5a998` |
| Stage | `/tmp/layer7-release-v1.9.58` (fora do git) |

## Assets F1.2 no stage (privada **não** incluída)

- `pfSense-pkg-layer7-1.9.58.pkg`
- `pfSense-pkg-layer7-1.9.58.pkg.sha256`
- `install.sh` (carimbado fail-closed)
- `uninstall.sh`
- `release-manifest.v1.txt`
- `release-manifest.v1.txt.sig`
- `release-signing-public-key.pem`

## Notas de segurança

- A chave privada **não** foi copiada para o builder nem para o repositório.
- O teste ingénuo de prefixo de path (`Layer 7` vs `Layer 7 License`) foi
  evitado com match `$ROOT/` (barra final).
- Produção observada `.254` permanece **`1.9.54`** neste bloco.
