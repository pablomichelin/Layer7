## Layer7 v1.7.4 — Segunda revisão: código morto, stats e validação GUI

Pacote Layer 7 para pfSense CE com classificacao em tempo real via nDPI.

### O que foi corrigido (v1.7.4)

**Bug Médio — `generate_rdr_rules()` era código morto em `layer7-pfctl`:**
- Após o fix v1.7.3, a função `generate_rdr_rules()` (40 linhas de PHP inline) permanecia no script mas não era chamada por nenhum código — `write_rules()` foi alterado e não a invoca
- Código morto desta dimensão pode confundir maintainers futuros que assumen que é chamado
- Correcção: função removida

**Bug Menor — `bl_lookups` subestimado (faltava contagem SNI):**
- `s_bl_lookups` era incrementado apenas no DNS callback
- No SNI check (`layer7_on_classified_flow()`), `l7_blacklist_lookup()` era chamado sem incrementar o contador
- Resultado: `cat /tmp/layer7-stats.json | grep bl_lookups` mostrava apenas os lookups DNS, ignorando os lookups SNI
- Correcção: `s_bl_lookups++` adicionado antes do lookup SNI

**Bug Menor — `force_dns` activo sem CIDRs falhava silenciosamente:**
- Utilizador podia activar "Forçar DNS local para estes CIDRs" sem definir nenhum CIDR de origem
- O backend (`layer7_generate_nat_rules()`) ignorava a regra silenciosamente — nenhuma regra `rdr` era gerada
- O utilizador ficava convencido que a funcionalidade estava activa
- Correcção: validação adicionada no formulário PHP — erro claro se `force_dns=true` e `src_cidrs` vazio

---

### Novidades incluídas (v1.7.2 — mantidas)

**Melhoria A — DNS Forçado via PF `rdr`:**
- Regras `rdr` geradas via `nat_rules_needed` → `layer7_generate_nat_rules()`
- Checkbox "Forçar DNS local para estes CIDRs" na GUI (com validação de CIDRs obrigatórios)

**Melhoria B — Bloqueio por TLS SNI:**
- SNI capturado via nDPI → blacklist lookup → `layer7_bld_N`

**Melhoria C — Estatísticas DNS vs SNI:**
- `bl_dns_hits`, `bl_sni_hits`, `bl_lookups` (agora incluindo lookups SNI)

---

### Instalação / Actualização

```sh
fetch -o /tmp/install.sh https://raw.githubusercontent.com/pablomichelin/Layer7/main/install.sh && sh /tmp/install.sh
```

Ou directamente:

```sh
fetch -o /tmp/pfSense-pkg-layer7-1.7.4.pkg \
  https://github.com/pablomichelin/Layer7/releases/download/v1.7.4/pfSense-pkg-layer7-1.7.4.pkg
pkg add /tmp/pfSense-pkg-layer7-1.7.4.pkg
```

---

### Verificação pós-instalação

```sh
layer7d -V                              # deve mostrar 1.7.4
cat /tmp/layer7-stats.json | grep bl_   # bl_lookups agora inclui SNI
pfctl -s nat | grep force_dns           # regras rdr na secção NAT (se force_dns activo)
```

---

### Rollback

```sh
fetch -o /tmp/pfSense-pkg-layer7-1.7.3.pkg \
  https://github.com/pablomichelin/Layer7/releases/download/v1.7.3/pfSense-pkg-layer7-1.7.3.pkg
pkg delete pfSense-pkg-layer7 && pkg add /tmp/pfSense-pkg-layer7-1.7.3.pkg
```

---

### Compatibilidade

- pfSense CE 2.7.x e 2.8.x
- FreeBSD 14.x / 15.x
- Retrocompatível com configurações existentes
