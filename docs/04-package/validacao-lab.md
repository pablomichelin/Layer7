# Validacao em lab - `pfSense-pkg-layer7`

**Objetivo:** obter evidencia objetiva de que o port gera um pacote instalavel no pfSense CE (`.pkg` ou `.txz`, conforme o host), que os ficheiros aparecem no disco, que o servico pode arrancar e que a pagina responde sem erro fatal.

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
- o documento e o fluxo do projeto continuam validos para `.txz` quando esse for o formato emitido no host de build

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

- validar `pfctl` do fluxo de enforce (secao 6b do plano original)
- validar reboot e persistencia
- validar GUI manual completa e menu do pacote
- reduzir ou eliminar a dependencia de `IGNORE_OSVERSION=yes`

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
