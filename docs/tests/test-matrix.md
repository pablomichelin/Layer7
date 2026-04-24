# Matriz de testes — V1 + addendum F3 + F4.3 (enforcement)

Fase 9 do roadmap. Cada teste indica se pode ser executado no **CI** (GitHub Actions / Ubuntu), no **builder** (FreeBSD) ou no **appliance** (pfSense lab). O ponto **6.7** liga-se ao roteiro F4.3 (BG-011) em
`docs/04-package/validacao-lab.md` (secção 11).

---

## 1. Build e compilação

| # | Teste | Onde | Status |
|---|-------|------|--------|
| 1.1 | `make` compila `layer7d` sem erro | CI, builder | OK |
| 1.2 | `smoke-layer7d.sh` passa | CI, builder | OK |
| 1.3 | `check-port-files.sh` sem falhas | CI, builder | OK |
| 1.4 | `make package` gera `.pkg` oficial | builder | OK |

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
| 6.7 | DNS forcado (`force_dns`): `pfctl -a natrules/layer7_nat -s nat` mostra `rdr` coerente após reload PF | appliance | Pendente (F4.3; `validacao-lab` sec. 11) |

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

## 11. Licenciamento e activação

| # | Teste | Onde | Status |
|---|-------|------|--------|
| 11.1 | Primeira activação válida fixa `hardware_id` uma única vez e grava `activated_at` | revisão de código/backend | OK (2026-04-01) |
| 11.2 | Re-activação do mesmo hardware não rebinda a licença e preserva o primeiro `activated_at` | revisão de código/backend | OK (2026-04-01) |
| 11.3 | Corrida de primeira activação com `hardware_id` diferente mantém bind único e rejeita o segundo com `409` | revisão de código/backend | OK (2026-04-01) |
| 11.4 | Grace local de `14` dias continua funcional no daemon com `.lic` expirado já emitido | appliance | Pendente (F3.6; ver matriz detalhada) |
| 11.5 | Activação online de licença expirada continua a falhar fechado sem quebrar a licença local já emitida | appliance | Pendente (F3.6; ver matriz detalhada) |
| 11.6 | Fingerprint mantém previsibilidade documentada em reinstall, troca de NIC, clone de VM, restore, migracao de hypervisor e appliance com multiplas NICs | appliance/lab | Pendente (F3.6; ver matriz detalhada) |
| 11.7 | Renovação + re-activação reemite `.lic` actualizado sem quebrar o bind existente | appliance | Pendente (F3.6; ver matriz detalhada) |
| 11.8 | Estado efectivo (`active` / `expired` / `revoked`) permanece coerente entre `activate`, `licenses`, `customers` e `dashboard` | revisão de código/backend | OK (2026-04-01) |
| 11.9 | Download administrativo de licença efectivamente expirada falha fechado | revisão de código/backend | OK (2026-04-01) |
| 11.10 | Download administrativo de licença revogada falha fechado | revisão de código/backend | OK (2026-04-01) |
| 11.11 | Revogação no servidor não invalida imediatamente um `.lic` já emitido em appliance offline | revisão de código/backend + daemon | OK (2026-04-01) |
| 11.12 | Rebind administrativo permanece bloqueado na F3.3 por risco de `.lic` antigo continuar válido offline | revisão arquitectural/F3.3 | OK (2026-04-01) |
| 11.13 | Update administrativo bloqueia mudança de `customer_id` em licença activada/bindada com `409` | revisão de código/backend | OK (2026-04-01) |
| 11.14 | Update administrativo continua a permitir mudança de `customer_id` antes do bind/activação | revisão de código/backend | OK (2026-04-01) |
| 11.15 | Renovação por `expiry` continua permitida em licença bindada sem alterar o bind | revisão de código/backend | OK (2026-04-01) |
| 11.16 | Auditoria de `license_updated` passa a registar campos alterados e flags de bind/activação | revisão de código/backend | OK (2026-04-01) |
| 11.17 | Activação pública passa a auditar emissão do artefacto com `flow` e `emission_kind` | revisão de código/backend | OK (2026-04-01) |
| 11.18 | Download administrativo passa a auditar hashes e contexto do artefacto devolvido | revisão de código/backend | OK (2026-04-01) |
| 11.19 | Backend distingue `initial_issue` de `reactivation_reissue` no fluxo público sem mudar `{ data, sig }` | revisão de código/backend | OK (2026-04-01) |
| 11.20 | O sistema continua sem enforcement de "artefacto mais recente único", mas a limitação fica formalizada e rastreável | revisão arquitectural/F3.5 | OK (2026-04-01) |

