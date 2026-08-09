# Runbook — destino HTTPS lab local (`198.18.0.10/32` via `.54`)

**Estado:** **Fase A+B+C = PASS**; Fase D histórica NO-GO (`185035Z`) supersedida por **Gate C** (`210753Z`) + **GO teste controlado** (`215442Z` **PASS**, rollback OK; permanente **NO-GO**).  
**Tipo:** topologia de teste sem VPS (custo zero).  
**Pré-requisito pacote:** `.254` `1.9.46` passivo (MITM OFF) — lab/`latest` Gate C; rollback lab `1.9.42`.  
**Proibido neste desenho:** `.234` / `.235`; mutações sem fail-safe; CDN público.  
**Evidência Fase A:** [`docs/tests/evidence/20260809T180157Z-phaseA-54/`](../tests/evidence/20260809T180157Z-phaseA-54/)  
**Evidência Fase B:** [`docs/tests/evidence/20260809T180624Z-phaseB-254/`](../tests/evidence/20260809T180624Z-phaseB-254/)  
**Evidência Fase C:** [`docs/tests/evidence/20260809T181302Z-phaseC-24/`](../tests/evidence/20260809T181302Z-phaseC-24/)

---

## Declaração (obrigatória)

| Campo | Valor |
|-------|--------|
| **Objectivo** | Criar destino HTTPS `/32` dedicado em lab, fora de `192.168.100.0/24`, para que o cliente `.24` atravesse o gateway `.254` e permita activação MITM escopada (`source=.24/32` + `dest=198.18.0.10/32`) |
| **Impacto** | Alias/loopback + listener HTTPS na `.54`; rota estática `/32` na `.254`; hosts/SNI/CA só na `.24`; possível regra PF pass temporária LAN→lab; **sem** activar MITM nesta fase A–C |
| **Risco** | Médio — produção `.254` recebe rota + possível regra pass; assimetria L2 se o retorno não for forçado; janela curta obrigatória |
| **Testes** | Path forward via `.254`; path return via `.254`; TLS na `.54`; depois (fase D, GO separado) MITM rdr só `.24` |
| **Rollback** | Remover rota/regra na `.254`; derrubar listener/alias/rota host na `.54`; limpar hosts/CA na `.24`; smoke GUI 9999 + Internet |

---

## 0. Factos read-only (observados `2026-08-09`)

| Host | Facto |
|------|--------|
| `.254` | `1.9.42` passivo; LAN `vmx0`=`192.168.100.254/24`; `ip.forwarding=1`; **sem** rotas `198.18/19`; `staticroutes` vazio; ARP vê `.24` e `.54` |
| `.54` | Ubuntu lab; `ens160`=`192.168.100.54/24`; GW=`.254`; `ip_forward=1`; ufw inactive; INPUT ACCEPT; **sem** listen `:443` |
| L2 | `.24`, `.54`, `.254` no **mesmo** broadcast `192.168.100.0/24` |
| Path crítico | `ip route get 192.168.100.24` na `.54` → **on-link** `ens160` (retorno **não** passa no `.254` por defeito) |

---

## 1. Auditoria adversária da proposta

### 1.1 Destino `192.168.100.54` (mesmo /24) — **NO-GO**

Tráfego `.24`→`.54` é L2 on-link: **não** entra no gateway `.254` ⇒ rdr MITM **nunca** vê o fluxo.

### 1.2 Destino `198.18.0.10/32` (RFC 2544 /15) + rota via `.54` — **viável com condições**

| Tema | Achado | Mitigação obrigatória |
|------|--------|------------------------|
| Mesma LAN/L2 | Confirmado | Destino **fora** do /24 (RFC 2544) |
| Forward `.24`→`198.18.0.10` | Off-link ⇒ default GW `.254` | Rota host `/32` no `.254` → `192.168.100.54` |
| Return assimétrico | `.54` responde on-link a `.24`, **bypassa** `.254` | Na `.54`: `ip route replace 192.168.100.24/32 via 192.168.100.254` durante o teste |
| Estado PF | Retorno assimétrico pode partir TCP pré-MITM | Mesma rota de retorno + janela curta; validar com `tcpdump` nos dois hops |
| NAT WAN | NAT activo só em `pppoe*`; LAN forward lab não deve SNAT para WAN | Não usar `198.18` na Internet; rota estritamente local |
| `rdr → 127.0.0.1:8443` | Com MITM ON, cliente termina no proxy `.254`; upstream é **nova** sessão `.254`→`.54` | Upstream return é para `.254` (simétrico) — OK para o gate MITM |
| `route-to` / `reply-to` | Podem alterar caminho se existirem regras LAN especiais | Pré-check `pfctl -sr \| egrep 'reply-to\|route-to'` antes de activar; abort se conflito |
| ARP | Next-hop `.54` já em ARP no `.254` | Sem VIP 198.18 no `.254` (evitar proxy ARP confuso) |
| Conflito de rota | Nenhuma rota `198.18` hoje | Usar `198.18.0.10/32` dedicado; documentar |
| `.234/.235` | Proibidos | Não alterar; source MITM só `.24/32` |

