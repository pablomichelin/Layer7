# Validacao em lab - `pfSense-pkg-layer7`

**Objetivo:** obter evidencia objetiva de que o port gera o pacote instalavel oficial em `.pkg` para pfSense CE, que os ficheiros aparecem no disco, que o servico pode arrancar e que a pagina responde sem erro fatal.

**Politica do projeto:** o pacote so sera instalado no pfSense quando estiver totalmente completo. Este documento regista a execucao de validacao quando esse momento chegar.

**Regra:** sem outputs reais, o gate nao esta fechado.

**Gates oficiais F4 (subfases):** antes de declarar F4.1 / F4.2 / F4.3
concluidas em relatorio, cumprir
[`docs/02-roadmap/checklist-mestre.md`](../02-roadmap/checklist-mestre.md)
(itens F4.1, F4.2, F4.3) e
[`docs/tests/test-matrix.md`](../tests/test-matrix.md) (testes **3.8**,
**12.1–12.2**, **6.7**), mapeando as seccoes **10a**, **10b** e **11** deste
ficheiro; na **11**, cenário opcional multi-interface / VLAN para aproximar a
evidência **6.7** / **BG-011** (incl. **anti-QUIC** opcional na mesma secção **11**,
labels `layer7:anti-quic` em `pfctl -s rules` quando a GUI tiver interfaces
seleccionadas). Sobre o port `1.8.11_12` no branch: [`CORTEX.md`](../../CORTEX.md)
(*Próximos passos*, ponto 7) e o rascunho de publicacao
[`docs/06-releases/release-notes-1.8.11_10-DRAFT.md`](../06-releases/release-notes-1.8.11_10-DRAFT.md)
(sem tag nem `.pkg` publico ainda).

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
| **11** | BG-011 (F4.3) | DNS forçado, anchor `natrules/layer7_nat`, `pfctl -s nat`; anti-QUIC opcional (`pfctl -s rules`, labels `layer7:anti-quic:*`); cenário opcional multi-interface / VLAN no mesmo roteiro | **6.7** |

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
| 13 | F4.3: `force_dns` / anchor NAT e, se aplicável, anti-QUIC (ver secção **11**; cenário opcional multi-interface / VLAN no mesmo roteiro) | [ ] |
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

## 11. Roteiro F4.3 — DNS forcado (rdr no ruleset principal) e anti-QUIC (opcional)

> **Actualizacao `1.8.11_45`:** as regras `rdr` entram no **ruleset
> principal** via `layer7_generate_rules("nat")` (hook `filter_rule_function`).
> O anchor `natrules/layer7_nat` usado ate a `_44` **nunca era avaliado**
> para `rdr` (so existe `nat-anchor "natrules/*"` no pfSense) — comandos
> `pfctl -a natrules/layer7_nat ...` neste roteiro sao obsoletos; usar
> `pfctl -s nat` em seu lugar.

**Objectivo:** recolher evidencia de que as regras `rdr` de **Forcar DNS local**
(`force_dns` nas blacklists) carregam sem rejeitar o `pfctl` e que o ruleset
pode ser inspeccionado; e, **opcionalmente no mesmo roteiro**, que regras
**anti-QUIC** por interface (GUI) aparecem no ruleset com labels coerentes
(`layer7:anti-quic:*`), alinhado a [`../05-daemon/pf-enforcement.md`](../05-daemon/pf-enforcement.md).

**Pre-requisitos:** pacote com o bloco F4.3; em **Services > Layer 7** (ou
equivalente), interfaces correctas; pelo menos uma regra de blacklist com
`force_dns` activo, **CIDRs de origem** IPv4 validos; Layer7 e resolver
conforme o teu cenario de lab.

**Pré-requisito (repositório / builder):** antes de instalar o `.pkg` no
pfSense, na raiz do clone correr `sh scripts/package/check-port-files.sh` e
`sh scripts/package/smoke-layer7d.sh` (secção **3**); no builder,
`make package` quando o bloco exigir artefacto com o código F4.3 de
`layer7.inc`. Isto não substitui a evidência no appliance (`pfctl` no anchor).

**Comandos (SSH no pfSense, como root; `>= 1.8.11_45`):**

```sh
pfctl -s nat | grep -E "port = domain|8099"
```

**Opcional (anti-QUIC):** com bloqueio QUIC por interface activo na GUI e
**Apply** / reload do filtro:

```sh
pfctl -s rules | grep -F layer7:anti-quic
```

Esperam-se linhas `block drop quick on <if> inet ... label "layer7:anti-quic:<if>"` (e
variante IPv6 `layer7:anti-quic6:`) para cada interface PF válida; desde
**`1.8.11_12`**, nomes de interface inválidos são omitidos antes de gerar a
linha (mesmo critério DRY que `force_dns` — `layer7_pf_ifname_for_rules`).
Não usar `_28`: a ordem antiga `inet on <if>` falha no parser; `_29` corrige
FP-018.

Com `force_dns` activo e CIDRs validos, esperam-se linhas contendo
`rdr` para UDP/TCP porta 53 com destino `127.0.0.1`. Se desactivar
`force_dns` e nao houver outras regras, o anchor pode ficar vazio; apos
alteracao, execute **Apply** / reload de filtro na GUI e volte a verificar.

**Criterio minimo de PASS (evidencia):**

