# Release Notes — `pfSense-pkg-layer7` `1.8.11_14`

- **Data:** 2026-04-24
- **Tag GitHub:** `v1.8.11_14`
- **URL:** https://github.com/pablomichelin/Layer7/releases/tag/v1.8.11_14
- **Tipo:** hotfix do **GUI updater** sobre `1.8.11_13`
- **Trust chain F1.2 do pacote:** **nao activado** nesta release
  (`BG-028`); publicacao mantem o padrao `.pkg` + `.pkg.sha256`
- **Trust chain F1.3 das blacklists:** **activado e em uso desde
  `1.8.11_13`** (chave embutida nao foi rodada nesta release; a
  fingerprint continua
  `6190b8d26fb9cb951ccb2c1f4e921228e4edf388c23f51afd93f1fd3ca1ba4fc`
  e a snapshot publica `pablomichelin/Layer7 / blacklists-ut1-current`
  continua valida)

---

## 1. Sintoma corrigido

Em `1.8.11_13`, a pagina **Services > Layer 7 > Definicoes > Sistema >
Actualizacao** apresentava um loop perceptivel:

1. utilizador clicava **Verificar actualizacao**;
2. GUI mostrava `Versao instalada: 1.8.11` e `Mais recente: 1.8.11_13`;
3. utilizador clicava **Actualizar para 1.8.11_13**;
4. `pkg add -f` reinstalava o mesmo `pfSense-pkg-layer7-1.8.11_13.pkg`
   (com sucesso — daemon reiniciava, mensagem verde aparecia);
5. `Versao instalada` continuava a aparecer como `1.8.11`;
6. um novo clique em **Verificar actualizacao** voltava ao passo 2.

A logica de bloqueio do produto **estava sempre correcta**: o pacote
real instalado era de facto `1.8.11_13` (`pkg query %v
pfSense-pkg-layer7` confirmava). O problema era apenas no **GUI
updater**.

## 2. Causa raiz

O ficheiro `version.str` (incluido por `src/layer7d/main.c` em
compilacao via `#include <version.str>`) era gerado pelo Makefile do
port assim:

```make
do-build:
	@${ECHO_CMD} '"${PORTVERSION}"' > ${WRKSRC}/version.str
```

Como so usa `${PORTVERSION}` (= `1.8.11`) e ignora `${PORTREVISION}`,
o banner `layer7d -V` ficou eternamente preso em `1.8.11` mesmo apos
varios bumps de revisao. O `layer7_settings.php` usava esse banner
para popular `current` antes de comparar com a tag do GitHub
(`v1.8.11_13` -> `latest=1.8.11_13`); `version_compare("1.8.11_13",
"1.8.11", ">")` devolve `true`, dai o loop.

## 3. Correcoes neste bloco

### 3.1 `package/pfSense-pkg-layer7/Makefile`

- `PORTREVISION` `13` -> `14`.
- `do-build`: `'"${PKGVERSION}"' > version.str`. `PKGVERSION` e a
  variavel canonica do `bsd.port.mk` (formato
  `PORTVERSION[_PORTREVISION]`) e ja era usada na linha 137 para
  carimbar `info.xml` e `layer7.xml`. Agora o banner do daemon fica
  coerente com a versao real do pacote pfSense.

### 3.2 `files/usr/local/pkg/layer7.inc`

- nova `layer7_pkg_version()` — devolve `pkg query %v
  pfSense-pkg-layer7` (string vazia se nao instalado). E a fonte
  canonica de "versao instalada" para qualquer codigo do GUI que
  precise de comparar contra releases.

### 3.3 `files/usr/local/www/packages/layer7/layer7_settings.php`

- `check_update`: `current` passa a vir de `layer7_pkg_version()`,
  com fallback para `layer7_daemon_version()`.
- `do_update`: mensagem de sucesso passa a usar `layer7_pkg_version()`.
- display da seccao Sistema mostra a versao do pkg como principal e
  exibe o banner do daemon entre parenteses **so se divergir** da
  versao do pkg (debug visivel sem ruido em estado normal).
- **Defesa em profundidade (`BG-030`):** o updater **ignora**
  releases cujo `tag_name` nao case com `/^v?\d+\.\d+/` (ex.:
  `blacklists-ut1-current`), mostrando o erro
  *"Release mais recente nao e uma versao do pacote (tag ignorada): ..."*.
  Reforca a convencao operacional registada em `1.8.11_13` (releases
  nao-pacote sao publicadas como `prerelease` no GitHub).