### 1.3 O que **não** invalida a proposta

- `rdr` para `127.0.0.1:8443` **não** exige que `.24` fale directamente com `.54` no path MITM.  
- Upstream do `layer7-tlsproxy` sai do `.254` com src LAN ⇒ retorno `.54`→`.254` é natural.

### 1.4 O que **pode** invalidar (abort)

1. Regra `reply-to`/`route-to` que force caminho incompatível.  
2. Política que bloqueie forward LAN→`198.18.0.10`.  
3. Alguém publicar `198.18.0.10` noutro sítio / conflito.  
4. Esquecer rollback da rota no `.254` (produção).  
5. Activar MITM sem source `.24/32` (regressão dest-only — já mitigada em `1.9.42`).

---

## 2. Parâmetros canónicos (lab)

| Parâmetro | Valor |
|-----------|--------|
| Destino lab | `198.18.0.10/32` |
| Next-hop | `192.168.100.54` |
| Listener | `.54` `nginx`/`openssl s_server` ou `layer7-tlsproxy` lab em `198.18.0.10:443` |
| SNI / hosts (só `.24`) | `mitm-lab.test` → `198.18.0.10` |
| MITM (fase D, GO separado) | `source_cidr=192.168.100.24/32`, `dest_cidr=198.18.0.10/32`, `block_sni=mitm-lab.test` |
| CA | Efêmera na `.54`; trust **só** na `.24` |

---

## 3. Fases (exactas) — **parar se qualquer gate falhar**

### Fase A — Preparar `.54` (lab descartável) — **PASS** `2026-08-09T18:01:57Z`

**Escrita só em `.54` (GO humano recebido e executado).**

1. Alias/loopback: `ip addr add 198.18.0.10/32 dev lo`  
2. Rota de retorno do cliente: `ip route replace 192.168.100.24/32 via 192.168.100.254`  
3. Cert efêmero + HTTPS em `198.18.0.10:443` (bind explícito a esse IP; Python `http.server`+TLS)  
4. Teste local na `.54`: `curl --cacert … --resolve mitm-lab.test:443:198.18.0.10 https://mitm-lab.test/` → marcador `L7-PHASE-A-OK-198.18.0.10`  
5. Rollback ensaiado (ciclo completo) + re-apply; fail-safe `at` +180 min  
6. Layout: `/opt/layer7-poc/mitm-lab-a/` (certs `0600`; controlo `phase-a-control.sh`)

**Estado deixado UP** para Fase B (listener + alias + rota retorno).  
**Rollback A (executável):** `ssh root@192.168.100.54 '/opt/layer7-poc/mitm-lab-a/phase-a-control.sh rollback'`

### Fase B — Rota + pass mínimo no `.254` (produção; janela curta) — **PASS** `2026-08-09T18:06:24Z`

**Escrita em `.254` (GO humano recebido e executado).**

1. Backup operacional: `/tmp/config.xml.bak-phaseB-20260809T180624Z` (+ bak passivo prévio).  
2. Pré-check `reply-to`/`route-to`: matches existem (WAN dual + policy pontual `.244`/dest `191.5.179.29`); **nenhum** conflito com path `vmx0→198.18.0.10` → prosseguiu.  
3. Rota **runtime apenas** (sem GUI/`staticroutes`): `route add -host 198.18.0.10 192.168.100.54`.  
4. Regra PF temporária: **não necessária** — `pass in quick on vmx0 inet all` já permite; TLS/ICMP `.254→lab` OK.  
5. Fail-safe ≤20 min: `sleep 1140` + `at now + 15 minutes` → `/tmp/l7-phaseB-rollback.sh` (validado antes da rota).

**Testes B executados (sem tocar `.24`):**

```text
route get 198.18.0.10 → gateway 192.168.100.54 (vmx0)
curl/openssl .254 → mitm-lab.test @198.18.0.10 → L7-PHASE-A-OK-198.18.0.10
tcpdump vmx0 + ens160: SYN/ACK :443 + ICMP
GUI :9999=200; layer7d running; Internet OK; zero :8443 / rdr MITM
staticroutes config vazio (não persistido)
```

