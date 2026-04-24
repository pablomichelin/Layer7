# Directrizes de Implementação: Blacklists UT1

> **LEIA TODO ESTE DOCUMENTO ANTES DE ESCREVER QUALQUER LINHA DE CÓDIGO.**
> Cada secção contém regras obrigatórias extraídas dos padrões reais do
> projecto Layer7. Violar qualquer regra resulta em bugs, falhas de build
> ou regressões.

> **ADDENDUM NORMATIVO F1.3 (2026-04-01)** — qualquer exemplo antigo neste
> documento que aponte para `blacklists.tar.gz` directo via HTTP deve ser lido
> como **historico**. A trilha actual de consumo confiavel usa manifesto
> assinado em HTTPS, public key dedicada, mirror controlado e
> last-known-good local.

> **ADDENDUM NORMATIVO F1.4 (2026-04-01)** — falha nova nao pode virar sucesso
> silencioso. O updater passa a deixar rasto explicito em
> `/usr/local/etc/layer7/blacklists/.state/fallback.state` com `status`,
> `mode`, `reason`, `safe_state` e `operator_action`.

> **ADDENDUM F4.3 (2026-04-24)** — mudanças em `layer7.inc` que afectem **DNS
> forçado** (`force_dns` / anchor `natrules/layer7_nat`) ou **anti-QUIC** por
> interface devem acompanhar o roteiro de evidência em
> [`docs/04-package/validacao-lab.md`](../04-package/validacao-lab.md) (secção **11**;
> anti-QUIC opcional no mesmo roteiro) e o teste **6.7** em
> [`docs/tests/test-matrix.md`](../tests/test-matrix.md), sem declarar trilha
> fechada só com build no builder.

---

## Índice

