# CORTEX.md

## Projeto
Layer7 para pfSense CE

## Status atual
**No repositorio (0.0.31):** Settings grava `interfaces[]` (CSV), a GUI Layer7 foi reorganizada para um layout consistente com o padrao visual do pfSense e a operacao segura da WebGUI do appliance ficou documentada em runbook.  
**Validado em lab (2026-03-19):** build do pacote, `pkg add`, ficheiros instalados, `layer7d` a subir/parar, evidencia HTTP 200 para as paginas Layer7 no appliance e revalidacao do login/dashboard do pfSense apos incidente operacional na WebGUI.  
**Ainda pendente em lab:** `pfctl`/enforce, reboot/persistencia e eliminar a necessidade de `IGNORE_OSVERSION=yes`.

## Fase atual
Ha evidencia de pacote + daemon em lab. O gate pfSense abriu para os proximos blocos, mas ainda faltam testes de appliance para endurecimento.

## Ultima entrega
- Builder FreeBSD 15 preparado com `git`, `gh`, `ca_root_nss` e `/usr/ports`
- `smoke-layer7d.sh` validado no builder
- Port ajustado para empacotar ficheiros GUI e `priv`
- `pkg-plist` alinhado ao stage real do pacote
- Layout das paginas `Status`, `Settings`, `Policies`, `Exceptions`, `Events` e `Diagnostics` reorganizado para melhorar espacamento, navegacao e legibilidade
- Pacote instalado e removido com sucesso no pfSense de lab
- Logs do appliance com `daemon_start`, `daemon_stop` e instalacao/remocao do pacote
- Incidente operacional na WebGUI do pfSense analisado e recuperado; runbook adicionado com causas, correcao e "nao fazer novamente"

## Objetivo imediato
1. Fechar os pendentes do lab: `pfctl`, reboot e persistencia.
2. Remover ou reduzir a necessidade de `IGNORE_OSVERSION=yes` no pacote de lab.
3. Corrigir port/daemon apenas com base nas falhas observadas nesses testes.

## Proximos 3 passos
1. Validar `pfctl`/enforce no appliance.
2. Validar reboot e persistencia da configuracao/servico.
3. Integrar `layer7_on_classified_flow` no loop nDPI so depois do pacote ficar estavel em lab.

## Decisoes congeladas
- instalacao no pfSense apenas quando o pacote estiver totalmente completo
- foco em pfSense CE
- pacote open source
- distribuicao inicial por artefacto `.txz`
- lab distribution via GitHub Releases: builder FreeBSD -> GitHub Release -> pfSense teste
- sem software pago obrigatorio
- V1 sem TLS MITM universal
- V1 com modo monitor e enforce
- documentacao viva obrigatoria
- engine de classificacao: nDPI (ADR-0001)

## Riscos ativos
- assumir compatibilidade plena enquanto ainda depende de `IGNORE_OSVERSION=yes`
- mexer na WebGUI base do pfSense fora do fluxo oficial do appliance
- escopo crescer antes de reboot/persistencia/enforce

## Itens adiados
- console central
- identidade avancada
- TLS inspection seletiva
- integracao profunda com Suricata
- console multi-firewall

## Politica de trabalho
- um bloco por vez
- uma validacao por vez
- nada marcado como feito sem evidencia de lab quando o criterio for appliance
- docs no mesmo commit

## Definition of Done da V1
- pacote instalavel com evidencia
- daemon funcional com evidencia
- GUI basica com evidencia
- policy engine
- enforcement minimo
- observabilidade basica
- rollback validado
- docs completas
