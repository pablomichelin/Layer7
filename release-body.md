## Layer7 v1.7.9 — Fix sintaxe rdr (pfSense 2.8/FreeBSD 15)

### O que foi corrigido

**Bug crítico — pfSense CE nunca processava `nat_rules_needed` do package XML**

O campo `force_dns: true` na configuração de blacklists, que deveria redirecionar DNS externo para o Unbound local via regras `rdr`, **nunca gerava regras PF** — mesmo com o campo activado e o sub-anchor `natrules/layer7_nat` referenciado.

**Causa raiz dupla:**

1. **`nat_rules_needed` não é suportado no pfSense CE**: o `pkg-utils.inc` do pfSense CE só processa `filter_rules_needed`. O tag `<nat_rules_needed>` do nosso XML era completamente ignorado — a função `layer7_generate_nat_rules()` nunca era chamada pelo pfSense.

2. **Tag XML `custom_php_resync_command` errado**: pfSense CE espera `<custom_php_resync_config_command>` (com valor PHP eval-safe). O tag que tínhamos (`custom_php_resync_command`) não existe no pfSense → `layer7_resync()` nunca era chamado automaticamente no save de configuração.

**Correcção:**
- Nova função `layer7_inject_nat_to_anchor()` que carrega as `rdr` rules directamente em `natrules/layer7_nat` via `pfctl -a natrules/layer7_nat -N -f`
- Chamada em `layer7_generate_rules()` — executada a cada reload PF via `filter_rule_function` (o único hook que pfSense CE garante)
- Chamada em `layer7_resync()` — executada no save de configuração
- pfSense usa `pfctl -f` sem `-F flush` → sub-anchor `natrules/layer7_nat` persiste entre reloads

---

### Como verificar após actualização

```sh
# 1. Ver versão instalada
layer7d -V
# Deve mostrar: 1.7.9

# 2. Verificar se regras rdr foram geradas
pfctl -a natrules/layer7_nat -s Nat 2>/dev/null
# Deve mostrar:
# rdr pass on em1.46 inet proto udp from 10.0.60.0/23 to !127.0.0.1 port 53 -> 127.0.0.1 label "layer7:force_dns"
# rdr pass on em1.46 inet proto tcp from 10.0.60.0/23 to !127.0.0.1 port 53 -> 127.0.0.1 label "layer7:force_dns"

# 3. Confirmar referência no NAT principal
pfctl -s nat | grep natrules
# Deve mostrar: nat-anchor "natrules/*" all

# 4. Verificar QUIC bloqueado
pfctl -sr | grep anti-quic
# Deve mostrar: block drop quick inet proto udp from any to any port = https label "layer7:anti-quic"
```

---

### Notas de upgrade

Após instalação, forçar reload das regras PF em **Layer7 → Configurações → Guardar** para injectar as regras `rdr` imediatamente. Em reboots futuros e reloads de firewall, a injecção ocorre automaticamente.

---

### Instalação / Actualização

```sh
fetch -o /tmp/pfSense-pkg-layer7-1.7.9.pkg \
  https://github.com/pablomichelin/Layer7/releases/download/v1.7.9/pfSense-pkg-layer7-1.7.9.pkg \
  && IGNORE_OSVERSION=yes pkg add -f /tmp/pfSense-pkg-layer7-1.7.9.pkg \
  && service layer7d restart \
  && layer7d -V
```

---

### Rollback

```sh
fetch -o /tmp/pfSense-pkg-layer7-1.7.7.pkg \
  https://github.com/pablomichelin/Layer7/releases/download/v1.7.7/pfSense-pkg-layer7-1.7.7.pkg \
  && IGNORE_OSVERSION=yes pkg add -f /tmp/pfSense-pkg-layer7-1.7.7.pkg \
  && service layer7d restart
```

---

### Compatibilidade

- pfSense CE 2.7.x / 2.8.x
- FreeBSD 14.x / 15.x
- Retrocompatível: instalações sem `force_dns` não são afectadas
