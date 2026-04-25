# Manual de Instalacao — Layer7 para pfSense CE

> Comandos prontos para copiar e colar no pfSense.
> Executar tudo como **root**.

---

## Regra operacional deste manual

**SEMPRE actualizar este ficheiro quando houver nova versao publicada.**

Ao subir uma nova versao, actualizar no mesmo bloco:
- links directos da release
- nome do `.pkg`
- comandos de install, upgrade e reinstall
- exemplos com `--version`

**Contrato operacional vigente da F1.2:** o canal oficial usa GitHub Releases
versionado para `install.sh`, `uninstall.sh`, `.pkg`, `.pkg.sha256`,
`release-manifest.v1.txt`, `release-manifest.v1.txt.sig` e
`release-signing-public-key.pem`.
O fluxo de validacao detalhado da release fica em
`docs/06-releases/RELEASE-SIGNING.md`.

**Addendum operacional da F1.3:** o pacote em `main` passa a incluir a trilha
segura de blacklists com manifesto dedicado, public key propria de
blacklists, origem oficial HTTPS, mirror controlado, cache local em
`/usr/local/etc/layer7/blacklists/.cache/` e last-known-good em
`/usr/local/etc/layer7/blacklists/.last-known-good/`.
O restauro explicito da ultima snapshot valida passa a ser:

```sh
/usr/local/etc/layer7/update-blacklists.sh --restore-lkg
```

**Addendum operacional da F1.4:** o `install.sh` oficial publicado por tag
passa a validar `release-manifest.v1.txt`, `release-manifest.v1.txt.sig` e o
`sha256` do `.pkg` antes de instalar. Se manifesto, assinatura, fingerprint
da public key ou checksum divergirem, o comportamento passa a ser
**fail-closed**: o pacote novo nao e instalado. Falhas locais pos-install
(PF, Unbound, arranque do servico) ficam rastreaveis como `DEGRADED` no
stdout e no syslog (`layer7-install`).

**Addendum operacional da F4.1 (BG-009, branch / `PORTVERSION` de trabalho):**
o hook **POST-INSTALL** do port executa `service layer7d onestop` antes de
`onestart`, para que um **upgrade** por `pkg` carregue o binario do pacote
de novo instalado (evita processo antigo a continuar a correr). O **rc.d**
`layer7d` ajusta as permissoes do **pidfile** apos o arranque de forma
coerente com `service layer7d status` e valida o conteudo do ficheiro
(PID numerico, sem espacos parasitas) em `start`/`stop`/`status`/`reload`,
na mesma linha que `update-blacklists.sh` e `layer7-stats-collect.sh`. Em
PHP, `layer7_daemon_pid_from_file()` em `usr/local/pkg/layer7.inc` le so a
primeira linha do pidfile antes de validar o PID (Dashboard, reload via
GUI, stats). Nao
editar `/var/run/layer7d.pid` manualmente; em duvida, `service layer7d
restart`. A reconfiguracao via GUI (`layer7_apply`, reload) alinha-se ao
`reload` do script: sinal `HUP` se o processo estiver vivo; caso contrario,
arranque do daemon conforme `layer7.enabled` no JSON. O par
`PORTVERSION`/`PORTREVISION` em desenvolvimento no branch consta em
`CORTEX.md` e no `Makefile` do port (nao confundir com o `.pkg` listado em
**Links da versao actual** ate nova release). Isto entra nas proximas
release notes quando o `.pkg` correspondente for publicado; ate la, a
referencia de instalacao publica continua a versao listada em **Links da
versao actual** abaixo.

