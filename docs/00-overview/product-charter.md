# Product charter (V1)

**Pacote:** `pfSense-pkg-layer7` · **Daemon:** `layer7d`

## Problema

O pfSense CE não oferece, de fábrica, classificação Layer 7 orientada a aplicação com políticas simples e GUI própria.

## Objetivo da V1

- Classificação L7 (aplicação/protocolo/categoria);
- Políticas: `monitor`, `tag`, `allow`, `block`;
- Enforcement: PF (aliases/tables) + política por host/domínio quando aplicável;
- GUI no padrão pfSense;
- Logs locais + syslog;
- Instalação/upgrade/rollback previsíveis.

## Fora da V1 (congelado)

- MITM TLS universal;
- console central multi-firewall;
- equivalência com vendors enterprise.

## Documento expandido

Detalhes completos: [`../../01-VISAO-GERAL-E-ESCOPO.md`](../../01-VISAO-GERAL-E-ESCOPO.md) (raiz do repositório).
