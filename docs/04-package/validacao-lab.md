# Validacao em lab - `pfSense-pkg-layer7`

**Objetivo:** obter evidencia objetiva de que o port gera o pacote instalavel oficial em `.pkg` para pfSense CE, que os ficheiros aparecem no disco, que o servico pode arrancar e que a pagina responde sem erro fatal.

**Politica do projeto:** o pacote so sera instalado no pfSense quando estiver totalmente completo. Este documento regista a execucao de validacao quando esse momento chegar.

**Regra:** sem outputs reais, o gate nao esta fechado.

---

## Execucao registada - 2026-03-19

- Builder: FreeBSD `15.0-RELEASE-p4` em `192.168.0.129`
- pfSense CE lab: `2.8.1` em `192.168.0.195`
- Artefacto gerado: `/root/pfsense-layer7/package/pfSense-pkg-layer7/work/pkg/pfSense-pkg-layer7-0.0.31.pkg`
- Smoke no builder: `check-port-files: OK`, `smoke-layer7d: OK`
- Instalacao no pfSense: `pkg add` OK com `IGNORE_OSVERSION=yes`
- Servico: `layer7d` sobe, para e volta a subir
- Logs: `daemon_start version=0.0.31` e `daemon_stop` em `/var/log/system.log`
- GUI: evidencia indireta em `/var/log/nginx.log` com HTTP `200` para `layer7_status.php` e `layer7_settings.php`
- Rollback: `pkg delete -y pfSense-pkg-layer7` OK; pacote reinstalado no fim do teste

---

## 1. Pre-requisitos - host builder (FreeBSD)

- [x] FreeBSD com `make` + `cc`
- [x] Clone completo do repositorio Layer7
- [x] Utilizador com permissao para compilar e gerar pacote
- [x] Arvore `/usr/ports` presente para `bsd.port.mk`

## 2. Pre-requisitos - pfSense lab

- [x] Appliance pfSense CE acessivel por SSH
- [ ] Snapshot antes da instalacao
- [x] Caminho para copiar o pacote do builder para o pfSense

## 3. Build

Comandos executados no builder:

```sh
cd /root/pfsense-layer7
sh scripts/package/check-port-files.sh
sh scripts/package/smoke-layer7d.sh
cd package/pfSense-pkg-layer7
make clean 2>/dev/null || true
make package
```

Resultado minimo:

```text
check-port-files: OK
smoke-layer7d: OK
```

Artefacto gerado:

```text
/root/pfsense-layer7/package/pfSense-pkg-layer7/work/pkg/pfSense-pkg-layer7-0.0.31.pkg
```

Nota:

- neste lab o builder FreeBSD 15 gerou `.pkg`
- referencias antigas a `.txz` ficam preservadas apenas como historico e nao
  fazem parte do contrato oficial de distribuicao da F1.1
- **macOS e Windows nao sao ambientes de validacao tecnica do produto**. No
  macOS, usar apenas como workspace de edicao/git/documentacao. A validacao
  canónica do smoke + `make package` permanece no **builder FreeBSD**; a
  validacao funcional permanece no **pfSense appliance**.

## 4. Instalacao no pfSense

Pacote copiado para:

```text
/root/pfSense-pkg-layer7-0.0.31.pkg
```

Comando usado:

```sh
env ASSUME_ALWAYS_YES=yes IGNORE_OSVERSION=yes pkg add -f /root/pfSense-pkg-layer7-0.0.31.pkg
```

Saida relevante:

```text
Installing pfSense-pkg-layer7-0.0.31...
Extracting pfSense-pkg-layer7-0.0.31: 100%
Saving updated package information... done.
Successfully installed package: layer7.
```

Nota:

- foi necessario `IGNORE_OSVERSION=yes` porque o pacote foi gerado com `FreeBSD_version 1500068`
- o kernel do pfSense no lab reportou `1500029`

## 5. Metadados do pacote

Comandos:

```sh
pkg info pfSense-pkg-layer7
pkg info -l pfSense-pkg-layer7
```

Saida relevante:

```text
Name           : pfSense-pkg-layer7
Version        : 0.0.31
Prefix         : /usr/local
Architecture   : FreeBSD:15:amd64
```

Ficheiros confirmados por `pkg info -l`:

