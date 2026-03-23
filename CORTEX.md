# CORTEX.md

## Projeto
Layer7 para pfSense CE

## Status atual
**Validado em lab (2026-03-23):** **Enforce end-to-end funcional.** Pipeline completo nDPI → policy engine → pfctl comprovado em ambiente real:
- `pf_add_ok=7`, zero falhas — 6 IPs de dispositivos IoT (TuyaLP), rede (SSDP, MDNS) automaticamente adicionados à tabela PF `layer7_tagged`
- Exceções respeitadas (IPs .195 e .129 não tagados)
- Decisões de block/tag logadas a nível NOTICE para visibilidade operacional
- CLI `-e` valida: BitTorrent→block, HTTP→monitor, IP excecionado→allow
- Pacote v0.1.0 compilado no builder (FreeBSD 15.0-RELEASE-p4) e instalado no pfSense CE 2.8.1-dev

**Validado anteriormente (2026-03-22):** 58/58 testes OK. nDPI integrado e a classificar tráfego real. GUI completa com 6 páginas. Fleet management. Custom protocols file.

## Fase atual
Fases 0-10 completas (58/58 testes OK + nDPI + enforce real validado). Fase 11 (release V1) pronta para publicação.

## Ultima entrega
- **Enforce real validado (2026-03-23):** nDPI → policy → pfctl end-to-end, 7 adds OK, 6 IPs na tabela PF
- Logging melhorado: block/tag decisions a LOG_NOTICE (visíveis), allow/monitor a LOG_DEBUG
- Sync automático builder → pfSense via scripts lab
- GUI melhorada: Diagnostics, Events, Status com dados operacionais reais
- Fleet management: `fleet-update.sh` + `fleet-protos-sync.sh`
- Port version 0.1.0 compilado e instalado

## Objetivo imediato
**V1 PUBLICADA.** Release v0.1.0 disponível em https://github.com/pablomichelin/pfsense-layer7/releases/tag/v0.1.0

## Proximos 3 passos (pos-V1)
1. Piloto estável 24h+ (opcional — monitorar daemon running sem crash).
2. Fase 13: nDPI produção (tunning, coverage de mais protocolos).
3. Fase 14: GUI completa (gráficos, dashboards operacionais).

## Gates pendentes para V1
- [x] Fase 6: caso simples de block validado no appliance (`pfctl`) — OK 2026-03-22
- [x] Fase 6: whitelist validada no appliance — OK 2026-03-22
- [x] Fase 9: whitelist e fallback testados — OK 2026-03-22
- [x] Fase 10: nDPI integrado e a classificar trafego real — OK 2026-03-22
- [x] Fase 10: enforce end-to-end via nDPI validado — OK 2026-03-23 (pf_add_ok=7, 6 IPs)
- [ ] Fase 10: piloto estavel 24h+ sem incidente (opcional)
- [x] Fase 11: release V1 final (0.1.0) — publicada 2026-03-23 no GitHub

## Decisoes congeladas
- instalacao no pfSense apenas quando o pacote estiver totalmente completo
- foco em pfSense CE
- pacote open source
- distribuicao inicial por artefacto `.txz`
- lab distribution via GitHub Releases: builder FreeBSD -> GitHub Release -> pfSense teste; ver [`docs/04-package/deploy-github-lab.md`](docs/04-package/deploy-github-lab.md)
- sem software pago obrigatorio
- V1 sem TLS MITM universal
- V1 com modo monitor e enforce
- documentacao viva obrigatoria
- engine de classificacao: nDPI (ADR-0001)
- atualizacao nDPI: compilar 1x no builder + `fleet-update.sh` para N firewalls; custom protocols file em runtime via `fleet-protos-sync.sh`; ver [`docs/core/ndpi-update-strategy.md`](docs/core/ndpi-update-strategy.md)

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

**Trilha pos-V1 (documental):** fases **13-22** em `03-ROADMAP-E-FASES.md` (nDPI producao, GUI completa, DNS, observabilidade, identidade, TLS opt-in, IDS, escala/HA, ciclo nDPI, API local).

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
