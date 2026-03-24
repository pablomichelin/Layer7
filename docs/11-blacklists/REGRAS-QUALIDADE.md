# Regras de Qualidade e Segurança: Blacklists UT1

> Regras que o agente e o desenvolvedor DEVEM seguir para garantir
> que o código é seguro, estável e de qualidade.

---

## 1. Regras de segurança C

### 1.1 Buffer overflow — zero tolerância

```c
/* PROIBIDO — buffer overflow garantido com domínios longos */
char buf[64];
strcpy(buf, domain);

/* OBRIGATÓRIO — cópia segura com tamanho */
char buf[64];
strncpy(buf, domain, sizeof(buf) - 1);
buf[sizeof(buf) - 1] = '\0';
```

### 1.2 Integer overflow — verificar antes de multiplicar

```c
/* PROIBIDO — overflow se n_entries for muito grande */
size_t total = n_entries * sizeof(struct l7_bl_entry);

/* OBRIGATÓRIO — verificar antes */
if (n_entries > SIZE_MAX / sizeof(struct l7_bl_entry)) {
    L7_ERROR("bl: entry count overflow");
    return NULL;
}
size_t total = n_entries * sizeof(struct l7_bl_entry);
```

### 1.3 Path traversal — validar nomes de categorias

```c
/* PROIBIDO — permite ../../../../etc/passwd */
snprintf(path, sizeof(path), "%s/%s/domains", dir, category);

/* OBRIGATÓRIO — validar que categoria só tem [a-z0-9_-] */
static int
cat_name_valid(const char *name)
{
    const char *p;
    if (!name || !*name || strlen(name) > 47)
        return 0;
    for (p = name; *p; p++) {
        if (!islower((unsigned char)*p) && !isdigit((unsigned char)*p) &&
            *p != '_' && *p != '-')
            return 0;
    }
    return 1;
}
```

### 1.4 File descriptor leak — fechar SEMPRE

```c
/* Cada fopen DEVE ter um fclose em TODOS os caminhos de saída */
FILE *f = fopen(path, "r");
if (!f)
    return -1;

/* Se ocorrer erro no meio: */
if (bad_condition) {
    fclose(f);    /* ← NUNCA esquecer */
    return -1;
}

/* Caminho normal */
fclose(f);
return 0;
```

### 1.5 Null pointer — verificar TUDO

```c
/* Todos os ponteiros recebidos devem ser verificados */
const char *
l7_blacklist_lookup(const struct l7_blacklist *bl, const char *domain)
{
    if (!bl)        return NULL;
    if (!bl->buckets) return NULL;
    if (!domain)    return NULL;
    if (!*domain)   return NULL;
    /* ... agora sim, usar os ponteiros ... */
}
```

### 1.6 Compilar com flags de segurança

```makefile
CFLAGS += -Wall -Wextra -O2
# Flags recomendadas para desenvolvimento/teste:
# CFLAGS += -fsanitize=address -fsanitize=undefined -fno-omit-frame-pointer
```

---

## 2. Regras de robustez

### 2.1 Daemon é um processo de longa duração

O `layer7d` corre **24/7 durante meses**. Qualquer leak, acumulação
ou degradação é inaceitável:

| Recurso | Regra |
|---------|-------|
| Memória | Zero leaks — cada malloc tem free |
| Ficheiros | Zero leaks — cada fopen tem fclose |
| Tabela PF | Entradas expiram com TTL |
| Hash table | Substituída (não acumulada) no reload |
| Contadores | Tipos `unsigned long long` para não overflow |

### 2.2 Reload atómico

O reload de blacklists deve ser **atómico**: primeiro carregar a
nova blacklist em memória, depois substituir o ponteiro, depois
libertar a anterior.