```text
/etc/inc/priv/layer7.priv.inc
/usr/local/etc/layer7.json.sample
/usr/local/etc/rc.d/layer7d
/usr/local/pkg/layer7.inc
/usr/local/pkg/layer7.xml
/usr/local/sbin/layer7d
/usr/local/share/pfSense-pkg-layer7/info.xml
/usr/local/www/packages/layer7/layer7_diagnostics.php
/usr/local/www/packages/layer7/layer7_events.php
/usr/local/www/packages/layer7/layer7_exceptions.php
/usr/local/www/packages/layer7/layer7_policies.php
/usr/local/www/packages/layer7/layer7_settings.php
/usr/local/www/packages/layer7/layer7_status.php
```

## 6. Servico

Comandos:

```sh
cp /usr/local/etc/layer7.json.sample /usr/local/etc/layer7.json
service layer7d onestart
service layer7d status
ps auxww | grep layer7d | grep -v grep
service layer7d onestop
service layer7d onestart
```

Saida relevante:

```text
layer7d is running as pid 49115.
root ... daemon: /usr/local/sbin/layer7d[49115] (daemon)
root ... /usr/local/sbin/layer7d
```

Versao do binario:

```sh
/usr/local/sbin/layer7d -V
```

```text
0.0.31
```

Logs relevantes:

```text
Mar 19 00:22:30 pfSense pkg[58002]: pfSense-pkg-layer7-0.0.31 installed
Mar 19 00:22:31 pfSense layer7d[65989]: daemon_start version=0.0.31
Mar 19 00:22:31 pfSense layer7d[65989]: config file present: /usr/local/etc/layer7.json (452 bytes)
Mar 19 00:22:31 pfSense layer7d[65989]: config: layer7.enabled=false - idle (sem motor L7)
Mar 19 00:23:54 pfSense layer7d[65989]: daemon_stop
Mar 19 00:23:54 pfSense layer7d[49115]: daemon_start version=0.0.31
```

## 7. GUI / HTTP

URL:

```text
https://192.168.0.195/packages/layer7/layer7_status.php
```

Estado:

- [x] Abre sem erro PHP: sim
- [ ] Menu "Layer7" visivel: nao verificado

Evidencia capturada no appliance:

```text
GET /packages/layer7/layer7_status.php HTTP/2.0" 200
GET /packages/layer7/layer7_settings.php HTTP/2.0" 200
```

### Revalidacao visual - commit `f7faecb`

Objetivo:

- reinstalar o pacote apos a reorganizacao visual das paginas `Status`, `Settings`, `Policies`, `Exceptions`, `Events` e `Diagnostics`

Comandos:

```sh
env ASSUME_ALWAYS_YES=yes IGNORE_OSVERSION=yes pkg add -f /root/pfSense-pkg-layer7-0.0.31.pkg
php -l /usr/local/pkg/layer7.inc
php -l /usr/local/www/packages/layer7/layer7_status.php
php -l /usr/local/www/packages/layer7/layer7_settings.php
php -l /usr/local/www/packages/layer7/layer7_policies.php
php -l /usr/local/www/packages/layer7/layer7_exceptions.php
php -l /usr/local/www/packages/layer7/layer7_events.php
php -l /usr/local/www/packages/layer7/layer7_diagnostics.php
service layer7d status
```

Saida relevante:

```text
Installing pfSense-pkg-layer7-0.0.31...
package pfSense-pkg-layer7 is already installed, forced install
Extracting pfSense-pkg-layer7-0.0.31: ......... done

No syntax errors detected in /usr/local/pkg/layer7.inc
No syntax errors detected in /usr/local/www/packages/layer7/layer7_status.php
No syntax errors detected in /usr/local/www/packages/layer7/layer7_settings.php
No syntax errors detected in /usr/local/www/packages/layer7/layer7_policies.php
No syntax errors detected in /usr/local/www/packages/layer7/layer7_exceptions.php
No syntax errors detected in /usr/local/www/packages/layer7/layer7_events.php
No syntax errors detected in /usr/local/www/packages/layer7/layer7_diagnostics.php

layer7d is running as pid 49115.
```

Resultado:

- [x] pacote reinstalado com sucesso apos refresh visual
- [x] sintaxe PHP valida em todas as paginas do pacote
- [x] servico `layer7d` permaneceu operacional apos a reinstalacao
- [ ] validacao visual humana final pendente no browser do pfSense

### Ajuste fino de espacamento - commit `a294831`

Objetivo:

- introduzir padding interno adicional nas paginas para evitar textos e secoes colados na borda do painel

Comandos:

```sh
env ASSUME_ALWAYS_YES=yes IGNORE_OSVERSION=yes pkg add -f /root/pfSense-pkg-layer7-0.0.31.pkg
service php_fpm onerestart
service layer7d onestop
service layer7d onestart
service layer7d status
```

Saida relevante:

```text
Installing pfSense-pkg-layer7-0.0.31...
package pfSense-pkg-layer7 is already installed, forced install
Extracting pfSense-pkg-layer7-0.0.31: ......... done

Stopping php_fpm.
Starting php_fpm.

layer7d is running as pid 89955.
```

Resultado:

- [x] pacote reinstalado apos o ajuste fino visual
- [x] `php_fpm` recarregado para reduzir risco de cache/opcache
- [x] `layer7d` confirmado em execucao apos a troca

### Incidente da WebGUI do pfSense e recuperacao segura

Objetivo:

- registar a quebra da WebGUI do appliance durante a rodada visual, a causa real e o procedimento correto para nao repetir o incidente em proximas sessoes

Causa encontrada:

- o frontend base do pfSense foi tocado fora do fluxo oficial do appliance
- o `webConfigurator` esperava `php-fpm` em `unix:/var/run/php-fpm.socket`
- o `php-fpm` estava configurado para `127.0.0.1:9000`, o que produziu `502 Bad Gateway`
- depois disso, o dashboard autenticado ainda falhava por permissoes incorretas em `/tmp/symfony-cache`

Erro relevante do Crash Reporter:

```text
unlink(/tmp/symfony-cache/filesystem/...): Permission denied
```

Recuperacao aplicada no appliance:

```sh
service php_fpm onerestart
/etc/rc.restart_webgui
chown -R www:www /tmp/symfony-cache
find /tmp/symfony-cache -type d -exec chmod 775 {} +
find /tmp/symfony-cache -type f -exec chmod 664 {} +
rm -f /tmp/sess_*
rm -rf /tmp/symfony-cache
install -d -o www -g www -m 775 /tmp/symfony-cache
/etc/rc.restart_webgui
```

Configuracao operacional que ficou valida:

```text
/usr/local/etc/php-fpm.d/www.conf
listen = /var/run/php-fpm.socket
listen.owner = www
listen.group = www
listen.mode = 0660
```

Regra operacional resultante:

- nunca usar `service nginx restart` ou `service nginx onerestart` para a WebGUI do pfSense
- para reiniciar a GUI do appliance, usar apenas `/etc/rc.restart_webgui`
- so marcar recuperacao como concluida depois de validar raiz, login, dashboard autenticado e paginas Layer7

### Reinstalacao controlada apos recuperacao da WebGUI

Objetivo:

- recolocar o pacote no appliance apenas depois de estabilizar a GUI base do pfSense

Comandos:

```sh
env ASSUME_ALWAYS_YES=yes IGNORE_OSVERSION=yes pkg add -f /root/pfSense-pkg-layer7-0.0.31.pkg
pkg info pfSense-pkg-layer7
service layer7d status
```

Validacao HTTP/autenticacao:

- `curl -k -I https://192.168.0.195/` -> `HTTP/1.1 200 OK`
- login autenticado `POST /` -> `HTTP/1.1 302 Found`
- `GET /index.php` autenticado -> `HTTP/1.1 200 OK` repetido
- `GET /packages/layer7/layer7_status.php` autenticado -> `HTTP/1.1 200 OK`
- `GET /packages/layer7/layer7_settings.php` autenticado -> `HTTP/1.1 200 OK`

Resultado:

- [x] GUI base do pfSense recuperada antes da reinstalacao do pacote
- [x] pacote reinstalado sem voltar a mexer manualmente no frontend base do appliance
- [x] login e dashboard do pfSense revalidados apos a reinstalacao
- [x] utilizador aprovou o visual final do pacote no browser

