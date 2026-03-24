# Manual de Instalacao — Layer7 para pfSense CE

> Comandos prontos para copiar e colar no pfSense.
> Executar tudo como **root**.

---

## Como executar os comandos

Existem duas formas de executar comandos no pfSense:

### Opcao A: SSH ou Console (terminal)

Aceda via SSH (`ssh admin@IP_PFSENSE`) ou pelo menu **Console** do pfSense.
Neste modo pode executar os comandos um a um, como listados abaixo.

### Opcao B: Diagnostics > Command Prompt (GUI web)

Aceda pelo menu **Diagnostics > Command Prompt** no browser.
**IMPORTANTE:** Neste modo, cole o **comando unico (uma linha)** indicado
em cada seccao. Nao cole varios comandos separados — o pfSense executa
tudo como um unico comando e os restantes sao ignorados silenciosamente.

Cada seccao abaixo inclui:
- **Comando unico** — para colar no Command Prompt (uma linha com `&&`)
- **Passo a passo** — para executar no SSH/Console (um por vez)

---

## 1. Instalar (primeira vez)

**Instalador automatico (recomendado — uma linha):**

```sh
fetch -o /tmp/install.sh https://raw.githubusercontent.com/pablomichelin/pfsense-layer7/main/scripts/release/install.sh && sh /tmp/install.sh
```

Este script faz tudo automaticamente: baixa o `.pkg`, instala, cria tabelas PF, configura e inicia o servico.

Para uma versao especifica: `sh /tmp/install.sh --version 1.3.4`

**Comando unico manual (Command Prompt):**

Comando para instalar em uma linha
fetch -o /tmp/install.sh https://raw.githubusercontent.com/pablomichelin/pfsense-layer7/main/scripts/release/install.sh && sh /tmp/install.sh


```sh
fetch -o /tmp/pfSense-pkg-layer7-1.3.4.pkg https://github.com/pablomichelin/pfsense-layer7/releases/download/v1.3.4/pfSense-pkg-layer7-1.3.4.pkg && IGNORE_OSVERSION=yes pkg add -f /tmp/pfSense-pkg-layer7-1.3.4.pkg && sysrc layer7d_enable=YES && service layer7d onestart && layer7d -V
```

**Passo a passo (SSH/Console):**

```sh
fetch -o /tmp/pfSense-pkg-layer7-1.3.4.pkg https://github.com/pablomichelin/pfsense-layer7/releases/download/v1.3.4/pfSense-pkg-layer7-1.3.4.pkg
```
```sh
fetch -o /tmp/install.sh https://raw.githubusercontent.com/pablomichelin/pfsense-layer7/main/scripts/release/install.sh && sh /tmp/install.sh --force
```
```sh
IGNORE_OSVERSION=yes pkg add -f /tmp/pfSense-pkg-layer7-1.3.4.pkg
```

```sh
sysrc layer7d_enable=YES
```

```sh
service layer7d onestart
```

Verificar:

```sh
layer7d -V
```

```sh
service layer7d onestatus
```

---

## 2. Activar licenca

Ver o fingerprint do hardware (anotar para usar no painel web):

```sh
layer7d --fingerprint
```

Activar online (substitua CHAVE pela chave de 32 hex do painel):

```sh
layer7d --activate CHAVE
```

Verificar estado da licenca:

```sh
layer7d --license-status
```

---

## 3. Instalar licenca manualmente (offline)

Se o pfSense nao tem acesso a internet para contactar o servidor de licencas,
copie o ficheiro `.lic` de outro computador via SCP:

```sh
# No computador que tem o ficheiro .lic:
scp layer7-XXXXXXXX.lic admin@IP_PFSENSE:/usr/local/etc/layer7.lic
```

Depois no pfSense:

```sh
service layer7d onerestart
```

```sh
layer7d --license-status
```

---

## 4. Actualizar (upgrade)

**Instalador automatico (recomendado — uma linha):**

```sh
fetch -o /tmp/install.sh https://raw.githubusercontent.com/pablomichelin/pfsense-layer7/main/scripts/release/install.sh && sh /tmp/install.sh
```

O script detecta a versao instalada e faz o upgrade automaticamente.

**Comando unico manual (Command Prompt):**

```sh
service layer7d onestop && fetch -o /tmp/pfSense-pkg-layer7-1.3.4.pkg https://github.com/pablomichelin/pfsense-layer7/releases/download/v1.3.4/pfSense-pkg-layer7-1.3.4.pkg && IGNORE_OSVERSION=yes pkg add -f /tmp/pfSense-pkg-layer7-1.3.4.pkg && service layer7d onestart && layer7d -V
```

**Passo a passo (SSH/Console):**

```sh
service layer7d onestop
```

```sh
fetch -o /tmp/pfSense-pkg-layer7-1.3.4.pkg https://github.com/pablomichelin/pfsense-layer7/releases/download/v1.3.4/pfSense-pkg-layer7-1.3.4.pkg
```

