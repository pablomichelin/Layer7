# ADR-0001: Engine de classificação Layer 7 — nDPI

## Status
Aceito

## Contexto

A V1 precisa classificar tráfego (aplicação, protocolo, metadados como SNI quando disponível) sem MITM TLS universal. O ecossistema open source mais maduro para isso em C/integração kernel/userspace é **nDPI** (biblioteca derivada do OpenDPI, amplamente usada).

## Decisão

Usar **nDPI** como núcleo principal do engine de classificação na PoC e na V1, encapsulado no daemon (`layer7d`), com camada interna para confiança, eventos normalizados e limites documentados.

## Consequências

- **Positivas:** cobertura ampla de protocolos/apps; comunidade ativa; licença compatível com projeto open source.
- **Negativas:** tráfego cifrado/obfuscado (QUIC, ECH, etc.) tem limites inerentes — documentar, não “vender” cobertura total.
- **Alternativas recusadas para V1:** engine proprietária autoral; múltiplos engines em paralelo (complexidade).

## Revisão

Reavaliar na V2 apenas se houver requisito objetivo não atendível com nDPI.