### Correcoes de save da GUI (Settings / Policies / Exceptions)

Objetivo:

- eliminar o erro de token de formulario invalido e fechar o fluxo real de save da GUI no appliance

Causa encontrada:

- as paginas Layer7 usavam um CSRF customizado (`form_token`) em paralelo ao `__csrf_magic` nativo do pfSense
- a WebGUI do pfSense corre como `www`, que nao consegue criar ficheiros novos em `/usr/local/etc`
- o save inicial tentava criar ficheiro temporario e promover o resultado para `/usr/local/etc/layer7.json`, o que falhava no appliance

Correcao aplicada no codigo:

- remocao do CSRF customizado das paginas `Settings`, `Policies` e `Exceptions`
- uso exclusivo do CSRF nativo da WebGUI do pfSense
- `layer7_save_json()` ajustado para gravar diretamente no `layer7.json` existente com `LOCK_EX`
- `pkg-install` ajustado para:
  - criar `/usr/local/etc/layer7.json` a partir do sample quando ausente
  - aplicar `chown www:wheel /usr/local/etc/layer7.json`
  - aplicar `chmod 0664 /usr/local/etc/layer7.json`

Ajuste aplicado no appliance de lab para alinhar ao comportamento final do pacote:

```sh
chown www:wheel /usr/local/etc/layer7.json
chmod 0664 /usr/local/etc/layer7.json
php -l /usr/local/pkg/layer7.inc
service php_fpm onerestart
/etc/rc.restart_webgui
```

Validacao real:

- abertura de `Settings` sem erro
- alteracao manual de valores na GUI
- submit com sucesso
- confirmacao de que `/usr/local/etc/layer7.json` mudou no appliance

Evidencia relevante:

```text
stat -f '%Su %Sg %Sp %m %Sm %N' /usr/local/etc/layer7.json
www wheel -rw-rw-r-- 1773886734 Mar 19 02:18:54 2026 /usr/local/etc/layer7.json
```

```text
{
    "layer7": {
        "enabled": true,
        "mode": "enforce",
        "log_level": "debug",
        "syslog_remote_port": 515,
        "debug_minutes": 5,
        "interfaces": [
            "lan"
        ]
    }
}
```

Resultado:

- [x] erro de token removido do fluxo real da GUI
- [x] save de `Settings` validado no browser com persistencia em disco
- [x] codigo do pacote alinhado ao comportamento validado no appliance
- [ ] revalidacao por reinstalacao limpa do pacote com o `pkg-install` novo ainda pendente

### Reboot e persistencia da configuracao

Objetivo:

- confirmar que as definicoes gravadas pela GUI sobrevivem ao reboot do appliance

Validacao real:

- reboot do pfSense executado em lab
- login na WebGUI revalidado apos o arranque
- pagina `Settings` aberta apos reboot
- opcoes previamente guardadas continuaram refletidas na GUI
- persistencia revalidada no ficheiro `/usr/local/etc/layer7.json`

Resultado:

- [x] reboot do appliance validado
- [x] persistencia da configuracao validada apos reboot

### Rebuild do pacote no builder apos as correcoes

Objetivo:

- garantir que o artefacto rebuilt do port incorpora as correcoes de GUI save e a correcao do `check-port-files`

Builder:

- FreeBSD `15.0-RELEASE-p4` em `192.168.0.129`

Sequencia executada:

```sh
cd /root/pfsense-layer7
git pull --ff-only origin main
sh scripts/package/check-port-files.sh
sh scripts/package/smoke-layer7d.sh
cd package/pfSense-pkg-layer7
make clean 2>/dev/null || true
make package
```

Resultado:

- [x] builder sincronizado com `origin/main`
- [x] `check-port-files.sh` OK apos correcao para `pkg-plist` com caminhos absolutos
- [x] `smoke-layer7d.sh` OK
- [x] `make package` OK

Artefacto gerado:

```text
/root/pfsense-layer7/package/pfSense-pkg-layer7/work/pkg/pfSense-pkg-layer7-0.0.31.pkg
```

Estado:

- [ ] artefacto rebuilt ainda nao reinstalado no pfSense de lab
- [x] artefacto rebuilt publicado como GitHub Release / artefacto descarregavel