### Addendum operativo da F3.2

| Cenario manual a observar | Expectativa conservadora actual |
|---------------------------|---------------------------------|
| Reinstalacao sem troca de hardware | manter bind apenas se `kern.hostuuid` e MAC efectiva permanecerem iguais |
| Troca de NIC / MAC | tender a gerar fingerprint novo e exigir accao administrativa |
| Reordenacao de interfaces | pode trocar a primeira NIC elegivel e provocar falso bloqueio |
| Clone de VM | tratar como reactivacao suspeita por defeito |
| Restore de snapshot | aceitar apenas se o fingerprint resultante continuar igual |
| Migracao de hypervisor | nao assumir compatibilidade sem validar fingerprint antes e depois |

### Addendum operativo da F3.3

| Cenario manual a observar | Expectativa conservadora actual |
|---------------------------|---------------------------------|
| Licenca expirada no servidor com `.lic` ja emitido | activacao/download negados no servidor; daemon ainda pode aceitar ate `expiry + 14 dias` |
| Licenca revogada no servidor com `.lic` ja emitido | activacao/download negados no servidor; daemon nao corta offline imediatamente |
| Servidor indisponivel com `.lic` local valido | appliance continua localmente; activacao nova falha |
| Appliance offline dentro do grace | enforce continua localmente com `license_grace=true` |
| Appliance offline apos o grace | daemon invalida licenca e cai para monitor-only |
| Rebind administrativo com `.lic` antigo em campo | continua inseguro e fora de escopo enquanto nao houver politica para invalidacao offline |

### Addendum operativo da F3.4

| Cenario manual a observar | Expectativa conservadora actual |
|---------------------------|---------------------------------|
| Mudar `customer_id` antes do bind | permitido no CRUD normal |
| Mudar `customer_id` depois do bind | falhar fechado com `409` |
| Renovar `expiry` em licenca bindada | permitido; bind mantido |
| Reemitir `.lic` da mesma licenca apos renovar `expiry` | permitido no mesmo hardware/bind |
| Tentar editar `hardware_id`, `status`, `revoked_at` ou `license_key` via `PUT /api/licenses/:id` | rejeitado pelo schema/validacao do CRUD normal |

### Addendum operativo da F3.5

| Cenario manual a observar | Expectativa conservadora actual |
|---------------------------|---------------------------------|
| Primeira activacao valida | `activations_log` regista sucesso e a auditoria regista `initial_issue` |
| Re-activacao legitima do mesmo hardware | auditoria regista `reactivation_reissue` |
| Download administrativo de licenca bindada | auditoria regista `admin_download_reissue` com hashes do artefacto |
| Dois downloads administrativos da mesma licenca | cada acto fica auditado, mesmo sem versionamento forte no `.lic` |
| Artefacto antigo e artefacto novo coexistirem em campo | continua possivel; a trilha auditada melhora investigacao, nao enforcement |

### Addendum operativo da F3.6/F3.7/F3.8

Referencia canónica detalhada:
`docs/01-architecture/f3-validacao-manual-evidencias.md`

Pack operacional, template e convencao de ficheiros:
`docs/01-architecture/f3-pack-operacional-validacao.md`

