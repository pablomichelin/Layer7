# CORTEX.md

## Projeto
Layer7 para pfSense CE

## Status atual
**Bloco 2 (documentação):** topologia de lab, builder FreeBSD, syslog remoto, snapshots/gate descritos em `docs/08-lab/`. **Pendente operador:** provisionar VMs e fechar checklist em `docs/08-lab/snapshots-e-gate.md`.

## Fase atual
Fase 1 — gate físico da bancada (quando checklist OK → avançar PoC)

## Última entrega
- `docs/08-lab/lab-topology.md`, `builder-freebsd.md`, `syslog-remote.md`, `snapshots-e-gate.md`, `lab-inventory.template.md`
- `scripts/build/BUILDER.md`, `scripts/lab/LAB-SETUP.md`
- `.gitignore`: `docs/08-lab/lab-inventory.md` (cópia local preenchida)

## Objetivo imediato
1. Operador: VMs builder + pfSense lab + syslog + snapshot (`snapshots-e-gate.md`).
2. **Bloco 3:** PoC nDPI no builder (`src/`).

## Próximos 3 passos
1. Fechar gate Fase 1 (checklist) ou iniciar PoC em paralelo no builder.
2. Stub CMake + submódulo ou fetch nDPI + binário mínimo de classificação.
3. Formato de evento JSON documentado.

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
