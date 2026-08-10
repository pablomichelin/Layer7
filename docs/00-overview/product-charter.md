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

## Pack de produto (canónico)

| Documento | Papel |
|-----------|--------|
| [`pack-produto-layer7.md`](pack-produto-layer7.md) | Índice do pack (PRD + UML + catálogo + fluxo Report) |
| [`prd-layer7.md`](prd-layer7.md) | PRD — requisitos, personas, aceitação, riscos |
| [`uml-layer7.md`](uml-layer7.md) | UML — classes e sequências (Mermaid) |
| [`catalogo-funcionalidades.md`](catalogo-funcionalidades.md) | Catálogo com status Produção / Lab / NO-GO |