```c
/* CORRECTO: carregar novo, trocar ponteiro, libertar antigo */
struct l7_blacklist *new_bl = l7_blacklist_load(...);
struct l7_blacklist *old_bl = s_blacklist;
s_blacklist = new_bl;  /* troca atómica do ponteiro */
if (old_bl)
    l7_blacklist_free(old_bl);

/* ERRADO: free primeiro, depois load (janela sem blacklist) */
l7_blacklist_free(s_blacklist);
s_blacklist = l7_blacklist_load(...);  /* se falhar, s_blacklist é NULL */
```

**Nota**: como o daemon é single-threaded, não há race condition real,
mas o padrão "carregar novo → trocar → libertar antigo" é mais seguro
porque se o load falhar, a blacklist anterior continua activa.

### 2.3 Graceful degradation

Se a blacklist falha ao carregar:
- Daemon continua a funcionar com políticas manuais
- Log ERROR é emitido
- Stats JSON mostra `bl_enabled: false`
- Próximo SIGHUP pode tentar novamente

Se o download falha:
- Script shell exit 1
- Última versão das listas mantida no disco
- Log ERROR em `/var/log/layer7-bl-update.log`
- Daemon não é perturbado (sem SIGHUP)

### 2.4 Limites defensivos

Definir limites razoáveis e rejeitá-los com log:

```c
#define L7_BL_MAX_FILE_SIZE   (512 * 1024 * 1024)  /* 512MB máximo por ficheiro */
#define L7_BL_MAX_TOTAL       (8 * 1024 * 1024)     /* 8M domínios total */
#define L7_BL_MAX_LINE_LEN    512                    /* bytes por linha */
#define L7_BL_MAX_CATS        64                     /* categorias máximas */

/* No load: */
if (bl->n_entries >= L7_BL_MAX_TOTAL) {
    L7_WARN("bl_load: max entries reached (%d), skipping rest",
        L7_BL_MAX_TOTAL);
    break;
}
```

---

## 3. Regras de qualidade PHP

### 3.1 Validação de input — SEMPRE

```php
/* Todo input do utilizador DEVE ser validado */
$category = trim($_POST["category"] ?? "");
if (!preg_match('/^[a-z0-9_-]+$/', $category)) {
    $input_errors[] = gettext("Nome de categoria inválido.");
    /* NÃO prosseguir */
}
```

### 3.2 Output escaping — SEMPRE

```php
/* Todo output HTML DEVE ser escapado */
echo htmlspecialchars($category, ENT_QUOTES, 'UTF-8');
```

### 3.3 Execução de comandos — SEMPRE escapar

```php
/* OBRIGATÓRIO: escapeshellarg() para cada argumento */
$cmd = "/usr/local/etc/layer7/update-blacklists.sh --categories " .
    escapeshellarg($cat_string);
```

### 3.4 Ficheiros — verificar existência

```php
/* Antes de ler qualquer ficheiro */
if (!file_exists($path) || !is_readable($path)) {
    /* fallback seguro */
}
```

### 3.5 JSON — verificar parse

```php
$data = json_decode($raw, true);
if (!is_array($data)) {
    /* fallback para defaults */
}
```

---

## 4. Regras de qualidade shell

### 4.1 Variáveis — quotar SEMPRE

```sh
# CORRECTO
fetch -o "$TMP/blacklists.tar.gz" "$BL_URL"
rm -rf "$TMP"

# ERRADO — falha com espaços no path
fetch -o $TMP/blacklists.tar.gz $BL_URL
rm -rf $TMP
```

### 4.2 Exit codes — propagar

```sh
# Verificar CADA comando crítico
if ! fetch -o "$TARBALL" "$URL"; then
    log "ERROR: download failed"
    exit 1
fi

if ! tar xzf "$TARBALL" -C "$TMP" "blacklists/$cat/domains"; then
    log "ERROR: extraction failed for $cat"
    # continuar com outras categorias, não exit
fi
```

### 4.3 Cleanup — SEMPRE com trap

```sh
cleanup() {
    rm -rf "$TMP"
    rm -f "$LOCK"
}
trap cleanup EXIT
```