**Addendum operacional da F4.3 (BG-011, `force_dns` / DNS forcado):** nas
regras de **blacklist** com opcao *Forcar DNS local* (`force_dns`), o
pfSense **nao** aplica `rdr` pelo fluxo `nat_rules_needed` do XML; o pacote
injecta regras no sub-anchor NAT `natrules/layer7_nat` (via
`layer7_inject_nat_to_anchor` em todo reload de filtro coerente com a GUI).
**Se** existem regras activas, verifique-as com
`pfctl -a natrules/layer7_nat -s nat` (isto nao e o mesmo que o ficheiro
`/usr/local/etc/layer7/pf.conf` das tabelas de bloqueio). Cada
origem em **CIDRs** deve ser **IPv4** valido (CIDR ou host); valores que
nao passam a validacao sao **ignorados** na geracao, para o `pfctl` nao
rejeitar o anchor. A lista de interfaces do Layer7 fica **deduplicada** ao
gerar as linhas; pares **(interface, CIDR)** repetidos em mais do que uma regra
com `force_dns` geram uma unica dupla de `rdr` UDP/TCP (evita entradas
redundantes no anchor). A partir de `1.8.11_9`, as interfaces efectivas sao
ordenadas **alfabeticamente** antes da emissao das linhas `rdr`, para ordem
estavel no anchor entre reloads. A partir de `1.8.11_10`, em cada regra, os
CIDRs IPv4 validos sao unicos, ordenados alfabeticamente e validados uma vez
antes do cruzamento com as interfaces. Esta trilha gera apenas regras **inet** (IPv4); nao
inclui `rdr` **inet6** para DNS. Comportamento alinhado ao branch com
`PORTVERSION` / `PORTREVISION` de trabalho; a referencia de `.pkg` publica
continua a seccao **Links da versao actual** ate nova release. Nomes de
interface na geracao de `rdr` seguem o mesmo padrao restritivo que o
anti-QUIC; se o `pfctl` falhar ao carregar o anchor, o sistema pode registar
um aviso no log do pfSense (`log_error`). Roteiro de validacao no appliance
para esta trilha (comandos `pfctl`, critérios de **PASS**, anti-QUIC opcional no
mesmo roteiro e cenario sugerido multi-interface / VLAN):
`docs/04-package/validacao-lab.md`, seccao **11**.

**Addendum operacional da F4.2 adicional (`1.8.11_7`, branch de trabalho):**
a trilha de blacklists passa a falhar de forma mais segura em reload: se uma
nova carga de categorias falhar, o daemon preserva a blacklist anterior e as
tabelas activas, em vez de limpar o estado antes de validar a nova carga. A
classificacao por DNS passa a usar o IP do cliente observado na resposta DNS
para respeitar `src_cidrs` por regra, alinhando-se ao comportamento por SNI.
A GUI passa a mostrar erro quando nao conseguir gravar
`/usr/local/etc/layer7/blacklists/config.json` ou os overlays
`_custom/*.domains`; o package ajusta permissoes para `www:wheel`. O cron de
auto-update usa `update_interval_hours` para gerar campos cron coerentes. Estes
pontos exigem build no FreeBSD builder e evidencia no appliance antes de
fechamento F4.2/F4.3.

**Addendum operacional da F2.5:** a operacao do license server passa a usar
runbooks canónicos especificos para segredos/bootstrap administrativo e
backup/restore do PostgreSQL:

- `docs/05-runbooks/license-server-segredos-bootstrap.md`
- `docs/05-runbooks/license-server-backup-restore.md`

**Addendum operacional da release `1.8.11_13` (rotacao da chave F1.3 +
publicacao de pacote sem trust chain F1.2 activo):** esta release publica em
`pablomichelin/Layer7` **apenas** os assets
`pfSense-pkg-layer7-1.8.11_13.pkg` e `pfSense-pkg-layer7-1.8.11_13.pkg.sha256`,
mantendo o padrao operacional das releases publicas anteriores (`v1.7.8` a
`v1.8.11_12`). O trust chain F1.2/F1.4 do **pacote**
(`release-manifest.v1.txt`, `release-manifest.v1.txt.sig`,
`release-signing-public-key.pem` e `install.sh` carimbado fail-closed)
continua **nao activado**; a activacao formal pela primeira vez fica registada
em `docs/02-roadmap/backlog.md` como **BG-028** e sera tratada num bloco
controlado proprio com ADR. O caminho oficial nesta release e o **comando
unico manual** das seccoes **1**/**4**/**5** (sem `install.sh`/`uninstall.sh`).

A novidade da `1.8.11_13` e a **rotacao da chave Ed25519 publica embutida no
pacote** que valida o trust chain das **blacklists** (F1.3/F1.4): a chave
anterior (fingerprint `e501f5635bf56c6dfc6891ee969ef04ff193ed3afc879997bd4066b6ba3cb064`)
nunca foi usada para assinar uma snapshot publica e foi substituida pela
nova chave (fingerprint
`6190b8d26fb9cb951ccb2c1f4e921228e4edf388c23f51afd93f1fd3ca1ba4fc`); a
**chave privada** correspondente ficou em custodia humana, **fora do builder
e fora do repositorio**. A primeira snapshot UT1 publica assinada com esta
chave foi publicada em paralelo em
`https://github.com/pablomichelin/Layer7/releases/tag/blacklists-ut1-current`
(rolling tag `blacklists-ut1-current`, com `layer7-blacklists-manifest.v1.txt`,
`layer7-blacklists-manifest.v1.txt.sig`, `blacklists-signing-public-key.pem`
e `layer7-blacklists-ut1.tar.gz`). O updater
`/usr/local/etc/layer7/update-blacklists.sh` desta release **so aceita**
manifestos assinados por esta nova chave.

