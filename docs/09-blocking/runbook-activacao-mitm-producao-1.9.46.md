# Runbook — activação MITM produção (pós-`1.9.46`)

**Estado:** runbook canónico para teste MITM **controlado e temporário** na `.254`.  
**Supersede:** [`runbook-activacao-mitm-producao-1.9.42.md`](runbook-activacao-mitm-producao-1.9.42.md) (histórico; `quic_mode=bypass` obsoleto para esta janela).  
**Pré-requisito pacote:** `1.9.46`  
`SHA256=10998477ef7ae966e6c3566baeb973f922858fc72cc4d3a2dcdd0fb17bae72f5`  
**Cliente de teste único:** `192.168.100.24/32` (VM Windows descartável)  
**Destino único:** `198.18.0.10/32` via lab `.54` + SNI `mitm-lab.test`  
**Proibido:** `.234` / `.235`; domínios reais / outros hosts; senha Windows neste documento; activação permanente.  
**Topologia destino:** [`runbook-destino-lab-19818-via-54.md`](runbook-destino-lab-19818-via-54.md)  
**Gate C (prévia):** [`../tests/evidence/20260809T210753Z-phaseBD-d1-254/`](../tests/evidence/20260809T210753Z-phaseBD-d1-254/)

---

## Decisão humana formal (`2026-08-09`)

| Campo | Valor |
|-------|--------|
| Decisão | **GO** para teste MITM controlado na produção `.254` |
| Permanência | **NÃO** activar permanentemente após PASS |
| Origem | **apenas** `192.168.100.24/32` |
| Destino | **apenas** `198.18.0.10/32` + SNI `mitm-lab.test` |
| Fora de escopo | qualquer domínio real / outro host; `.234` / `.235` |
| Janela máxima | **15 minutos** |
| CA | efémera gerada **no appliance**; confiada **só** `LocalMachine\Root` da `.24` durante o teste; removida no fim |
| Autorização | utilizador autorizou todos os testes necessários no Cursor |

---

## Declaração (obrigatória)

| Campo | Valor |
|-------|--------|
| **Objectivo** | Provar intercept MITM escopado em produção `.254` (Edge real, sem flags/bypass) e regressar imediatamente a baseline OFF |
| **Impacto** | CA efémera no appliance; PF rdr TCP/443 + anti-QUIC UDP/443 só `src=.24 → dst=198.18.0.10`; helper `:8443` temporário; trust CA só na `.24`; rota lab `198.18.0.10` via `.54` durante a janela |
| **Risco** | Médio — toca produção `.254` em janela curta; blast radius limitado pelo `/32`×`/32`; falha de rollback deixaria rdr/CA |
| **Teste** | Preflight limpo → fail-safe 15 min → activação escopada → Edge block page + fingerprints + negativos → rollback completo + baseline |
| **Rollback** | `mitm.enabled=false`; listas vazias; stop tlsproxy; flush tabelas; remover rdr/anti-QUIC; apagar CA appliance; limpar CA/hosts `.24`; remover rota `198.18.0.10`; limpar jobs `at` |

---

## Parâmetros canónicos (esta janela)

| Campo | Valor |
|-------|--------|
| Pacote | `1.9.46` (`SHA256=10998477…ae72f5`) |
| `mitm.enabled` | `true` só durante a janela |
| `intercept.source_cidr` | `192.168.100.24/32` |
| `intercept.dest_cidr` | `198.18.0.10/32` |
| `intercept.block_sni` | `mitm-lab.test` |
| `quic_mode` | **`block`** (proibido `bypass` nesta activação) |
| Anti-QUIC PF | `block drop quick inet proto udp from <layer7_mitm_src> to <layer7_mitm_dst> port 443` — **só** esse escopo |
| Rdr | `from <layer7_mitm_src> to <layer7_mitm_dst> → 127.0.0.1:8443` — **nunca** `from any` |
| CA | `layer7_mitm_ca_generate` no appliance; **não** imprimir/commitar chave |
| Browser | Edge real **sem** `--disable-quic` / `--ignore-certificate-errors` |