### 4.4 Espaço em disco — verificar antes

```sh
# Verificar espaço livre antes do download
AVAIL=$(df -k /usr/local/etc | tail -1 | awk '{print $4}')
if [ "$AVAIL" -lt 200000 ]; then
    log "ERROR: insufficient disk space (need 200MB, have ${AVAIL}KB)"
    exit 1
fi
```

### 4.5 Progresso para a GUI (NOVO v2)

```sh
# Escrever progresso para ficheiro que a GUI lê via AJAX
PROGRESS="/tmp/layer7-bl-progress.txt"

log() {
    msg="$(date '+%Y-%m-%d %H:%M:%S') $*"
    echo "$msg" >> "$LOG"
    echo "$msg" >> "$PROGRESS"
    echo "$*"
}

# Limpar progresso anterior ao início
: > "$PROGRESS"
```

### 4.6 Auto-descoberta de categorias (NOVO v2)

```sh
# Gerar discovered.json após extracção
# NÃO usar jq ou ferramentas externas — sh puro
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

Nota: usar escrita atómica (`mv` de ficheiro temporário) para evitar
que a GUI leia um `discovered.json` parcial.

---

## 5. Regras de compatibilidade

### 5.1 Retrocompatibilidade JSON (ACTUALIZADO v2)

O daemon DEVE funcionar quando o ficheiro
`/usr/local/etc/layer7/blacklists/config.json` NÃO existir.
O `layer7.json` NÃO é alterado (decisão v2).

```c
/* Se config.json não existir, simplesmente não carregar */
if (l7_bl_config_load(path, &cfg) != 0)
    s_blacklist = NULL;  /* OK — daemon opera normalmente */
```

**CRÍTICO**: o `config_parse.c` NÃO foi alterado. Se o ficheiro
`config.json` for apagado ou corrompido, o daemon continua a
funcionar com as políticas manuais V1.

### 5.2 Retrocompatibilidade GUI (ACTUALIZADO v2)

Se o `config.json` não existir, a GUI deve mostrar defaults
(tudo desactivado). Se o `discovered.json` não existir, mostrar
"Faça o download da lista primeiro":

```php
$bl_config = layer7_bl_config_load();
/* Retorna defaults se não existir — NUNCA crashar */