- Nenhum aviso recorrente no log do sistema do tipo
  `Layer7: pfctl nat load` com configuracao intencionalmente valida
  (falha pontual de `tempnam` ou ruleset nao e objectivo deste teste)
- Saida de `pfctl -s nat` coerente com a configuracao
  (regras presentes ou ausentes de forma explicavel)

**Nota (pacote >= `1.8.11_8`):** se varias regras de blacklist com `force_dns`
partilharem o mesmo par **(interface, CIDR)**, o pacote emite **uma** dupla
`rdr` UDP/TCP para esse par (sem linhas duplicadas no anchor). Ao contar
regras esperadas no lab, usar esta regra e nao o produto bruto
`regras × CIDRs × interfaces`.

**Nota (pacote >= `1.8.11_9`):** a ordem das interfaces na geracao de `rdr` e
**alfabetica** (nao depende da ordem em **Services → Layer 7**); a ordem das
regras de blacklist no `config.json` continua a determinar a sequencia global
das linhas.

**Nota (pacote >= `1.8.11_10`):** dentro de cada regra com `force_dns`, os
CIDRs IPv4 validos sao **unicos** e ordenados **alfabeticamente** antes de
cruzar com as interfaces; duplicar o mesmo CIDR na lista da regra nao duplica
`rdr` para esse par (interface, CIDR) gracas ao `seen` global.

**Cenario lab sugerido (multi-interface / VLAN):** para aproximar o backlog
**BG-011** de combinacoes com varios segmentos sem mudar codigo no repositorio,
configurar em **Services → Layer 7** pelo menos **duas** interfaces cujo
`get_real_interface` no pfSense produza nomes PF distintos (ex.: LAN e uma
opt/VLAN com nomes do tipo `em0` e `em0.20`). Usar uma ou mais regras de
blacklist com `force_dns` e `src_cidrs` apenas com sub-redes IPv4 validas que
existem por tras de cada segmento. Apos **Apply** / reload do filtro:

1. `pfctl -s nat` deve listar `rdr` **inet** UDP e TCP
   porta 53 para cada par **(interface real, CIDR)** esperado, sem linhas
   duplicadas quando o dedupe (>= `1.8.11_8`) se aplica.
2. Opcional: trafego de teste a partir de um host em cada sub-rede para
   confirmar comportamento de DNS no desenho do lab (evidencia qualitativa;
   o criterio minimo de **PASS** continua a ser a saida de `pfctl` coerente e
   ausencia de avisos recorrentes `Layer7: pfctl nat load` com configuracao
   intencionalmente valida).

**Nota:** nesta fase a trilha gera apenas regras **inet** (IPv4); nao exige
`rdr` IPv6. Ver addendum F4.3 em `docs/10-license-server/MANUAL-INSTALL.md`.

**Registo sugerido no relatorio de campanha / evidencias:** data, versao do
`.pkg` (`pkg info`), saida (redigida) de `pfctl -s nat`.

---

## 12. Roteiro Caminho B / E3 — enforcement escopado two-client (BG-048)

**Objectivo:** validar que, com `enforcement_model=scoped_hybrid`, bloquear
YouTube (ou equivalente) para o cliente **A** nao bloqueia o cliente **B** na
mesma LAN. Este gate e **obrigatorio** antes de avancar para E4.

**Estado:** **PENDENTE**. Em `2026-07-29`, o acesso SSH read-only ao appliance
foi restabelecido e `_24` foi confirmado instalado, intencionalmente
`enabled=false` / `mode=monitor`. O diagnóstico abriu o candidato `_25`; não
houve activação nem alteração do pfSense.

**Onde:** appliance `192.168.100.254` ou lab equivalente. O equipamento real
observado é **pfSense Plus 26.03.1 / FreeBSD 16.0-CURRENT**, não pfSense CE;
registar essa diferença como parte do gate de compatibilidade.

### Build E3 (2026-06-16, builder `192.168.100.12`)

| Item | Resultado |
|------|-----------|
| Sync | tar+SSH ficheiros E0–E3 do workspace local → `/root/pfsense-layer7` (sem commit) |
| Compilacao | **OK** apos forward declaration de `layer7_pf_add_with_selfheal` em `main.c` |
| Artefacto | `pfSense-pkg-layer7-1.8.11_24.pkg` (~2,3 MiB) — release publica GitHub `v1.8.11_24`; copia local opcional em `artifacts/` |
| Instalacao appliance | **nao executada** |
| Gate sec.12 | **PENDENTE** |

### Pré-gate do candidato `_25` (obrigatório antes do two-client)

Build FreeBSD concluído: `pfSense-pkg-layer7-1.8.11_25.pkg`,
`SHA256=c4e9c197f79ad00d7ddb68f8ececcd391455e86011e558596102877c325d388d`.

1. Verificar novamente esse SHA256 antes de qualquer instalação.
2. Instalar com Layer7 desactivado/monitor e confirmar **uma única** árvore
   `daemon(8) + layer7d`.
3. `service layer7d onestatus` deve reconhecer o PID gravado sem newline.
4. `layer7.json` deve conter interfaces reais (`vmx0`, `vmx0.10`, etc.), não
   IDs amigáveis `lan`/`optN`.
