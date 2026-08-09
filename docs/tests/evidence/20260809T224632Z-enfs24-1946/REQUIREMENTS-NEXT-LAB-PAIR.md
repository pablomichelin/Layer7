# Requisitos mínimos — par allow/block LAB local/isolado (próximo bloco)

Pré-condição para qualquer smoke enforce a partir de `.24` após este NO-GO parcial.
**Sem este par aprovado por GO humano: zero tráfego de teste.**

---

## 1. Política de alvos (obrigatória)

| Regra | Valor |
|-------|--------|
| Rede | Somente IPs **RFC1918 da malha lab** (ou hosts lab documentados) |
| Proibido | Qualquer destino público/Internet (`17.250.*`, DNS públicos, `*.com` externos, CDNs, etc.) |
| Fonte | `192.168.100.24` (já com canal SSH confirmado) |
| Appliance | `192.168.100.254` com Layer7 `1.9.46`, MITM **OFF** |
| Clientes produção | `.234` / `.235` **fora** do par salvo GO explícito em contrário |
| PoC descartável | `.54` elegível como *sink* de serviço local (não mutar `.254` para hospedar HTTP se evitável) |

---

## 2. Par mínimo (dois destinos)

Definir **antes** do smoke, por escrito no GO / evidência:

| Papel | Requisito |
|-------|-----------|
| **BLOCK_LAB** | IP:porta lab onde um listener HTTP(S) ou TCP responde; IP entra em `layer7_block_dst` (ou política scoped que o materialize) **só** para a janela de teste |
| **ALLOW_LAB** | IP:porta lab distinto, alcançável a partir de `.24` via `.254`; **não** em `block_dst`; preferir membro explícito de `layer7_allow_dst` / allowlist durante a janela |
| Isolamento | Ambos os listeners sob controlo do lab (ex. `python -m http.server` / `nc` / nginx em `.54` ou VLAN lab dedicada) |
| Identidade | IPs estáticos documentados; sem DNS público; hostname local opcional só se resolver internamente |

Critério PASS do smoke (sugestão canónica):

1. De `.24`: ligação a **BLOCK_LAB** falha/timeout (PF drop observável).
2. De `.24`: ligação a **ALLOW_LAB** sucede.
3. Correlação no `.254`: evento/contador Layer7/PF para BLOCK_LAB com origem `.24`.
4. MITM continua OFF (`mitm.enabled=false`, sem tlsproxy intercept, sem tabelas mitm povoadas).

Só com **PASS completo** → candidato a disable + `layer7-pfctl flush-all`.

---

## 3. Checklist de preparação (próximo bloco)

- [ ] GO humano nomeia `BLOCK_LAB` e `ALLOW_LAB` (IP + porta + host que serve)
- [ ] Confirmar rota `.24` → destinos via `.254` (leitura/`traceroute` lab, sem Internet)
- [ ] Subir listeners locais nos sinks escolhidos
- [ ] Materializar BLOCK só na tabela/política de teste (janela curta + rollback)
- [ ] Materializar ALLOW (tabela ou ausência de block + allowlist se necessário)
- [ ] Snapshot / nota de restore (snapshot pré-G2 já existe; opcional snap fresco)
- [ ] Smoke **somente** aos dois IPs lab
- [ ] Reverter entradas de teste das tabelas/políticas no mesmo bloco se não for disable global

---

## 4. Fora de escopo até novo GO

- Tráfego para Internet / destinos públicos
- Usar membros actuais de `block_dst`/`allow_dst` públicos como prova
- Desactivar Layer7 ou flush canónico sem smoke PASS
- MITM / tlsproxy / rdr
- Mutar `.234` / `.235` sem autorização explícita

---

## 5. Estado herdado útil

| Item | Valor |
|------|--------|
| Pacote | `1.9.46` (SHA oficial OK) |
| Acesso fonte | SSH `.24` OK |
| Enforce live | `enabled=true` (manter até smoke PASS) |
| Snapshot | `/tmp/l7-preg2-snap-20260809T221619Z-preG2-G2-254` |
| Evidência base | este `run_id` |