$discovered = layer7_bl_discovered_load();
if ($discovered === null) {
    print_info_box(gettext("Faça o download da lista primeiro."), 'warning');
}
```

### 5.3 Retrocompatibilidade do pacote

- O `.pkg` novo deve instalar sobre o antigo sem perder config
- As políticas, excepções, grupos existentes devem permanecer
- O directório `blacklists/` é criado na instalação
- Se já existir, não apagar conteúdo

### 5.4 FreeBSD — sistema de ficheiros

- `/usr/local/etc/layer7/` já existe (criado pelo pacote V1)
- `/usr/local/etc/layer7/blacklists/` precisa de ser criado
- Permissões: `root:wheel`, ficheiros `0644`, scripts `0755`
- `/tmp/` para temporários (limpo no boot)

---

## 6. Regras de performance

### 6.1 Lookup DEVE ser O(1) médio

O callback DNS é chamado para CADA resposta DNS que passa pelo
firewall. O lookup DEVE ser rápido:

- Hash table com hash FNV-1a: O(1) médio
- Suffix matching: máximo ~5 lookups por domínio (5 labels)
- Total: O(1) médio × 5 = O(5) = O(1)

### 6.2 Load pode ser lento (é feito 1x)

O carregamento de ficheiros é feito no startup e reload. Pode
demorar alguns segundos (aceitável):

- 200K domínios: ~0.5 segundos
- 1M domínios: ~2-3 segundos
- 5M domínios: ~10-15 segundos

### 6.3 Download é background

O download da blacklist é feito por script shell, em background.
Não bloqueia o daemon nem a GUI.

---

## 7. Regras de teste

### 7.1 Teste unitário do módulo C

Antes de integrar, testar o módulo isoladamente:

```c
/* Criar ficheiro temporário com domínios de teste */
/* Carregar com l7_blacklist_load() */
/* Verificar count, lookup, suffix, whitelist */
/* Free e verificar que não há leak */
```

### 7.2 Teste de integração (ACTUALIZADO v2)

```
1. Instalar pacote no pfSense lab
2. Abrir GUI > Blacklists > Secção 1: Configurar URL e Download
3. Verificar log de download visível na GUI
4. Verificar discovered.json gerado com categorias correctas
5. Secção 2: Seleccionar "deny" para "ai" (~74 domínios)
6. Guardar → config.json actualizado
7. Verificar daemon carrega (log bl_load)
8. Resolver domínio da lista via DNS
9. Verificar pfctl -t layer7_block_dst -T show
10. Verificar acesso ao site bloqueado
11. Secção 3: Adicionar domínio à whitelist → acesso restaurado
12. Secção 3: Adicionar IP a excepções
13. Verificar pfctl -t layer7_bl_except -T show
14. IP excepcionado acede a destino bloqueado
15. IP normal continua bloqueado
```

### 7.2b Teste de auto-descoberta (NOVO v2)

```
1. Remover discovered.json
2. Abrir GUI > Blacklists
3. Verificar mensagem "Faça o download da lista primeiro"
4. Clicar Download → verificar log com progresso
5. Após conclusão: tabela de categorias auto-descobertas aparece
6. Contar categorias — deve ser ~80+
7. Verificar contagem de domínios por categoria
8. Verificar aviso ⚠ em categorias > 1M domínios (ex: adult)
```

### 7.2c Teste de excepções PF (NOVO v2)

```
1. Activar categoria com domínio conhecido (ex: gambling)
2. Configurar IP 192.168.10.50 como excepção
3. Guardar excepções
4. Verificar: pfctl -t layer7_bl_except -T show → 192.168.10.50
5. Verificar: pfctl -sr → pass ANTES de block para layer7_bl_except
6. Do IP 192.168.10.50: resolver DNS de site gambling → ACESSO
7. Do IP 192.168.10.51 (sem excepção): → BLOQUEADO
8. Remover 192.168.10.50 das excepções e guardar
9. Verificar: pfctl -t layer7_bl_except -T show → vazia
10. Do IP 192.168.10.50: → agora BLOQUEADO
```

### 7.3 Teste de stress

```
1. Activar categoria "adult" (~4.6M domínios)
2. Verificar tempo de carga (< 30 segundos aceitável)
3. Verificar uso de memória (ps -o rss layer7d)
4. Verificar performance de lookup (não degradar tráfego)
5. Fazer reload (SIGHUP) — verificar que memória não acumula
6. Repetir 3x — memória deve manter-se estável
```

### 7.4 Teste de regressão V1 (ACTUALIZADO v2)

```
1. Criar política manual (match.hosts = ["example.com"])
2. Verificar que bloqueia (antes da blacklist)
3. Activar blacklist
4. Verificar que política manual continua a funcionar
5. Criar excepção allow para um IP
6. Verificar que excepção continua a funcionar
7. Perfil rápido: aplicar e verificar
8. Dashboard: verificar contadores
9. Teste de política: verificar simulação
10. Backup/restore: verificar que config blacklist é incluída
11. Daemon sem config.json de blacklists → inicia normalmente
12. config_parse.c NÃO foi alterado (verificar git diff)
13. layer7.json NÃO foi alterado (verificar git diff)
14. IP em layer7_bl_except acede a destinos bloqueados por política manual
    (a regra pass é global para layer7_block_dst)
```

---

## 8. Diagramas de fluxo de dados

### 8.1 Fluxo de download e carga (ACTUALIZADO v2)

```
GUI: Secção 1 → Clicar "Download"
    │
    ▼
