## Layer7 v1.7.3 — Hotfix: Correcção de bugs críticos do Bloqueio Total

Pacote Layer 7 para pfSense CE com classificacao em tempo real via nDPI.

### O que foi corrigido (v1.7.3)

**Bug Crítico — `rdr` rules no filter anchor (pfSense rejectava o ruleset):**
- As regras `rdr` para DNS forçado (Melhoria A do v1.7.2) estavam a ser incluídas no filter anchor do PF
- O FreeBSD PF rejeita `rdr` em filter anchors com `"rdr rule not allowed in filter ruleset"` — o ruleset inteiro falhava a carregar, deixando as regras de bloqueio inoperacionais
- Correcção: as regras `rdr` são agora injectadas **exclusivamente** via o hook `nat_rules_needed` → `layer7_generate_nat_rules()` registado no `layer7.xml`

**Bug Médio — Nomes de interface `lan`/`wan` não eram detectados no fallback:**
- O regex de validação `^[a-z][a-z0-9]+[0-9]$` exigia que o último caractere fosse um dígito
- Interfaces como `lan` e `wan` (sem dígito no final) eram silenciosamente ignoradas
- Em instalações onde o `config.xml` não conseguia mapear a interface, as regras `rdr` não eram geradas para essas interfaces
- Correcção: regex alterado para `^[a-z][a-z0-9]+$/i` (cobre `lan`, `wan`, `em0`, `opt1`, etc.)

**Bug Menor — Contadores `bl_sni_hits` inconsistentes:**
- `s_bl_sni_hits` era incrementado por cada `pfctl add` bem-sucedido dentro do loop de regras
- O contador DNS equivalente (`s_bl_dns_hits`) era incrementado uma vez por domínio match
- Correcção: `s_bl_sni_hits` e `s_bl_hits` movidos para antes do loop — comportamento agora consistente entre DNS e SNI

---

### Novidades incluídas (v1.7.2 — mantidas)

**Melhoria A — DNS Forçado via PF `rdr` (fecha brecha de DNS externo):**
- Quando uma regra blacklist tem `src_cidrs` e `force_dns: true`, o Layer7 cria automaticamente regras PF que redirecionam TODO o DNS (porta 53 UDP+TCP) desses CIDRs para o Unbound local
- Mesmo que o dispositivo tenha 8.8.8.8 hardcoded, a query DNS passa pelo Layer7
- Checkbox "Forçar DNS local para estes CIDRs" na GUI (activado por defeito em novas regras)

**Melhoria B — Bloqueio por TLS SNI (fecha brechas de DNS em cache e CDNs):**
- O daemon captura o SNI (Server Name Indication) do TLS ClientHello via nDPI
- Quando o browser já tem o IP em cache (sem nova query DNS), o SNI ainda é visível no TLS handshake
- Se o SNI casa com a blacklist → IP destino adicionado à tabela `layer7_bld_N` → conexão actual e futuras bloqueadas
- Cobre CDNs não listados na UT1 (ex: `xvideos-cdn.com`) com SNI visível

**Melhoria C — Estatísticas DNS vs SNI:**
- `cat /tmp/layer7-stats.json` agora mostra:
  ```json
  "bl_dns_hits": 145,
  "bl_sni_hits": 38
  ```

---

### Instalação / Actualização

```sh
fetch -o /tmp/install.sh https://raw.githubusercontent.com/pablomichelin/Layer7/main/install.sh && sh /tmp/install.sh
```

Ou directamente:

```sh
fetch -o /tmp/pfSense-pkg-layer7-1.7.3.pkg \
  https://github.com/pablomichelin/Layer7/releases/download/v1.7.3/pfSense-pkg-layer7-1.7.3.pkg
pkg add /tmp/pfSense-pkg-layer7-1.7.3.pkg
```

---

### Verificação pós-instalação

```sh
layer7d -V                              # deve mostrar 1.7.3
cat /tmp/layer7-stats.json | grep bl_   # bl_dns_hits e bl_sni_hits
pfctl -s nat | grep force_dns           # regras rdr na secção NAT (se force_dns activo)
```

---

### Rollback

```sh
fetch -o /tmp/pfSense-pkg-layer7-1.7.2.pkg \
  https://github.com/pablomichelin/Layer7/releases/download/v1.7.2/pfSense-pkg-layer7-1.7.2.pkg
pkg delete pfSense-pkg-layer7 && pkg add /tmp/pfSense-pkg-layer7-1.7.2.pkg
```

---

### Compatibilidade

- pfSense CE 2.7.x e 2.8.x
- FreeBSD 14.x / 15.x
- Retrocompatível com configurações existentes — nenhum campo novo obrigatório