```sh
IGNORE_OSVERSION=yes pkg add -f /tmp/pfSense-pkg-layer7-1.3.4.pkg
```

```sh
service layer7d onestart
```

```sh
layer7d -V
```

Politicas, excepcoes, grupos, blacklists e licenca sao preservados durante o upgrade.

---

## 5. Reinstalar (mesma versao)

**Comando unico (Command Prompt):**

```sh
service layer7d onestop && pkg delete -y pfSense-pkg-layer7 && IGNORE_OSVERSION=yes pkg add -f /tmp/pfSense-pkg-layer7-1.3.4.pkg && sysrc layer7d_enable=YES && service layer7d onestart
```

**Passo a passo (SSH/Console):**

```sh
service layer7d onestop
```

```sh
pkg delete -y pfSense-pkg-layer7
```

```sh
IGNORE_OSVERSION=yes pkg add -f /tmp/pfSense-pkg-layer7-1.3.4.pkg
```

```sh
sysrc layer7d_enable=YES
```

```sh
service layer7d onestart
```

---

## 6. Desinstalar

```sh
service layer7d onestop
```

```sh
pkg delete -y pfSense-pkg-layer7
```

Limpar configs (opcional — remove tudo):

```sh
rm -f /usr/local/etc/layer7.json
rm -f /usr/local/etc/layer7.lic
rm -f /usr/local/etc/layer7-protos.txt
rm -rf /usr/local/etc/layer7/blacklists
```

---

## 7. Controle do servico

| Acao       | Comando                          |
|------------|----------------------------------|
| Iniciar    | `service layer7d onestart`       |
| Parar      | `service layer7d onestop`        |
| Reiniciar  | `service layer7d onerestart`     |
| Status     | `service layer7d onestatus`      |
| Reload     | `service layer7d onereload`      |
| Habilitar  | `sysrc layer7d_enable=YES`       |
| Desabilitar| `sysrc layer7d_enable=NO`        |

---

## 8. Verificacoes e diagnostico

Versao instalada:

```sh
layer7d -V
```

Status do daemon:

```sh
service layer7d onestatus
```

Fingerprint de hardware:

```sh
layer7d --fingerprint
```

Estado da licenca:

```sh
layer7d --license-status
```

Logs do sistema:

```sh
tail -50 /var/log/system.log | grep layer7
```

Verificar tabelas PF:

```sh
pfctl -s Tables | grep layer7
```

Ver IPs bloqueados:

```sh
pfctl -t layer7_block -T show
```

Ver IPs de destino bloqueados (sites/blacklists):

```sh
pfctl -t layer7_block_dst -T show
```

Ver IPs de destino bloqueados por regra de blacklist (N = 0-7):

```sh
pfctl -t layer7_bld_0 -T show
```

Ver log de actualizacao de blacklists:

```sh
tail -30 /var/log/layer7-bl-update.log
```

Verificar se o binario esta presente:

```sh
ls -la /usr/local/sbin/layer7d
```

Verificar se a config existe:

```sh
cat /usr/local/etc/layer7.json
```

Verificar se a licenca existe:

```sh
cat /usr/local/etc/layer7.lic
```

---

## 9. Caminhos importantes

| Ficheiro                             | Descricao                        |
|--------------------------------------|----------------------------------|
| `/usr/local/sbin/layer7d`            | Binario do daemon                |
| `/usr/local/etc/layer7.json`         | Configuracao principal           |
| `/usr/local/etc/layer7.lic`          | Ficheiro de licenca              |
| `/usr/local/etc/layer7-protos.txt`   | Lista de protocolos conhecidos   |
| `/usr/local/etc/layer7/lang/`        | Ficheiros de traducao (en.php, pt.php) |
| `/usr/local/etc/layer7/blacklists/`  | Directorio de blacklists UT1     |
| `/usr/local/etc/layer7/blacklists/config.json` | Config das blacklists   |
| `/usr/local/etc/layer7/blacklists/discovered.json` | Categorias auto-descobertas |
| `/usr/local/etc/layer7/update-blacklists.sh` | Script de download        |
| `/usr/local/etc/rc.d/layer7d`        | Script rc.d do servico           |
| `/var/run/layer7d.pid`               | PID do daemon                    |
| `/var/log/system.log`                | Logs do daemon                   |
| `/var/log/layer7-bl-update.log`      | Log de actualizacao de blacklists|

---

## 10. Rollback de emergencia

Se algo der errado apos instalar ou actualizar:

```sh
service layer7d onestop
```

```sh
pkg delete -y pfSense-pkg-layer7
```

```sh
pfctl -t layer7_block -T flush
pfctl -t layer7_block_dst -T flush
pfctl -t layer7_bld_0 -T flush
pfctl -t layer7_bld_1 -T flush
```

O pfSense volta ao funcionamento normal imediatamente.
A configuracao (`layer7.json`), a licenca (`layer7.lic`) e as blacklists
sao preservadas para uma reinstalacao futura.