PHP: layer7_bl_download_start()
    │ mwexec_bg("update-blacklists.sh --download")
    │
    ▼
Shell: fetch blacklists.tar.gz
    │ (progresso escrito em /tmp/layer7-bl-progress.txt)
    │
    ▼
Shell: tar xzf (TODAS as categorias)
    │
    ▼
Shell: auto-descoberta → gerar discovered.json
    │ (listar categorias + contar domínios)
    │
    ▼
Shell: copiar TODAS as pastas para blacklists/
    │
    ▼
GUI: Operador selecciona categorias (---/deny)
    │ Guardar → config.json actualizado
    │
    ▼
PHP: layer7_bl_apply() → SIGHUP
    │
    ▼
Daemon: handler SIGHUP → reload_req = 1
    │
    ▼
Daemon: main loop detecta reload_req
    │
    ▼
Daemon: l7_bl_config_load() → ler config.json SEPARADO
    │
    ▼
Daemon: l7_blacklist_load() → hash table (só categorias deny)
    │ (whitelist passada internamente)
    │
    ▼
Daemon: swap ponteiro → free antigo
    │
    ▼
Daemon: popular tabela PF layer7_bl_except com except_ips[]
    │
    ▼
Log: "blacklists: loaded 145230 domains in 5 categories"
```

### 8.2 Fluxo de bloqueio por DNS (ACTUALIZADO v2)

```
Cliente resolve DNS: example.com → 93.184.216.34
    │
    ▼
pfSense Unbound → resposta DNS passa pelo firewall
    │
    ▼
capture.c: observe_dns_response()
    │
    ▼
capture.c: dns_cb(domain="example.com", ip="93.184.216.34", ttl=300)
    │
    ▼
main.c: layer7_on_dns_resolved()
    │
    ├─ 1. Enforce mode activo? NÃO → return
    │
    ├─ 2. layer7_domain_is_blocked() → política manual?
    │     SIM → pfctl add 93.184.216.34 → return
    │
    ├─ 3. s_blacklist existe?
    │     NÃO → return
    │
    └─ 4. l7_blacklist_lookup() → na blacklist?
          (whitelist verificada INTERNAMENTE antes do hash lookup)
          SIM → pfctl add 93.184.216.34 → log bl_block
          NÃO → return (permitido)

Nota: a excepção por IP opera a NÍVEL PF (não no daemon):
    pass quick from <layer7_bl_except> to <layer7_block_dst>
    block drop quick to <layer7_block_dst>
```

### 8.3 Fluxo de excepção PF (NOVO v2)

```
Cliente (192.168.10.50, em layer7_bl_except) tenta aceder a
IP bloqueado (93.184.216.34, em layer7_block_dst):

1. Pacote: 192.168.10.50 → 93.184.216.34
    │
    ▼
2. PF avalia regras na ordem:
    │
    ├─ pass quick from <layer7_bl_except> to <layer7_block_dst>
    │  → 192.168.10.50 está em layer7_bl_except? SIM
    │  → MATCH → PASS (permite o tráfego)
    │
    └─ block drop quick to <layer7_block_dst>
       → NÃO AVALIADA (regra anterior fez match)

Cliente (192.168.10.51, NÃO em layer7_bl_except):

1. Pacote: 192.168.10.51 → 93.184.216.34
    │
    ▼
2. PF avalia regras na ordem:
    │
    ├─ pass quick from <layer7_bl_except> to <layer7_block_dst>
    │  → 192.168.10.51 está em layer7_bl_except? NÃO
    │  → sem match, continuar
    │
    └─ block drop quick to <layer7_block_dst>
       → 93.184.216.34 está em layer7_block_dst? SIM
       → MATCH → DROP (bloqueia o tráfego)
```

---

*Documento criado em 2026-03-23. Actualizado em 2026-03-24 (v2: testes PF except, testes auto-descoberta, fluxos actualizados). Projecto Layer7 — Systemup Solução em Tecnologia.*