5. Em monitor: zero regras Layer7 `block drop` e tabelas dinâmicas vazias.
6. Após activar apenas captura/monitor na interface do cliente de teste,
   evidenciar `captures > 0` e `cap_pkts > 0` antes de usar enforce.

**Bloqueios históricos para instalacao/gate (2026-06-16):**

1. **SSH a partir desta estacao de operacao:** `192.168.100.254:22` responde a
   ICMP mas **timeout** em TCP/22 (firewall/rede); impede `pkg add` e passos do
   roteiro a partir do Mac do operador.
2. **SSH builder → appliance:** porta 22 alcancavel de `192.168.100.12`, mas
   autenticacao falhou (`root`/`codex`/`admin` sem chave autorizada; builder
   sem `sshpass`/`expect` para password interactiva).
3. **Clientes A/B (`10.0.0.10`, `10.0.0.20`):** nao acessiveis deste ambiente
   para `curl`/navegacao exigidos nos passos 4–5.

Os itens 1–2 de acesso SSH foram superados em `2026-07-29` (login `admin`,
menu opção 8). O item 3 e uma janela de teste controlada continuam pendentes.
Não usar clientes reais de produção como A/B sem escolha humana explícita.

**Proximo passo operacional:** instalar o `.pkg` no pfSense (GUI *System → Package Manager* ou
`pkg add` via SSH a partir de rede com acesso) e executar o roteiro abaixo com
evidencias; so entao marcar **PASS**.

### Diagnostico e smoke (E7)

Antes ou durante o gate, recolher estado com:

```sh
# Copiar e executar no pfSense; por defeito nao sinaliza nem reinicia o daemon
scp scripts/diagnose-layer7-appliance.sh root@192.168.100.254:/tmp/
ssh root@192.168.100.254 'sh /tmp/diagnose-layer7-appliance.sh' | tee evidence/l7-diag.txt
```

`L7_DIAG_REFRESH_STATS=1` envia `SIGUSR1` e fica reservado ao lab. Em
produção, manter o default passivo.

Smoke automatizado (modo **appliance** — correr **no** pfSense apos configurar scoped):

```sh
sh tests/lab/smoke-enforcement-scoped.sh
# Two-client opcional (SSH batch dos clientes lab):
L7_CLIENT_A=10.0.0.10 L7_CLIENT_B=10.0.0.20 sh tests/lab/smoke-enforcement-scoped.sh
```

Modo **estatico** (workspace Mac/CI, sem SSH ao appliance):

```sh
sh tests/lab/smoke-enforcement-scoped.sh   # verifica testes E1-E3 e layer7.inc; SKIP gate appliance
```

**Artefacto release:** `pfSense-pkg-layer7-1.8.11_24.pkg` — GitHub tag `v1.8.11_24` ou:

```sh
gh release download v1.8.11_24 --repo pablomichelin/Layer7 \
  --pattern 'pfSense-pkg-layer7-1.8.11_24.pkg*' --dir artifacts/
```

### Pre-requisitos

| Item | Valor |
|------|-------|
| `enforcement_model` | `scoped_hybrid` |
| `mode` | `enforce` |
| `enabled` | `true` |
| Licenca | valida (enforce ao vivo exige licenca) |
| Politica P0 | `action=block`, hosts=`youtube.com`, `src_hosts=[10.0.0.10]` |
| Cliente A | `10.0.0.10` |
| Cliente B | `10.0.0.20` |

Apos gravar politicas: **Filter reload** (ou Resync Layer7) para materializar
regras `layer7_pdst_0` em `/tmp/rules.debug` / `pfctl -sr`.

### Passos

1. De **A** (`10.0.0.10`): `nslookup youtube.com` ou navegar para YouTube.
2. No pfSense:
   ```sh
   pfctl -t layer7_pdst_0 -T show
   ```
   Deve conter IP(s) do YouTube.
3. Verificar que a tabela global legacy esta vazia:
   ```sh
   pfctl -t layer7_block_dst -T show
   ```
   Deve estar **VAZIA** em modo scoped.
4. De **A**: `curl -m 5 https://www.youtube.com` — esperado **timeout/falha**.
5. De **B** (`10.0.0.20`): `curl -m 5 https://www.youtube.com` — esperado
   **SUCESSO** (HTTP 200 ou redirect).
6. (Opcional) Repetir com trafego nDPI classificado como YouTube se o lab
   permitir.

### Evidencias minimas (PASS)

- Screenshot ou log de `layer7d` com linha `enforce_block: kind=dst_scoped
  table=layer7_pdst_0 … policy=…`
- Saida de `pfctl -t layer7_pdst_0 -T show` com IP YouTube
- Saida de `pfctl -t layer7_block_dst -T show` vazia
- Prova de curl OK de B e falha de A

### Criterio FAIL

- IP YouTube em `layer7_block_dst` com `scoped_hybrid` activo
- B bloqueado quando A acede YouTube
- Regras `layer7_pdst_*` ausentes do ruleset apos reload

### Rollback

- Settings → `enforcement_model=legacy_global` → Save → Resync
- `layer7-pfctl flush-all` ou restart `layer7d`

**Plano SSOT:** [`../09-blocking/plano-enforcement-100-porcento.md`](../09-blocking/plano-enforcement-100-porcento.md) (Bloco E3).

**Nota:** nao marcar este gate como PASS sem execucao real no appliance.