### 3.4 `files/usr/local/etc/layer7/lang/en.php`

- novas keys `daemon` e
  `Release mais recente nao e uma versao do pacote (tag ignorada): `.
  `pt` continua como lingua base (devolve a key directamente).

## 4. O que **nao** muda

- Logica de enforcement (PF, nDPI, force_dns, anti-QUIC, blacklists,
  excepcoes, politicas, relatorios): **inalterada**.
- Pipeline de licenciamento e activacao: **inalterado**.
- Trust chain F1.3 das blacklists: chave **nao** rotacionada;
  fingerprint embutida continua
  `6190b8d26fb9cb951ccb2c1f4e921228e4edf388c23f51afd93f1fd3ca1ba4fc`.
- Snapshot UT1 publica em `blacklists-ut1-current`: **nao
  republicada** (continua `snapshot_id=ut1-2026-04-25`,
  `SHA256=4191e2ebdc13e3c87d777103528bab4fda6b273bc40c62a2c39cb820ad493d36`).

## 5. Instalar / actualizar

Comando unico no Command Prompt do pfSense (opcao `8`) ou via SSH como
root:

```sh
fetch -o /tmp/pfSense-pkg-layer7-1.8.11_14.pkg https://github.com/pablomichelin/Layer7/releases/download/v1.8.11_14/pfSense-pkg-layer7-1.8.11_14.pkg && IGNORE_OSVERSION=yes pkg add -f /tmp/pfSense-pkg-layer7-1.8.11_14.pkg && sysrc layer7d_enable=YES && service layer7d onestart && layer7d -V
```

Ultima linha esperada: `1.8.11_14`. Confirma tambem com:

```sh
pkg query %v pfSense-pkg-layer7
```

Resultado esperado: `1.8.11_14`.

A configuracao (`/usr/local/etc/layer7.json`), licenca
(`/usr/local/etc/layer7.lic`) e blacklists locais sao preservadas.

## 6. Validar a correccao do GUI

1. Abrir **Services > Layer 7 > Definicoes**, separador **Sistema**.
2. A linha "Versao instalada" deve mostrar **`1.8.11_14`** (sem
   anotacao de banner divergente entre parenteses).
3. Clicar **Verificar actualizacao** -> deve devolver
   `Mais recente: 1.8.11_14` e exibir
   *"Ja esta na versao mais recente."* (texto verde com check).
4. Se algum dia o GitHub vier a publicar uma release nao-pacote sem o
   flag `prerelease` (cenario que a convencao actual evita), o GUI
   passa a mostrar erro
   *"Release mais recente nao e uma versao do pacote (tag ignorada):
   ..."* em vez de oferecer um update inutil.

## 7. Rollback

Para `1.8.11_13`:

```sh
fetch -o /tmp/pfSense-pkg-layer7-1.8.11_13.pkg https://github.com/pablomichelin/Layer7/releases/download/v1.8.11_13/pfSense-pkg-layer7-1.8.11_13.pkg && IGNORE_OSVERSION=yes pkg add -f /tmp/pfSense-pkg-layer7-1.8.11_13.pkg && service layer7d onerestart
```

A configuracao e blacklists sao preservadas.

## 8. Hashes

| Artefacto | SHA256 |
|-----------|--------|
| `pfSense-pkg-layer7-1.8.11_14.pkg` | `f9fb1217780bfb90e83821c2652d7177d92eaf5b83f3dfa1fe29d85eaf284705` |

## 9. Risco e mitigacao

- **Risco:** P (so PHP + uma macro de Makefile + um simbolo `static
  const char` no daemon). Comportamento de enforcement nao foi tocado.
- **Mitigacao:** rollback documentado para `1.8.11_13` em meio comando.

## 10. Referencias cruzadas

- `docs/changelog/CHANGELOG.md` — entrada `[1.8.11_14] - 2026-04-24`.
- `docs/02-roadmap/backlog.md` — `BG-030` marcado **Concluido**.
- `CORTEX.md` — checkpoint canonico actualizado.
- `docs/10-license-server/MANUAL-INSTALL.md` — links e comandos
  actualizados.
