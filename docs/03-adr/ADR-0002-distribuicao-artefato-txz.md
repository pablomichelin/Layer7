# ADR-0002: Distribuição V1 — artefato `.txz`

## Status
Aceito

## Contexto

Instalar “direto do GitHub” no pfSense aumenta risco de colisão com upgrades do sistema e dificulta reprodutibilidade. O ecossistema pfSense/FreeBSD espera pacotes como artefatos versionados.

## Decisão

1. **Fonte:** código e documentação no GitHub; releases com checksums.
2. **Instalação V1:** artefato **`.txz`** (pacote pfSense) gerado em builder controlado; instalação em ambiente de lab/produção só após validação.
3. **Fluxo:** desenvolver → versionar → build reproduzível → publicar artefato → instalar no firewall.

## Consequências

- **Positivas:** rollback claro; alinhamento com prática pfSense; menos surpresa em upgrade do CE.
- **Negativas:** exige manter pipeline/build documentado (ver Bloco 2 do plano).

## Alternativas recusadas para V1

- Instalação única via `git clone` no appliance.
- Dependência de repositório binário pago obrigatório.