---

## 13. Roteiro BG-054 — contenção L1 de logs (`1.8.11_26`)

Executar somente depois do build e com o Layer7 primeiro
`enabled=false`/`mode=monitor`. Não gerar tráfego sintético pesado nem apagar
logs no appliance de produção sem janela e validação humana.

### Pré-gate read-only

```sh
service layer7d onestatus
grep -E '"enabled"|"mode"|"event_log_enabled"|"log_file_' \
  /usr/local/etc/layer7.json
ls -lh /var/log/layer7d.log* /var/log/layer7-events.log* 2>/dev/null
du -h /usr/local/etc/layer7/reports/reports.db 2>/dev/null
```

### Passos controlados

1. Com detalhe desligado, aguardar pelo menos 30 minutos e confirmar que não
   surgem mensagens periódicas `still idle`, `license_recheck` sem transição
   ou `SIGUSR1 stats` em nível normal.
2. Confirmar na GUI os limites de ficheiro/cópias e do SQLite.
3. Ligar detalhe apenas numa interface de lab, gerar poucas consultas e
   confirmar eventos nessa interface em `/var/log/layer7-events.log`.
4. Desligar detalhe e confirmar que novas consultas deixam de ser gravadas.
5. Em gate de enforce já autorizado, confirmar que um bloqueio continua
   registado como auditoria mesmo com detalhe desligado.
6. Confirmar que **Limpar visualização** não altera os tamanhos no disco.
7. Se o histórico for limpo por decisão humana, confirmar SQLite vazio,
   cursores avançados e logs rotativos preservados.

### PASS

- nenhum crescimento repetitivo conhecido em `info`;
- eventos detalhados obedecem toggle/interface;
- bloqueio auditado com detalhe OFF;
- tamanho máximo nominal visível e coerente;
- rotação não interrompe a ingestão;
- nenhuma alteração de enforcement fora do gate two-client.

### Rollback

Voltar a detalhe OFF, `enabled=false`/`mode=monitor`, copiar evidência e
reinstalar `_24`. Não apagar os ficheiros antes de concluir o diagnóstico.

---

## 14. Roteiro BG-056 — allow PF seguro (`1.8.11_28`)

Este gate valida a correcção de FP-017 sem transformar uma decisão Layer7 em
autorização de firewall. Em produção, executar apenas em janela aprovada,
começando com `enabled=false`/`mode=monitor`, configuração exportada e acesso
de recuperação confirmado.

Build candidato no FreeBSD 15: `pfSense-pkg-layer7-1.8.11_28.pkg`,
`SHA256=62dd9ae5923ade45b0bb484dca4e835b29b139f7a2aaa0a3624272ba07e59dc6`;
`pkg info`, binário `-V`/`-t`, conteúdo `pallow`/`blsrc` e ausência do
`pass quick` histórico: PASS. Isso não substitui o parser/ruleset do appliance.

### Pré-gate read-only

```sh
pkg info pfSense-pkg-layer7
service layer7d onestatus
/usr/local/libexec/layer7-pfctl show
pfctl -sr | grep -E 'L7ALLOW|layer7_blsrc|layer7_pallow|layer7_pdst|layer7_psrc'
```

Confirmar que não existe regra Layer7 `pass quick`, que as regras
`match ... tag L7ALLOW` aparecem antes dos blocks Layer7 e que o ruleset
completo continua carregado pelo pfSense.

### Passos controlados

1. Criar uma regra nativa pfSense temporária, escopada ao cliente A e a um
   destino de teste, que bloqueie o tráfego mesmo quando uma política Layer7
   allow casar.
2. Criar política Layer7 block para A e B e uma política allow de prioridade
   maior apenas para A, no mesmo destino controlado.
3. Confirmar `layer7_pallow_N` populada somente após decisão allow válida e
   `layer7_pdst_N`/`layer7_bld_N` ainda aplicável a B.
4. Provar que A fica livre do block Layer7, mas continua bloqueado pela regra
   nativa; remover a regra nativa e provar A permitido/B bloqueado.
5. Aguardar o TTL ou provocar reload autorizado e confirmar que não ficam
   entradas `pallow` herdadas por política reordenada.
6. Repetir o save de uma excepção allow e confirmar flush/resync antes da
   nova ordem de índices.

### PASS

- nenhuma regra Layer7 `pass quick`;
- regra nativa pfSense continua a vencer;
- allow A não libera B;
- block B permanece efectivo no mesmo destino;
- TTL, reload, disable e rollback não deixam `pallow` stale;
- daemon, WebGUI e regras não afectadas permanecem funcionais.

### Rollback

Desactivar Layer7, executar `/usr/local/libexec/layer7-pfctl flush-all`,
recarregar o filtro pelo mecanismo normal do pfSense, remover apenas a regra
nativa temporária do teste e reinstalar `_24`/artefacto anterior aprovado.
Restaurar a configuração exportada. Não apagar tabelas ou regras nativas
manualmente fora desse escopo.

---

## 15. Roteiro BG-057 — parser PF anti-QUIC (`1.8.11_29`)

Pré-gate read-only executado no pfSense Plus 26.03.1 / FreeBSD 16:

