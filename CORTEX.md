# CORTEX.md

## Projeto
Layer7 para pfSense CE

## Status atual
**Bloco 4 concluído:** `docs/core/` (config, evento, runtime, policy, categorias, precedência), `src/common/layer7_types.h`, `samples/config/layer7-minimal.json`.

## Fase atual
Fase 3 (modelo de dados) — **próximo: Bloco 5** skeleton do pacote pfSense

## Última entrega
- `docs/core/*.md`, `samples/config/layer7-minimal.json`
- `src/common/layer7_types.h`

## Objetivo imediato
1. Bloco 5: XML pacote, rc script, página GUI mínima, Makefile/port utilizável em lab.
2. (Paralelo) Fechar PoC resultados em `docs/poc/` se ainda pendente.

## Próximos 3 passos
1. Esqueleto `pfSense-pkg-layer7` instalável em VM lab.
2. `layer7d` stub que lê config e loga `daemon_start`.
3. Integrar tipos/enums com primeiro parse de config.

## Decisões congeladas
- foco em pfSense CE;
- pacote open source;
- distribuição inicial por artefato `.txz`;
- sem software pago obrigatório;
- V1 sem TLS MITM universal;
- V1 com modo monitor e enforce;
- documentação viva obrigatória;
- engine classificação: **nDPI** (ADR-0001).

## Riscos ativos
- escopo crescer cedo demais;
- empacotamento ficar mais complexo que o core;
- tentar resolver QUIC/ECH cedo demais;
- dedicar esforço à GUI antes do runtime.

## Itens adiados
- console central;
- identidade avançada;
- TLS inspection seletiva;
- integração profunda com Suricata;
- console multi-firewall.

## Política de trabalho
- um bloco por vez;
- uma validação por vez;
- docs no mesmo commit;
- sem refactor amplo sem necessidade.

## Definition of Done da V1
- pacote instalável
- daemon funcional
- GUI básica
- policy engine
- enforcement mínimo
- observabilidade básica
- rollback validado
- docs completas
