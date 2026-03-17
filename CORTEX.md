# CORTEX.md

## Projeto
Layer7 para pfSense CE

## Status atual
**Bloco 1 concluído:** estrutura de repositório, `docs/` operacional, ADR-0001/0002, LICENSE, README de produto, esqueleto `package/` e `src/`.

## Fase atual
Fase 1 (laboratório + builder) — **próximo gate**

## Última entrega
- Árvore `docs/` (`00-overview` … `08-lab`, `changelog`)
- ADR-0001 (nDPI), ADR-0002 (`.txz`)
- `package/pfSense-pkg-layer7/` (Makefile stub)
- `src/`, `webgui/`, `scripts/`, `tests/`, `samples/`
- `LICENSE` BSD-2-Clause, `.github/pull_request_template.md`

## Objetivo imediato
1. ~~criar repositório no GitHub~~ (`pablomichelin/pfsense-layer7`)
2. ~~subir estrutura inicial~~
3. **montar builder + lab** (Bloco 2)
4. iniciar PoC do engine (Bloco 3)

## Próximos 3 passos
1. Definir host builder (FreeBSD alinhado ao CE) e documentar em `docs/08-lab/`
2. Rede de lab + snapshots (runbook mínimo)
3. PoC nDPI: binário mínimo + evento normalizado

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