- `_24`, `enabled=false`, `mode=monitor`, daemon parado e pacote íntegro;
- regras actuais e `/tmp/rules.debug`: `pfctl -nf` PASS;
- tabelas `layer7_block`, `block_dst` e `tagged`: vazias;
- snippet `_28`: FAIL na linha `inet on <if>`;
- mesmo snippet com `on <if> inet`: PASS, incluindo `L7ALLOW`, `pallow`,
  `blsrc`, anti-DoT e anti-QUIC;
- nenhuma regra foi carregada (`-n`) e nenhuma configuração foi alterada.

Após build `_29`, instalar somente passivo e validar o ruleset completo antes
de tocar no toggle anti-QUIC. Se o parser falhar, não aplicar/recarregar,
preservar `_24` e recolher a linha exacta da falha.

Build `_29` concluído no builder FreeBSD 15 em `2026-07-29`:

- `check-port-files`, smoke C, suite funcional, lint PHP/shell: PASS;
- build nDPI e metadados `pkg info -F`: `1.8.11_29`,
  `FreeBSD:15:amd64`;
- pacote extraído: `-V`, dois `-t`, monitor `-e -n`, enforcement
  `-d 203.0.113.10 -e ... -n`, conteúdo e `layer7.inc`: PASS;
- artefacto: `pfSense-pkg-layer7-1.8.11_29.pkg`;
- `SHA256=bea385ddb6f61bb6a9bffde0b781cea7a852b3956f620b8b004c914b0ab01840`.

Isto fecha apenas o gate do builder. Instalação passiva, parser do ruleset
completo instalado, toggle anti-QUIC e two-client continuam pendentes.

## 16. Roteiro BG-058 — pressão da tabela de fluxos (`1.8.11_30`)

Build no FreeBSD 15, suite C/PHP/shell e validação do pacote extraído: PASS.
Artefacto `pfSense-pkg-layer7-1.8.11_30.pkg`,
`SHA256=3a54c667a601e29995562714691f4ee3e9e8e78a02fcd3e600955ae90d2e9b40`.

Instalar somente com `enabled=false`; confirmar versão, bibliotecas e config
antes de iniciar captura. Em monitor passivo, solicitar stats e verificar:

```sh
kill -USR1 "$(tr -d '[:space:]' </var/run/layer7d.pid)"
sleep 1
cat /tmp/layer7-stats.json
```

PASS mínimo: `captures > 0`, `cap_pkts` crescente, JSON válido,
`cap_dropped=0`; `cap_evicted` deve permanecer zero em tráfego normal ou ter
causa documentada em teste de pressão. Qualquer crescimento inesperado exige
parar o daemon, preservar JSON/logs e voltar a `_29`/`_24` passivo.

## 17. Roteiro BG-059 — refinamento nDPI (`1.8.11_31`)

Build no FreeBSD 15, suite C/PHP/shell e validação do pacote extraído: PASS.
Artefacto `pfSense-pkg-layer7-1.8.11_31.pkg`,
`SHA256=dc5118dd01193a83a6c6d15cc3ae4ca300647294a5b188e1991a363b4c453e33`.
O binário não-stripped contém `ndpi_detection_giveup`; versão, dependências,
dois `-t`, dry-run e conteúdo do pacote passaram.

Instalar somente em modo passivo. Gerar tráfego TLS/QUIC conhecido para uma
aplicação presente numa política de teste, mantendo enforcement desligado.
Correlacionar pcap e `flow_decide`: o daemon não pode encerrar no primeiro
TLS/QUIC parcial e deve emitir somente uma decisão com a melhor
aplicação/categoria/SNI disponível.

PASS mínimo: build nDPI contém referências a `NDPI_STATE_CLASSIFIED` e
`ndpi_detection_giveup`; fluxo real recebe ida/volta, não duplica decisão e
refina o protocolo quando o nDPI o fizer. Se ficar sempre genérico, preservar
pcap/logs/stats e voltar a `_30`/`_24` passivo sem activar PF.

## 18. Roteiro BG-062 — pagina de bloqueio utilizador final (`1.8.11_35`)

Pre-requisitos: `mode=enforce`, servico Layer7 activo, Unbound activo no pfSense,
cliente LAN a usar DNS do pfSense.

### 18.1 Activar pagina de bloqueio

1. **Definições > Pagina de bloqueio:** activar toggle; confirmar IP portal
   (auto ou manual); gravar.
2. Verificar servico: `service layer7-blockpage status` → running.
3. Verificar Unbound: `grep -A2 'Layer7 block-page' /var/unbound/unbound.conf`
   ou custom_options no config.xml — deve conter `local-data` para dominios
   das politicas block activas.
4. Verificar NAT: `pfctl -a natrules/layer7_nat -s nat | grep blockpage`

### 18.2 Teste YouTube (HTTP)

1. Activar perfil **YouTube** (Politicas > Ligar) com politica `block`.
2. No cliente LAN: `drill youtube.com @<IP_pfsense>` → deve resolver para IP portal.
3. Abrir `http://youtube.com` no browser → pagina «Acesso bloqueado» Layer7
   (nao timeout infinito).
4. Abrir `https://youtube.com` → documentar: erro TLS esperado (sem MITM).

### 18.3 Blacklist (opcional)

Com blacklist UT1 activa e «Incluir dominios blacklist» ON: confirmar que pelo
menos um dominio de categoria activa aparece no sinkhole (respeitando limite).

### 18.4 Rollback

