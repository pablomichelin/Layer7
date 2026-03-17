# CORTEX.md

## Projeto
Layer7 para pfSense CE

## Status atual
**Bloco 3 (código + build):** PoC `layer7_ndpi_poc` (PCAP → JSONL nDPI). Falta **operador** rodar no FreeBSD, capturar PCAP real e preencher `docs/poc/resultados-poc.template.md`.

## Fase atual
Fase 2 (PoC motor) — aceite quando resultados-poc estiver preenchido

## Última entrega
- `src/poc_ndpi/layer7_ndpi_poc.c`, `src/poc_ndpi/README.md`
- `scripts/build/build-poc-freebsd.sh`
- `docs/poc/README.md`, `docs/poc/resultados-poc.template.md`

## Objetivo imediato
1. No builder: `./scripts/build/build-poc-freebsd.sh` + testar com PCAP.
2. Documentar bem/mal/limites em `docs/poc/`.
3. **Bloco 4:** modelo de config/evento/policy.

## Próximos 3 passos
1. Validar linkagem nDPI na sua versão (tag ajustável `NDPI_TAG`).
2. Preencher resultados do PoC.
3. Esboço de `config.xml` / evento daemon (Bloco 4).

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
