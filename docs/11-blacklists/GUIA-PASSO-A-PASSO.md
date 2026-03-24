# Guia Passo-a-Passo: Implementação das Blacklists UT1

> Este documento descreve a ordem exacta de operações para cada bloco.
> Cada passo indica: o que fazer, em que ficheiro, e como validar.
> Siga na ordem. Não saltar passos.

---

## Pré-requisitos (antes de começar qualquer bloco)

```
□ Ler PLANO-BLACKLISTS-UT1.md por inteiro (incluindo actualizações v2)
□ Ler DIRETRIZES-IMPLEMENTACAO.md por inteiro (incluindo actualizações v2)
□ Ler REGRAS-QUALIDADE.md por inteiro
□ Confirmar acesso SSH ao builder (192.168.100.12)
□ Confirmar acesso HTTP ao servidor UT1 a partir do pfSense lab
□ Confirmar espaço em disco no pfSense lab (> 500MB livres)
□ Versão confirmada: 1.1.0
□ Interfaces: global (confirmado)
□ Config: ficheiro separado config.json (NÃO alterar layer7.json)
□ Criar branch git: git checkout -b feature/blacklists-ut1
```

---

## Bloco 1: Script de download e extracção (ACTUALIZADO v2)

### Objectivo
Criar o script shell que descarrega a blacklist UT1, extrai TODAS
as categorias para auto-descoberta, gera metadados, e prepara os
ficheiros para o daemon.

### Passo 1.1 — Criar ficheiro no pacote

```
Ficheiro: package/pfSense-pkg-layer7/files/usr/local/etc/layer7/update-blacklists.sh
```

Criar o ficheiro com as seguintes responsabilidades:
- Suportar modos: `--download` (descarregar + auto-descoberta),
  `--apply` (SIGHUP), sem argumentos (ambos — usado pelo cron)
- Ler URL do `config.json` (ou usar default UT1)
- Verificar lock file (evitar execuções paralelas)
- Descarregar `blacklists.tar.gz` com `fetch`
- Verificar integridade (tamanho mínimo > 1MB)
- Extrair TODAS as categorias (não só as activas)
- Auto-descoberta: listar categorias + contar domínios
- Gerar `discovered.json` com metadados
- Copiar TODAS as pastas de categorias para `$BL_DIR/`
- Gravar timestamp em `last-update.txt`
- Enviar SIGHUP ao daemon
- Limpar temporários em trap EXIT
- Escrever progresso em `/tmp/layer7-bl-progress.txt` (para GUI)
- Log para `/var/log/layer7-bl-update.log`

### Passo 1.2 — Extracção completa para auto-descoberta

```sh
# Extrair TUDO para auto-descoberta (v2)
tar xzf "$TARBALL" -C "$TMP" 2>>"$LOG"

# Copiar TODAS as pastas de categorias para $BL_DIR
for catdir in "$TMP"/blacklists/*/; do
    cat=$(basename "$catdir")
    domfile="$catdir/domains"
    if [ -f "$domfile" ]; then
        mkdir -p "$BL_DIR/$cat"
        cp "$domfile" "$BL_DIR/$cat/domains"
        COUNT=$(wc -l < "$domfile" | tr -d ' ')
        log "INFO: extracted $cat ($COUNT domains)"
    fi
done
```

**Nota v2**: extraímos TUDO (não só categorias activas) para que a
auto-descoberta funcione. O daemon carrega apenas as categorias
presentes na lista `categories[]` do `config.json`.

### Passo 1.3 — Gerar `discovered.json` (auto-descoberta)

Após a extracção, gerar `/usr/local/etc/layer7/blacklists/discovered.json`:

```sh
DISCOVERED="$BL_DIR/discovered.json"
printf '{"source":"%s","discovered_at":"%s","categories":[' \
    "$URL" "$(date -u '+%Y-%m-%dT%H:%M:%SZ')" > "$DISCOVERED.tmp"
first=1
for catdir in "$TMP"/blacklists/*/; do
    cat=$(basename "$catdir")
    domfile="$catdir/domains"
    if [ -f "$domfile" ]; then
        count=$(wc -l < "$domfile" | tr -d ' ')
        [ $first -eq 0 ] && printf ',' >> "$DISCOVERED.tmp"
        printf '{"id":"%s","domains_count":%d}' "$cat" "$count" >> "$DISCOVERED.tmp"
        first=0
    fi
done
echo ']}' >> "$DISCOVERED.tmp"
mv "$DISCOVERED.tmp" "$DISCOVERED"
```

Resultado:

```json
{
  "source": "http://dsi.ut-capitole.fr/blacklists/download/blacklists.tar.gz",
  "discovered_at": "2026-03-24T03:00:00Z",
  "categories": [
    {"id": "adult", "domains_count": 4623451},
    {"id": "agressif", "domains_count": 396},
    {"id": "gambling", "domains_count": 8234}
  ]
}
```

A GUI lê este ficheiro para listar categorias disponíveis.