> **Nota historica:** o addendum operacional da release `1.8.11_12` ficou
> registado no `CHANGELOG.md` em `[1.8.11_12] - 2026-04-24`; a logica
> operacional aqui descrita aplica-se desde entao, e a `1.8.11_13` apenas
> rotaciona a chave de blacklists.

**Addendum operacional pos-upgrade (recompilacao obrigatoria do filtro PF):**
`pfctl -sr | grep -i layer7` deve devolver as regras `block drop quick` da
trilha Layer7 apos o `pkg add`. Se devolver vazio (regras nao integradas no
ruleset activo), executar **uma vez** `/etc/rc.filter_configure_sync` para
forcar a recompilacao do filtro pelo pfSense. Esta etapa **deve** ser feita
sempre que o pacote for instalado/actualizado num ambiente onde o filtro nao
foi recompilado desde a versao anterior, e e segura (e o mesmo procedimento
disparado por **Apply** em **Firewall > Rules** na GUI).

---

## Links da versao actual (para teste)

**Versao actual:** `1.8.11_13`

- **Release:** `https://github.com/pablomichelin/Layer7/releases/tag/v1.8.11_13`
- **Pacote `.pkg`:** `https://github.com/pablomichelin/Layer7/releases/download/v1.8.11_13/pfSense-pkg-layer7-1.8.11_13.pkg`
- **SHA256:** `https://github.com/pablomichelin/Layer7/releases/download/v1.8.11_13/pfSense-pkg-layer7-1.8.11_13.pkg.sha256`
- **SHA256 esperado (`pfSense-pkg-layer7-1.8.11_13.pkg`):** `041e1ace4611ebb1cebd7bfadc22e0bb2c9b2b24b99900e3034f107b534351ae`

**Blacklists UT1 oficiais (F1.3 — primeira publicacao):**

- **Release:** `https://github.com/pablomichelin/Layer7/releases/tag/blacklists-ut1-current`
- **Manifesto:** `https://github.com/pablomichelin/Layer7/releases/download/blacklists-ut1-current/layer7-blacklists-manifest.v1.txt`
- **Assinatura:** `https://github.com/pablomichelin/Layer7/releases/download/blacklists-ut1-current/layer7-blacklists-manifest.v1.txt.sig`
- **Chave publica:** `https://github.com/pablomichelin/Layer7/releases/download/blacklists-ut1-current/blacklists-signing-public-key.pem`
- **Snapshot tar.gz:** `https://github.com/pablomichelin/Layer7/releases/download/blacklists-ut1-current/layer7-blacklists-ut1.tar.gz`
- **Snapshot id:** `ut1-2026-04-25` (69 categorias, 6 623 069 dominios, 31 169 229 bytes)
- **SHA256 do tar.gz:** `4191e2ebdc13e3c87d777103528bab4fda6b273bc40c62a2c39cb820ad493d36`
- **Fingerprint da chave publica embutida no pacote:** `6190b8d26fb9cb951ccb2c1f4e921228e4edf388c23f51afd93f1fd3ca1ba4fc`

> **Nota operacional sobre F1.2 nesta release:** o trust chain F1.2/F1.4
> (`release-manifest.v1.txt`, `release-manifest.v1.txt.sig`,
> `release-signing-public-key.pem`, `install.sh` carimbado e fail-closed) **nao
> esta activo nesta release**, mantendo o padrao operacional das releases
> publicas anteriores (`v1.7.8` a `v1.8.3`), que tambem so publicaram `.pkg` +
> `.pkg.sha256`. A activacao formal da F1.2 assinada pela primeira vez fica
> registada como item dedicado no backlog (ver `docs/02-roadmap/backlog.md`,
> **BG-028**) e sera tratada num bloco controlado proprio com ADR. Nesta
> release, instalar/actualizar via **comando unico manual** desta seccao (sem
> `install.sh`/`uninstall.sh`).

**Comandos rapidos de teste:**

