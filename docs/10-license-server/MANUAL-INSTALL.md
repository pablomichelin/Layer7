# Manual de Instalacao — Layer7 para pfSense CE

> Comandos prontos para copiar e colar no shell do pfSense (SSH ou Console).
> Executar tudo como **root**.

---

## 1. Instalar (primeira vez)

```sh
fetch -o /tmp/pfSense-pkg-layer7-1.0.2.pkg https://github.com/pablomichelin/pfsense-layer7/releases/download/v1.0.2/pfSense-pkg-layer7-1.0.2.pkg
```

```sh
IGNORE_OSVERSION=yes pkg add -f /tmp/pfSense-pkg-layer7-1.0.2.pkg
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

```sh
service layer7d onestop
```

```sh
fetch -o /tmp/pfSense-pkg-layer7-1.0.2.pkg https://github.com/pablomichelin/pfsense-layer7/releases/download/v1.0.2/pfSense-pkg-layer7-1.0.2.pkg
```

```sh
IGNORE_OSVERSION=yes pkg add -f /tmp/pfSense-pkg-layer7-1.0.2.pkg
```

```sh
service layer7d onestart
```

```sh
layer7d -V
```

---

## 5. Reinstalar (mesma versao)

```sh
service layer7d onestop
```

```sh
pkg delete -y pfSense-pkg-layer7
```

```sh
IGNORE_OSVERSION=yes pkg add -f /tmp/pfSense-pkg-layer7-1.0.2.pkg
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
| `/usr/local/etc/rc.d/layer7d`        | Script rc.d do servico           |
| `/var/run/layer7d.pid`               | PID do daemon                    |
| `/var/log/system.log`                | Logs do daemon                   |

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
```

O pfSense volta ao funcionamento normal imediatamente.
A configuracao (`layer7.json`) e a licenca (`layer7.lic`) sao preservadas
para uma reinstalacao futura.