### Passo 1.4 — Progresso para a GUI

O script escreve progresso num ficheiro temporário que a GUI lê
via AJAX:

```sh
PROGRESS="/tmp/layer7-bl-progress.txt"

log() {
    msg="$(date '+%Y-%m-%d %H:%M:%S') $*"
    echo "$msg" >> "$LOG"
    echo "$msg" >> "$PROGRESS"
    echo "$*"
}

# Limpar progresso anterior
: > "$PROGRESS"
```

### Passo 1.5 — Tornar executável e testar

```sh
chmod +x update-blacklists.sh
```

### Validação do Bloco 1

```
□ Script executa sem erros com sh -n (syntax check)
□ fetch descarrega o tar.gz (testar no pfSense lab)
□ tar extrai TODAS as categorias
□ Ficheiros domains aparecem em /usr/local/etc/layer7/blacklists/*/
□ discovered.json gerado com categorias e contagens correctas
□ last-update.txt contém timestamp
□ Lock file impede execução paralela
□ Progresso escrito em /tmp/layer7-bl-progress.txt
□ Temporários são limpos mesmo em caso de erro (trap)
□ Log é legível e informativo
□ URL lida do config.json (ou default se não existir)
□ Modo --download funciona isoladamente
□ Sem argumentos: download + apply
```

---

## Bloco 2: Módulo C de blacklists (hash table)

### Objectivo
Criar `blacklist.h` e `blacklist.c` com hash table eficiente para
milhões de domínios.

### Passo 2.1 — Criar `src/layer7d/blacklist.h`

```c
/*
 * blacklist.h — Blacklists externas (UT1 Université Toulouse).
 *
 * Hash table de domínios organizada por categoria, com suffix matching.
 * Subsistema paralelo ao policy engine V1 — não altera nenhuma
 * estrutura existente.
 */
#ifndef LAYER7_BLACKLIST_H
#define LAYER7_BLACKLIST_H

#define L7_BL_DIR_DEFAULT  "/usr/local/etc/layer7/blacklists"
#define L7_BL_HASH_BITS    20
#define L7_BL_HASH_SIZE    (1 << L7_BL_HASH_BITS)
#define L7_BL_MAX_CATS     64
#define L7_BL_CAT_LEN      48
#define L7_BL_DOMAIN_MAX   256
#define L7_BL_WL_MAX       128

struct l7_blacklist;

struct l7_blacklist *l7_blacklist_load(const char *dir,
    const char **cats, int n_cats);
const char *l7_blacklist_lookup(const struct l7_blacklist *bl,
    const char *domain);
int l7_blacklist_is_whitelisted(const struct l7_blacklist *bl,
    const char *domain);
void l7_blacklist_set_whitelist(struct l7_blacklist *bl,
    const char **domains, int n_domains);
void l7_blacklist_free(struct l7_blacklist *bl);
int l7_blacklist_count(const struct l7_blacklist *bl);
int l7_blacklist_cat_count(const struct l7_blacklist *bl);

#endif /* LAYER7_BLACKLIST_H */
```

### Passo 2.2 — Criar `src/layer7d/blacklist.c`

Implementar na seguinte ordem (cada função testável isoladamente):

1. **`fnv1a_hash()`** — função de hash
2. **`domain_valid()`** — validação de domínio (sem /, :, espaços)
3. **`domain_normalize()`** — lowercase + trim
4. **Struct interna** — `struct l7_bl_entry` com chaining
5. **`insert_domain()`** — inserir na hash table
6. **`load_domains_file()`** — ler ficheiro linha a linha
7. **`l7_blacklist_load()`** — carregar todas as categorias
8. **`l7_blacklist_lookup()`** — lookup com suffix matching
9. **`l7_blacklist_is_whitelisted()`** — verificar whitelist
10. **`l7_blacklist_set_whitelist()`** — configurar whitelist
11. **`l7_blacklist_free()`** — libertar TUDO
12. **`l7_blacklist_count()`** — contar domínios
13. **`l7_blacklist_cat_count()`** — contar categorias

### Passo 2.3 — Estrutura interna detalhada

```c
struct l7_bl_entry {
    char *domain;              /* alocação dinâmica (tamanho exacto) */
    uint8_t cat_idx;           /* índice na tabela de categorias */
    struct l7_bl_entry *next;  /* chaining */
};

struct l7_blacklist {
    struct l7_bl_entry **buckets;
    int n_entries;
    int n_cats;
    char cats[L7_BL_MAX_CATS][L7_BL_CAT_LEN];
    /* Whitelist: array simples (máx ~100 entradas, busca linear OK) */
    int n_whitelist;
    char whitelist[L7_BL_WL_MAX][L7_BL_DOMAIN_MAX];
};
```

**Nota sobre `domain` como ponteiro**: usar `strdup()` ou `malloc(len+1)`
em vez de array fixo. Isto reduz o uso de memória drasticamente:
- Array fixo `char domain[256]`: 256 bytes por entrada
- Ponteiro `char *domain` com domínio médio de 25 chars: ~33 bytes por
  entrada (25 + ponteiro + malloc overhead)
