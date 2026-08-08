# Precedência de políticas (V1)

SSOT de ordem de avaliação do motor de decisão (`layer7_decide_for_client` /
`layer7_flow_decide`). Identity (IM6 / passos 20.23–20.25) **não** altera o
motor V1 — apenas acrescenta critérios de origem `ad_*` resolvidos no mapa
daemon.

## Regra principal

1. Apenas políticas com `enabled=true`.
2. Ordenar por **`priority` numérico decrescente** (maior prioridade primeiro).
3. Empate: ordem estável pelo **`id` lexicográfico** (determinístico).

## Primeira vitória

O primeiro match **ganha** (first match). Não há merge de ações entre regras.

## Interação com default implícito

Se nenhuma regra casar:

- **Modo monitor:** tratar como `monitor` implícito (evento opcional `no_policy_match`).
- **Modo enforce:** default **`allow`** (não bloquear o que não foi classificado/regrado) — **explícito na GUI** como política padrão.

> Alternativa futura: default `block` em zona restrita; fora de escopo V1 sem UX clara.

## Exceções (`exceptions[]`)

Avaliadas **antes** da matriz principal (maior precedência), com ordem interna por `priority` ou ordem de lista.

No enforcement PF, a precedência allow é materializada com a tag interna
`L7ALLOW`: somente os blocks administrados pelo Layer7 ignoram pacotes
marcados. A precedência do policy engine nunca substitui a política de
segurança nativa do pfSense e não gera `pass quick`.

Allowlist nativa (ADR-0020) e VIP / exclusões globais (ADR-0016 / ADR-0019)
permanecem **antes** da matriz principal — inalteradas pela trilha Identity.

## Conflito

Dois matches simultâneos não ocorrem com first-match. Se validação detectar regras idênticas de prioridade e overlap total, emitir aviso na GUI (backlog) ou `policy_conflict` em debug.

## Exclusão por política (`src_exclude_*`, BG-066 / ADR-0019)

Campos `match.src_exclude_cidrs` e `match.src_exclude_groups` fazem com que a
origem **não case** na política (first-match continua para políticas seguintes).
Isto é distinto da excepção global `vip-isentos`: a isenção é **só desta**
política. A GUI rejeita origem simultaneamente incluída e excluída.

Em `scoped_hybrid`, o PF usa `layer7_pexc_N` + `match from pexc to pdst tag
L7ALLOW`. Em `legacy_global`, a exclusão actua só no daemon (trade-off: destino
já em `layer7_block_dst` por outro cliente permanece bloqueado).

---

## Identity: `ad_users` / `ad_groups` (IM6 / 20.25)

**Decisão estrutural (R-M, plano Identity §3.1):** políticas com
`match.ad_users` / `match.ad_groups` **não** formam uma camada separada.
Entram na **mesma lista** ordenada por `priority` desc. No momento do match,
o alvo `ad_*` **resolve para o conjunto de IPs actual do mapa daemon**
(`identity_map` — não SSOT PHP tipo `device_ips` / ADR-0012).

Consequência prática: uma política `ad_groups` com `priority` **alta** pode
vencer uma política IP/`src_hosts` com `priority` **baixa** — coerente com o
motor V1 e previsível para o operador (GI7.4).

### Origem efectiva (match)

Para uma política:

| Critérios de origem | Comportamento |
|---------------------|---------------|
| Nenhum (`src_*` vazio e `ad_*` vazio) | Qualquer IP (após exclusões) |
| Só `src_hosts` / `src_cidrs` / `groups` (IP/MAC) | Match estático (expandido) |
| Só `ad_users` / `ad_groups` | Match se o IP do cliente mapear para user/grupo no mapa |
| Estático **e** `ad_*` | **OR** — casa se estático **ou** Identity |

`match.groups` (grupos Layer7 IP/MAC) é **distinto** de `match.ad_groups`
(grupos AD no cache da sessão Identity).

### Não-match `ad_*` (first-match continua)

1. Identity **OFF** (sem entitlement / mapa não ligado) → `ad_*` não casam;
   políticas só IP/MAC aplicam-se como em `1.9.8`.
2. User/IP **ausente** do mapa → não-match `ad_*`.
3. IP em estado **`multi_user`** (ADR-0027 §4.1) → não-match `ad_*`
   (fallback seguro); políticas IP/MAC seguintes podem ainda casar.
4. LDAP/fonte indisponível: fail-mode ADR-0027 §4 — cache até TTL; depois
   `ad_*` → não-match; **base intacta**; fail-closed total da LAN **proibido**.

### Conflito de sessão no mesmo IP

- Troca normal: **last-writer** + audit (`identity_ip_last_writer`).
- Users concorrentes na janela: estado `multi_user` + audit
  (`identity_ip_conflict`) → não-match `ad_*`.

### MITM (se reaberto)

MITM (DEFER 20.7a) aplica-se ao **caminho TLS** seleccionado; **não** altera
a precedência allow/block — só visibilidade/UX HTTPS.

### Empates e default

Exactamente como a regra principal V1 (id lexicográfico; enforce default
allow).

### Referência de implementação

- Parse/GUI: passo **20.23** (`1.9.27`)
- Match via mapa: passo **20.24** (`1.9.28`) — `layer7_policies_set_identity_map`
- Formalização deste documento: passo **20.25**
- Lab GI7: passo **20.26**
