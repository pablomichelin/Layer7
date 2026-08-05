# Relatório QA IPv4 — Layer7 `1.9.2`

| Campo | Valor |
|-------|-------|
| **Data** | 2026-08-05 |
| **Versão testada** | `pfSense-pkg-layer7-1.9.2` |
| **Appliance** | `192.168.100.254` (`systemupfw.system.up`) |
| **OS appliance** | pfSense Plus `26.03.1` / FreeBSD `16.0-CURRENT` (**não CE**) |
| **Cliente A** | `192.168.100.234` (Ubuntu) |
| **Cliente B planeado** | `192.168.100.232` — **inalcançável** |
| **Cliente B efectivo** | `192.168.100.235` (`zpro-aimirim`) |
| **Builder smoke** | `smoke-layer7d: OK` (`192.168.100.12`) |
| **Suite local** | `tests/run-local.sh` — **PASS** |
| **Âmbito** | IPv4 apenas; **sem correção de código** |
| **Evidência** | este directório |

---

## Veredicto executivo

**NÃO GO para substituição total do Squid em produção multi-cliente**, no estado observado da `1.9.2`.

O que **funciona bem** no modo actual (`legacy_global` + `force_dns` + políticas por host):

- bloqueio DNS/sinkhole de listas de hosts (adulto/jogos/anti-DoH);
- página de bloqueio HTTP;
- captura/classificação IPv4 activa;
- licença, pacote, daemon, GUI (:9999);
- regressão básica: Google/YouTube livres na config de produção; bancos **não** sinkholedos pelo Layer7.

O que **falha ou é inaceitável** para o caso Squid (políticas por cliente / two-client):

1. Com `force_dns`, sinkhole DNS de hosts é **global** — política scoped ao cliente A bloqueia também o B.
2. Com `force_dns` OFF + `scoped_hybrid` + SNI + match host/app YouTube, **não houve bloqueio PF** (`pdst` vazia, `pf_add_ok=0`, YouTube HTTP 200 no A).
3. Resíduos operacionais e avisos PF/self-heal no ciclo de vida.

Configuração de produção **foi restaurada** no fim dos testes (`legacy_global`, 4 políticas originais, `force_dns=1`).

---

## Matriz de resultados

| # | Área | Resultado | Notas |
|---|------|-----------|-------|
| 1 | Pacote `1.9.2` instalado | **PASS** | instalado 2026-08-05 00:26 |
| 2 | `layer7d` a correr | **PASS** | PID estável após restore |
| 3 | Licença | **PASS** | `valid=1`, 702 dias, customer=Systemup |
| 4 | Suite local macOS | **PASS** | `ALL LOCAL TESTS PASSED` |
| 5 | Smoke builder | **PASS** | `smoke-layer7d: OK` |
| 6 | Captura IPv4 | **PASS** | `cap_pkts_v4` alto; ifaces `vmx0` + `vmx0.95` |
| 7 | GUI status/settings | **PASS** | HTTP 200 em `https://127.0.0.1:9999/packages/layer7/...` (443 directo falhou) |
| 8 | Bloqueio host adulto (DNS) | **PASS** | `pornhub.com` → `192.168.100.254` |
| 9 | Página bloqueio HTTP | **PASS** | título «Acesso bloqueado» |
| 10 | Página bloqueio HTTPS | **FAIL / LIMITAÇÃO** | TLS EOF no portal (sem MITM) |
| 11 | `force_dns` (rdr DNS) | **PASS** | `dig @8.8.8.8 pornhub.com` → portal |
| 12 | Anti-bypass DoH/hosts | **PASS** | DNS Google / mask.icloud sinkhole/erro |
| 13 | Regressão bancos | **PASS*** | IPs reais (não sinkhole); HTTP 403/301/302 de CDN — *não* bloqueio Layer7 |
| 14 | Two-client scoped + `force_dns` | **FAIL** | A e B sinkholedos no YouTube |
| 15 | Two-client scoped PF-only | **FAIL** | A continua com YouTube 200; tabelas `pdst` vazias |
| 16 | Scoped + SNI + app YouTube | **FAIL** | idem; `total_blocked=0` / `pf_add_ok=0` |
| 17 | Legacy global + DNS YouTube | **PASS** | A e B bloqueados (esperado global) |
| 18 | Blacklists UT1 runtime | **FAIL / NÃO ACTIVO** | 233 MB em disco; `bl_enabled` vazio/0; sem bloco `blacklists` no JSON |
| 19 | Reload/restart | **PASS com ressalva** | sobe; log mostra `enforce_cfg=0` até SIGHUP |
| 20 | Cliente `.232` | **BLOCKED** | SSH timeout / unreachable |
| 21 | CE físico | **LIMITAÇÃO** | Plus 26.03.1 (ADR-0022) |

