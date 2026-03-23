# CORTEX.md

## Projeto
Layer7 para pfSense CE

## Status atual
**Versão: 0.2.5 — Hostname e destino nos eventos**

Pacote funcional com motor de políticas granulares por interface, listas de IPs/CIDRs e selecção de apps nDPI na GUI. Pronto para teste em pfSense real.

**Validação lab (2026-03-23):** Enforce end-to-end funcional — pipeline nDPI → policy engine → pfctl comprovado:
- `pf_add_ok=7`, zero falhas — 6 IPs automaticamente adicionados à tabela PF
- Excepções respeitadas (IPs .195 e .129 não tagados)
- Decisões block/tag logadas a NOTICE
- CLI `-e` valida: BitTorrent→block, HTTP→monitor, IP excepcionado→allow

**Hostname e destino nos eventos (v0.2.5):**
- logs passam a incluir `dst=` do fluxo
- logs passam a incluir `host=` quando houver correlacao DNS

**Monitor ao vivo na GUI (v0.2.4):**
- aba `Events` com atualizacao automatica dos ultimos eventos
- botoes para pausar e atualizar manualmente

**Log local do daemon (v0.2.3):**
- `layer7d` grava eventos em `/var/log/layer7d.log`
- GUI Events/Diagnostics leem o log local diretamente

**Labels amigaveis de interface (v0.2.2):**
- GUI mostra a descricao configurada da interface no pfSense
- fallback seguro para label padrao quando nao houver descricao

**Empacotamento autocontido (v0.2.1):**
- `layer7d` linkado com `libndpi.a` no builder
- pacote `.pkg` sem dependência de `libndpi.so` no pfSense
- script de release valida `ldd` do binário staged

**Motor Multi-Interface (v0.2.0):**
- Políticas por interface (LAN, WIFI, ADMIN, etc.)
- Listas de IPs/CIDRs por política e excepção
- ~350 apps/categorias nDPI seleccionáveis na GUI
- Daemon `--list-protos` para enumeração dinâmica
- GUI completa com 6 páginas

## Fase atual
Fases 0-10 completas. Motor multi-interface v0.2.0 implementado. Próximo: teste em pfSense real de produção.

## Ultima entrega
- **v0.2.1 — Empacotamento autocontido (2026-03-23):**
  - build do port usa `/usr/local/lib/libndpi.a`
  - `update-ndpi.sh` aborta se o binário final ainda depender de `libndpi.so`
  - pacote validado em FreeBSD 15 lab sem dependência runtime de nDPI
- **v0.2.5 — Hostname e destino nos eventos (2026-03-23):**
  - `flow_decide` passa a mostrar `dst=` e `host=`
  - `host=` e derivado por correlacao DNS observada na propria captura
- **v0.2.4 — Monitor ao vivo na GUI (2026-03-23):**
  - aba `Events` ganha monitor ao vivo com auto-refresh
  - filtro atual da pagina tambem se aplica ao monitor ao vivo
- **v0.2.3 — Log local do daemon (2026-03-23):**
  - `layer7d` passa a gravar eventos em `/var/log/layer7d.log`
  - GUI Events e Diagnostics deixam de depender do syslog do pfSense
- **v0.2.2 — Labels amigaveis de interface (2026-03-23):**
  - GUI Settings passa a mostrar a descricao configurada da interface
  - GUI Policies e Exceptions reutilizam o mesmo label amigavel
- **v0.2.0 — Motor Multi-Interface (2026-03-18):**
  - GUI Settings: checkboxes dinâmicos de interfaces pfSense
  - Políticas: `interfaces[]`, `match.src_hosts[]`, `match.src_cidrs[]`
  - Excepções: múltiplos `hosts[]`/`cidrs[]` + `interfaces[]`
  - `layer7d --list-protos`: JSON com protocolos/categorias nDPI
  - GUI Policies: multi-select com pesquisa para apps nDPI
  - Policy engine filtra por interface, IP e CIDR de origem
- **Documentação: Guia Completo** — `docs/tutorial/guia-completo-layer7.md` (18 secções)
- **Documentação GitHub actualizada** — README, CORTEX, CHANGELOG, checklist, roadmap

## Objetivo imediato
**Teste em pfSense real** — validar v0.2.5 em ambiente de produção.

## Proximos 3 passos
1. Testar v0.2.5 em pfSense real (hostnames + monitor ao vivo)
2. Piloto estável 24h+ com regras multi-interface
3. Ajustes com base no feedback do teste real

## Gates pendentes para V1
- [x] Fase 6: block validado no appliance (`pfctl`) — OK 2026-03-22
- [x] Fase 6: whitelist validada no appliance — OK 2026-03-22
- [x] Fase 9: whitelist e fallback testados — OK 2026-03-22
- [x] Fase 10: nDPI integrado e a classificar tráfego real — OK 2026-03-22
- [x] Fase 10: enforce end-to-end via nDPI validado — OK 2026-03-23
- [ ] Fase 10: piloto estável 24h+ sem incidente
- [x] Fase 11: release V1 final (0.1.0) — publicada 2026-03-23
- [x] Motor multi-interface v0.2.0 — implementado 2026-03-18

## Decisoes congeladas
- foco em pfSense CE
- pacote open source
- distribuição por artefacto `.pkg`
- lab distribution via GitHub Releases
- sem software pago obrigatório
- V1 sem TLS MITM universal
- V1 com modo monitor e enforce
- documentação viva obrigatória
- engine de classificação: nDPI (ADR-0001)
- actualização nDPI: compilar 1x no builder + `fleet-update.sh`; custom protocols em runtime via `fleet-protos-sync.sh`
- políticas granulares: por interface + por IP/CIDR + por app/categoria nDPI

## Riscos ativos
- assumir compatibilidade plena enquanto depende de `IGNORE_OSVERSION=yes`
- mexer na WebGUI base do pfSense fora do fluxo oficial
- primeiro teste real em produção pendente

## Itens adiados
- console central
- identidade avançada
- TLS inspection selectiva
- integração profunda com Suricata
- console multi-firewall

**Trilha pós-V1 (documental):** fases **13-22** em `03-ROADMAP-E-FASES.md`.

## Politica de trabalho
- um bloco por vez
- uma validação por vez
- nada marcado como feito sem evidência de lab
- docs no mesmo commit

## Definition of Done da V1
- [x] pacote instalável com evidência
- [x] daemon funcional com evidência
- [x] GUI básica com evidência
- [x] policy engine
- [x] enforcement mínimo
- [x] observabilidade básica
- [ ] rollback validado em produção
- [x] docs completas