Baixar o `.pkg` directo da versao `1.8.11_13`:

```sh
fetch -o /tmp/pfSense-pkg-layer7-1.8.11_13.pkg https://github.com/pablomichelin/Layer7/releases/download/v1.8.11_13/pfSense-pkg-layer7-1.8.11_13.pkg
```

Validar checksum:

```sh
fetch -o /tmp/pfSense-pkg-layer7-1.8.11_13.pkg.sha256 https://github.com/pablomichelin/Layer7/releases/download/v1.8.11_13/pfSense-pkg-layer7-1.8.11_13.pkg.sha256 && sha256 -q /tmp/pfSense-pkg-layer7-1.8.11_13.pkg | tee /tmp/l7-actual.sha256 && cat /tmp/pfSense-pkg-layer7-1.8.11_13.pkg.sha256
```

Os dois ultimos `cat` devem mostrar o mesmo `sha256`. Esperado:
`041e1ace4611ebb1cebd7bfadc22e0bb2c9b2b24b99900e3034f107b534351ae`.

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

> **Nesta release (`1.8.11_13`)** o caminho oficial e o **comando unico manual**
> abaixo. O `install.sh` automatico (carimbado/assinado F1.2) nao e publicado
> nesta release: ver nota em **Links da versao actual** e **BG-028** no
> backlog.

**Comando unico manual (recomendado — uma linha, Command Prompt ou SSH):**

```sh
fetch -o /tmp/pfSense-pkg-layer7-1.8.11_13.pkg https://github.com/pablomichelin/Layer7/releases/download/v1.8.11_13/pfSense-pkg-layer7-1.8.11_13.pkg && IGNORE_OSVERSION=yes pkg add -f /tmp/pfSense-pkg-layer7-1.8.11_13.pkg && sysrc layer7d_enable=YES && service layer7d onestart && layer7d -V
```

**Passo a passo (SSH/Console):**

```sh
fetch -o /tmp/pfSense-pkg-layer7-1.8.11_13.pkg https://github.com/pablomichelin/Layer7/releases/download/v1.8.11_13/pfSense-pkg-layer7-1.8.11_13.pkg
```