Desactivar toggle ou `pkg install ..._34`; confirmar remocao de overrides Unbound
e paragem de `layer7-blockpage`.

PASS minimo: passos 18.1 + 18.2 (HTTP) OK; HTTPS documentado como limitacao.
Teste automatizado local: `sh tests/test_blockpage_config.sh`.

## 19. Roteiro BG-064/065 — gestor isento (VIP + verificador, `1.8.11_48`+)

Pre-requisitos: pacote `>= 1.8.11_48`, grupo **Gestores** com IP do gestor
(dispositivo ou CIDR), snapshot/rollback documentado (padrao BG-060).

### 19.1 Isencao VIP global (`vip-isentos`)

1. **Politicas > Perfis rapidos > Opcoes (YouTube):** accao `block`; em
   **Isentos**, seleccionar grupo Gestores (ou IP manual); criar politica.
2. **Excepcoes:** confirmar entrada `vip-isentos` com badge **Perfis rapidos**.
3. **Teste / verificador:** IP do gestor + dominio `youtube.com` → veredicto
   **PERMITIDO — excepcao `vip-isentos`**.
4. IP de outro cliente na mesma LAN → **BLOQUEADO — politica `profile-youtube`**
   (em `mode=enforce`).

### 19.2 Desligar perfil nao remove VIP

1. Desligar perfil YouTube (botao **Desligar**).
2. Confirmar politica `profile-youtube` removida; excepcao `vip-isentos`
   **permanece**.
3. Gestor continua PERMITIDO no verificador.

### 19.3 Exclusao por politica (BG-066, `>= 1.8.11_50`, scoped)

Quando BG-066 estiver instalado: em **Avancado** do modal, **Excluir origens**
so deste perfil; repetir teste two-client — gestor isento deste perfil mas
sujeito a outros; validar tabela PF `layer7_pexc_N` em `scoped_hybrid`.

### 19.4 Rollback

Reinstalar `_47` remove secao Isentos da GUI; excepcao `vip-isentos` no JSON
permanece valida para versoes anteriores (allow global inalterado).

PASS minimo: 19.1 + 19.2 no verificador; enforce real (two-client) continua
sujeito a gates G2–G7 — **NO-GO producao inalterado**.

---

## 20. Roteiro BG-071/072/073 — director isento de tudo (`1.8.11_61`+ recomendado; `_60` minimo fix P1)

**Objectivo:** validar ponta a ponta a feature **Lista VIP global** (Blocos A–D):
origem na excepção canónica `vip-isentos` isenta de perfis block, blacklists UT1,
anti-bypass DNS **e** sinkhole Unbound da pagina de bloqueio — o «director isento
de tudo», incluindo dominios sinkhole. Complementa a secção **19** (verificador
e modal); aqui o foco e enforce real + block page + caminho DNS (**ADR-0020**).

**Impacto:** confirma ou refuta a opção (a) view Unbound `layer7-vip-exempt` no
appliance; documenta trade-offs de host overrides nativos e modo fallback (b).
Não altera o veredicto NO-GO de producao (`1.8.11_24` até gates G2–G7).

**Risco:** medio — activa enforce, sinkhole global, NAT rdr :53 e view Unbound
em lab; erro de isenção DNS expoe VIP a pagina de bloqueio ou libera cliente
não-VIP. Mitigação: snapshot/rollback (padrao BG-060), two-client controlado,
paragem obrigatoria para validacao humana antes de qualquer mudanca de enforce
em producao.

**Gate humano:** este roteiro **nao** fecha producao nem promove `_61`/`_60` como
referencia enforce. Registar evidencias (screenshots, saidas `drill`/`dig`,
`pfctl`, trecho Unbound) e obter OK explicito antes de activar enforce fora do
lab. **NO-GO producao inalterado** — referencia enforce continua `1.8.11_24`.

### Pre-requisitos

| Item | Valor |
|------|-------|
| Pacote | `>= 1.8.11_60` (fix P1 `filter_configure`; **recomendado `>= 1.8.11_61`** — linha Lista VIP completa); `_59` parcial; `_57`/`_58` so parciais |
| `mode` | `enforce` |
| `enabled` | `true` |
| Licenca | valida (enforce ao vivo exige licenca) |
| Unbound | activo; clientes LAN usam DNS do pfSense |
| Snapshot | config export + nota da versao instalada (BG-060) |
| Cliente VIP (director) | IP estavel — ver **20.1** |
| Cliente não-VIP | outro host na mesma LAN (ex.: `192.168.1.20`) |
| Dominio de teste | dominio sinkhole conhecido (ex.: `youtube.com` via perfil block **ou** dominio de blacklist UT1 activa) |

**Recomendacao DHCP static mapping (directores):** registar o dispositivo do
director em **Services > DHCP Server > Static Mappings** (IP fixo ligado ao MAC).
Adicionar esse IP (ou CIDR da sala de direcção) na **Lista VIP** em
**Excepções > Lista VIP (isencao total)** com descricao legivel (ex. «Director
— notebook»). Evitar depender de lease dinamico — drift de IP quebra isenção PF,
view Unbound e verificador. Para varios directores, preferir grupo **Grupos**
resolvido para IPs estaveis + entradas manuais na Lista VIP conforme necessario.

### 20.1 Setup — perfil block + blacklist UT1 + block page