**Estado deixado UP** com fail-safe armado (rota some ao disparar).  
**Rollback B:** `ssh root@192.168.100.254 '/tmp/l7-phaseB-rollback.sh'`

### Fase C — Cliente `.24` apenas (DNS/hosts + CA) — **PASS** `2026-08-09T18:13:02Z`

**Escrita só em `.24` (GO humano; fail-safe B estendido antes dos testes).**

1. Backup `hosts` + inventário `LocalMachine\Root`  
2. CA efêmera (só `.crt`) → `certutil -addstore Root` — subject `CN=Layer7-PhaseA-Ephemeral-CA`, thumbprint `768AD5B382F2D950DB4273D64E122788732575D8`  
3. `hosts` marcado: `198.18.0.10 mitm-lab.test # L7-PHASE-C-19818-mitm-lab.test`  
4. Validação: resolve→`198.18.0.10`, GW `.254`, TCP/443, TLS trust sem bypass, Edge 151 real **sem** intersticial; **proibido** `--ignore-certificate-errors` / `curl -k` como prova de gate ([`politica-tls-sem-bypass.md`](politica-tls-sem-bypass.md))  
5. Rollback ensaiado (CA+hosts) + re-apply — estado deixado UP para Fase D

**Rollback C:** `powershell -File C:\Windows\Temp\phase-c-24.ps1 rollback`

### Fase D — Activação MITM (GO **separado**; fora deste desenho até confirmação)

Seguir [`runbook-activacao-mitm-producao-1.9.46.md`](runbook-activacao-mitm-producao-1.9.46.md) (canónico; supersede `1.9.42`) com:

| Campo | Valor |
|-------|--------|
| `intercept.source_cidr` | `192.168.100.24/32` |
| `intercept.dest_cidr` | `198.18.0.10/32` |
| `intercept.block_sni` | `mitm-lab.test` |

Abort se rdr tiver `from any` ou tabelas sem src.

---

## 4. Alternativa sem VPS se o GO da rota em produção for recusado

| Alternativa | Ideia | Prós | Contras |
|-------------|-------|------|---------|
| **A1 (esta)** | RFC 2544 + rota no `.254` + `.54` | Barata; destino dedicado; path real via gateway | Toca rota/PF em produção |
| **A2** | Segunda vNIC/VLAN só-lab na `.54` + interface/opt no `.254` | Isolamento L2 melhor | Precisa VLAN/switch (pode ter custo/ops) |
| **A3** | HTTPS só em `127.0.0.1` na `.54` + teste MITM **só** na `.54` (já feito em 20.11) | Zero risco produção | **Não** prova path produção `.254` |
| **A4** | Destino público `/32` estável não-CDN | Sem rota lab | Raro sem VPS; risco partilha IP |

**Sem VPS e com prova em produção:** **A1 é a única opção operacionalmente realista** neste lab.

---

## 5. Veredicto para confirmação humana

| Questão | Veredicto |
|---------|-----------|
| Usar `.54` como servidor HTTPS dedicado? | **GO** (lab descartável adequado) |
| Usar IP `192.168.100.54` como `dest_cidr`? | **NO-GO** (mesmo L2) |
| Usar `198.18.0.10/32` + rota via `.54`? | **GO CONDICIONAL** |
| Condições do GO | (1) rota retorno `.24 via .254` na `.54`; (2) pré-check `reply-to/route-to`; (3) pass PF mínimo só `.24`; (4) fail-safe temporizado + rollback; (5) fases A→B→C antes de qualquer MITM; (6) `.234/.235` intocados |
| Aplicar Fase A? | **GO executado — PASS** (`20260809T180157Z-phaseA-54`) |
| Aplicar Fase B? | **GO executado — PASS** (`20260809T180624Z-phaseB-254`; PF extra não preciso) |
| Aplicar Fase C? | **GO executado — PASS** (`20260809T181302Z-phaseC-24`; Edge sem intersticial) |
| Aplicar Fase D agora? | **NO-GO** — Edge `ERR_SSL_KEY_USAGE_INCOMPATIBLE`; rollback feito; ver Gate D0 |
| Gate D0 (diagnóstico)? | **PASS** — [`diagnostico-D0-phaseBD-mitm-20260809.md`](diagnostico-D0-phaseBD-mitm-20260809.md) |
| Reaplicar Fase D? | **Bloqueado** até Gate D1 (mint leaf) + **novo GO humano** |

**Pedido ao proprietário:** GO para **Gate D1** (código: leaf por SNI). Não reactivar MITM em `.254` só com trust da CA — o peer actual é a própria CA.