```sh
IGNORE_OSVERSION=yes pkg add -f /tmp/pfSense-pkg-layer7-1.8.11_13.pkg
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

Pela GUI (v1.4.8+), tambem pode registar e revogar em:
**Services > Layer 7 > Definicoes > Licenca**.

- Registo: introduzir o codigo e clicar **Registar licenca**
- Codigo activo fica mascarado (5 primeiros caracteres + `************`)
- Revogacao: botao **Revogar licenca**
- Importante: o codigo e case-sensitive no servidor; na v1.4.8 a GUI preserva o case introduzido

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

> **Nesta release (`1.8.11_13`)** o caminho oficial e o **comando unico manual**
> abaixo (sem `install.sh`). Ver nota em **Links da versao actual** e
> **BG-028**.

**Comando unico manual (recomendado — uma linha, Command Prompt ou SSH):**

```sh
service layer7d onestop && fetch -o /tmp/pfSense-pkg-layer7-1.8.11_13.pkg https://github.com/pablomichelin/Layer7/releases/download/v1.8.11_13/pfSense-pkg-layer7-1.8.11_13.pkg && IGNORE_OSVERSION=yes pkg add -f /tmp/pfSense-pkg-layer7-1.8.11_13.pkg && service layer7d onestart && layer7d -V
```

**Passo a passo (SSH/Console):**

```sh
service layer7d onestop
```

```sh
fetch -o /tmp/pfSense-pkg-layer7-1.8.11_13.pkg https://github.com/pablomichelin/Layer7/releases/download/v1.8.11_13/pfSense-pkg-layer7-1.8.11_13.pkg
```

```sh
IGNORE_OSVERSION=yes pkg add -f /tmp/pfSense-pkg-layer7-1.8.11_13.pkg
```

```sh
service layer7d onestart
```

```sh
layer7d -V
```

Politicas, excepcoes, grupos, blacklists e licenca sao preservados durante o upgrade.

**Apos o upgrade, recompilar o ruleset PF (uma vez) para garantir que as regras
Layer7 entram em `/tmp/rules.debug`:**

```sh
/etc/rc.filter_configure_sync
```

Verificar (deve retornar varias linhas):

```sh
pfctl -sr | grep -i layer7
```

---

## 5. Reinstalar (mesma versao)

**Comando unico (Command Prompt):**

```sh
service layer7d onestop && pkg delete -y pfSense-pkg-layer7 && fetch -o /tmp/pfSense-pkg-layer7-1.8.11_13.pkg https://github.com/pablomichelin/Layer7/releases/download/v1.8.11_13/pfSense-pkg-layer7-1.8.11_13.pkg && IGNORE_OSVERSION=yes pkg add -f /tmp/pfSense-pkg-layer7-1.8.11_13.pkg && sysrc layer7d_enable=YES && service layer7d onestart
```

**Passo a passo (SSH/Console):**

```sh
service layer7d onestop
```

```sh
pkg delete -y pfSense-pkg-layer7
```

```sh
fetch -o /tmp/pfSense-pkg-layer7-1.8.11_13.pkg https://github.com/pablomichelin/Layer7/releases/download/v1.8.11_13/pfSense-pkg-layer7-1.8.11_13.pkg
```

```sh
IGNORE_OSVERSION=yes pkg add -f /tmp/pfSense-pkg-layer7-1.8.11_13.pkg
```

```sh
sysrc layer7d_enable=YES
```

```sh
service layer7d onestart
```

---

## 6. Desinstalar

> **Nesta release (`1.8.11_13`)** o `uninstall.sh` automatico nao e publicado
> como asset (depende do trust chain F1.2 — ver **BG-028**). Use a
> **desinstalacao manual** abaixo, que executa as mesmas etapas: stop do
> servico, `pkg delete`, limpeza de ficheiros residuais, flush das tabelas PF
> e remocao do servico do boot.

### Opcoes do uninstall.sh

| Flag | O que faz |
|------|-----------|
| `--clean-unbound` | Remove overrides anti-DoH do Unbound (`config.xml`) |
| `--keep-config` | Preserva `layer7.json` e `layer7.lic` para reinstalacao futura |
| `--keep-license` | Preserva apenas `layer7.lic` |
| `--yes` | Nao pede confirmacao (obrigatorio no Command Prompt) |
| `--help` | Mostra ajuda |

### Exemplos de uso

Remocao completa (remove tudo, incluindo licenca e configs):

```sh
sh /tmp/uninstall.sh --clean-unbound --yes
```

Remocao preservando licenca e configuracao (para reinstalar depois):

```sh
sh /tmp/uninstall.sh --keep-config --clean-unbound --yes
```

Remocao preservando apenas a licenca:

```sh
sh /tmp/uninstall.sh --keep-license --clean-unbound --yes
```

Remocao sem limpar o Unbound:

```sh
sh /tmp/uninstall.sh --yes
```

### Desinstalacao manual (SSH/Console)

Se preferir fazer manualmente sem o script:

A partir do pacote **1.8.8** (branch de desenvolvimento / próxima release), o
`pkg delete` dispara hooks do port: em **PRE-DEINSTALL** o serviço é parado
(`onestop`); em **POST-DEINSTALL** remove-se um `layer7d.pid` stale e define-se
`layer7d_enable=NO`. Em releases anteriores, o `onestop` manual antes do delete
continua recomendado.

```sh
service layer7d onestop
```

```sh
pkg delete -y pfSense-pkg-layer7
```

Limpar ficheiros residuais:

```sh
rm -f /usr/local/sbin/layer7d
rm -f /usr/local/etc/layer7.json
rm -f /usr/local/etc/layer7.lic
rm -f /usr/local/etc/layer7-protos.txt
rm -rf /usr/local/etc/layer7
rm -rf /usr/local/www/packages/layer7
rm -f /usr/local/pkg/layer7.xml
rm -f /usr/local/pkg/layer7.inc
rm -f /etc/inc/priv/layer7.priv.inc
rm -f /usr/local/libexec/layer7-pfctl
rm -f /usr/local/libexec/layer7-unbound-anti-doh
rm -f /var/run/layer7d.pid
rm -f /var/log/layer7d.log
```

Limpar tabelas PF:

```sh
pfctl -t layer7_block -T flush
pfctl -t layer7_block_dst -T flush
for t in $(pfctl -s Tables 2>/dev/null | awk '/^layer7_bld_[0-9]+$/{print $1}'); do pfctl -t "$t" -T flush; done
```

Limpar overrides anti-DoH do Unbound (manual):
va em **Services > DNS Resolver**, clique em **Display Custom Options**,
apague todo o conteudo entre `# --- Layer7 anti-DoH/Relay START ---` e
`# --- Layer7 anti-DoH/Relay END ---`, e clique **Save** + **Apply Changes**.