- Para 200K domínios: ~48MB vs ~6.6MB

**IMPORTANTE**: se usar `strdup`/`malloc` para domínio, o `free` em
`l7_blacklist_free` deve libertar CADA domínio individualmente.

### Passo 2.4 — Suffix matching (detalhe)

```c
/*
 * Suffix matching: "video.adult-site.com" casa com "adult-site.com".
 * Tentativas progressivas removendo labels da esquerda:
 *   1. "video.adult-site.com" → lookup directo
 *   2. "adult-site.com"       → lookup (match se existir)
 *   3. "com"                  → lookup (normalmente não existe)
 *
 * NUNCA fazer match só no TLD (.com, .org, .net) — seria over-blocking.
 * Parar quando restarem menos de 2 labels (domínio.tld mínimo).
 */
const char *
l7_blacklist_lookup(const struct l7_blacklist *bl, const char *domain)
{
    const char *p;
    int labels;

    if (!bl || !bl->buckets || !domain || !*domain)
        return NULL;

    p = domain;
    while (p && *p) {
        /* Contar labels restantes */
        labels = count_labels(p);
        if (labels < 2)
            break;

        /* Lookup directo */
        struct l7_bl_entry *e = find_in_bucket(bl, p);
        if (e)
            return bl->cats[e->cat_idx];

        /* Avançar para o próximo label */
        p = strchr(p, '.');
        if (p)
            p++;
    }
    return NULL;
}
```

### Passo 2.5 — Actualizar Makefile standalone

Em `src/layer7d/Makefile`, adicionar `blacklist.c` a `SRCS_BASE`:

```makefile
SRCS_BASE = main.c config_parse.c policy.c enforce.c license.c blacklist.c
```

### Validação do Bloco 2

```
□ Compila sem warnings com -Wall -Wextra
□ Carregar ficheiro com 100 domínios: count() retorna 100
□ Carregar ficheiro com 100K domínios: sem crash, count() correcto
□ Lookup de domínio existente: retorna categoria
□ Lookup de domínio inexistente: retorna NULL
□ Suffix matching: "sub.listed.com" casa com "listed.com"
□ Suffix matching: "com" NÃO casa sozinho
□ Whitelist: domínio na whitelist retorna NULL mesmo se na blacklist
□ Free: sem memory leaks (testar com instrumentação ou contadores)
□ Linhas vazias e comentários ignorados
□ Domínios com / ou : rejeitados
□ Domínios convertidos para lowercase
```

---

## Bloco 3: Integração no daemon (ACTUALIZADO v2)

### Objectivo
Ligar o módulo de blacklists ao daemon, adicionando consulta no
DNS callback e suporte a reload via SIGHUP. Usar `config.json`
separado (NÃO alterar `config_parse.c`).

### Passo 3.1 — Criar `src/layer7d/bl_config.c` e `bl_config.h`

Novo módulo dedicado ao parse do `config.json` das blacklists.
**NÃO altera o parser existente `config_parse.c`.**

```c
/* bl_config.h */
#ifndef LAYER7_BL_CONFIG_H
#define LAYER7_BL_CONFIG_H

#include "blacklist.h"

struct l7_bl_config {
    int enabled;
    char categories[64][L7_BL_CAT_LEN];
    int n_categories;
    char whitelist[256][L7_BL_DOMAIN_MAX];
    int n_whitelist;
    char except_ips[64][48];
    int n_except_ips;
};

/*
 * Lê /usr/local/etc/layer7/blacklists/config.json.
 * Retorna 0 se OK, -1 se ficheiro não existe ou erro.
 * Se o ficheiro não existir, cfg->enabled = 0.
 */
int l7_bl_config_load(const char *path, struct l7_bl_config *cfg);

#endif
```

Implementação em `bl_config.c`:
- Abrir ficheiro com `fopen`
- Ler para buffer (ficheiro é pequeno, < 4KB)
- Parse manual de `enabled`, `categories[]`, `whitelist[]`, `except_ips[]`
- Seguir o mesmo padrão do parser existente (sem biblioteca JSON)

### Passo 3.2 — Adicionar includes e variáveis em `main.c`

No topo do ficheiro, junto aos outros includes:

```c
#include "blacklist.h"
#include "bl_config.h"
```

Junto às variáveis estáticas globais (~linha 60):

```c
static struct l7_blacklist *s_blacklist;
static unsigned long long s_bl_hits;
static unsigned long long s_bl_lookups;
```

### Passo 3.3 — Modificar `layer7_on_dns_resolved()`

**Alteração cirúrgica** — adicionar APÓS o bloco existente:

```c
static void
layer7_on_dns_resolved(const char *domain, const char *resolved_ip,
    uint32_t ttl)
{
    int r;
    const char *bl_cat;

    if (!s_have_parse || !s_ge)
        return;

    /* V1: verificação de políticas manuais (INALTERADO) */
    if (layer7_domain_is_blocked(s_rules, s_np, domain)) {
        r = layer7_pf_exec_table_add(L7_PF_TABLE_BLOCK_DST, resolved_ip);
        if (r == 0) {
            s_pf_dst_add_ok++;
            dst_cache_add(resolved_ip, ttl);
            L7_INFO("dns_block: domain=%s ip=%s ttl=%u table=%s",
                domain, resolved_ip, ttl, L7_PF_TABLE_BLOCK_DST);
        } else {
            s_pf_dst_add_fail++;
        }
        return;
    }

    /* NOVO: verificação na blacklist UT1 */
    /* Whitelist verificada DENTRO de l7_blacklist_lookup() */
    if (s_blacklist) {
        s_bl_lookups++;
        bl_cat = l7_blacklist_lookup(s_blacklist, domain);
        if (bl_cat) {
            s_bl_hits++;
            r = layer7_pf_exec_table_add(L7_PF_TABLE_BLOCK_DST,
                resolved_ip);
            if (r == 0) {
                s_pf_dst_add_ok++;
                dst_cache_add(resolved_ip, ttl);
                L7_INFO("bl_block: domain=%s cat=%s ip=%s ttl=%u",
                    domain, bl_cat, resolved_ip, ttl);
            } else {
                s_pf_dst_add_fail++;
            }
        }
    }
}
```

**Nota v2**: a whitelist é verificada DENTRO de `l7_blacklist_lookup()`.
Não é necessário chamar `l7_blacklist_is_whitelisted()` separadamente.

### Passo 3.4 — Carga na inicialização e reload SIGHUP

Na rotina de reload (procurar onde `s_rules` é recarregado), adicionar:

```c
/* Recarregar blacklists do config.json separado (v2) */
{
    struct l7_bl_config bl_cfg;

    if (s_blacklist) {
        l7_blacklist_free(s_blacklist);
        s_blacklist = NULL;
    }

    if (l7_bl_config_load(L7_BL_DIR_DEFAULT "/config.json", &bl_cfg) == 0
        && bl_cfg.enabled && bl_cfg.n_categories > 0) {

        const char *cats[64], *wl[256];
        int i;

        for (i = 0; i < bl_cfg.n_categories; i++)
            cats[i] = bl_cfg.categories[i];
        for (i = 0; i < bl_cfg.n_whitelist; i++)
            wl[i] = bl_cfg.whitelist[i];

        s_blacklist = l7_blacklist_load(L7_BL_DIR_DEFAULT,
            cats, bl_cfg.n_categories, wl, bl_cfg.n_whitelist);

        if (s_blacklist)
            L7_NOTE("blacklists: loaded %d domains in %d categories",
                l7_blacklist_count(s_blacklist),
                l7_blacklist_cat_count(s_blacklist));
        else
            L7_WARN("blacklists: failed to load");

        /* Popular tabela PF de excepções (v2) */
        for (i = 0; i < bl_cfg.n_except_ips; i++)
            layer7_pf_exec_table_add("layer7_bl_except",
                bl_cfg.except_ips[i]);
    }
}
```

### Passo 3.5 — Cleanup no exit

Na rotina de saída do daemon:

```c
if (s_blacklist) {
    l7_blacklist_free(s_blacklist);
    s_blacklist = NULL;
}
```

### Passo 3.6 — Estatísticas no stats JSON

Na função `write_stats_json()`, adicionar antes do `}` final:

```c
fprintf(f, "  \"bl_enabled\": %s,\n",
    s_blacklist ? "true" : "false");
fprintf(f, "  \"bl_domains_loaded\": %d,\n",
    s_blacklist ? l7_blacklist_count(s_blacklist) : 0);
fprintf(f, "  \"bl_categories_active\": %d,\n",
    s_blacklist ? l7_blacklist_cat_count(s_blacklist) : 0);
fprintf(f, "  \"bl_lookups\": %llu,\n",
    (unsigned long long)s_bl_lookups);
fprintf(f, "  \"bl_hits\": %llu,\n",
    (unsigned long long)s_bl_hits);
```

**Nota v2**: adicionar também contadores por categoria para o top hits.

### Passo 3.7 — Actualizar Makefile standalone

Em `src/layer7d/Makefile`, adicionar `bl_config.c` a `SRCS_BASE`:

```makefile
SRCS_BASE = main.c config_parse.c policy.c enforce.c license.c blacklist.c bl_config.c
```

### Validação do Bloco 3

```
□ Daemon compila sem warnings
□ Daemon inicia sem blacklists configuradas (retrocompatível)
□ Daemon inicia sem config.json (retrocompatível)
□ Daemon carrega blacklists ao startup (log bl_load)
□ SIGHUP recarrega blacklists (log blacklists: loaded)
□ config.json separado é lido correctamente
□ config_parse.c NÃO foi alterado
□ DNS lookup de domínio na blacklist → bl_block no log
□ DNS lookup de domínio NÃO na blacklist → sem bloqueio
□ Domínio na whitelist → sem bloqueio (whitelist interna)
□ Política manual tem prioridade sobre blacklist
□ Tabela PF layer7_bl_except populada com except_ips[]
□ Stats JSON contém campos bl_*
□ Daemon sem blacklist continua funcional (V1 inalterada)
□ Free no exit: sem leaks
```

