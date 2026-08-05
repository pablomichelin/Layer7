# Mapa de rastreabilidade — IPv6 (lógica e código)

**Data:** 2026-08-04  
**Rev.:** 2026-08-04b  
**Estado:** SSOT da trilha IPv6 (componentes × gaps × ondas)  
**Plano:** [`plano-ipv6-completo.md`](../02-roadmap/plano-ipv6-completo.md)  
**ADR:** [`ADR-0024`](../03-adr/ADR-0024-suporte-ipv6-ativacao-faseada.md)  
**Arranque:** [`START-HERE-fecho-producao.md`](../00-overview/START-HERE-fecho-producao.md)

> Passos **12.x** da trilha ≠ `test-matrix.md` §12 (blacklists F4.2).

---

## 1. Resumo executivo

| Camada | IPv4 hoje | IPv6 hoje | Gap | Onda |
|--------|-----------|-----------|-----|------|
| Captura / nDPI | Completo | **Ignorado** (`ip_v != 4`) | FP-010 | V2 |
| Decisão política (daemon) | Completo | **Não** | FP-010 | V3 |
| PF global (`layer7_block*`) | Completo | Regras `inet6` existem; tabelas só v4 | Parcial | V2–V3 |
| PF scoped (`pdst`/`psrc`/…) | Completo | **Só `inet`** | REV-018 | V1 |
| Allowlist | IPv4+CIDR | **Não** | allowlist.h | V3 |
| DNS forçado / sinkhole | `rdr inet` | **Não** | ADR-0018 | V5 |
| Block page NAT | `rdr inet` | **Não** | ADR-0017 | V5 |
| VIP isenção DNS | IPv4 rdr/view | **Não** | ADR-0020 | V5 |
| GUI / validação JSON | IPv4 | **Não** | layer7.inc | V4 |
| Anti-DoT/QUIC PF | inet+inet6 | **Sim** | — | — |
| Docs / GUI disclosure | Parcial | Incompleto | plano-enforcement §429 | V0 |

---

## 2. Matriz por ficheiro (SSOT técnico)

Legenda **Acção:** `DOC` documentar | `PF` regras PF | `CAP` captura | `POL` política | `ENF` enforce | `CFG` config/GUI | `NAT` rdr/NAT | `TST` testes

| ID | Ficheiro | Função | Estado IPv6 | Gap / notas | Onda | BG | Acção |
|----|----------|--------|-------------|-------------|------|-----|-------|
| M-01 | `src/layer7d/capture.c` | libpcap → parse IP → nDPI | v4 only L551–563 | EtherType `0x0800` only | V2 | BG-080 | CAP |
| M-02 | `src/layer7d/capture_flow_key.h` | Hash fluxo bidireccional | `uint32` IPv4 | Redesenhar chave v6 | V2 | BG-080 | CAP |
| M-03 | `src/layer7d/main.c` | flow_decide, DNS hint, PF add | `AF_INET` only ~L920 | Endereços v6 em decisões | V3 | BG-081 | ENF |
| M-04 | `src/layer7d/policy.c` | Parse/match políticas | CIDR `/32` max L292 | `parse_cidr_str` v6 | V3 | BG-081 | POL |
| M-05 | `src/layer7d/policy.h` | Structs decisão | IPs como string v4 | Campos ou tipo unificado | V3 | BG-081 | POL |
| M-06 | `src/layer7d/enforce.c` | `pfctl -T add/del`, kill states | IPv4 strings | `pfctl` com addr v6 | V3 | BG-081 | ENF |
| M-07 | `src/layer7d/enforce.h` | API enforce | `src_ipv4`/`dst_ipv4` | Renomear/generalizar | V3 | BG-081 | ENF |
| M-08 | `src/layer7d/allowlist.c` + `.h` | Allowlist destinos | `L7_AL_IPV4_*` only L19–20 | Host/CIDR v6 | V3 | BG-081 | POL |
| M-09 | `src/layer7d/blacklist.c` | Sinkhole / bl tables | Orientado A records | AAAA: tabelas v6 em V3; `rdr`/sinkhole só V5 ou limite ADR | V3–V5 | BG-081/083 | ENF |