### Apos desinstalar

O pfSense volta ao funcionamento normal imediatamente.
Para reinstalar a versao actual (`1.8.11_13`), usar o **comando unico manual**
da seccao **1**:

```sh
fetch -o /tmp/pfSense-pkg-layer7-1.8.11_13.pkg https://github.com/pablomichelin/Layer7/releases/download/v1.8.11_13/pfSense-pkg-layer7-1.8.11_13.pkg && IGNORE_OSVERSION=yes pkg add -f /tmp/pfSense-pkg-layer7-1.8.11_13.pkg && sysrc layer7d_enable=YES && service layer7d onestart && layer7d -V
```

---

## 7. Controle do servico

| Acao       | Comando                          |
|------------|----------------------------------|
| Iniciar    | `service layer7d onestart`       |
| Parar      | `service layer7d onestop`        |
| Reiniciar  | `service layer7d onerestart`     |
| Status     | `service layer7d onestatus`      |
| Reload     | `service layer7d reload`         |
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

Ver eventos do instalador oficial:

```sh
tail -50 /var/log/system.log | grep layer7-install
```

Blacklists UT1 + categorias personalizadas (v1.6.5+):

- Na pagina **Services > Layer 7 > Blacklists**, pode:
  - criar categoria local com os seus proprios dominios;
  - usar o mesmo ID de uma categoria UT1 para adicionar dominios extras (extensao da categoria da Capitole).
- O bloqueio continua por regra: selecione a categoria na regra e guarde.
- As extensoes locais sao aplicadas em runtime sem alterar o feed original da UT1.

Verificar se o binario esta presente:

```sh
ls -la /usr/local/sbin/layer7d
```

Verificar se a config existe:

```sh
ls -l /usr/local/etc/layer7.json
```

Verificar se a licenca existe:

```sh
ls -l /usr/local/etc/layer7.lic
```

---

## 9. Relatorios

A partir da v1.4.8, o Layer7 inclui um modulo de relatorios executivos acessivel em
**Services > Layer 7 > Relatorios**.

### O que faz

- Recolhe eventos de forma incremental para SQLite local (historico detalhado)
- Gera visao executiva (resumo textual, timeline, dispositivos e sites)
- Permite filtros por IP/dispositivo, site e resultado (bloqueado/permitido/monitorado)
- Permite exportar relatorios em CSV, HTML ou JSON para diretoria e auditoria
- v1.4.4: melhora visual da GUI para separar claramente blocos com acoes de guardar
- v1.4.5: ingestao incremental forcada na abertura/exportacao e parser de log robusto (ISO + syslog)
- v1.4.8: correlacao DNS por `dns_query` para mostrar dominio realmente tentado por IP
- v1.4.8: eventos com dominio inferido ficam identificados com etiqueta visual **Host inferido (DNS)**
- v1.4.8: registo de licenca preserva maiusculas/minusculas para evitar falso invalido por alteracao de case

### Configuracao

Em **Services > Layer 7 > Definicoes**, seccao **Relatorios**:

- **Activar recolha**: liga/desliga o cron de recolha de dados
- **Retencao**: presets 7/15/30/60/90/180/365 dias (+ custom)
- **Intervalo**: frequencia de recolha (5, 10, 15, 30 ou 60 minutos)

### Ficheiros de dados

| Ficheiro | Descricao |
|----------|-----------|
| `/usr/local/etc/layer7/reports/reports.db` | Base SQLite de relatorios executivos |
| `/usr/local/etc/layer7/reports/ingest.cursor` | Cursor de leitura incremental do log |
| `/usr/local/etc/layer7/reports/stats-history.jsonl` | Historico de stats (JSONL) |
| `/usr/local/etc/layer7/layer7-reports-collect.php` | Colector incremental para SQLite |
| `/usr/local/etc/layer7/layer7-stats-collect.sh` | Script colector (cron) |
| `/usr/local/etc/layer7/layer7-stats-purge.sh` | Script de limpeza (cron) |

### Exportacao

Na pagina de Relatorios, clique nos botoes CSV, HTML ou JSON no topo.
O ficheiro exportado cobre o periodo seleccionado no filtro.

O formato HTML gera um relatorio executivo pronto para impressao, PDF ou email.

---

## 10. Troubleshooting — Tabelas PF