---

## Bloco 4: GUI — Página de Blacklists (REESCRITO v2)

### Objectivo
Criar a página PHP de gestão de blacklists com layout inspirado no
SquidGuard: download com progresso → categorias auto-descobertas
→ dropdown `---`/`deny` → excepções (whitelist + IPs).

### Passo 4.1 — Adicionar helpers em `layer7.inc`

No final do ficheiro, adicionar:

```php
/* --- Blacklists helpers (v2) --- */

define('L7_BL_DIR', '/usr/local/etc/layer7/blacklists');
define('L7_BL_CONFIG', L7_BL_DIR . '/config.json');
define('L7_BL_DISCOVERED', L7_BL_DIR . '/discovered.json');
define('L7_BL_PROGRESS', '/tmp/layer7-bl-progress.txt');
define('L7_BL_SCRIPT', '/usr/local/etc/layer7/update-blacklists.sh');

function layer7_bl_config_load() { /* ... ler config.json ... */ }
function layer7_bl_config_defaults() { /* ... defaults ... */ }
function layer7_bl_config_save($config) { /* ... gravar config.json ... */ }
function layer7_bl_discovered_load() { /* ... ler discovered.json ... */ }
function layer7_bl_get_stats() { /* ... ler stats do daemon ... */ }
function layer7_bl_last_update() { /* ... ler last-update.txt ... */ }
function layer7_bl_download_start() {
    $cmd = L7_BL_SCRIPT . " --download";
    if (function_exists("mwexec_bg")) {
        mwexec_bg($cmd);
    } else {
        @shell_exec($cmd . " > /dev/null 2>&1 &");
    }
}
function layer7_bl_download_status() {
    if (file_exists(L7_BL_PROGRESS))
        return file_get_contents(L7_BL_PROGRESS);
    return "";
}
function layer7_bl_apply() {
    layer7_sighup_daemon();
}
function layer7_bl_pf_sync_except($ips) {
    mwexec("/sbin/pfctl -t layer7_bl_except -T flush");
    foreach ($ips as $ip) {
        $ip = trim($ip);
        if ($ip !== '')
            mwexec("/sbin/pfctl -t layer7_bl_except -T add " . escapeshellarg($ip));
    }
}
```

### Passo 4.2 — Criar `layer7_blacklists.php` com 4 secções

Seguir exactamente o template da secção 3.1 das directrizes.
A página tem **4 secções** no estilo SquidGuard:

**Secção 1 — URL e Download:**
- Campo editável para URL da blacklist (default: UT1 Capitole)
- Botão "Download" que executa `layer7_bl_download_start()`
- Textarea readonly para log de download (AJAX polling de `L7_BL_PROGRESS`)
- Script JavaScript para polling do progresso:

```php
<script>
function pollDownloadLog() {
    var xhr = new XMLHttpRequest();
    xhr.open('GET', '/packages/layer7/layer7_bl_ajax.php?action=progress', true);
    xhr.onreadystatechange = function() {
        if (xhr.readyState == 4 && xhr.status == 200) {
            document.getElementById('download_log').value = xhr.responseText;
        }
    };
    xhr.send();
}
var pollTimer = null;
</script>
```

**Secção 2 — Categorias (auto-descobertas):**
- Ler `discovered.json` via `layer7_bl_discovered_load()`
- Se não existir: mostrar mensagem "Faça o download da lista primeiro"
- Se existir: tabela com colunas Categoria | Domínios | Acção
- Cada categoria tem dropdown `<select>`: `---` (ignorar) / `deny` (bloquear)
- Aviso ⚠ para categorias com > 1M domínios (impacto de RAM)
- Campo de pesquisa JavaScript para filtrar categorias
- Botão "Guardar categorias"

```php
$discovered = layer7_bl_discovered_load();
if ($discovered === null) {
    print_info_box(gettext("Faça o download da lista primeiro."), 'warning');
} else {
    /* Tabela de categorias com dropdown */
    foreach ($discovered['categories'] as $cat) {
        $cat_id = htmlspecialchars($cat['id']);
        $count = number_format($cat['domains_count'], 0, ',', '.');
        $selected = in_array($cat['id'], $bl_config['categories']) ? 'deny' : '---';
        $warning = ($cat['domains_count'] > 1000000)
            ? ' ⚠ ' . gettext('RAM elevada')
            : '';
        /* Render row com <select> */
    }
}
```

**Secção 3 — Excepções:**
- Textarea: domínios na whitelist (nunca bloqueados pela blacklist,
  um domínio por linha)