1. [Regras gerais do projecto](#1-regras-gerais-do-projecto)
2. [Convenções C do daemon](#2-convencoes-c-do-daemon)
3. [Convenções PHP da GUI](#3-convencoes-php-da-gui)
4. [Convenções shell script](#4-convencoes-shell-script)
5. [Convenções JSON config](#5-convencoes-json-config)
6. [Convenções do empacotamento](#6-convencoes-do-empacotamento)
7. [Padrões de erro e fallback](#7-padroes-de-erro-e-fallback)
8. [O que PODE ser feito](#8-o-que-pode-ser-feito)
9. [O que NÃO PODE ser feito](#9-o-que-nao-pode-ser-feito)
10. [Armadilhas conhecidas](#10-armadilhas-conhecidas)
11. [Regra de ouro: compilar, documentar e sincronizar](#11-regra-de-ouro)
12. [Checklist pré-commit](#12-checklist-pre-commit)
13. [Checklist pré-build](#13-checklist-pre-build)
14. [Checklist pré-release](#14-checklist-pre-release)

---

## 1. Regras gerais do projecto

### 1.1 Princípio fundamental

**Não quebrar o que já funciona.** O Layer7 V1 está em produção. Qualquer
alteração que cause regressão na V1 é inaceitável.

### 1.2 Regras de ouro

1. **Um bloco por vez** — nunca implementar dois blocos em paralelo
2. **Documentação no mesmo commit** — nunca commitar código sem actualizar docs
3. **Teste antes de marcar como feito** — nunca marcar bloco como concluído sem evidência
4. **Rollback sempre possível** — cada bloco deve poder ser revertido isoladamente
5. **Sem surpresas** — toda mudança precisa de objectivo, impacto, risco, teste e rollback declarados

### 1.3 Estrutura de directórios (respeitá-la rigidamente)

```
src/layer7d/           ← código C do daemon (blacklist.c/h vão aqui)
src/common/            ← tipos compartilhados (NÃO ALTERAR sem necessidade)
package/pfSense-pkg-layer7/
  files/usr/local/pkg/          ← layer7.xml, layer7.inc
  files/usr/local/www/packages/layer7/ ← páginas PHP da GUI
  files/usr/local/etc/layer7/   ← perfis, pf.conf, blacklists dir
  files/usr/local/libexec/      ← scripts executáveis do pacote
  Makefile                       ← port FreeBSD
  pkg-plist                      ← lista de ficheiros do pacote
docs/11-blacklists/             ← documentação desta feature
```

### 1.4 Versionamento

- **PORTVERSION** no `package/pfSense-pkg-layer7/Makefile` deve ser
  incrementado apenas quando a mudanca exigir nova versao base; para rebuilds
  do mesmo port, incrementar `PORTREVISION` (ex.: `1.8.11_11` -> `1.8.11_12`)
- O `version.str` é gerado automaticamente pelo build
- Nunca hardcodar versões em código C ou PHP

---

## 2. Convenções C do daemon

### 2.1 Estilo de código (obrigatório — copiar exactamente)

O projecto usa **estilo BSD/KNF** (Kernel Normal Form). Observar:

```c
/* Função com abertura na linha seguinte */
static int
nome_da_funcao(const char *arg1, int arg2)
{
	/* Indentação com TAB (não espaços) */
	int i;
	const char *p;

	if (!arg1 || !*arg1)
		return -1;

	for (i = 0; i < arg2; i++) {
		if (condicao) {
			/* bloco */
		} else {
			/* outro bloco */
		}
	}
	return 0;
}
```

**Regras extraídas do código existente:**

| Regra | Exemplo | Ficheiro de referência |
|-------|---------|----------------------|
| Indentação: TAB (não espaços) | `\t` | Todos os .c |
| Tipo de retorno na linha anterior | `static int\nnome(...)` | enforce.c, policy.c |
| `{` na mesma linha de `if/for/while` | `if (x) {` | capture.c |
| `{` na linha seguinte para funções | `nome()\n{` | enforce.c |
| Declarações de variáveis no topo | `int i;\nchar *p;` | policy.c |
| Guard `#ifndef` com nome completo | `#ifndef LAYER7_BLACKLIST_H` | capture.h |
| Comentário de topo com `/*` multilinha | Ver capture.h | capture.h |
| Prefixo `l7_` ou `layer7_` em nomes públicos | `l7_blacklist_load` | policy.h |
| Prefixo `s_` em variáveis estáticas globais | `s_blacklist` | main.c |
| Cast explícito `(unsigned char)` em `isalnum` | `isalnum((unsigned char)*p)` | enforce.c |
| `size_t` para tamanhos, `int` para contadores | Ver policy.h | policy.h |

### 2.2 Nomes

| Tipo | Convenção | Exemplo |
|------|-----------|---------|
| Funções públicas | `l7_blacklist_xxx` ou `layer7_blacklist_xxx` | `l7_blacklist_load` |
| Funções estáticas | nome descritivo minúsculo | `hash_domain`, `load_file` |
| Structs | `struct l7_bl_xxx` | `struct l7_bl_entry` |
| Defines/macros | `L7_BL_XXX` | `L7_BL_HASH_SIZE` |
| Variáveis estáticas globais | `s_xxx` | `s_blacklist` |
| Parâmetros | minúsculo com `_` | `const char *domain` |

### 2.3 Includes (ordem)

```c
/* 1. Header próprio do módulo */
#include "blacklist.h"

/* 2. Headers do projecto */
#include "policy.h"

/* 3. Headers do sistema (por ordem alfabética) */
#include <ctype.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
```

### 2.4 Gestão de memória — REGRAS CRÍTICAS

```
╔═══════════════════════════════════════════════════════════════════╗
║  O daemon é um processo de LONGA DURAÇÃO. Memory leaks são       ║
║  FATAIS — vão acumular até crashar o pfSense.                    ║
╚═══════════════════════════════════════════════════════════════════╝
```

1. **Toda alocação (`malloc`/`calloc`) DEVE ter um `free` correspondente**
2. **`l7_blacklist_free()` DEVE libertar TUDO** — buckets, entries, a struct
3. **Reload (SIGHUP) DEVE chamar `free` antes de `load`** — nunca acumular
4. **Verificar TODOS os retornos de `malloc`/`calloc`** — se NULL, log e continuar sem blacklist
5. **Nunca usar `realloc` em paths críticos** — alocar tamanho final de uma vez
6. **Testar com `valgrind` ou equivalente** se possível antes de deploy

**Padrão de free correcto:**

```c
void
l7_blacklist_free(struct l7_blacklist *bl)
{
	int i;
	struct l7_bl_entry *e, *next;

	if (!bl)
		return;
	if (bl->buckets) {
		for (i = 0; i < L7_BL_HASH_SIZE; i++) {
			e = bl->buckets[i];
			while (e) {
				next = e->next;
				free(e);
				e = next;
			}
		}
		free(bl->buckets);
	}
	free(bl);
}
```

### 2.5 Strings — REGRAS CRÍTICAS

1. **Sempre verificar ponteiros NULL antes de usar**: `if (!domain || !*domain)`
2. **Nunca usar `strcpy`** — usar `strncpy` ou `snprintf` com tamanho
3. **Sempre terminar com NUL** após `strncpy`: `buf[sizeof(buf)-1] = '\0'`
4. **Converter para lowercase** com `tolower((unsigned char)c)` — SEMPRE com cast
5. **Tamanho máximo de domínio**: 253 caracteres (RFC 1035)
6. **Validar entrada**: rejeitar linhas com caracteres inválidos

```c
/* Padrão correcto de cópia segura */
strncpy(entry->domain, domain, sizeof(entry->domain) - 1);
entry->domain[sizeof(entry->domain) - 1] = '\0';
```

### 2.6 Leitura de ficheiros — REGRAS CRÍTICAS

```
╔═══════════════════════════════════════════════════════════════════╗
║  Os ficheiros de blacklist podem ter MILHÕES de linhas.          ║
║  Nunca carregar o ficheiro inteiro em memória de uma vez.        ║
║  Ler linha a linha com fgets().                                   ║
╚═══════════════════════════════════════════════════════════════════╝
```

**Padrão correcto:**

```c
static int
load_domains_file(struct l7_blacklist *bl, const char *path,
    const char *category)
{
	FILE *f;
	char line[512];
	int count = 0;

	f = fopen(path, "r");
	if (!f)
		return -1;

	while (fgets(line, sizeof(line), f)) {
		char *nl = strchr(line, '\n');
		if (nl)
			*nl = '\0';
		/* Trim \r se houver */
		nl = strchr(line, '\r');
		if (nl)
			*nl = '\0';

		/* Ignorar linhas vazias e comentários */
		if (line[0] == '\0' || line[0] == '#')
			continue;

		/* Validar e inserir */
		if (insert_domain(bl, line, category) == 0)
			count++;
	}
	fclose(f);
	return count;
}
```

**Nunca fazer:**

```c
/* ERRADO — pode alocar GBs para um ficheiro grande */
char *buf = malloc(filesize);
fread(buf, 1, filesize, f);
```

### 2.7 Hash function — Implementação FNV-1a

```c
static uint32_t
fnv1a_hash(const char *str)
{
	uint32_t h = 2166136261U;

	while (*str) {
		h ^= (uint32_t)(unsigned char)tolower((unsigned char)*str);
		h *= 16777619U;
		str++;
	}
	return h;
}
```

**Por que FNV-1a:**
- Simples (poucas linhas, sem dependências)
- Rápida (operações simples)
- Boa distribuição para strings (domínios)
- Determinística (mesmo input → mesmo hash)
- Usada amplamente em tabelas de hash de propósito geral

### 2.8 Thread safety

O daemon NÃO é multi-threaded. Tudo corre no main loop. Portanto:
- Não usar `pthread_mutex_t` nem locks
- Não usar variáveis `_Thread_local`
- `s_blacklist` é um ponteiro global estático, modificado apenas no reload
- A rotina de reload é síncrona (chamada do main loop via SIGHUP handler)

### 2.9 Logging — usar macros existentes

O daemon tem macros de log com nível. **Usar APENAS estas:**

```c
L7_ERROR("bl_load: failed to open %s: %s", path, strerror(errno));
L7_WARN("bl_load: skipping invalid line in %s:%d", path, lineno);
L7_NOTE("bl_load: loaded %d domains from category %s", count, cat);
L7_INFO("bl_block: domain=%s cat=%s ip=%s ttl=%u", domain, cat, ip, ttl);
L7_DEBUG("bl_lookup: domain=%s hash=0x%08x bucket=%d", domain, h, bucket);
```

| Macro | Nível | Uso |
|-------|-------|-----|
| `L7_ERROR` | 0 | Falhas que impedem funcionamento |
| `L7_WARN` | 1 | Problemas recuperáveis |
| `L7_NOTE` | 2 (syslog NOTICE) | Eventos operacionais (load, reload) |
| `L7_INFO` | 2 | Eventos normais (bloqueios) |
| `L7_DEBUG` | 3 | Debug (nunca em produção) |

### 2.10 Integração no main.c — Pontos de alteração EXACTOS (ACTUALIZADO v2)

```
main.c tem ~1585 linhas. As alterações DEVEM ser cirúrgicas:

1. INCLUDE: adicionar #include "blacklist.h" e #include "bl_config.h"
   junto aos outros includes (~linha 13)

2. VARIÁVEIS GLOBAIS: adicionar junto às outras variáveis estáticas (~linha 60):
   static struct l7_blacklist *s_blacklist;
   static unsigned long long s_bl_hits;
   static unsigned long long s_bl_lookups;

3. DNS CALLBACK: modificar layer7_on_dns_resolved() (~linha 591):
   Após o if (layer7_domain_is_blocked(...)) existente, adicionar
   o bloco de consulta à blacklist.
   Nota: a whitelist é verificada DENTRO de l7_blacklist_lookup()

4. RELOAD (SIGHUP): na rotina de recarga de configuração, após
   o parse de políticas:
   a) Chamar l7_bl_config_load() para ler config.json SEPARADO
   b) Reconstruir hash table com l7_blacklist_load() (com whitelist)
   c) Popular tabela PF layer7_bl_except com except_ips[]

5. STATS JSON: na função write_stats_json(), adicionar campos
   de blacklist ao JSON (incluindo contadores por categoria)

6. CLEANUP: na rotina de cleanup/exit, chamar l7_blacklist_free()
```

**Nota v2**: o `config_parse.c` NÃO é alterado. O parse da config de
blacklists é feito num módulo separado `bl_config.c`.

**NUNCA alterar:**
- A ordem dos includes de sistema
- A assinatura de `layer7_on_dns_resolved`
- A lógica existente de `layer7_domain_is_blocked`
- O parser JSON existente `config_parse.c`
- O formato base do stats JSON (apenas adicionar campos)
- As macros de log

---

## 3. Convenções PHP da GUI

### 3.1 Estrutura de uma página PHP do Layer7

Todas as páginas seguem exactamente este template:

```php
<?php
##|+PRIV
##|*IDENT=page-services-layer7-blacklists
##|*NAME=Services: Layer 7 (blacklists)
##|*DESCR=Allow access to Layer 7 blacklists.
##|*MATCH=layer7_blacklists.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("/usr/local/pkg/layer7.inc");

/* ... lógica PHP (POST handling, load config) ... */

$pgtitle = array(gettext("Services"), gettext("Layer 7"), gettext("Blacklists"));
$pglinks = array("", "/packages/layer7/layer7_status.php", "@self");
include("head.inc");

/* ... tabs do Layer7 (copiar exactamente das outras páginas) ... */

/* ... HTML do form ... */

include("foot.inc");
?>
```

### 3.2 Regras PHP obrigatórias

| Regra | Razão |
|-------|-------|
| Usar `gettext()` em todos os textos da GUI | Internacionalização do pfSense |
| Validar TODOS os `$_POST` com `isset()` e `trim()` | Segurança |
| Usar `htmlspecialchars()` em output HTML | Prevenir XSS |
| Usar `$input_errors[]` para erros de validação | Padrão pfSense |
| Usar `display_input_errors($input_errors)` | Mostra erros no topo |
| Nunca usar `echo` directo para output HTML | Usar os helpers pfSense |
| Chamar `layer7_save_json()` para gravar config | Padrão Layer7 |
| Chamar `layer7_sighup_daemon()` após save | Daemon lê config nova |
| Chamar `filter_configure()` se mudar regras PF | Recarregar PF no pfSense |

### 3.3 Tabs do Layer7 — COPIAR EXACTAMENTE

```php
$tab_array = array();
$tab_array[] = array(gettext("Status"), false, "/packages/layer7/layer7_status.php");
$tab_array[] = array(gettext("Settings"), false, "/packages/layer7/layer7_settings.php");
$tab_array[] = array(gettext("Policies"), false, "/packages/layer7/layer7_policies.php");
$tab_array[] = array(gettext("Groups"), false, "/packages/layer7/layer7_groups.php");
$tab_array[] = array(gettext("Categories"), false, "/packages/layer7/layer7_categories.php");
$tab_array[] = array(gettext("Exceptions"), false, "/packages/layer7/layer7_exceptions.php");
$tab_array[] = array(gettext("Test"), false, "/packages/layer7/layer7_test.php");
$tab_array[] = array(gettext("Events"), false, "/packages/layer7/layer7_events.php");
$tab_array[] = array(gettext("Diagnostics"), false, "/packages/layer7/layer7_diagnostics.php");
$tab_array[] = array(gettext("Blacklists"), true, "/packages/layer7/layer7_blacklists.php");
display_top_tabs($tab_array);
```

**IMPORTANTE:** ao adicionar a tab "Blacklists", TODAS as outras páginas
PHP precisam de ter o mesmo array de tabs actualizado. Caso contrário,
a tab não aparece em navegação entre páginas.

**Lista de ficheiros que precisam de actualização de tabs:**
- `layer7_status.php`
- `layer7_settings.php`
- `layer7_policies.php`
- `layer7_groups.php`
- `layer7_categories.php`
- `layer7_exceptions.php`
- `layer7_test.php`
- `layer7_events.php`
- `layer7_diagnostics.php`
- `layer7_blacklists.php` (nova)

### 3.4 Helpers em `layer7.inc` (ACTUALIZADO v2)

As funções de blacklists lêem/gravam o ficheiro **separado**
`/usr/local/etc/layer7/blacklists/config.json` (NÃO o `layer7.json`).

```php
define('L7_BL_DIR', '/usr/local/etc/layer7/blacklists');
define('L7_BL_CONFIG', L7_BL_DIR . '/config.json');
define('L7_BL_DISCOVERED', L7_BL_DIR . '/discovered.json');

function layer7_bl_config_load()
{
	$path = L7_BL_CONFIG;
	if (!file_exists($path))
		return layer7_bl_config_defaults();
	$data = json_decode(file_get_contents($path), true);
	if (!is_array($data))
		return layer7_bl_config_defaults();
	return $data;
}

function layer7_bl_config_defaults()
{
	return array(
		"enabled" => false,
		"source_url" => "http://dsi.ut-capitole.fr/blacklists/download/blacklists.tar.gz",
		"auto_update" => false,
		"update_interval_hours" => 24,
		"categories" => array(),
		"whitelist" => array(),
		"except_ips" => array()
	);
}

function layer7_bl_config_save($config)
{
	@mkdir(L7_BL_DIR, 0755, true);
	file_put_contents(L7_BL_CONFIG,
		json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function layer7_bl_discovered_load()
{
	$path = L7_BL_DISCOVERED;
	if (!file_exists($path))
		return null;
	return json_decode(file_get_contents($path), true);
}

function layer7_bl_pf_sync_except($ips)
{
	mwexec("/sbin/pfctl -t layer7_bl_except -T flush");
	foreach ($ips as $ip) {
		$ip = trim($ip);
		if ($ip !== '')
			mwexec("/sbin/pfctl -t layer7_bl_except -T add " . escapeshellarg($ip));
	}
}
```

**Regras para helpers:**
- Prefixo `layer7_bl_` para todas as funções de blacklists
- Config separada em `config.json` (NÃO altera `layer7.json`)
- Sempre retornar valores padrão se a config não existir
- Nunca assumir que campos existem — usar `isset()`
- Usar `is_array()` antes de iterar arrays
- Usar `json_encode`/`json_decode` para o ficheiro separado
- `layer7_bl_pf_sync_except()` sincroniza IPs na tabela PF

### 3.5 Execução de comandos shell no PHP

```php
/* CORRECTO: usar escapeshellarg() sempre */
$cmd = "/usr/local/etc/layer7/update-blacklists.sh " . escapeshellarg($category);
if (function_exists("mwexec")) {
    mwexec($cmd);  /* pfSense helper (preferred) */
} else {
    @shell_exec($cmd);  /* fallback */
}

/* ERRADO: concatenação directa (injection vulnerability!) */
$cmd = "/usr/local/etc/layer7/update-blacklists.sh " . $category;
```

---

## 4. Convenções shell script

### 4.1 Cabeçalho obrigatório

```sh
#!/bin/sh
# update-blacklists.sh — Descarga e extracção de blacklists UT1
# Layer7 para pfSense CE — Systemup (www.systemup.inf.br)
#
# Uso: update-blacklists.sh [--force]
# Chamado via cron ou manualmente pela GUI.

set -eu
```

### 4.2 Regras para shell scripts no pfSense/FreeBSD

| Regra | Razão |
|-------|-------|
| Usar `#!/bin/sh` (NÃO `#!/bin/bash`) | pfSense não tem bash |
| Usar `fetch -o` para downloads (NÃO `wget`/`curl`) | `fetch` é base FreeBSD |
| Usar `tar xzf` com paths explícitos | Evitar extracção acidental |
| Usar caminhos absolutos para binários | Ex: `/bin/sh`, `/usr/bin/tar` |
| Usar `set -eu` (exit on error + undefined vars) | Segurança |
| Testar existência de ficheiros com `[ -f ]` | Evitar erros |
| Usar `lockf` ou ficheiro de lock | Evitar execuções paralelas |
| Log para ficheiro com timestamp | Diagnóstico |
| Não usar bashisms (`[[ ]]`, arrays, `$()` nested) | `sh` puro |
| Exit code 0 = sucesso, não-zero = falha | Padrão Unix |

### 4.3 Comandos disponíveis no pfSense CE

| Disponível | Não disponível |
|------------|----------------|
| `fetch` | `wget`, `curl` |
| `tar` | `gtar` |
| `sh` | `bash`, `zsh` |
| `kill`, `pgrep` | `killall` (pode estar) |
| `wc`, `sort`, `uniq` | `rsync` (não garantido) |
| `crontab` | `systemctl` |
| `pfctl` | `iptables` |
| `sysctl` | |
| `sha256`, `md5` | `sha256sum` |
| `date` | |

### 4.4 Template do script de download (ACTUALIZADO v2)

```sh
#!/bin/sh
set -eu

BL_DIR="/usr/local/etc/layer7/blacklists"
CONFIG="$BL_DIR/config.json"
DISCOVERED="$BL_DIR/discovered.json"
PROGRESS="/tmp/layer7-bl-progress.txt"
TMP="/tmp/layer7-bl-update.$$"
LOCK="/tmp/layer7-bl-update.lock"
LOG="/var/log/layer7-bl-update.log"
PID_FILE="/var/run/layer7d.pid"
DEFAULT_URL="http://dsi.ut-capitole.fr/blacklists/download/blacklists.tar.gz"

log() {
    msg="$(date '+%Y-%m-%d %H:%M:%S') $*"
    echo "$msg" >> "$LOG"
    echo "$msg" >> "$PROGRESS"
    echo "$*"
}

cleanup() {
    rm -rf "$TMP"
    rm -f "$LOCK"
}
trap cleanup EXIT

# Lock
if [ -f "$LOCK" ]; then
    log "ERROR: update already running (lock exists)"
    exit 1
fi
echo $$ > "$LOCK"

# Ler URL do config.json (ou usar default)
if [ -f "$CONFIG" ]; then
    # Parse simples do source_url (sh puro, sem jq)
    BL_URL=$(sed -n 's/.*"source_url"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' "$CONFIG")
fi
BL_URL="${BL_URL:-$DEFAULT_URL}"

# Limpar progresso anterior
: > "$PROGRESS"
log "INFO: starting blacklist update from $BL_URL"

# Download
mkdir -p "$TMP"
log "INFO: downloading blacklists.tar.gz..."
if ! fetch -o "$TMP/blacklists.tar.gz" "$BL_URL" 2>>"$LOG"; then
    log "ERROR: download failed"
    exit 1
fi

# Verificar tamanho mínimo (>1MB = provavelmente válido)
SIZE=$(stat -f%z "$TMP/blacklists.tar.gz" 2>/dev/null || echo 0)
if [ "$SIZE" -lt 1000000 ]; then
    log "ERROR: downloaded file too small ($SIZE bytes)"
    exit 1
fi
log "INFO: download complete (${SIZE} bytes)"

# Extrair TUDO para auto-descoberta
log "INFO: extracting archive..."
tar xzf "$TMP/blacklists.tar.gz" -C "$TMP" 2>>"$LOG"
log "INFO: extraction complete"

# Auto-descoberta: gerar discovered.json
log "INFO: discovering categories..."
mkdir -p "$BL_DIR"
printf '{"source":"%s","discovered_at":"%s","categories":[' \
    "$BL_URL" "$(date -u '+%Y-%m-%dT%H:%M:%SZ')" > "$DISCOVERED.tmp"
first=1
for catdir in "$TMP"/blacklists/*/; do
    cat=$(basename "$catdir")
    domfile="$catdir/domains"
    if [ -f "$domfile" ]; then
        count=$(wc -l < "$domfile" | tr -d ' ')
        if [ $first -eq 0 ]; then
            printf ',' >> "$DISCOVERED.tmp"
        fi
        printf '{"id":"%s","domains_count":%d}' "$cat" "$count" >> "$DISCOVERED.tmp"
        first=0
    fi
done
echo ']}' >> "$DISCOVERED.tmp"
mv "$DISCOVERED.tmp" "$DISCOVERED"
log "INFO: discovered categories written to discovered.json"

# Copiar TODAS as pastas de categorias para $BL_DIR
for catdir in "$TMP"/blacklists/*/; do
    cat=$(basename "$catdir")
    if [ -f "$catdir/domains" ]; then
        mkdir -p "$BL_DIR/$cat"
        cp "$catdir/domains" "$BL_DIR/$cat/domains"
    fi
done
log "INFO: all category files copied to $BL_DIR"

# Gravar timestamp
date -u '+%Y-%m-%dT%H:%M:%SZ' > "$BL_DIR/last-update.txt"

# SIGHUP ao daemon
if [ -f "$PID_FILE" ]; then
    kill -HUP "$(cat "$PID_FILE")" 2>/dev/null || true
    log "INFO: sent SIGHUP to daemon"
fi

log "INFO: update complete"
```

**Nota v2**: o script extrai TODAS as categorias (para auto-descoberta),
gera `discovered.json` com contagens, e escreve progresso em
`/tmp/layer7-bl-progress.txt` para a GUI ler via AJAX.

---

## 5. Convenções JSON config (ACTUALIZADO v2)

### 5.1 Ficheiros de configuração — SEPARADOS do layer7.json

**Decisão v2**: a configuração de blacklists usa ficheiros **separados**
em `/usr/local/etc/layer7/blacklists/`. O `layer7.json` **NÃO é alterado**.

Isto preserva o parser existente `config_parse.c` intacto e elimina
o risco de regressão.

**Ficheiro 1: `config.json`** (gerido pela GUI, lido pelo daemon):

```json
{
  "enabled": true,
  "source_url": "https://downloads.systemup.inf.br/layer7/blacklists/ut1/current/layer7-blacklists-manifest.v1.txt",
  "mirror_urls": [
    "https://github.com/pablomichelin/Layer7/releases/download/blacklists-ut1-current/layer7-blacklists-manifest.v1.txt"
  ],
  "auto_update": true,
  "update_interval_hours": 24,
  "categories": ["adult", "gambling", "malware", "phishing"],
  "whitelist": ["google.com", "microsoft.com"],
  "except_ips": ["192.168.10.50", "192.168.10.51"]
}
```

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `enabled` | boolean | Activar/desactivar blacklists |
| `source_url` | string | URL do manifesto oficial assinado |
| `mirror_urls` | string[] | Mirrors oficiais apenas para disponibilidade |
| `auto_update` | boolean | Actualização automática via cron |
| `update_interval_hours` | int | Intervalo em horas (mínimo 1) |
| `categories` | string[] | IDs das categorias com acção `deny` |
| `whitelist` | string[] | Domínios NUNCA bloqueados pela blacklist |
| `except_ips` | string[] | IPs/CIDRs que acedem a destinos bloqueados |

**Ficheiro 2: `discovered.json`** (gerado pelo script de download):

```json
{
  "source": "https://downloads.systemup.inf.br/layer7/blacklists/ut1/current/layer7-blacklists-manifest.v1.txt",
  "discovered_at": "2026-03-24T03:00:00Z",
  "categories": [
    {"id": "adult", "domains_count": 4623451},
    {"id": "agressif", "domains_count": 396},
    {"id": "gambling", "domains_count": 8234}
  ]
}
```

**Persistencia minima F1.3 obrigatoria:**

- `/usr/local/share/pfSense-pkg-layer7/blacklists-signing-public-key.pem`
- `/usr/local/etc/layer7/blacklists/.cache/<snapshot_id>/`
- `/usr/local/etc/layer7/blacklists/.state/active-snapshot.state`
- `/usr/local/etc/layer7/blacklists/.last-known-good/`
- `/usr/local/etc/layer7/update-blacklists.sh --restore-lkg`

A GUI lê `discovered.json` para listar categorias disponíveis com
contagem de domínios. Se não existir, mostra "Faça o download primeiro".

### 5.2 Regras para os ficheiros JSON

1. **Retrocompatibilidade**: se `config.json` não existir, o daemon
   DEVE funcionar normalmente (como se não houvesse blacklists)
2. **Defaults**: o PHP gera `config.json` com defaults no primeiro acesso
3. **Tipo de campos**: respeitar estritamente (boolean, string, int, array)
4. **O `layer7.json` NÃO é modificado** — nenhuma secção `blacklists` adicionada
5. **O `config_parse.c` NÃO é alterado** — parser independente em `bl_config.c`

### 5.3 Parse no daemon C

Novo ficheiro `bl_config.c` com parser dedicado para `config.json`:

```c
struct l7_bl_config {
    int enabled;
    char categories[64][L7_BL_CAT_MAX];
    int n_categories;
    char whitelist[256][L7_BL_DOMAIN_MAX];
    int n_whitelist;
    char except_ips[64][48];
    int n_except_ips;
};

int l7_bl_config_load(const char *path, struct l7_bl_config *cfg);
```

O parser lê o ficheiro e preenche a struct. Usa o mesmo padrão de
parse manual (sem biblioteca JSON). Se o ficheiro não existir,
`l7_bl_config_load` retorna -1 e o daemon opera sem blacklists.

### 5.4 Regra PF para excepções de IP

Os IPs em `except_ips[]` do `config.json` são inseridos na tabela PF
`layer7_bl_except`. A regra PF correspondente é gerada em
`layer7_pf_default_rules_text()` no `layer7.inc`:

```
pass quick inet from <layer7_bl_except> to <layer7_block_dst> label "layer7:bl:except"
block drop quick inet to <layer7_block_dst> label "layer7:block:dst"
```

A regra `pass` DEVE vir **antes** da regra `block`. A ordem é crítica.

---

## 6. Convenções do empacotamento

### 6.1 Ficheiros no `pkg-plist`

Cada ficheiro novo DEVE ser adicionado ao `pkg-plist`:

```
/usr/local/www/packages/layer7/layer7_blacklists.php
/usr/local/etc/layer7/update-blacklists.sh
@dir /usr/local/etc/layer7/blacklists
```

**Nota v2**: os ficheiros `config.json` e `discovered.json` são gerados
em runtime (pela GUI e pelo script). NÃO devem estar no `pkg-plist`.

### 6.2 Makefile do port — alterações necessárias

```makefile
# 1. Verificar existência de blacklist.c
.if !exists(${LAYER7D_DIR}/blacklist.c)
.error "layer7d: falta blacklist.c"
.endif

# 2. Adicionar ao build
${CC} ... ${LAYER7D_DIR}/blacklist.c ...

# 3. Instalar ficheiros novos
${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/packages/layer7/layer7_blacklists.php \
    ${STAGEDIR}${PREFIX}/www/packages/layer7
${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/etc/layer7/update-blacklists.sh \
    ${STAGEDIR}${PREFIX}/etc/layer7
${MKDIR} ${STAGEDIR}${PREFIX}/etc/layer7/blacklists
```

### 6.3 layer7.xml — adicionar tab

```xml
<tab>
    <text>Blacklists</text>
    <url>/packages/layer7/layer7_blacklists.php</url>
</tab>
```

Posicionar **após** Diagnostics (última tab existente).

### 6.4 Privileges (`layer7.priv.inc`)

Adicionar permissão para a nova página:

```php
$priv_list['page-services-layer7-blacklists'] = array();
$priv_list['page-services-layer7-blacklists']['name'] = 'WebCfg - Services: Layer 7 (Blacklists)';
$priv_list['page-services-layer7-blacklists']['descr'] = 'Allow access to Layer 7 blacklists page.';
$priv_list['page-services-layer7-blacklists']['match'] = array();
$priv_list['page-services-layer7-blacklists']['match'][] = '/packages/layer7/layer7_blacklists.php*';
```

### 6.5 Fluxo de build (builder 192.168.100.12)

```sh
# 1. Push código para GitHub
# 2. SSH no builder
sshpass -p 'pablo' ssh root@192.168.100.12

# 3. Pull + preservar ficheiros de produção
cd /root/pfsense-layer7
git stash
git pull origin main
git checkout "stash@{0}" -- src/layer7d/license.c src/layer7d/Makefile
git stash drop

# 4. Build
cd package/pfSense-pkg-layer7
make clean
DISABLE_LICENSES=yes make package DISABLE_VULNERABILITIES=yes

# 5. Verificar
ls -la work/pkg/pfSense-pkg-layer7-*.pkg
ldd work/stage/usr/local/sbin/layer7d  # não deve mostrar libndpi.so

# 6. Copiar para local
# (do macOS:)
sshpass -p 'pablo' scp root@192.168.100.12:/root/pfsense-layer7/package/pfSense-pkg-layer7/work/pkg/PACOTE.pkg .
```

---

## 7. Padrões de erro e fallback

### 7.1 Princípio: falhar graciosamente

```
╔═══════════════════════════════════════════════════════════════════╗
║  Se as blacklists falharem, o daemon DEVE continuar a funcionar  ║
║  normalmente com as políticas manuais da V1.                     ║
║  A blacklist é um COMPLEMENTO, nunca uma DEPENDÊNCIA.            ║
╚═══════════════════════════════════════════════════════════════════╝
```

### 7.2 Cenários de falha e comportamento esperado

| Cenário | Comportamento correcto |
|---------|----------------------|
| Directório de blacklists não existe | `s_blacklist = NULL`, daemon opera normal |
| Ficheiro `domains` não existe para uma categoria | Log WARN, pular categoria, continuar |
| Ficheiro `domains` vazio | Log INFO, 0 domínios carregados, continuar |
| Ficheiro `domains` corrompido | Ignorar linhas inválidas, carregar as válidas |
| `malloc` falha durante load | Log ERROR, `s_blacklist = NULL`, opera sem blacklist |
| Download falha (script shell) | Log ERROR, manter última versão, exit 1 |
| Secção `blacklists` não existe no JSON | Tratar como `enabled: false` |
| Nenhuma categoria activada | `s_blacklist = NULL`, opera normal |
| Categoria desconhecida na config | Log WARN, ignorar, continuar |

### 7.3 Padrão de código defensivo

```c
/* SEMPRE verificar ponteiros antes de usar */
if (!bl || !bl->buckets || !domain || !*domain)
    return NULL;

/* SEMPRE verificar retorno de malloc */
entry = calloc(1, sizeof(*entry));
if (!entry) {
    L7_ERROR("bl_insert: out of memory");
    return -1;
}

/* SEMPRE verificar retorno de fopen */
f = fopen(path, "r");
if (!f) {
    L7_WARN("bl_load: cannot open %s: %s", path, strerror(errno));
    return -1;
}

/* SEMPRE fechar ficheiros em TODOS os paths de saída */
if (erro) {
    fclose(f);
    return -1;
}
/* ... código ... */
fclose(f);
return 0;
```

---

## 8. O que PODE ser feito

| Acção | Condição |
|-------|----------|
| Adicionar novo módulo C (`blacklist.c/h`) | Não alterar módulos existentes desnecessariamente |
| Adicionar nova página PHP | Seguir template exacto |
| Adicionar nova tab ao menu | Actualizar TODAS as páginas |
| Adicionar campos ao stats JSON | Apenas adicionar, nunca remover/renomear |
| Criar `config.json` separado para blacklists | Evita alterar `layer7.json` e `config_parse.c` |
| Gerar `discovered.json` no script de download | Auto-descoberta de categorias |
| Adicionar tabela PF `layer7_bl_except` | Excepções por IP de origem |
| Adicionar regra `pass quick` em `layer7.inc` | Antes da regra `block` existente |
| Adicionar script shell ao pacote | Usar `#!/bin/sh`, sem bashisms |
| Adicionar directório ao pacote | Via `@dir` no pkg-plist |
| Adicionar cron job via pfSense API | `install_cron_job()` |
| Ler ficheiros de blacklist no daemon | Via `fopen`/`fgets`/`fclose` |
| Adicionar helper functions ao `layer7.inc` | Prefixo `layer7_blacklists_` |
| Usar `fetch` para download | Base system FreeBSD |
| Usar `tar` para extracção | Base system FreeBSD |
| Enviar SIGHUP ao daemon | Via PID file |
| Adicionar campos à GUI | Seguir padrões pfSense |
| Incrementar PORTVERSION | Obrigatório para nova feature |

---

## 9. O que NÃO PODE ser feito

```
╔═══════════════════════════════════════════════════════════════════╗
║                    LISTA DE PROIBIÇÕES                            ║
╚═══════════════════════════════════════════════════════════════════╝
```

| Proibição | Razão |
|-----------|-------|
| Alterar a assinatura de funções públicas existentes | Quebra backward compat |
| Alterar `layer7_types.h` | Tipo compartilhado, impacta tudo |
| Alterar o parser JSON existente (`config_parse.c`) | Config de blacklists é ficheiro separado |
| Alterar a lógica de `layer7_domain_is_blocked()` | Motor V1 estável |
| Alterar a lógica de `layer7_flow_decide()` | Motor V1 estável |
| Alterar o formato do `layer7.json` existente | Config de blacklists é ficheiro separado |
| Adicionar secção `blacklists` ao `layer7.json` | Usar `config.json` separado (decisão v2) |
| Colocar regra `block` antes da regra `pass` para `layer7_bl_except` | Excepções não funcionariam |
| Remover campos do stats JSON | GUI depende deles |
| Usar `bash` em scripts | pfSense não tem bash |
| Usar `wget` ou `curl` em scripts | Não garantidos no pfSense |
| Usar `rsync` no download | Não garantido no pfSense |
| Usar bibliotecas C externas (cJSON, jansson) | Sem dependências adicionais |
| Usar `pthread` ou threads | Daemon é single-threaded |
| Alocar memória sem libertar | Memory leak = crash eventual |
| Ler ficheiro inteiro em memória | Ficheiros podem ter milhões de linhas |
| Hardcodar versão no código | Gerada pelo build |
| Fazer `echo` ou `printf` para o utilizador no daemon | Usar macros de log |
| Mudar o `PORTNAME` | Quebra upgrade do pacote |
| Mexer em ficheiros fora de `src/layer7d/` e `package/` | Escopo do bloco |
| Ignorar erros de `malloc`/`fopen` | Crash em produção |
| Usar `system()` no daemon C | Inseguro; usar `fork`+`exec` |
| Incluir a chave privada Ed25519 em qualquer ficheiro | Segurança |
| Commitar ficheiros binários (`.pkg`, `.o`) | Repositório |
| Alterar `LICENSE` ou `EULA` | Jurídico |

---

## 10. Armadilhas conhecidas

### 10.1 Builder FreeBSD — ficheiros locais

```
╔═══════════════════════════════════════════════════════════════════╗
║  O builder (192.168.100.12) tem MUDANÇAS LOCAIS que NÃO devem    ║
║  ser commitadas. Após git pull, SEMPRE restaurar:                ║
║    src/layer7d/license.c   (chave pública de produção)           ║
║    src/layer7d/Makefile     (license.c e -lcrypto adicionados)   ║
╚═══════════════════════════════════════════════════════════════════╝
```

O Makefile no builder difere do repositório porque inclui `license.c`
no build. O `license.c` no builder tem a chave pública real (não o
placeholder all-zeros do repositório).

### 10.2 Makefile BSD vs GNU Make

O Makefile do port usa **BSD Make** (não GNU Make). Diferenças críticas:

```makefile
# BSD Make (correcto para o port)
.if $(NDPI) == 1
.endif

# GNU Make (NÃO funciona no builder)
ifeq ($(NDPI),1)
endif
```

O Makefile standalone de `src/layer7d/` também usa BSD Make.

### 10.3 Domínios com encoding estranho

Os ficheiros da UT1 podem conter:
- Domínios com caracteres Unicode (IDN): tratar como ASCII (punycode)
- Linhas com espaços no final: fazer `trim`
- Linhas com `\r\n` (Windows): remover `\r`
- Domínios com `/` (URLs parciais): rejeitar
- Domínios com `:` (portas): rejeitar
- Domínios iniciando com `.`: remover o ponto

### 10.4 Tamanho da tabela PF

A tabela PF `layer7_block_dst` acumula IPs. Com blacklists grandes,
muitos domínios vão gerar muitas entradas. Considerar:
- O TTL da cache de destino existente já lida com expiração
- Em cenário extremo, a tabela PF pode ter milhares de IPs
- pfSense CE lida bem com tabelas PF de até ~200K entradas
- Se necessário, o sweep periódico já existente no daemon limpa entradas expiradas

### 10.5 Espaço em disco

| Ficheiro | Tamanho estimado |
|----------|-----------------|
| `blacklists.tar.gz` (download) | ~20 MB |
| Todas as categorias extraídas | ~150-200 MB |
| 5-10 categorias activas | ~30-50 MB |
| Script + config | < 1 MB |

pfSense CE mínimo recomenda 8GB de disco. Verificar espaço antes do
download no script.

### 10.6 Ordem de avaliação no DNS callback (ACTUALIZADO v2)

```
layer7_on_dns_resolved(domain, ip, ttl)
│
├─ 1. Verificar enforce_mode activo
│     (se monitor-only, não bloquear nada)
│
├─ 2. Verificar políticas manuais (V1)
│     layer7_domain_is_blocked()
│     → se match: bloquear e RETURN (prioridade)
│
└─ 3. Verificar blacklist UT1 (NOVO)
      l7_blacklist_lookup()
      → whitelist verificada INTERNAMENTE no lookup
      → se match e não na whitelist: bloquear
      → IP adicionado a layer7_block_dst

NOTA: A excepção por IP opera a NÍVEL PF (não no daemon):
  pass quick from <layer7_bl_except> to <layer7_block_dst>
  block drop quick to <layer7_block_dst>

Isto significa que IPs em layer7_bl_except acedem a destinos
bloqueados INDEPENDENTEMENTE de quem inseriu o IP na tabela
(política manual ou blacklist).
```

A ordem no daemon é **crítica**: políticas manuais TÊM prioridade sobre
blacklists. Se o operador criou uma política `allow` para `example.com`,
a blacklist NÃO deve sobrepor.

A excepção por IP é mais eficiente a nível PF (avaliada antes do tráfego
chegar ao daemon) e funciona tanto para políticas manuais como para
blacklists.

### 10.7 Whitelist — cuidados

A whitelist é uma lista de domínios que NUNCA devem ser bloqueados pela
blacklist, mesmo que estejam numa categoria activa. Exemplos comuns:
- `google.com` (pode aparecer em categorias amplas)
- `microsoft.com`
- `apple.com`

A whitelist NÃO afecta políticas manuais — apenas a blacklist.

---

## 11. Regra de ouro: SEMPRE compilar, documentar e sincronizar

```
╔═══════════════════════════════════════════════════════════════════╗
║  Após QUALQUER modificação no sistema, SEMPRE:                    ║
║                                                                   ║
║  1. COMPILAR — build no FreeBSD builder se houve código alterado  ║
║  2. DOCUMENTAR — actualizar todos os docs afectados               ║
║  3. SINCRONIZAR — commit + push para GitHub                       ║
║                                                                   ║
║  Estas 3 acções são INSEPARÁVEIS de qualquer modificação.         ║
║  Nunca terminar uma sessão sem verificar git status e push.       ║
╚═══════════════════════════════════════════════════════════════════╝
```

---

## 12. Checklist pré-commit

Antes de cada commit, verificar TODOS os itens:

```
□ Código C compila sem warnings com -Wall -Wextra
□ Nenhum malloc sem free correspondente
□ Nenhum fopen sem fclose
□ Nenhum ponteiro usado sem verificação NULL
□ Strings terminadas com NUL após strncpy
□ Cast (unsigned char) em isalnum/isalpha/tolower
□ Indentação com TABs (não espaços) em código C
□ PHP: todos os textos com gettext()
□ PHP: todos os $_POST com isset() e trim()
□ PHP: output HTML com htmlspecialchars()
□ Shell: #!/bin/sh (não bash)
□ Shell: set -eu
□ Shell: sem bashisms ([[ ]], arrays associativos)
□ Shell: caminhos absolutos para binários
□ JSON: retrocompatível (daemon funciona sem novos campos)
□ pkg-plist actualizado com novos ficheiros
□ Makefile do port actualizado
□ layer7.xml actualizado (se nova tab)
□ Tabs actualizadas em TODAS as páginas PHP
□ Documentação actualizada (TODOS os docs afectados)
□ CORTEX.md actualizado
□ Nenhum ficheiro binário no commit
□ Nenhuma chave/segredo no commit
□ Push para GitHub após commit
```

---

## 13. Checklist pré-build

Antes de cada build no FreeBSD builder:

```
□ Código commitado e pushado no GitHub
□ PORTVERSION incrementado no Makefile do port
□ ssh root@192.168.100.12 funciona
□ git stash && git pull no builder
□ license.c e Makefile de produção restaurados (git checkout stash)
□ make clean executado
□ Build sem erros
□ ldd layer7d NÃO mostra libndpi.so
□ Pacote .pkg gerado em work/pkg/
□ Tamanho do .pkg razoável (~800KB+)
```

---

## 14. Checklist pré-release

Antes de publicar uma release:

```
□ Todos os blocos da feature concluídos
□ Todos os testes do plano passaram
□ PORTVERSION correcto
□ CORTEX.md actualizado com nova versão
□ CHANGELOG actualizado
□ README actualizado (se necessário)
□ Build final no builder
□ .pkg copiado para local
□ Push para GitHub
□ GitHub Release criada com .pkg como artefacto
□ install.sh actualizado com nova versão default
□ Teste de instalação limpa (pkg add)
□ Teste de upgrade (pkg delete + pkg add)
□ Daemon inicia sem erros
□ GUI acessível sem erros
□ Funcionalidade testada end-to-end
□ Rollback testado (desactivar blacklists)
```

---

*Documento criado em 2026-03-23. Actualizado em 2026-03-24 (v2: config.json separado, discovered.json, tabela PF except, GUI SquidGuard). Projecto Layer7 — Systemup Solução em Tecnologia.*