1. **Excepções > Lista VIP:** adicionar IP do director (descricao + IP/CIDR);
   confirmar excepção `vip-isentos` actualizada (SSOT inalterado; labels em
   `vip_meta.labels` apenas GUI).
2. **Definições > Pagina de bloqueio:** activar toggle; **Forcar DNS local**
   ON se disponivel; gravar e confirmar `service layer7-blockpage status` →
   running.
3. **Politicas > Perfis rapidos:** activar perfil **YouTube** (ou outro block)
   com acção `block` — **nao** duplicar o director em Isentos do modal se ja
   estiver na Lista VIP (mesmo SSOT `vip-isentos`).
4. **Blacklists UT1:** activar feed com pelo menos uma categoria; confirmar regra
   UT1 com acção block e «Incluir dominios blacklist» ON na block page (sec.
   **18.3**).
5. **Filter reload** / Resync Layer7; confirmar servico `layer7d` activo.
6. Escolher dominio **A** sinkhole pelo perfil (ex. `youtube.com`) e dominio
   **B** sinkhole pela blacklist UT1 (anotar qual categoria/domínio).

Verificacao read-only pos-setup:

```sh
grep -A6 'Layer7 VIP DNS exempt' /var/unbound/unbound.conf
# ou custom_options no config.xml — markers START/END e view layer7-vip-exempt

grep -A2 'Layer7 block-page' /var/unbound/unbound.conf
pfctl -a natrules/layer7_nat -s nat | grep -E 'blockpage|:53'
pfctl -t layer7_exc_allow_0 -T show 2>/dev/null || pfctl -t layer7_exc_allow_1 -T show
```

### 20.2 Modo DNS efectivo — opção (a) vs fallback (b)

Na GUI **Lista VIP**, ler o alerta no topo da seccao:

| Modo GUI | Significado | O que verificar no appliance |
|----------|-------------|------------------------------|
| **Isencao DNS** (info, azul) | Opção **(a)** activa — view `layer7-vip-exempt` | Markers `# --- Layer7 VIP DNS exempt START ---` em `custom_options`; bloco `view: name: "layer7-vip-exempt"` e `access-control-view: <IP/CIDR> layer7-vip-exempt` para cada origem VIP; **sem** `view-first` no snippet Layer7 |
| **Aviso DNS (fallback)** (warning) | Opção **(b)** — rdr `:53` exclui VIP, sinkhole local permanece | Markers VIP ausentes ou stripados; regras NAT `:53` com `from !<layer7_exc_allow_N>` (nao `from any` puro para origens nao isentas); GUI declara limitacao sinkhole |

Comandos adicionais:

```sh
# Confirmar unbound-checkconf passou na ultima gravacao (log system ou mensagem GUI)
/usr/local/sbin/unbound-checkconf /var/unbound/unbound.conf

# Modo rdr fallback: inspeccionar NAT DNS forcado
pfctl -a natrules/layer7_nat -s nat -v | grep -E '53|layer7_exc_allow'
```

**Resultado esperado (a):** director resolve dominio sinkhole para IP real
(nao IP portal); navega HTTP/HTTPS sem pagina «Acesso bloqueado».

**Resultado esperado (b):** director **nao** redireccionado na porta 53 se usar
DNS externo; se usar Unbound local, **continua** sujeito a sinkhole — documentar
como limitacao honesta (ADR-0020 §3).

### 20.3 Two-client — VIP isento vs não-VIP bloqueado

Executar de **dois** clientes distintos (nao misturar origem na mesma sessao).

**Cliente VIP (director):**

1. `drill <dominio_A> @<IP_pfsense>` — **nao** deve devolver IP portal (modo a);
   anotar IP real ou NXDOMAIN legitimo.
2. `http://<dominio_A>/` — pagina real ou erro de origem, **nao** block page Layer7.
3. Repetir para **dominio_B** (blacklist UT1 sinkhole).
4. **Teste / verificador** (`layer7_test.php`): IP director + dominio →
   **PERMITIDO — excepcao `vip-isentos`**.

**Cliente não-VIP:**

1. `drill <dominio_A> @<IP_pfsense>` → IP portal (sinkhole).
2. `http://<dominio_A>/` → pagina «Acesso bloqueado» Layer7.
3. Verificador: **BLOQUEADO** (politica ou blacklist conforme dominio).

**Evidencias minimas (PASS):**

- Modo DNS documentado (a ou b) coerente com GUI e Unbound/NAT.
- VIP: DNS + HTTP livres em dominios sinkhole (a) ou limitacao (b) explicita.
- Não-VIP: sinkhole + block page em ambos dominios A e B.
- `pfctl -t layer7_exc_allow_*` contem IP do director.

### 20.4 Persistencia apos `filter_configure` externo (`>= 1.8.11_60`)

**Objectivo:** validar que `layer7_vip_dns_rdr_fallback_enabled()` deriva estado
persistente (mesmos criterios que `layer7_vip_dns_mode_get`) e sobrevive a reloads
PF desencadeados **fora** do Resync Layer7 — correcao `_60`; `_61` nao altera
comportamento observavel mas e a linha recomendada para lab (performance em
`filter_configure`).