\* Bancos: BB/Itaú devolveram 403 de CDN/WAF a partir do cliente de teste; Bradesco/Caixa redireccionam. Destinos **não** eram o portal Layer7.

---

## Defeitos e falhas (priorizados)

### D1 — CRÍTICO — Sinkhole DNS global quebra isolamento por cliente

**Evidência:** `06-two-client.txt`  
Política `qa-yt-block-a` com `src_hosts=[192.168.100.234]` + `scoped_hybrid` + `force_dns=true`:

- Cliente A: `youtube.com` → `192.168.100.254` (bloqueado)
- Cliente B (`.235`): **igual** → portal (não deveria)

**Impacto:** impossível oferecer “bloqueia YouTube só para o filho / só para o VLAN X” com a UX actual de block page/DNS sem afectar toda a LAN.

### D2 — CRÍTICO — Enforcement PF scoped não bloqueou YouTube neste lab (LAN `vmx0`)

**Evidência:** `08-pfonly-two-client.txt`, `10-sni-two-client.txt`, `12-app-traffic.txt`, `16-after-resync-tables.txt`

Com `force_dns=false`, `sni_inspection=true`, política host+`ndpi_app` YouTube scoped ao A:

- 8–15 pedidos HTTPS YouTube do A → **HTTP 200**
- `layer7_pdst_*` vazias
- `pf_add_ok=0`, `dst_add_ok=0`, `total_blocked=0`

Nota histórica: em `layer7-events.log` (2026-07-30) há matches YouTube em **`vmx0.95`** com `enforce_block` para IPs reais. No teste actual na LAN `vmx0` com curl a `www.youtube.com`, o caminho PF **não** reproduziu bloqueio.

**Impacto:** caminho alternativo ao DNS (necessário para two-client) **não validado** nesta campanha.

### D3 — ALTO — Janela `enforce_cfg=0` após restart

**Evidência:** `14-enforce-cfg-race.txt`, logs em `13-app-results.txt`

Padrão repetido:

1. `service layer7d restart` → log `enforce_cfg=0 reload#1`
2. Só após `layer7_pf_config_resync` / SIGHUP → `enforce_cfg=1 reload#2`

**Impacto:** risco de período sem enforce após reboot/restart do serviço até algo enviar SIGHUP.

### D4 — MÉDIO — Avisos PF / self-heal / tabelas em falta

Durante resync/restart:

- `layer7-pfctl: required tables missing, trying rules.debug reload`
- `pf_selfheal: failed table=base` (às vezes; outras com `fallback=1` sucesso)
- `pfctl flush failed table=layer7_allow_dst`

**Impacto:** ruído operacional; possível fragilidade em reload sob carga.

### D5 — MÉDIO — Resíduo de teste G5 no PF

**Evidência:** baseline / `19-bl-restore.txt`

Anchor `layer7_g5_test` ainda contém:

```text
block drop in quick on vmx0 inet from 192.168.100.234 to 142.251.156.4
```

**Impacto:** regra órfã fora do ciclo de vida normal do pacote; pode confundir troubleshooting.

### D6 — MÉDIO — Blacklists UT1 não activas em runtime

Dados UT1 presentes (`/usr/local/etc/layer7/blacklists`, ~233 MB, snapshot `ut1-2026-04-25`), mas:

- `layer7.json` sem configuração `blacklists` (`null`)
- stats: `bl_domains_loaded=0`, `bl_rules_active=0`

