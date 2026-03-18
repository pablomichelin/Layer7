# CORTEX.md

## Projeto
Layer7 para pfSense CE

## Status atual
**No repositório (0.0.31):** **Settings** grava **`interfaces[]`** (CSV).  
**Anterior (0.0.30):** interfaces só leitura.  
**Ainda não validado em lab:** `pkg add` + **teste de exec pfctl** no appliance — [`docs/04-package/validacao-lab.md`](docs/04-package/validacao-lab.md).

## Fase atual
Há **código testável no builder** (`./layer7d -t -c samples/...` ou smoke). Gate pfSense mantém-se aberto até evidência no lab.

## Última entrega
- **README** — estado alinhado (daemon, pacote, GUI, CI; lab pendente).
- **Guia Windows** — `docs/08-lab/guia-windows.md` + `check-port-files.ps1` para dev em Windows.
- **Quick-start lab** — `docs/08-lab/quick-start-lab.md` (fluxo builder→pfSense→validação).
- **Loop main.c** — comentário TODO(Fase 13) no ponto de integração nDPI→`layer7_on_classified_flow`.
- **BUILDER.md** — port pronto para `make package`; referência validacao-lab e quick-start.
- **CI** — job `check-windows` (PowerShell) em `smoke-layer7d.yml`.
- **Runbooks** — link para validacao-lab e quick-start em `docs/05-runbooks/README.md`.

## Objetivo imediato
1. No FreeBSD: `sh scripts/package/smoke-layer7d.sh` e/ou `make package` + procedimento em `validacao-lab.md`.
2. Corrigir port/daemon só com base em falhas observadas no lab.

## Próximos 3 passos
1. Fechar evidência lab (pacote + serviço + opcional §6b pfctl).
2. No loop nDPI: chamar `layer7_on_classified_flow` (já implementado; hoje só **`-e`** / **`-e -n`** no CLI).
3. nDPI no daemon (só após pacote estável no lab).

## Decisões congeladas
- **instalação no pfSense apenas quando o pacote estiver totalmente completo** — não colocar no firewall antes de estar totalmente desenvolvido;
- foco em pfSense CE;
- pacote open source;
- distribuição inicial por artefato `.txz`;
- **lab distribution via GitHub Releases** — fluxo builder FreeBSD → GitHub Release → pfSense teste; comando único `fetch + sh`; não substitui Package Manager oficial; ver [`docs/04-package/deploy-github-lab.md`](docs/04-package/deploy-github-lab.md);
- sem software pago obrigatório;
- V1 sem TLS MITM universal;
- V1 com modo monitor e enforce;
- documentação viva obrigatória;
- engine classificação: **nDPI** (ADR-0001).

## Riscos ativos
- assumir GUI/menu/serviço OK sem corrida no lab;
- escopo crescer antes da validação;
- empacotamento ficar mais complexo que o core.

## Itens adiados
- console central;
- identidade avançada;
- TLS inspection seletiva;
- integração profunda com Suricata;
- console multi-firewall.

**Trilha pós-V1 (documental):** fases **13–22** em `03-ROADMAP-E-FASES.md` (nDPI produção, GUI completa, DNS, observabilidade, identidade, TLS opt-in, IDS, escala/HA, ciclo nDPI, API local).

## Política de trabalho
- um bloco por vez;
- uma validação por vez;
- nada marcado como “feito” sem evidência de lab quando o critério for appliance;
- docs no mesmo commit.

## Definition of Done da V1
- pacote instalável *(com evidência)*
- daemon funcional *(com evidência)*
- GUI básica *(com evidência)*
- policy engine
- enforcement mínimo
- observabilidade básica
- rollback validado
- docs completas