Se apos instalar ou actualizar, a pagina **Diagnosticos** mostra
"Tabela nao existe" para `layer7_block`, `layer7_block_dst` ou
`layer7_bld_N`, execute os passos abaixo.

### Reparacao pela GUI (recomendado)

Na pagina **Services > Layer 7 > Diagnosticos**, clique no botao
**Reparar tabelas PF**. A partir da v1.4.2, esta accao:
1. Escreve o snippet `pf.conf` com as declaracoes de tabelas
2. Tenta criar as tabelas via `pfctl`
3. Chama `filter_configure()` para recarregar o filtro
4. Verifica se as tabelas foram criadas
5. Se necessario, forca `pfctl -f /tmp/rules.debug` como fallback sincrono

### Auto-recuperacao em runtime (v1.4.14+)

Quando o daemon detectar falha de `pfctl -T add` por ausencia de tabela,
ele tenta auto-recuperar automaticamente:
1. executa `layer7-pfctl ensure`
2. valida novamente as tabelas
3. aplica fallback com `pfctl -f /tmp/rules.debug` se necessario
4. repete o `add` uma unica vez

Esta rotina reduz o estado inconsistente de "daemon em execucao + tabelas
ausentes" apos reloads de filtro externos.

### Leitura correcta de tabelas no PF (v1.4.16+)

Em alguns ciclos do pfSense, a tabela pode estar referenciada no filtro ativo
(`pfctl -sr`, formato `<layer7_xxx:...>`) e ainda nao aparecer em
`pfctl -s Tables` no mesmo instante.

A partir da v1.4.16, o pacote considera estado valido quando a tabela:

1. existe em `pfctl -s Tables`; **ou**
2. esta referenciada no filtro ativo em `pfctl -sr`.

Na GUI de Diagnostics, esse caso aparece como:
**"Tabela referenciada no filtro activo (sem entradas no momento)."**

Isto evita falso erro operacional quando o enforcement esta funcional.

### Reparacao manual (SSH/Console)

Se o botao nao resolver, force manualmente:

```sh
/usr/local/libexec/layer7-pfctl ensure && pfctl -f /tmp/rules.debug
```

Verificar que as tabelas existem:

```sh
pfctl -s Tables | grep layer7
```

Resultado esperado:

```
layer7_block
layer7_block_dst
layer7_tagged
layer7_bld_0
```

Se as tabelas continuarem em falta, reinicie o servico:

```sh
service layer7d onerestart
```

O rc.d do layer7d chama `layer7-pfctl ensure` automaticamente no arranque.

### Causa raiz (v1.4.0 e anteriores)

Nas versoes anteriores a v1.4.2, o `ensure_table()` usava `pfctl -t TABLE -T add`
para criar tabelas ad-hoc, mas essa tecnica podia falhar silenciosamente se
o PF nao tivesse as tabelas declaradas no ruleset carregado. Alem disso,
`filter_configure()` no pfSense CE e assincrono, o que causava uma race
condition entre a criacao e a verificacao.

A v1.4.2 corrige isto com: escrita de `pf.conf` antes da criacao,
verificacao pos-tentativa, e fallback sincrono via `pfctl -f rules.debug`.

---

## 11. Caminhos importantes

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
| `/usr/local/etc/layer7/reports/`             | Directorio de relatorios   |
| `/usr/local/etc/layer7/reports/reports.db`   | Base SQLite dos relatorios |
| `/usr/local/etc/layer7/reports/ingest.cursor` | Cursor da ingestao incremental |
| `/usr/local/etc/layer7/layer7-reports-collect.php` | Colector incremental (PHP) |
| `/usr/local/etc/layer7/layer7-stats-collect.sh` | Colector de stats (cron) |
| `/usr/local/etc/layer7/layer7-stats-purge.sh` | Limpeza de historico (cron) |
| `/usr/local/etc/rc.d/layer7d`        | Script rc.d do servico           |
| `/var/run/layer7d.pid`               | PID do daemon                    |
| `/var/log/system.log`                | Logs do daemon                   |
| `/var/log/layer7-bl-update.log`      | Log de actualizacao de blacklists|

---

## 11b. Activar blacklists UT1 (apos instalar/actualizar para `1.8.11_13`)