| M-10 | `src/layer7d/config_parse.c` | JSON runtime | Sem tipo IP explícito | Validar hosts v6 no parse | V4 | BG-082 | CFG |
| M-11 | `package/.../layer7.inc` | Geração regras PF | Global `inet6` OK; scoped `inet` only L987+ | REV-018 | V1 | BG-079 | PF |
| M-12 | `package/.../layer7.inc` | `layer7_ipv4_valid`, `layer7_cidr_valid` | IPv4 only L3130+ | `layer7_ipv6_valid`, CIDR v6 | V4 | BG-082 | CFG |
| M-13 | `package/.../layer7.inc` | `layer7_generate_rdr_rules_*` | `rdr ... inet` L317+ | `inet6` ou ADR exclusão | V5 | BG-083 | NAT |
| M-14 | `package/.../layer7.inc` | VIP DNS / Unbound view | IPv4 | DHCPv6/VIP v6 | V5 | BG-083 | NAT |
| M-15 | `package/.../layer7-pfctl` | Snippet PF helper | `inet6` block global | Alinhar com V1 scoped | V1 | BG-079 | PF |
| M-16 | `package/.../pf.conf.sample` | Amostra operador | Parcial `inet6` block | Documentar scoped | V0 | BG-078 | DOC |
| M-17 | `package/.../layer7_policies.php` (+ GUI) | CRUD políticas | Hosts/CIDR v4 | Campos + validação v6 | V4 | BG-082 | CFG |
| M-18 | `package/.../layer7_exceptions.php` | VIP / excepções | IPv4 | CIDR/host v6 | V4 | BG-082 | CFG |
| M-19 | `package/.../layer7_diagnostics.php` | Status / avisos | Sem banner v6 | Banner I1 | V0 | BG-078 | DOC |
| M-20 | `tests/functional/test_scoped_pf_inc.php` | Regressão PF scoped | Assert `inet6` anti-quic L234 | Assert scoped `inet6` | V1 | BG-079 | TST |
| M-21 | `tests/run-local.sh` + unit C | Regressão local | Sem testes v6 | Novos testes V2–V3 | V2–V3 | BG-080–081 | TST |
| M-22 | `tests/lab/run-f5-smoke-checklist.sh` | Smoke appliance | IPv4 | `run-ipv6-dualstack.sh` (novo) | V6 | BG-084 | TST |
| M-23 | `docs/09-blocking/matriz-limitacoes-dpi.md` | Limitações DPI | FP-010 disclosed (12.1) | Actualizar por onda | V0–V6 | BG-078 | DOC |
| M-24 | `docs/05-daemon/pf-enforcement.md` | SSOT enforcement | Menciona v4 | Secção dual-stack honesta | V0 | BG-078 | DOC |
| M-25 | `docs/04-package/validacao-lab.md` | Roteiro lab | Sem secção v6 | Nova secção §21 dual-stack | V6 | BG-084 | DOC |

---

## 3. Fluxo de dados (IPv4 actual vs alvo)

### 3.1 IPv4 (funcional)

```text
libpcap → Ethernet 0x0800 → IPv4 → flow_hash(sa,da) → nDPI → policy.c match
    → enforce.c pfctl -T add <ipv4> → PF inet/inet6 rules (tabela com v4)
```

### 3.2 IPv6 (alvo pós-V3)

```text
libpcap → Ethernet 0x86DD → IPv6 → flow_hash_v6(s,d) → nDPI → policy.c match v6
    → enforce.c pfctl -T add <ipv6> → PF inet6 rules scoped+global
```

### 3.3 Bypass actual (dual-stack)

```text
Cliente ──IPv6──► Internet   (capture.c return early → sem decisão → sem PF add)
Cliente ──IPv4──► Layer7 OK
```