---

## Abort criteria (parar já → rollback / NO-GO)

1. Preflight falha (pkg ≠ `1.9.46`, GUI/Internet/SSH mau, MITM já ON, residuais rdr/tabelas/QUIC/`:8443`)  
2. Rdr sem `<layer7_mitm_src>` ou com `from any`  
3. Anti-QUIC ausente, global (`from any`/`to any`/`inet6`) ou fora do escopo  
4. Tráfego de origem ≠ `.24` ou destino ≠ `198.18.0.10` a ser redireccionado  
5. Edge com intersticial TLS / flags de bypass no harness  
6. Smoke OFF / baseline falha após disable  
7. Qualquer necessidade de workaround

**Regra:** se qualquer gate falhar → **rollback imediato + NO-GO**, sem improvisar.

---

## Prechecks (read-only) — obrigatórios antes de escrita

```sh
pkg query '%v' pfSense-pkg-layer7
# esperado: 1.9.46
sockstat -l4 | egrep ':9999|:8443|:22'
# :9999 e :22 presentes; :8443 ausente
curl -sk -o /dev/null -w '%{http_code}\n' https://127.0.0.1:9999/
# esperado: 200 (ou challenge auth equivalente saudável)
ping -c 1 -W 2000 1.1.1.1
# Internet OK
test -f /var/run/layer7/mitm.effective && echo ON || echo OFF
# esperado: OFF
test -f /var/run/layer7/tlsproxy.product && echo GATE || echo NO_GATE
# esperado: NO_GATE
pfctl -s nat | egrep 'mitm|8443' || echo NO_MITM_RDR
pfctl -sr | egrep 'mitm-anti-quic' || echo NO_QUIC
pfctl -t layer7_mitm_src -T show 2>/dev/null || echo SRC_ABSENT
pfctl -t layer7_mitm_dst -T show 2>/dev/null || echo DST_ABSENT
# esperado: zero rdr / zero regras QUIC / tabelas ausentes ou vazias
route -n get 198.18.0.10 | head -6
# baseline limpa: sem rota host via .54 (default WAN ok)
df -h /
```

Confirmar no JSON: `mitm.enabled=false`, listas vazias, `ca_present=false`, `intercept_ready=true`, entitlement MITM presente.

Stage rollback local **antes** de qualquer escrita:

```sh
# ter disponível em /tmp ou tmp-release:
# pfSense-pkg-layer7-1.9.46.pkg + .sha256 (SHA acima)
# pfSense-pkg-layer7-1.9.42.pkg (rollback lab se necessário)
```

---

## Sequência (só com este GO)

### 1) Backup

```sh
cp -a /cf/conf/config.xml "/tmp/config.xml.bak-pre-mitm-19246-$(date -u +%Y%m%dT%H%M%SZ)"
cp -a /usr/local/etc/layer7.json "/tmp/layer7.json.bak-pre-mitm-19246-$(date -u +%Y%m%dT%H%M%SZ)" 2>/dev/null || true
```

### 2) Topologia lab (A/B/C) se baseline limpa

Seguir [`runbook-destino-lab-19818-via-54.md`](runbook-destino-lab-19818-via-54.md):

- **A** `.54`: alias `198.18.0.10/32` + listener HTTPS + rota retorno `.24 via .254`  
- **B** `.254`: `route add -host 198.18.0.10 192.168.100.54` (runtime; não persistir)  
- **C** `.24`: hosts `mitm-lab.test` → `198.18.0.10` (CA Phase-A só se necessário para path pré-MITM; CA MITM é a do appliance)

### 3) Fail-safe independente (≤15 min) — **antes** de activar MITM

```sh
# Em .254: script de emergência que desactiva MITM, flush tabelas,
# stop tlsproxy, remove rota 198.18.0.10, limpa gates.
# Armar com `at now + 15 minutes` (e/ou sleep watchdog separado).
# Limpar jobs at residuais no fim.
```