**Pre-condicao:** **20.3** PASS; em modo fallback **(b)** este passo e
**obrigatorio**; em modo **(a)** registar **N/A** com justificacao (teste
aplica-se sobretudo ao rdr `:53`).

1. Com VIP isento e nao-VIP bloqueado confirmados (**20.3**), gravar uma regra
   pfSense **nao relacionada** com Layer7 (ex.: regra temporaria em
   **Firewall > Rules** numa interface LAN) **ou** accionar evento equivalente
   que force `filter_configure` (ex.: pequeno toggle numa interface) — **sem**
   abrir Resync Layer7.
2. Confirmar reload PF (log system / timestamp de regras NAT).
3. Inspeccionar NAT DNS (modo **b**):

```sh
pfctl -a natrules/layer7_nat -s nat -v | grep -E '53|layer7_exc_allow'
```

   — regras `:53` devem manter `from !<layer7_exc_allow_N>`, **nao** `from any`
   puro.
4. Repetir testes **20.3** (two-client, dominios A e B):
   - **Cliente VIP:** `drill` + HTTP — continua isento do sinkhole.
   - **Cliente nao-VIP:** continua bloqueado (sinkhole + block page).
5. Remover regra temporaria pfSense; Filter reload se necessario.

**Evidencias minimas (PASS):**

- Modo **(b):** apos `filter_configure` externo, NAT `:53` preserva exclusao VIP;
  two-client inalterado (VIP livre, nao-VIP bloqueado).
- Modo **(a):** N/A documentado ou view Unbound intacta pos-reload.

### 20.5 Host overrides nativos Unbound (ADR-0020)

Trade-off da view dedicada: IPs VIP podem **nao** herdar host overrides nativos
do pfSense definidos na view global.

1. Em **Services > DNS Resolver > Host Overrides**, criar entrada de teste
   (ex.: `lab-vip-test.example.com` → `203.0.113.50`).
2. Do **cliente VIP**: `drill lab-vip-test.example.com @<IP_pfsense>` — registar
   se resolve para `203.0.113.50` ou comportamento diferente.
3. Do **cliente não-VIP**: repetir — deve reflectir override global habitual.
4. Documentar veredicto: **aceitavel** (override VIP funciona ou falha prevista
   e aceite para directores) vs **bloqueante** (falha impede producao da opção a)
   → nesse caso activar-se-ia fallback (b) no codigo; nao improvisar no appliance.

Remover override de teste apos o gate.

### 20.6 Modos de falha (FAIL)

| Sintoma | Causa provavel |
|---------|----------------|
| VIP recebe IP portal no `drill` | View Unbound inactiva; fallback (b) com DNS local; IP errado na Lista VIP |
| VIP ve block page HTTP | Isenção PF/daemon OK mas DNS nao; rever `layer7_vip_dns_sync` e markers |
| Não-VIP navega livremente | Enforce off; VIP CIDR demasiado largo; politica nao activa |
| `unbound-checkconf` FAIL apos gravar | Snippet VIP invalido — sistema deve cair para (b); confirmar alerta GUI |
| Host override VIP inaceitavel | Trade-off ADR-0020; escalar decisao humana antes de enforce prod |
| Verificador PERMITIDO mas browser bloqueado | Cache DNS no cliente; repetir com flush ou `drill` directo ao pfSense |
| VIP bloqueado apos gravar regra pfSense | Regressao `_59` ou anterior: `filter_configure` externo repoe rdr `:53` `from any`; actualizar para `>= _60` |
| NAT `:53` volta a `from any` pos-reload PF | `layer7_vip_dns_rdr_fallback_enabled()` sem estado persistente — confirmar versao `_60`+ |

Qualquer FAIL: preservar config export, logs Unbound/system, parar teste; **nao**
promover versao nem alterar referencia `_24`.

### 20.7 Rollback

1. Desactivar perfis block e blacklists de teste; desactivar block page na GUI.
2. Remover entradas de teste da Lista VIP (ou reinstalar snapshot exportado).
3. Regressao de pacote no lab:
   - problemas com `_61` → reinstalar **`1.8.11_60`** (fix P1 mantido);
   - problemas com `_60` ou isencao DNS incompleta → `_59` (parcial) ou `_58`/`_56`
     (perde isencao DNS completa mas mantem Lista VIP GUI parcial).
4. Confirmar remocao markers VIP e block page em Unbound; `service layer7-blockpage
   stop`; Filter reload.
5. Producao: manter **`1.8.11_24`** passivo até gates G2–G7.

**Teste automatizado local (builder, nao substitui appliance):**
`php tests/functional/test_vip_dns_exempt.php` + `sh tests/test_blockpage_config.sh`.

PASS minimo no appliance: **20.1** + **20.3** (two-client, dominios A e B) +
modo DNS documentado em **20.2**; **20.4** executado em modo **(b)** ou N/A
documentado em **(a)** (pacote `>= _60`); **20.5** executado ou explicitamente
dispensado com decisao humana registada. Pacote lab recomendado: **`>= 1.8.11_61`**.
**NO-GO producao inalterado.**

**Plano SSOT:** [`../02-roadmap/plano-isencao-vip-e-ux-gui.md`](../02-roadmap/plano-isencao-vip-e-ux-gui.md) (Bloco E);
[`../03-adr/ADR-0020-isencao-vip-dns.md`](../03-adr/ADR-0020-isencao-vip-dns.md).