- Textarea: IPs excepcionados (acedem a destinos bloqueados,
  um IP/CIDR por linha, populam tabela PF `layer7_bl_except`)
- Botão "Guardar excepções"

```php
/* Ao guardar excepções: */
$whitelist = array_filter(array_map('trim', explode("\n", $_POST['whitelist'])));
$except_ips = array_filter(array_map('trim', explode("\n", $_POST['except_ips'])));
$bl_config['whitelist'] = array_values($whitelist);
$bl_config['except_ips'] = array_values($except_ips);
layer7_bl_config_save($bl_config);
layer7_bl_pf_sync_except($except_ips);
layer7_bl_apply();
```

**Secção 4 — Definições e Estado:**
- Toggle: actualização automática
- Campo: intervalo em horas
- Informação: última actualização, categorias activas / total,
  domínios carregados, hits de blacklist
- Atribuição CC-BY-SA 4.0 da Université Toulouse Capitole
- Botão "Guardar definições"

### Passo 4.3 — Criar `layer7_bl_ajax.php` (AJAX endpoint)

Ficheiro auxiliar para polling do log de download:

```php
<?php
require_once("/usr/local/pkg/layer7.inc");
$action = $_GET['action'] ?? '';
if ($action === 'progress') {
    header('Content-Type: text/plain');
    echo layer7_bl_download_status();
    exit;
}
?>
```

### Passo 4.4 — Adicionar tab em TODAS as páginas

Actualizar o array `$tab_array` em TODOS os 9 ficheiros PHP existentes
E no novo `layer7_blacklists.php`:

```php
$tab_array[] = array(gettext("Blacklists"), false, "/packages/layer7/layer7_blacklists.php");
```

Na página `layer7_blacklists.php`, o segundo argumento é `true`.

### Passo 4.5 — Adicionar tab no layer7.xml

Adicionar antes de `</tabs>`:

```xml
<tab>
    <text>Blacklists</text>
    <url>/packages/layer7/layer7_blacklists.php</url>
</tab>
```

### Passo 4.6 — Adicionar privilege

Em `layer7.priv.inc`, adicionar o bloco de permissão para a nova
página e para o endpoint AJAX.

### Validação do Bloco 4

```
□ Página carrega sem erros PHP
□ Tab "Blacklists" aparece em TODAS as páginas
□ Secção 1: URL editável, botão Download funciona
□ Secção 1: Log de download visível via AJAX polling
□ Secção 2 sem discovered.json: mensagem "Faça o download primeiro"
□ Secção 2 com discovered.json: tabela de categorias auto-descobertas
□ Secção 2: Dropdown ---/deny por categoria
□ Secção 2: Aviso ⚠ para categorias > 1M domínios
□ Secção 2: Campo de pesquisa filtra categorias
□ Secção 3: Whitelist editável (textarea)
□ Secção 3: IPs excepcionados editáveis (textarea)
□ Secção 3: Guardar excepções sincroniza tabela PF layer7_bl_except
□ Secção 4: Toggle auto-update e intervalo funcionam
□ Secção 4: Estatísticas exibidas correctamente
□ Guardar grava config.json separado (NÃO layer7.json)
□ SIGHUP enviado após guardar
□ Nenhum erro de PHP no log do pfSense
```

---

## Bloco 5: Cron job

### Passo 5.1 — Registar cron via pfSense API

Em `layer7.inc`, adicionar lógica no save:

```php
function layer7_blacklists_setup_cron($enabled, $hour)
{
    $cmd = "/usr/local/etc/layer7/update-blacklists.sh";
    if (function_exists("install_cron_job")) {
        install_cron_job($cmd, $enabled, "0", $hour, "*", "*", "*");
    }
}
```

### Passo 5.2 — Chamar no save da GUI

Quando o operador activa/desactiva auto-update:

```php
layer7_blacklists_setup_cron($auto_update_enabled, "3");
```

### Validação do Bloco 5

```
□ Activar auto-update → cron job visível em crontab -l
□ Desactivar auto-update → cron job removido
□ Cron executa no horário configurado
□ Script actualiza listas e envia SIGHUP
□ Log em /var/log/layer7-bl-update.log
```

---

## Bloco 6: Excepções PF e precedência (ACTUALIZADO v2)

### Objectivo
Implementar excepções por IP via tabela PF dedicada `layer7_bl_except`
e estabelecer a precedência final de bloqueio.

### Passo 6.1 — Whitelist no módulo C

Já incluída no `blacklist.c` — passada no `l7_blacklist_load()` e
verificada INTERNAMENTE no `l7_blacklist_lookup()`. Array simples
com busca linear (< 256 entradas).

### Passo 6.2 — Adicionar regra PF em `layer7.inc`

Na função `layer7_pf_default_rules_text()`, adicionar a regra `pass`
**ANTES** da regra `block` existente:

```php
/* Em layer7_pf_default_rules_text() — ADICIONAR */
$rules .= "table <layer7_bl_except> persist\n";
$rules .= "pass quick inet from <layer7_bl_except> to <layer7_block_dst> label \"layer7:bl:except\"\n";
/* A regra block existente vem DEPOIS */
$rules .= "block drop quick inet to <layer7_block_dst> label \"layer7:block:dst\"\n";
```

**A ordem é CRÍTICA**: `pass` antes de `block`.

### Passo 6.3 — Secção de excepções na GUI

Na página `layer7_blacklists.php` (Secção 3 — Excepções):

```php
/* Whitelist de domínios */
$whitelist_text = implode("\n", $bl_config['whitelist'] ?? array());
/* Textarea: um domínio por linha */

/* IPs excepcionados */
$except_text = implode("\n", $bl_config['except_ips'] ?? array());
/* Textarea: um IP/CIDR por linha */
```

### Passo 6.4 — Sincronizar tabela PF ao guardar

Quando o operador guarda excepções:

```php
$except_ips = array_filter(array_map('trim', explode("\n", $_POST['except_ips'])));
$bl_config['except_ips'] = array_values($except_ips);
layer7_bl_config_save($bl_config);

/* Sincronizar tabela PF */
layer7_bl_pf_sync_except($except_ips);

/* SIGHUP para daemon recarregar whitelist */
layer7_bl_apply();
```

A função `layer7_bl_pf_sync_except()` faz:
1. `pfctl -t layer7_bl_except -T flush` (limpar tabela)
2. Para cada IP: `pfctl -t layer7_bl_except -T add <ip>`

### Passo 6.5 — Daemon popular tabela PF no SIGHUP

No `main.c`, após carregar `bl_cfg.except_ips[]`:

```c
for (i = 0; i < bl_cfg.n_except_ips; i++)
    layer7_pf_exec_table_add("layer7_bl_except", bl_cfg.except_ips[i]);
```

### Passo 6.6 — Documentar precedência final

```
        Resposta DNS observada
                │
    ┌───────────▼────────────────┐
    │ IP origem em               │
    │ layer7_bl_except?          │
    └───────────┬────────────────┘
      Sim │           │ Não
    ┌─────▼─────┐     │
    │ PERMITE   │     │
    │ (PF pass) │     │
    └───────────┘     │
    ┌─────────────────▼──────────┐
    │ Domínio em política        │
    │ manual block?              │
    └───────────┬────────────────┘
      Sim │           │ Não
    ┌─────▼─────┐     │
    │ BLOQUEIA  │     │
    └───────────┘     │
    ┌─────────────────▼──────────┐
    │ Domínio na whitelist?      │
    └───────────┬────────────────┘
      Sim │           │ Não
    ┌─────▼─────┐     │
    │ PERMITE   │     │
    │(ignora BL)│     │
    └───────────┘     │
    ┌─────────────────▼──────────┐
    │ Domínio em categoria deny? │
    └───────────┬────────────────┘
      Sim │           │ Não
    ┌─────▼─────┐ ┌───▼──────┐
    │ BLOQUEIA  │ │ PERMITE  │
    └───────────┘ │ (default)│
                  └──────────┘
```

A excepção por IP opera a nível PF (mais eficiente e fiável).

### Validação do Bloco 6

```
□ Regra pass aparece ANTES da regra block em pfctl -sr
□ IP em layer7_bl_except acede a destino bloqueado pela blacklist
□ IP NÃO em layer7_bl_except é bloqueado normalmente
□ Tabela PF flush + add funciona ao guardar excepções
□ Domínio na whitelist: não bloqueado pela blacklist
□ Domínio na whitelist + em política manual: BLOQUEADO (política manual)
□ Domínio em blacklist + não na whitelist: BLOQUEADO
□ Domínio fora de tudo: PERMITIDO
□ SIGHUP repopula tabela PF com except_ips do config.json
□ pfctl -t layer7_bl_except -T show mostra IPs correctos
```

---

## Bloco 7: Estatísticas e dashboard

### Passo 7.1 — Contadores no daemon

Já implementados no Bloco 3 (`s_bl_hits`, `s_bl_lookups`).

### Passo 7.2 — Card no dashboard (layer7_status.php)

Adicionar card de blacklists na secção de resumo:

```php
$stats = layer7_read_stats();
if (!empty($stats["bl_enabled"])) {
    /* Card com: categorias, domínios carregados, hits */
}
```

### Passo 7.3 — Top categorias hit

Requer contadores por categoria no daemon. Implementar com a mesma
lógica de `stats_increment()` já existente.

### Validação do Bloco 7

```
□ Stats JSON contém campos bl_*
□ Dashboard mostra card de blacklists (quando activo)
□ Card não aparece quando blacklists desactivadas
□ Contadores incrementam com uso
```

---

## Bloco 8: Empacotamento, documentação e testes finais

### Passo 8.1 — Actualizar pkg-plist (ACTUALIZADO v2)

Adicionar:

