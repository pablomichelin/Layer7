## Layer7 v1.7.7 — Correcção crítica: regras `rdr` (force_dns) agora funcionam em interfaces VLAN

### O que foi corrigido

**Bug crítico — `force_dns` não gerava regras `rdr` em interfaces VLAN**

O campo `force_dns: true` na configuração de blacklists (que redireciona DNS externo para o Unbound local via `rdr`) **nunca gerava regras PF** quando a interface de captura usava um nome de device VLAN com ponto — como `em1.46`, `igb0.100`, `vtnet0.200`.

**Causa raiz:** A função `layer7_generate_rdr_rules_snippet()` em `layer7.inc` usava o regex `/^[a-z][a-z0-9]+$/i` como fallback quando `get_real_interface()` retornava NULL. Interfaces VLAN do tipo `em1.46` contêm um ponto — o regex rejeitava-as. Resultado: `$real_ifaces` ficava vazio, a função retornava string vazia, e **zero regras `rdr` eram injectadas no PF**, mesmo com `force_dns: true` activo.

**Correcção:** Regex actualizado para `/^[a-z][a-z0-9]*(\.[0-9]+)?$/i` — aceita `em1.46`, `igb0.100`, `vtnet0.200`, `lagg0.10`, além de `lan`, `wan`, `em0`, `vtnet0`, etc.

---

### Como verificar após actualização

```sh
# 1. Ver versão instalada
layer7d -V
# Deve mostrar: 1.7.7

# 2. Verificar se regras rdr foram geradas (requer force_dns: true na blacklist)
pfctl -s nat | grep force_dns
# Deve mostrar: rdr pass on em1.46 inet proto udp from 10.x.y.0/z to !127.0.0.1 port 53 -> 127.0.0.1 ...

# 3. Testar que DNS externo é redirecionado para o Unbound
# (num cliente no CIDR coberto pela blacklist)
nslookup xvideos.com 8.8.8.8
# Deve retornar resultado do Unbound local (sem resolver o domínio no 8.8.8.8)
```

---

### Quem é afectado

Todos os clientes com:
- Interface de captura VLAN (nome com ponto: `em1.46`, `igb0.100`, etc.)
- Blacklist configurada com `force_dns: true`

Se usas `force_dns: true` mas as regras `rdr` não aparecem em `pfctl -s nat | grep force_dns`, esta actualização resolve o problema.

---

### Instalação / Actualização

```sh
# Actualizar
fetch -o /tmp/pfSense-pkg-layer7-1.7.7.pkg \
  https://github.com/pablomichelin/Layer7/releases/download/v1.7.7/pfSense-pkg-layer7-1.7.7.pkg \
  && IGNORE_OSVERSION=yes pkg upgrade -y -f /tmp/pfSense-pkg-layer7-1.7.7.pkg \
  && service layer7d restart \
  && layer7d -V
```

Após actualizar, aplicar as regras PF em **Layer7 → Configurações → Guardar** para forçar regeneração das regras `rdr`.

---

### Rollback

```sh
fetch -o /tmp/pfSense-pkg-layer7-1.7.6.pkg \
  https://github.com/pablomichelin/Layer7/releases/download/v1.7.6/pfSense-pkg-layer7-1.7.6.pkg \
  && IGNORE_OSVERSION=yes pkg upgrade -y -f /tmp/pfSense-pkg-layer7-1.7.6.pkg \
  && service layer7d restart
```

---

### Compatibilidade

- pfSense CE 2.7.x / 2.8.x
- FreeBSD 14.x / 15.x
- Retrocompatível: instalações sem `force_dns` ou com interfaces não-VLAN não são afectadas