**Impacto:** feature F4.2 não exercitada nesta instalação; não há evidência de bloqueio por lista UT1.

### D7 — BAIXO / UX — HTTPS na página de bloqueio

Cliente liga ao portal por HTTPS (SNI do domínio bloqueado) → `curl: (35) unexpected eof`.  
HTTP na porta 80 via rdr → página correcta.

**Impacto:** browsers em HTTPS mostram erro de certificado/conexão, não a página “Acesso bloqueado” (limitação conhecida sem MITM).

### D8 — LAB — Cliente `.232` offline

Two-client usou `.235` como B. O `.232` pedido não participou.

### D9 — PLATAFORMA — Não é pfSense CE

Appliance de validação = Plus 26.03.1. CE físico continua limitação (ADR-0022).

---

## O que passou com detalhe

### Bloqueio “estilo produção” (config restaurada)

- Interfaces: `vmx0` (LAN `192.168.100.254/24`), `vmx0.95` (Assistência)
- `mode=enforce`, `enforcement_model=legacy_global`, `force_dns=true`
- Políticas: monitor geral + anti-bypass DNS + protecção infantil + anti-bypass hosts
- Adulto/apostas: DNS → portal; HTTP block page OK
- YouTube/Google: livres após restore
- NAT Layer7: rdr HTTP→8099; rdr DNS (tcp/udp 53) em `vmx0` e `vmx0.95`

### Observabilidade

- Captura activa; eventos em `/var/log/layer7-events.log`
- Stats em `/tmp/layer7-stats.json` (não em `/var/db/layer7-stats.json`)
- Allowlist seed (~94 entradas) na tabela `layer7_allow_dst`

---

## Ambiente e restrições do teste

1. Alterações de **configuração** temporárias (scoped / force_dns / SNI) — **código do pacote não alterado**.
2. Config original restaurada de `/tmp/layer7.json.pre-qa-ipv4-192`.
3. Anchor `layer7_g5_test` **não removida** (defeito pré-existente; fora do âmbito “arranjar”).
4. IPv6 deliberadamente fora de âmbito.

---

## Recomendações para decisão (sem implementar agora)

1. ** decisião GO/NO-GO:** tratar `1.9.2` como candidata lab; **não** promover a “substituto Squid per-user” até D1+D2 fechados.
2. Priorizar análise de: sinkhole DNS **por cliente** vs PF `pdst` fiável com SNI/DNS QNAME na LAN.
3. Investigar D3 (`enforce_cfg` no boot/restart) — gate de regressão simples.
4. Limpar D5 (`layer7_g5_test`) em janela de manutenção.
5. Se blacklists forem requisito comercial: activar UT1 em lab e repetir matriz 12.1/12.2.
6. Repetir two-client quando `.232` estiver online (ou formalizar `.235` como B no inventário).
7. Manter pausa IPv6 até fechar acções deste relatório.

---

## Ficheiros de evidência

| Ficheiro | Conteúdo |
|----------|----------|
| `layer7.json.pre-qa-ipv4-192` | Backup pré-teste |
| `layer7.json.post-restore` | Config após restore |
| `01-run-local.txt` | Suite local |
| `02-appliance-deep.txt` | Stats/NAT/GUI |
| `03-clientB-baseline.txt` | Cliente `.235` |
| `05-apply-qa-scoped.txt` | Aplicação políticas QA |
| `06-two-client.txt` | **FAIL** scoped+force_dns |
| `07`/`08` | PF-only two-client |
| `09`/`10` | SNI two-client |
| `11`–`16` | App policy + race enforce_cfg |
| `17`/`18` | Legacy DNS YouTube OK |
| `19`/`20` | BL + restore + pós-restore |

---

## Rollback efectuado

- `cp /tmp/layer7.json.pre-qa-ipv4-192 → /usr/local/etc/layer7.json`
- `layer7_pf_config_resync` + `filter_configure` + `service layer7d restart`
- Verificado: políticas originais, `pornhub` sinkhole, `youtube` livre, daemon UP
