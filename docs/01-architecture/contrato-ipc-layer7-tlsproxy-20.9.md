# Contrato IPC — `layer7d` ↔ `layer7-tlsproxy` (passo 20.9)

**Estado:** `CONTRATO` — documentação vinculativa para implementação futura  
**Data:** `2026-08-08`  
**Passo:** **20.9 PASS** (`1.9.38`) — toggle + bypass endurecido; **sem** runtime no pacote  
**Desenho:** [`desenho-layer7-tlsproxy-mitm.md`](desenho-layer7-tlsproxy-mitm.md)  
**ADR:** [`../03-adr/ADR-0026-mitm-tls-inspection-opt-in.md`](../03-adr/ADR-0026-mitm-tls-inspection-opt-in.md)  
**Não-regressão OFF:** [`../03-adr/ADR-0017-pagina-bloqueio-utilizador-dns-sinkhole.md`](../03-adr/ADR-0017-pagina-bloqueio-utilizador-dns-sinkhole.md)

> **Segurança:** este contrato **não** autoriza intercept de produto. O helper
> **não** está no pacote `1.9.38`. `mitm_effective` permanece **false** até
> runtime empacotado + S1–S8 + GO produto (20.10). Squid **rejeitado**.
> PoC lab (`src/layer7-tlsproxy/` PoC-1): socket **só** lab (`/tmp` ou relativo)
> com `LAYER7_TLSPROXY_LAB=1`; path produto `/var/run/layer7/mitm.sock` recusado.

---

## 1. Objectivo

Definir a fronteira segura entre:

| Processo | Papel |
|----------|--------|
| `layer7d` | Policy, identity, PF, nDPI/pcap — **sem** terminar TLS |
| `layer7-tlsproxy` | Terminação TLS selectiva (futuro) — processo **separado** |

IPC = metadados e veredictos leves. **Proibido:** payload decifrado no hot path
do `layer7d`; IO bloqueante no loop de captura (ADR-0028).

---

## 2. Modelo de activação (gates em cascata)

```text
mitm_effective =
    mitm.enabled          (intenção do operador em layer7.json)
  ∧ entitlement mitm      (features ∩ .lic)
  ∧ CA presente           (cert+key em /usr/local/etc/layer7/mitm/)
  ∧ runtime_available     (binário layer7-tlsproxy no pacote)
  ∧ (futuro) GO lab / S8
```

| Campo | Quem escreve | Semântica |
|-------|--------------|-----------|
| `mitm.enabled` | GUI / JSON | Intenção; default **false**; upgrade não liga |
| `mitm_effective` | Runtime (PHP + futuro helper) | **Única** condição para bind/redirect |
| `mitm_runtime_available` | Pacote / compile-time | Hoje: **sempre false** |

**Regra:** sem `mitm_effective`, o helper **não** escuta, **não** há rdr PF
para MITM, ADR-0017 permanece a verdade.

---

## 3. Caminhos e permissões

| Artefacto | Path | Perms | Notas |
|-----------|------|-------|-------|
| CA cert | `/usr/local/etc/layer7/mitm/ca.crt` | `0644` | Exportável GPO |
| CA key | `/usr/local/etc/layer7/mitm/ca.key` | `0600` root:wheel | **Nunca** no git / JSON |
| Config | `/usr/local/etc/layer7.json` → `layer7.mitm` | existente | Sem chave privada |
| Socket IPC (futuro produto) | `/var/run/layer7/mitm.sock` | `0660` root:wheel | Unix domain; só com `mitm_effective` |
| Socket IPC (PoC-1 lab) | `/tmp/layer7-tlsproxy-poc.sock` ou relativo | `0600` | Exige `LAYER7_TLSPROXY_LAB=1`; **não** usar path produto |
| Pidfile (futuro) | `/var/run/layer7/tlsproxy.pid` | `0644` | |

Socket **só** após `mitm_effective`. Remover socket no stop.

---

## 4. Mensagens IPC (futuro — esboço estável)

Transporte: Unix datagram ou stream com frames length-prefix ≤ 4 KiB.  
Encoding: JSON UTF-8, **sem** payload TLS.

| Opcode | Direcção | Pedido | Resposta |
|--------|----------|--------|----------|
| `PING` | proxy→d / d→proxy | `{}` | `{ok:true, ts}` |
| `STATUS` | proxy→d | `{}` | effective, entitled, bypass_counts |
| `DECIDE` | proxy→d | `{sni, dst_ip, dst_port, src_ip}` | `{verdict: allow\|block\|bypass, policy_id?}` |
| `SHUTDOWN` | d→proxy | `{reason}` | ack + exit |

**DECIDE** deve ser **não-bloqueante** no `layer7d` (rwlock / cache de policy).
Timeout curto no proxy → **bypass** (fail-open selectivo, não fail-closed LAN).

### Proibições no IPC

- Certificados/chaves em mensagem  
- Body HTTP/TLS  
- Pedidos sem `src_ip`/`dst` quando efectivos  
- Escuta em TCP `0.0.0.0` para IPC  

---

## 5. Bypass endurecido (20.9)

Listas em `layer7.mitm.bypass.{sni,cidr}` + **protegidos** sempre activos:

| Tipo | Entradas protegidas (não removíveis) |
|------|--------------------------------------|
| CIDR | `127.0.0.1/32`, `::1/128` |

Validações:

- SNI: hostname DNS-like, ≤253, sem espaços; `*.` → apex  
- CIDR: IP ou IP/prefixo válido; IPv4 prefix 0–32; IPv6 0–128  
- Limites: ≤256 SNI, ≤128 CIDR  
- `quic_mode`: `bypass` \| `block` \| `downgrade` (default **`bypass`** — **S5 PASS documental** `2026-08-09`; prova lab sob runtime pendente)

Gestão pfSense / VIP: operador deve acrescentar CIDR/SNI explícitos; o produto
**não** intercepta localhost.

---

## 6. Integração PF (só 20.10+)

Com `mitm_effective`:

1. Helper sobe e faz bind nas portas/interfaces definidas.  
2. `layer7d` (ou script) instala **rdr** selectivo — **nunca** universal.  
3. Com `!mitm_effective`: **zero** regras MITM; smoke ≡ ADR-0017 (S8).

Este passo **20.9** **não** cria regras PF.

---

## 7. Observabilidade

Status JSON do daemon (já / a estender):

| Chave | 20.9 |
|-------|------|
| `mitm_entitled` | sim |
| `mitm_runtime_available` | `false` |
| `mitm_effective` | `false` |
| `mitm_enabled_config` | intenção lida do JSON (quando parser existir) |

GUI MITM mostra intenção vs efectivo (honesto para PME).

---

## 8. Rollback

1. `mitm.enabled=false` + save  
2. Remover entitlement `mitm`  
3. Parar helper (quando existir) + apagar socket  
4. Remover CA só se ops o exigir (GPO nos clientes)

---

## 9. Critério de fecho 20.9

- [x] Intenção `enabled` gravável com gates (entitlement + CA)  
- [x] `effective` false sem runtime  
- [x] Bypass endurecido + CIDR protegidos  
- [x] `quic_mode` default documentado  
- [x] Este contrato no repo  
- [ ] Runtime / socket / DECIDE — **fora** de 20.9 (20.10 + S1–S8 + GO lab)