Release publicada:

```text
https://github.com/pablomichelin/pfsense-layer7/releases/tag/v0.0.31-lab1
```

Assets publicados:

```text
https://github.com/pablomichelin/pfsense-layer7/releases/download/v0.0.31-lab1/pfSense-pkg-layer7-0.0.31.pkg
https://github.com/pablomichelin/pfsense-layer7/releases/download/v0.0.31-lab1/pfSense-pkg-layer7-0.0.31.pkg.sha256
```

## 8. Remove / rollback

Comandos:

```sh
pkg delete -y pfSense-pkg-layer7
pkg info pfSense-pkg-layer7
```

Saida relevante:

```text
Deinstalling pfSense-pkg-layer7-0.0.31...
Removing layer7 components...
pkg: No package(s) matching pfSense-pkg-layer7
```

## 9. Conclusao

- Data: `2026-03-19`
- Versao pfSense CE: `2.8.1`
- Resultado: `APROVADO` para o gate "pacote + daemon de smoke"

Pendencias conhecidas:

- reinstalar no pfSense de lab o `.pkg` publicado na release `v0.0.31-lab1`
- validar `pfctl` do fluxo de enforce (secao 6b do plano original)
- validar whitelist e fallback
- fechar evidencia do menu GUI do pacote no fluxo manual completo
- reduzir ou eliminar a dependencia de `IGNORE_OSVERSION=yes`

## Índice dos roteiros F4 (evidência em lab / appliance)

| Secção | Backlog | Objectivo resumido | Matriz (`test-matrix.md`) |
|--------|---------|-------------------|---------------------------|
| **10a** | BG-009 (F4.1) | pidfile, `rc.d`, permissões, consumidores do PID (sh + PHP ≥ `_6`) | **3.8** |
| **10b** | BG-010 (F4.2) | updater assinado, `send_sighup`, `fallback.state` | **12.1**, **12.2** |
| **11** | BG-011 (F4.3) | DNS forçado, anchor `natrules/layer7_nat`, `pfctl -s nat` | **6.7** |

**Antes do appliance:** nos três roteiros acima, instalar no pfSense apenas
depois de `check-port-files.sh` + `smoke-layer7d.sh` na raiz do clone e
`make package` no builder quando o bloco exigir `.pkg` novo (disciplina da
secção **3** e parágrafo *Pré-requisito (repositório / builder)* em cada
secção 10a / 10b / 11).

O checklist rápido abaixo (itens 13–15) referencia estas secções.

---

## 10. Checklist rapido

| # | Item | OK |
|---|------|----|
| 1 | Build `make package` sem erro | [x] |
| 2 | Ficheiro de pacote gerado | [x] |
| 3 | `pkg add` OK | [x] |
| 4 | `pkg info pfSense-pkg-layer7` OK | [x] |
| 5 | Ficheiros instalados coerentes com `pkg info -l` | [x] |
| 6 | `service layer7d onestart` OK | [x] |
| 7 | `service layer7d status` OK | [x] |
| 8 | `ps` mostra `layer7d` | [x] |
| 9 | Logs com `daemon_start` | [x] |
| 10 | URL `/packages/layer7/layer7_status.php` OK | [x] |
| 11 | Menu GUI anotado | [ ] |
| 12 | `pkg delete` OK | [x] |
| 13 | F4.3: anchor NAT `force_dns` (ver secção 11) | [ ] |
| 14 | F4.1: pidfile / `rc.d` / consumidores (ver secção 10a) | [ ] |
| 15 | F4.2: updater blacklists / fallback (ver secção 10b) | [ ] |

---

## 10a. Roteiro F4.1 — pidfile, `rc.d` e consumidores (BG-009)

**Objectivo:** recolher evidência de que `/var/run/layer7d.pid` é tratado de
forma consistente pelo `rc.d`, pela GUI, pelo updater de blacklists e pelo
cron de stats (leitura com trim, só dígitos, `kill -0` antes de sinais), e
que `service layer7d status` não falha indevidamente por permissões do
ficheiro após arranque normal (`chmod 0644` no bloco F4.1).