### 4) Activação escopada (janela curta)

1. Gerar CA efémera **no appliance** (`layer7_mitm_ca_generate`) — chave só no appliance.  
2. Aplicar intenção:

| Campo | Valor |
|-------|--------|
| `mitm.enabled` | `true` |
| `quic_mode` | `block` (**não** `bypass`) |
| `intercept.source_cidr` | `192.168.100.24/32` |
| `intercept.dest_cidr` | `198.18.0.10/32` |
| `intercept.block_sni` | `mitm-lab.test` |

3. `layer7_mitm_sync_helper` + `layer7_filter_configure_safe` / sync + `layer7_mitm_tables_apply_to_pf`.  
4. Validar:

```sh
pfctl -s nat | egrep 'layer7_mitm|8443'
# from <layer7_mitm_src> to <layer7_mitm_dst> — NUNCA from any
pfctl -t layer7_mitm_src -T show   # só 192.168.100.24
pfctl -t layer7_mitm_dst -T show   # só 198.18.0.10
pfctl -sr | grep -F 'layer7:mitm-anti-quic'
# UDP/443 src→dst; sem inet6 / from any / to any
openssl s_client -connect 127.0.0.1:8443 -servername mitm-lab.test
# leaf CN/SAN=mitm-lab.test; EKU serverAuth; CA:FALSE
```

### 5) Teste só em `.24` (Edge real)

- Instalar **apenas** a CA MITM do appliance em `LocalMachine\Root` da `.24`  
- Edge **sem** `--disable-quic` / `--ignore-certificate-errors` → `https://mitm-lab.test/`  
- Esperado: página de bloqueio Layer7 («acesso bloqueado») + screenshot  
- Negativos: origem/destino fora das tabelas; CA errada / sem CA → sem sucesso falso  
- Confirmar que outro host da LAN **não** é redireccionado

### 6) Rollback imediato + limpeza (mesmo se PASS)

```sh
# mitm.enabled=false; source/dest/block_sni vazios; quic_mode pode voltar default
# layer7_mitm_ca_delete; stop layer7-tlsproxy; flush layer7_mitm_*; filter sync
# remover rota 198.18.0.10; limpar at jobs
# na .24: remover CA Layer7 do Root + hosts marcados
# na .54: phase-a-control.sh rollback
```

### 7) Confirmar baseline `.254`

```sh
pkg query '%v' pfSense-pkg-layer7   # 1.9.46
test ! -f /var/run/layer7/mitm.effective
test ! -f /var/run/layer7/tlsproxy.product
sockstat -l4 | grep 8443 || echo NO_8443
pfctl -s nat | egrep 'mitm|8443' || echo NO_RDR
pfctl -sr | grep mitm-anti-quic || echo NO_QUIC
curl -sk -o /dev/null -w '%{http_code}\n' https://127.0.0.1:9999/
ping -c 1 -W 2000 1.1.1.1
```

---

## Rollback de pacote (só se necessário)

Preferir **config OFF** mantendo `1.9.46`. Se o pacote estiver corrompido:

```sh
IGNORE_OSVERSION=yes pkg add -f /tmp/pfSense-pkg-layer7-1.9.42.pkg
# SHA lab rollback: 6bd6ba374b398ec82cd43ea2246f16a3774f4377d3cac6411265472d3d3a4c4b
```

---

## Veredicto (preencher após execução)

| Item | Estado |
|------|--------|
| Pacote `1.9.46` + SHA | **GO** (publicado; Gate C PASS) |
| Preflight `.254` limpo | **pendente evidência desta janela** |
| Activação temporária escopada + Edge | **pendente evidência desta janela** |
| Rollback / baseline OFF | **obrigatório** no fim (PASS ou FAIL) |
| Activação permanente | **NO-GO** (decisão humana) |
| `.234` / `.235` | **intocadas** |
