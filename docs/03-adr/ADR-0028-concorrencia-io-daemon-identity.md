# ADR-0028 — Modelo de concorrência e IO do daemon para Identity (trilha IM)

**Estado:** Aceito  
**Data:** 2026-08-05  
**Aceite:** `2026-08-05` — passo **20.2** / GI0 (não adiado para 20.11a)  
**Decisores:** Operador (GO humano)  
**Plano:** [`../02-roadmap/plano-identity-mitm-addon.md`](../02-roadmap/plano-identity-mitm-addon.md)  
**Relação:** pré-requisito técnico de IM3–IM5 (mapa daemon, LDAP, RADIUS receiver, agente DC); baseline de perf mensurável continua obrigatória no **20.11a**

---

## Contexto

- O `layer7d` actual é um **loop único signal-driven**: `for (;;)` em `main.c`
  faz `layer7_capture_poll()` (captura + nDPI + policy + enforce) e reage a
  `SIGHUP` (reload) / `SIGUSR1` (stats). **Não há threads** no caminho quente.
- A trilha Identity acrescenta ao daemon IO de rede novo e potencialmente
  **bloqueante**:
  - cliente **LDAP/LDAPS** (bind, expansão de grupos) — chamadas de rede com
    latência de dezenas/centenas de ms e timeouts de segundos;
  - **RADIUS accounting receiver** (socket UDP, secret, ACL NAS);
  - **receiver do agente DC** (listener autenticado para push de logons).
- Qualquer chamada bloqueante dentro do loop principal **pára a captura e o
  enforcement** — regressão directa das superfícies NR-01/NR-03 do mapa de
  rastreabilidade. Este é o maior risco técnico da trilha.

---

## Decisão (proposta)

### 1. Regra inviolável

**Nenhuma chamada bloqueante de Identity no loop principal de captura.**
O hot path (captura → nDPI → policy → enforce) mantém o modelo actual,
inalterado com `identity` OFF.

### 2. Modelo canónico: threads produtoras + mapa partilhado

| Componente | Execução | Notas |
|------------|----------|-------|
| Loop principal (captura/enforce) | Thread principal (como hoje) | **Só lê** o mapa de sessão |
| Cliente LDAP (IM4) | Thread própria (worker) | Bind/expand com timeout; nunca chamado do hot path |
| RADIUS accounting receiver (IM5) | Thread própria com socket UDP não-bloqueante | Valida secret + ACL antes de tocar o mapa |
| Receiver agente DC (IM5) | Thread própria (listener) | Autenticação antes de tocar o mapa |
| Expiração TTL / limpeza stale | Thread housekeeping ou tick do loop principal (operação O(1)/incremental, sem IO) | |

### 3. Mapa de sessão partilhado

- Protegido por **`pthread_rwlock`**: escritores são as threads de fonte;
  o hot path adquire **read lock** apenas no momento do match `ad_*`.
- Regra de latência: a secção crítica de escrita deve ser **O(entrada)**
  (actualizar uma sessão), nunca IO nem expansão LDAP dentro do lock.
- Alternativa admissível na implementação: snapshot/swap de ponteiro
  (estilo RCU simplificado) se o rwlock mostrar contenção no lab — decisão
  local de IM3 sem novo ADR, desde que preserve a regra 1.

### 4. Ciclo de vida

- Com entitlement `identity` ausente ou módulo OFF: **nenhuma thread nova é
  criada**. O binário comporta-se como a baseline `1.9.8` (prova em GI1/GI4).
- `SIGHUP` (reload de config): threads de fonte relêem config; o **mapa de
  sessão vivo sobrevive ao reload** (não é descartado). Descarte total só em
  restart do processo.
- Shutdown: threads de fonte terminam antes do flush PF final (ordem
  determinística; sem uso do mapa após free).

### 5. Baseline de performance obrigatória

Antes do primeiro código IM3 (passo 20.11a), registar baseline mensurável da
`1.9.8` no lab/builder: CPU do daemon em tráfego de referência, throughput,
latência de bloqueio (tempo classificação→PF). Todo gate GI4–GI7 compara
contra esta baseline (tolerância definida no plano §6).

**Cumprido `2026-08-06`:** evidência
[`../tests/evidence/20260806T174000Z-20.11a-baseline-perf/`](../tests/evidence/20260806T174000Z-20.11a-baseline-perf/)
(pacote lab `1.9.13` pré-Identity; pin doc `1.9.8`; latência classify→PF live
adiada sem `.lic` — proxy builder documentado).

---

## Alternativas rejeitadas

| Alternativa | Motivo |
|-------------|--------|
| LDAP/RADIUS no loop principal (sem threads) | Bloqueia captura/enforce; regressão NR-01/NR-03 |
| Processo auxiliar separado (`layer7-identityd`) + IPC | Válido tecnicamente, mas duplica ciclo de vida, rc.d, watchdog e empacotamento; adiado — só reconsiderar se as threads mostrarem problema real no lab |
| Mapa via ficheiro/SQLite consultado por poll do PHP | Padrão `device_ips`: stale demais (já rejeitado em ADR-0027) |
| Async single-thread (kqueue integrando LDAP) | Bibliotecas LDAP síncronas tornam isto frágil; custo alto sem ganho sobre threads |

---

## Consequências

- `Makefile` do daemon passa a linkar `-lpthread` (verificar impacto no
  builder FreeBSD; ficheiros locais sensíveis do builder não são commitados).
- Testes novos em IM3: concorrência básica (escrita durante match), reload
  com mapa vivo, shutdown limpo.
- O mapa de rastreabilidade ganha M-24 (modelo de concorrência) e M-25
  (baseline de perf).

---

## Rollback

- `identity` OFF / sem entitlement → zero threads novas → comportamento
  baseline.
- Reinstalar pacote `1.9.8`.

---

## Referências

- Plano §0.0 R-K/R-O, §4 (passo 20.11a), §6, §7 (R17)
- Mapa de rastreabilidade NR-01/NR-03, M-24/M-25
- ADR-0027 (mapa no daemon), ADR-0021 (check-in)