**Onde:** appliance pfSense com pacote que inclua o bloco F4.1 (ex. linha
`1.8.11` com `PORTREVISION` ≥ 4 no port para `rc.d`/scripts; ver
`CORTEX.md` / `Makefile`). Para alinhar **GUI PHP** (Dashboard, Diagnostics,
reload/stats via `layer7.inc`) à mesma semântica (**primeira linha**, trim,
só dígitos), usar pacote com **`PORTREVISION` ≥ 6** (`layer7_daemon_pid_from_file`).

**Pré-requisito (repositório / builder):** antes de instalar o `.pkg` no
pfSense, na raiz do clone correr `sh scripts/package/check-port-files.sh` e
`sh scripts/package/smoke-layer7d.sh` (secção **3**); no builder,
`make package` quando o bloco exigir artefacto com `rc.d`, scripts de cron/
blacklists e/ou `layer7.inc` actualizados. Isto não substitui a evidência no
appliance (`service layer7d status`, GUI, pidfile).

**Comandos (SSH como root):**

```sh
service layer7d status
ls -l /var/run/layer7d.pid
```

Com o daemon activo, espera-se mensagem do tipo `layer7d is running as pid
<N>.` e um pidfile com modo **`-rw-r--r--`** (0644).

**Critério mínimo de PASS (evidência):**

- `status` coerente com `ps` / `pgrep layer7d`
- pidfile presente após `onestart`, conteúdo uma linha só com PID numérico
  (sem lixo visível); após edição manual acidental, os scripts devem recusar
  `HUP`/`USR1` em vez de enviar sinal a PID inválido (comportamento documentado
  em `update-blacklists.sh` / `layer7-stats-collect.sh`)
- Com **`PORTREVISION` ≥ 6**, a página **Services → Layer 7** (e Diagnostics)
  obtém o PID via `layer7_daemon_pid_from_file()` em `usr/local/pkg/layer7.inc`
  — coerente com `read -r` + trim nos shells; validar que o estado «Em execução»
  na GUI coincide com `service layer7d status` quando o daemon está activo.

**Opcional (suporte sem shell root):** se existir utilizador local de teste,
confirmar que consegue **ler** `/var/run/layer7d.pid` (o número exposto não é
segredo; evita regressão da página Status na mesma linha).

**Rollback:** reinstalar o `.pkg` anterior documentado como seguro;
`service layer7d onerestart` após substituir o pacote.

**Referências:** `docs/02-roadmap/f4-plano-de-implementacao.md` (F4.1),
`docs/05-daemon/README.md` (Pidfile), addendum F4.1 em
`docs/10-license-server/MANUAL-INSTALL.md`.

**Registo sugerido:** data, `pkg info pfSense-pkg-layer7`, saída de `service
layer7d status`, `ls -l /var/run/layer7d.pid`.

---

## 10b. Roteiro F4.2 — updater de blacklists, SIGHUP e fallback (BG-010)

**Objectivo:** recolher evidência de que o script oficial
`/usr/local/etc/layer7/update-blacklists.sh` consome a trilha F1.3 (manifesto
assinado), regista degradação em
`/usr/local/etc/layer7/blacklists/.state/fallback.state` e só envia **SIGHUP**
ao `layer7d` quando o PID do pidfile é válido e o processo responde a
`kill -0` (alinhado a `send_sighup` e ao passo 12 do
`PLANO-BLACKLISTS-UT1.md`).

**Onde:** appliance com pacote F4.x; rede com acesso à origem oficial de
manifesto/snapshot ou cenário de falha controlada (ver critérios abaixo).

**Pré-requisito (repositório / builder):** antes de instalar o `.pkg` no
pfSense, na raiz do clone correr `sh scripts/package/check-port-files.sh` e
`sh scripts/package/smoke-layer7d.sh` (mesma disciplina da secção **3**); no
builder FreeBSD, `make package` no port quando o bloco exigir artefacto novo.
Isto não substitui a evidência no appliance (updater + `fallback.state`).

**Comandos úteis (SSH como root):**

```sh
# Últimas linhas do log do updater (progresso + SIGHUP)
tail -n 40 /var/log/layer7-bl-update.log

# Estado de fallback F1.4 (key=value)
sed -n '1,12p' /usr/local/etc/layer7/blacklists/.state/fallback.state

# Reload apenas sinal (equivalente útil a validar send_sighup)
/usr/local/etc/layer7/update-blacklists.sh --apply
```