> A snapshot oficial assinada com a chave actual (fingerprint
> `6190b8d26fb9cb951ccb2c1f4e921228e4edf388c23f51afd93f1fd3ca1ba4fc`) esta
> publicada em
> `https://github.com/pablomichelin/Layer7/releases/tag/blacklists-ut1-current`.
> Pacotes anteriores a `1.8.11_13` recusam este manifesto por fingerprint
> mismatch (fail-closed F1.4 — comportamento correcto). Logo, **so actualize
> as blacklists depois de instalar `pfSense-pkg-layer7-1.8.11_13`** (seccoes
> **1**/**4**/**5**).

**Comando unico (Command Prompt ou SSH como root):**

```sh
/usr/local/etc/layer7/update-blacklists.sh --download
```

Verificar (em SSH/Console):

```sh
tail -n 30 /var/log/layer7-bl-update.log
```

```sh
ls /usr/local/etc/layer7/blacklists | head -20
```

```sh
cat /usr/local/etc/layer7/blacklists/.state/active-snapshot.state 2>/dev/null
```

```sh
cat /usr/local/etc/layer7/blacklists/.state/fallback.state 2>/dev/null
```

A primeira corrida baixa **~31 MB** (snapshot UT1) e popula
`/usr/local/etc/layer7/blacklists/` com as 69 categorias. O `fallback.state`
deve transitar de **`fail-closed`** (estado actual) para **`healthy`** apos a
primeira sync com sucesso. A partir dai, o cron interno mantem a snapshot
actualizada automaticamente (configuravel na GUI **Services > Layer 7 >
Blacklists**); restauro explicito da last-known-good fica em:

```sh
/usr/local/etc/layer7/update-blacklists.sh --restore-lkg
```

### 11b.1 Convencao de releases no GitHub (botao "Verificar actualizacao" no GUI)

A pagina **Services > Layer 7 > Definicoes > Sistema > Actualizacao** chama
`https://api.github.com/repos/pablomichelin/Layer7/releases/latest` para
descobrir a versao mais recente do pacote. Esse endpoint **ignora**
releases marcadas como `prerelease`/`draft`.

Por convencao canonica deste projecto, releases que **nao sao** versoes do
pacote (ex.: `blacklists-ut1-current`, futuras `signatures-*`) sao **sempre
publicadas como `prerelease`** no GitHub para nao "roubar" o `latest` do
canal de versoes do pacote (`v<MAJOR>.<MINOR>.<PATCH>[_<REVISION>]`). Caso
contrario o GUI mostra erradamente *"Release encontrado mas sem artefacto
.pkg."*.

Comando operacional (caso a release rolling tenha ficado por engano sem o
flag):

```sh
gh release edit blacklists-ut1-current --repo pablomichelin/Layer7 --prerelease
```

Esta convencao tambem esta descrita em
`docs/changelog/CHANGELOG.md` (entrada **[Unreleased] - Operational**) e
acompanhada pelo backlog `BG-030` (hardening defensivo do updater PHP).

---

## 12. Rollback de emergencia

Se algo der errado apos instalar ou actualizar, fazer rollback **manual**
preservando a configuracao (o `uninstall.sh` automatico nao e publicado nesta
release — ver nota em **Links da versao actual** e **BG-028**):

```sh
service layer7d onestop && pkg delete -y pfSense-pkg-layer7
```

Para reinstalar uma versao anterior conhecida (ex.: `1.8.3`, ultima publicada
no canal antigo):

```sh
fetch -o /tmp/pfSense-pkg-layer7-1.8.3.pkg https://github.com/pablomichelin/Layer7/releases/download/v1.8.3/pfSense-pkg-layer7-1.8.3.pkg && IGNORE_OSVERSION=yes pkg add -f /tmp/pfSense-pkg-layer7-1.8.3.pkg && sysrc layer7d_enable=YES && service layer7d onestart && layer7d -V
```

A configuracao (`/usr/local/etc/layer7.json`, `/usr/local/etc/layer7.lic`) e
preservada por defeito durante `pkg delete`/`pkg add -f`.

Ou limpeza manual completa:

```sh
service layer7d onestop
```

```sh
pkg delete -y pfSense-pkg-layer7
```

```sh
pfctl -t layer7_block -T flush
pfctl -t layer7_block_dst -T flush
for t in $(pfctl -s Tables 2>/dev/null | awk '/^layer7_bld_[0-9]+$/{print $1}'); do pfctl -t "$t" -T flush; done
```

O pfSense volta ao funcionamento normal imediatamente.
A configuracao (`layer7.json`), a licenca (`layer7.lic`) e as blacklists
sao preservadas para uma reinstalacao futura.