```
/usr/local/www/packages/layer7/layer7_blacklists.php
/usr/local/www/packages/layer7/layer7_bl_ajax.php
/usr/local/etc/layer7/update-blacklists.sh
@dir /usr/local/etc/layer7/blacklists
```

**Nota v2**: os ficheiros `config.json` e `discovered.json` são gerados
em runtime (pela GUI e pelo script). NÃO devem estar no `pkg-plist`.
O `blacklists-catalog.json` estático foi eliminado (substituído por
auto-descoberta via `discovered.json`).

### Passo 8.2 — Actualizar Makefile do port (ACTUALIZADO v2)

Adicionar `blacklist.c` e `bl_config.c` ao build e novos ficheiros ao install.

### Passo 8.3 — Actualizar PORTVERSION

```makefile
PORTVERSION=	1.1.0
```

### Passo 8.4 — Actualizar CORTEX.md

Adicionar secção sobre blacklists na última entrega.

### Passo 8.5 — Actualizar layer7.xml

Tab "Blacklists" adicionada.

### Passo 8.6 — Criar MANUAL-BLACKLISTS.md

Manual de uso com:
- O que são as blacklists
- Como activar
- Como escolher categorias
- Como usar a whitelist
- Como actualizar
- Troubleshooting

### Passo 8.7 — Testes end-to-end (ACTUALIZADO v2)

```
1. Build no FreeBSD builder (192.168.100.12)
2. Instalar .pkg no pfSense lab
3. Abrir GUI > Blacklists
4. Secção 1: Configurar URL e clicar "Download"
5. Verificar log de download visível (AJAX polling)
6. Verificar discovered.json gerado com categorias
7. Secção 2: Verificar categorias auto-descobertas na tabela
8. Secção 2: Seleccionar "deny" para "gambling" (pequena, ~8K)
9. Secção 2: Verificar aviso ⚠ em categorias > 1M domínios
10. Guardar categorias → config.json actualizado
11. Verificar que daemon recarregou (log bl_load)
12. Num cliente, resolver DNS de domínio na lista gambling
13. Verificar pfctl -t layer7_block_dst -T show
14. Verificar que o site é bloqueado
15. Secção 3: Adicionar domínio à whitelist → site desbloqueado
16. Secção 3: Adicionar IP a excepções → esse IP acede a bloqueados
17. Verificar pfctl -t layer7_bl_except -T show
18. Verificar pfctl -sr mostra pass ANTES de block
19. Mudar categoria de "deny" para "---" → domínios removidos
20. SIGHUP → verificar reload
21. Reboot do pfSense → verificar que tudo volta
22. Daemon sem config.json → inicia normalmente (retrocompatível)
```

### Passo 8.8 — Build final e release

```sh
# No builder
cd /root/pfsense-layer7
git stash && git pull origin main
git checkout "stash@{0}" -- src/layer7d/license.c src/layer7d/Makefile
git stash drop
cd package/pfSense-pkg-layer7
make clean
DISABLE_LICENSES=yes make package DISABLE_VULNERABILITIES=yes

# Copiar
sshpass -p 'pablo' scp root@192.168.100.12:/.../pfSense-pkg-layer7-1.1.0.pkg .

# Push + Release
git push origin main
gh release create v1.1.0 --title "v1.1.0 — Blacklists UT1" ...
```

### Validação Final do Bloco 8 (ACTUALIZADO v2)

```
□ Build sem erros
□ .pkg gerado
□ ldd layer7d não mostra libndpi.so
□ Instalação limpa funciona
□ Upgrade de v1.0.2 funciona
□ GUI sem erros PHP
□ 11 tabs visíveis
□ Download com log funciona
□ Auto-descoberta gera categorias correctas
□ Dropdown ---/deny funciona
□ Aviso ⚠ para categorias > 1M domínios
□ Excepção por IP (tabela PF layer7_bl_except) funciona
□ Regra pass antes de block no pfctl -sr
□ Blacklists funcional end-to-end
□ Políticas V1 continuam funcionando
□ Licenciamento continua funcionando
□ Dashboard com info de blacklists
□ config_parse.c NÃO foi alterado
□ layer7.json NÃO foi alterado
□ Rollback (desactivar) funcional
□ CORTEX.md actualizado
□ CHANGELOG actualizado
□ Release no GitHub com .pkg
```

---

## Ordem final de execução (ACTUALIZADA v2)

```
Semana 1: Bloco 1 (script + auto-descoberta) + Bloco 2 (módulo C + whitelist)
Semana 2: Bloco 3 (daemon + bl_config.c) + Bloco 4 (GUI SquidGuard 4 secções)
Semana 3: Bloco 5 (cron) + Bloco 6 (excepções PF) + Bloco 7 (stats)
Semana 4: Bloco 8 (empacotamento + testes + release)
```

---

*Documento criado em 2026-03-23. Actualizado em 2026-03-24 (v2: auto-descoberta, config.json separado, tabela PF except, GUI SquidGuard). Projecto Layer7 — Systemup Solução em Tecnologia.*