---

## 4. Rastreio de defeitos / limitações existentes

| Ref | Severidade | Descrição | Fecho |
|-----|------------|-----------|-------|
| FP-010 | Alta | Pipeline capture/enforce IPv4-only | **V2–V3 + GV3–GV4** (não só V2) |
| REV-018 | Alta | Scoped PF só `inet` | V1 + GV1 |
| AUD-007 | Alta | Limitação arquitectural captura | V2 |
| matriz-limitacoes §6 | Claim | “Dual-stack” seria falso | V0 I1 |

---

## 5. Dependências externas

| Dependência | Impacto IPv6 |
|-------------|--------------|
| nDPI 5.x estático | Suporta IPv6 no upstream; validar API `ndpi` com fluxos v6 no builder |
| libpcap | `DLT_EN10MB`/`DLT_RAW` — pacotes v6 na wire OK se parse correcto |
| pfSense PF | `inet6` rules já usadas para anti-quic; syntax validada G3 |
| Unbound | Sinkhole `local-data` — avaliar AAAA em V5 |
| Appliance lab | Precisa cliente dual-stack ou só-v6 para GV6 |

---

## 6. Regra de actualização deste mapa

A cada passo `12.x` concluído:

1. Actualizar coluna **Estado IPv6** da linha afectada.
2. Registar evidência `run_id` se gate físico.
3. Commit no **mesmo bloco** que o código (mensagem `trilha-ipv6/12.x:`).

---

## 7. Referências

- [`plano-ipv6-completo.md`](../02-roadmap/plano-ipv6-completo.md)
- [`plano-gates-ipv6.md`](../09-blocking/plano-gates-ipv6.md)
- [`START-HERE-fecho-producao.md`](../00-overview/START-HERE-fecho-producao.md)
- [`revisao-codigo-pre-install-2026-06-15.md`](../09-blocking/revisao-codigo-pre-install-2026-06-15.md) (REV-017–020)
- [`auditoria-end-to-end-2026-07-29.md`](../09-blocking/auditoria-end-to-end-2026-07-29.md) (AUD-007)

---

## 8. Salvaguardas de segurança IPv6 (obrigatórias V1+)

Estas regras entram no desenho **antes** de merge de código V1/V3. Gates:
GV1.5–GV1.7, GV3.5, GV4.5.

| ID | Regra | Onda mínima | Nota |
|----|-------|-------------|------|
| S-01 | Não quebrar **NDP** (Neighbor Solicitation/Advertisement) nem ICMPv6 essencial à LAN | V1 | Evitar `block` genérico a `inet6 proto ipv6-icmp` sem excepções |
| S-02 | Regras `to !<localsubnets>` (e equivalentes) devem considerar prefixos **IPv6** locais do pfSense | V1 | Validar no appliance dual-stack |
| S-03 | **Nunca** adicionar a tabelas de block: `::1`, `fe80::/10` (link-local), `ff00::/8` (multicast) | V1/V3 | Validação em `enforce.c` / helpers |
| S-04 | Distinguir ULA (`fc00::/7`) vs GUA em docs/lab; não assumir só GUA | V0 docs / V3 | |
| S-05 | Privacy Extensions: allowlist por IP único é frágil — preferir CIDR; MAC→IPv6 **fora** de I1–I8 | V3/V4 | Já diferido no plano |
| S-06 | Captura: tratar **extension headers** e fragmentação IPv6, não só EtherType `0x86DD` | V2 | |
| S-07 | M-09 AAAA sem V5 `rdr`: documentar half-fix; não claim «DNS forçado v6» | V3/V5 | ADR Opção B se adiar |
| S-08 | V1 com tabelas vazias + regras `inet6` é seguro (sem block v6 até daemon); smoke monitor obrigatório | V1 | |

**STOP:** se GV3.5 (NDP) falhar → rollback candidato; não avançar GV4.
