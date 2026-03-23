# Matriz de testes — V1

Fase 9 do roadmap. Cada teste indica se pode ser executado no **CI** (GitHub Actions / Ubuntu), no **builder** (FreeBSD) ou no **appliance** (pfSense lab).

---

## 1. Build e compilação

| # | Teste | Onde | Status |
|---|-------|------|--------|
| 1.1 | `make` compila `layer7d` sem erro | CI, builder | OK |
| 1.2 | `smoke-layer7d.sh` passa | CI, builder | OK |
| 1.3 | `check-port-files.sh` sem falhas | CI, builder | OK |
| 1.4 | `make package` gera `.pkg`/`.txz` | builder | OK |

## 2. Instalação e remoção

| # | Teste | Onde | Status |
|---|-------|------|--------|
| 2.1 | `pkg add` instala sem erro | appliance | OK |
| 2.2 | `pkg info pfSense-pkg-layer7` mostra metadados | appliance | OK |
| 2.3 | `pkg info -l` lista ficheiros coerentes | appliance | OK |
| 2.4 | `pkg delete` remove sem deixar lixo grave | appliance | OK |
| 2.5 | Reinstalação após delete funciona | appliance | OK (2026-03-22) |

## 3. Daemon — ciclo de vida

| # | Teste | Onde | Status |
|---|-------|------|--------|
| 3.1 | `service layer7d onestart` sobe processo | appliance | OK |
| 3.2 | `service layer7d onestop` para processo | appliance | OK |
| 3.3 | `ps` mostra `layer7d` após start | appliance | OK |
| 3.4 | Logs com `daemon_start` / `daemon_stop` | appliance | OK |
| 3.5 | Daemon sobe após reboot (`sysrc layer7d_enable=YES`) | appliance | OK |
| 3.6 | SIGHUP reload sem crash | appliance | OK |
| 3.7 | SIGUSR1 mostra stats | appliance | OK (2026-03-22) |

## 4. Configuração e persistência

| # | Teste | Onde | Status |
|---|-------|------|--------|
| 4.1 | `layer7d -t` parse JSON sem erro | CI, builder | OK |
| 4.2 | `layer7d -t` com config inválido dá erro claro | CI | OK |
| 4.3 | `layer7d -t` lista policies e exceptions | CI | OK |
| 4.4 | Save em Settings persiste em `layer7.json` | appliance | OK |
| 4.5 | `layer7.json` sobrevive a reboot | appliance | OK |

## 5. Policy engine

| # | Teste | Onde | Status |
|---|-------|------|--------|
| 5.1 | Policy match por `ndpi_app` (BitTorrent) | CI (smoke) | OK |
| 5.2 | Policy match por `ndpi_category` (Web) | CI (smoke) | OK |
| 5.3 | Exception por host (`10.0.0.99`) overrides policy | CI (smoke) | OK |
| 5.4 | Exception por CIDR (`192.168.77.0/24`) | CI (smoke) | OK |
| 5.5 | Prioridade: higher priority ganha | CI (smoke) | OK |
| 5.6 | Default: monitor (mode=monitor), allow (mode=enforce) | CI (smoke) | OK |
| 5.7 | Policy desabilitada não casa | CI (smoke) | OK |

## 6. Enforcement PF

| # | Teste | Onde | Status |
|---|-------|------|--------|
| 6.1 | `layer7d -e -n` dry-run mostra `pfctl` sugerido | CI (smoke) | OK |
| 6.2 | `layer7d -e` executa `pfctl -T add` | appliance | OK (2026-03-22) |
| 6.3 | Monitor mode nunca chama `pfctl` | CI (código) | OK |
| 6.4 | `pfctl -t layer7_block -T show` confirma IP | appliance | OK (2026-03-22) |
| 6.5 | `pfctl -t layer7_block -T delete` remove IP | appliance | OK (2026-03-22) |
| 6.6 | Block com tabela PF real bloqueia tráfego | appliance | OK (2026-03-22, cli -e) |

## 7. Whitelist e fallback

| # | Teste | Onde | Status |
|---|-------|------|--------|
| 7.1 | Exception allow impede enforce de IP | CI (smoke) | OK |
| 7.2 | Whitelist funciona no appliance com tráfego | appliance | OK (2026-03-22) |
| 7.3 | Fallback: config ausente → daemon sobe degradado | CI (smoke) | OK |
| 7.4 | Fallback: config inválido → snapshot anterior mantido | CI (smoke) | OK |

## 8. GUI

| # | Teste | Onde | Status |
|---|-------|------|--------|
| 8.1 | Status page carrega (HTTP 200) | appliance | OK |
| 8.2 | Settings page carrega | appliance | OK |
| 8.3 | Policies page carrega | appliance | OK |
| 8.4 | Exceptions page carrega | appliance | OK |
| 8.5 | Events page carrega | appliance | OK |
| 8.6 | Diagnostics page carrega | appliance | OK |
| 8.7 | Adicionar policy via GUI | appliance | OK |
| 8.8 | Editar policy via GUI | appliance | OK |
| 8.9 | Remover policy via GUI | appliance | OK |
| 8.10 | Adicionar exception via GUI | appliance | OK |
| 8.11 | Editar exception via GUI | appliance | OK |
| 8.12 | Remover exception via GUI | appliance | OK |
| 8.13 | Input inválido não quebra página | appliance | OK (revisão código PHP) |

## 9. Observabilidade

| # | Teste | Onde | Status |
|---|-------|------|--------|
| 9.1 | Logs locais legíveis em syslog | appliance | OK |
| 9.2 | Syslog remoto recebido pelo coletor | appliance | OK (2026-03-22, nc -ul 5514 + daemon SIGUSR1) |
| 9.3 | `debug_minutes` ativa LOG_DEBUG temporário | appliance | OK (2026-03-22, log debug_boost) |
| 9.4 | Diagnostics mostra `layer7d -V` | appliance | OK |

## 10. Rollback

| # | Teste | Onde | Status |
|---|-------|------|--------|
| 10.1 | `pkg delete` remove pacote | appliance | OK |
| 10.2 | pfSense funciona normalmente após delete | appliance | OK |
| 10.3 | Reinstalar versão anterior funciona | appliance | OK (2026-03-22, reinstall via GitHub) |

---

## Resumo

| Categoria | Total | OK | Pendente |
|-----------|-------|----|----------|
| Build | 4 | 4 | 0 |
| Instalação | 5 | 5 | 0 |
| Daemon | 7 | 7 | 0 |
| Config | 5 | 5 | 0 |
| Policy engine | 7 | 7 | 0 |
| Enforcement PF | 6 | 6 | 0 |
| Whitelist/fallback | 4 | 4 | 0 |
| GUI | 13 | 13 | 0 |
| Observabilidade | 4 | 4 | 0 |
| Rollback | 3 | 3 | 0 |
| **Total** | **58** | **58** | **0** |

Todos os 58 testes OK. Validação completa em 2026-03-22 no pfSense CE 2.8.1-dev (FreeBSD 15.0-CURRENT).