**Critério mínimo de PASS (evidência) — cenário feliz:**

- Com daemon activo e snapshot válida, o log contém linha do tipo
  `INFO: sent SIGHUP to daemon` **ou** o fluxo documenta skip explícito com
  `WARN` coerente (pidfile ausente, PID inválido, processo morto) **sem**
  erro fatal do shell por `set -e` inesperado.
- `fallback.state` existe e, após promoção bem-sucedida, inclui
  `status=healthy` e `component=blacklists-update` (formato do script).

**Critério complementar (opcional, cenário de stress):** simular indisponibilidade
do manifesto/snapshot conforme runbook interno; verificar que o ficheiro passa
a `degraded` ou `fail-closed` com `reason=` e `operator_action=` preenchidos,
sem promover ficheiros não validados — alinhado a F1.4.

**Critério adicional para pacote >= `1.8.11_7`:** provocar reload com
`config.json` valido mas categorias indisponiveis/incompletas e confirmar que
as tabelas `layer7_bld_N` e a blacklist anterior continuam activas; pela GUI,
confirmar que falhas de escrita em `config.json` ou `_custom/*.domains` geram
erro visivel e nao mensagem de sucesso.

**Rollback:** restaurar snapshot anterior com
`update-blacklists.sh --restore-lkg` quando aplicável; último `.pkg` seguro;
consultar `docs/11-blacklists/DIRETRIZES-IMPLEMENTACAO.md` e plano F4.

**Referências:** `docs/02-roadmap/f4-plano-de-implementacao.md` (F4.2),
`docs/11-blacklists/PLANO-BLACKLISTS-UT1.md`,
`docs/01-architecture/f1-arquitetura-de-confianca.md` (pipeline blacklists).

**Registo sugerido:** data, `pkg info`, excerto do log do updater, conteúdo
redigido de `fallback.state`.

---

## 11. Roteiro F4.3 — DNS forcado (`natrules/layer7_nat`)

**Objectivo:** recolher evidencia de que as regras `rdr` de **Forcar DNS local**
(`force_dns` nas blacklists) carregam sem rejeitar o `pfctl` e que o anchor
pode ser inspeccionado.

**Pre-requisitos:** pacote com o bloco F4.3; em **Services > Layer 7** (ou
equivalente), interfaces correctas; pelo menos uma regra de blacklist com
`force_dns` activo, **CIDRs de origem** IPv4 validos; Layer7 e resolver
conforme o teu cenario de lab.

**Pré-requisito (repositório / builder):** antes de instalar o `.pkg` no
pfSense, na raiz do clone correr `sh scripts/package/check-port-files.sh` e
`sh scripts/package/smoke-layer7d.sh` (secção **3**); no builder,
`make package` quando o bloco exigir artefacto com o código F4.3 de
`layer7.inc`. Isto não substitui a evidência no appliance (`pfctl` no anchor).

**Comandos (SSH no pfSense, como root):**

```sh
pfctl -a natrules/layer7_nat -s nat
```

Com `force_dns` activo e CIDRs validos, esperam-se linhas contendo
`rdr` para UDP/TCP porta 53 com destino `127.0.0.1`. Se desactivar
`force_dns` e nao houver outras regras, o anchor pode ficar vazio; apos
alteracao, execute **Apply** / reload de filtro na GUI e volte a verificar.

**Criterio minimo de PASS (evidencia):**

- Nenhum aviso recorrente no log do sistema do tipo
  `Layer7: pfctl nat load` com configuracao intencionalmente valida
  (falha pontual de `tempnam` ou ruleset nao e objectivo deste teste)
- Saida de `pfctl -a natrules/layer7_nat -s nat` coerente com a configuracao
  (regras presentes ou ausentes de forma explicavel)

**Nota:** nesta fase a trilha gera apenas regras **inet** (IPv4); nao exige
`rdr` IPv6. Ver addendum F4.3 em `docs/10-license-server/MANUAL-INSTALL.md`.

**Registo sugerido no relatorio de campanha / evidencias:** data, versao do
`.pkg` (`pkg info`), saida (redigida) de `pfctl -a natrules/layer7_nat -s nat`.