Gate oficial de fechamento e relatorio final unico de campanha:
`docs/01-architecture/f3-gate-fechamento-validacao.md`

| ID | Cenario manual a observar | Classificacao F3.6 | Evidencia minima esperada |
|----|---------------------------|--------------------|---------------------------|
| S01 | Activacao inicial valida | Obrigatorio | saida do `--activate`, estado da licenca, `activations_log`, `license_artifact_issued` |
| S02 | Re-activacao legitima do mesmo hardware | Obrigatorio | saida do `--activate`, `activated_at` preservado, `reactivation_reissue` |
| S03 | Activacao com hardware diferente para licenca bindada | Obrigatorio | HTTP `409`, `activations_log.result='fail'`, bind inalterado |
| S04 | Download administrativo de licenca bindada | Obrigatorio | download `{ data, sig }`, `license_downloaded`, hashes do artefacto |
| S05 | Mutacao permitida de `expiry` e reemissao | Obrigatorio | `PUT` bem-sucedido, bind preservado, download/reativacao do mesmo hardware |
| S06 | Tentativa de mudar `customer_id` em licenca bindada | Obrigatorio | HTTP `409`, estado persistido inalterado, `license_update_denied` |
| S07 | Licenca expirada no backend sem `.lic` local | Obrigatorio | activacao falha fechada, ausencia de `.lic` novo, estado efectivo `expired` |
| S08 | Licenca expirada no backend com `.lic` local ainda dentro da grace | Obrigatorio | backend `expired`, stats locais com `license_valid=true` e `license_grace=true` |
| S09 | Licenca revogada no backend com `.lic` antigo offline | Obrigatorio | revogacao no backend, activacao/download negados, appliance ainda valido localmente |
| S10 | Multiplos downloads/reemissoes da mesma licenca | Desejavel | dois actos auditados; ficheiros podem ser identicos no mesmo dia |
| S11 | Coexistencia de artefacto antigo e artefacto novo | Obrigatorio | dois `.lic` guardados, stats do appliance com cada artefacto, trilha de auditoria |
| S12 | Appliance offline antes e depois do grace | Obrigatorio | stats locais antes/dentro/depois da grace, transicao para monitor-only apos `14` dias |
| S13 | Divergencia de fingerprint por mudanca de NIC/UUID | Obrigatorio | `kern.hostuuid` e fingerprint antes/depois, stats locais, tentativa online se houve drift |

---

## Resumo

| Categoria | Total | OK | Pendente |
|-----------|-------|----|----------|
| Build | 4 | 4 | 0 |
| Instalação | 5 | 5 | 0 |
| Daemon | 7 | 7 | 0 |
| Config | 5 | 5 | 0 |
| Policy engine | 7 | 7 | 0 |
| Enforcement PF | 7 | 6 | 1 |
| Whitelist/fallback | 4 | 4 | 0 |
| GUI | 13 | 13 | 0 |
| Observabilidade | 4 | 4 | 0 |
| Rollback | 3 | 3 | 0 |
| Licenciamento/activação | 20 | 16 | 4 |
| **Total** | **79** | **74** | **5** |

A base V1 continua com 58 testes OK. O addendum da F3 acrescenta 20 cenarios
de licenciamento/activacao: 16 ficam fechados por revisao de codigo,
arquitectura e contrato canónico em `2026-04-01`, e 4 seguem pendentes como
blocos de validacao em appliance/lab. A F3.6 decompõe esses 4 blocos em 13
cenarios manuais explicitos com comandos, evidencias minimas e classificacao
obrigatorio/desejavel, sem fingir que a execucao real ja aconteceu; a F3.7
operacionaliza a recolha dessas evidencias com pack por `run_id`, nomes
padronizados de ficheiros e template minimo por cenario; a F3.8 acrescenta o
gate final e o relatorio unico da campanha para impedir fecho da fase sem
todos os obrigatorios em `PASS`.
