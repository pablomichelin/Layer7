## Layer7 v1.7.2 — Bloqueio Total: DNS Forçado + SNI Blocking + Estatísticas

Pacote Layer 7 para pfSense CE com classificacao em tempo real via nDPI.

### Novidades (v1.7.2)

**Melhoria A — DNS Forçado via PF `rdr` (fecha brecha de DNS externo):**
- Quando uma regra blacklist tem `src_cidrs` e `force_dns: true`, o Layer7 cria automaticamente regras PF que redirecionam TODO o DNS (porta 53 UDP+TCP) desses CIDRs para o Unbound local
- Mesmo que o dispositivo tenha 8.8.8.8 hardcoded, a query DNS passa pelo Layer7
- Checkbox "Forçar DNS local para estes CIDRs" na GUI (activado por defeito em novas regras)
- Mecanismo duplo: `layer7-pfctl` escreve as regras no `pf.conf`; `nat_rules_needed` injected via `layer7.xml`

**Melhoria B — Bloqueio por TLS SNI (fecha brechas de DNS em cache e CDNs):**
- O daemon captura o SNI (Server Name Indication) do TLS ClientHello via nDPI
- Quando o browser já tem o IP em cache (sem nova query DNS), o SNI ainda é visível no TLS handshake
- Se o SNI casa com a blacklist → IP destino adicionado à tabela `layer7_bld_N` → conexão actual e futuras bloqueadas
- Cobre CDNs não listados na UT1 (ex: xvideos-cdn.com) com SNI visível

**Melhoria C — Estatísticas DNS vs SNI:**
- `cat /tmp/layer7-stats.json` agora mostra:
  - `"bl_dns_hits"`: bloqueios via DNS (resposta DNS observada)
  - `"bl_sni_hits"`: bloqueios via SNI (TLS handshake sem DNS)

### Instalacao (um comando)

```sh
fetch -o /tmp/install.sh https://raw.githubusercontent.com/pablomichelin/Layer7/main/install.sh && sh /tmp/install.sh
```

### Verificacao pos-instalacao

```sh
# Versao correcta
/usr/local/sbin/layer7d -V

# Stats com novos contadores
cat /tmp/layer7-stats.json | grep bl_

# Regras rdr geradas (se houver regras com force_dns)
pfctl -s nat | grep force_dns
```

### Rollback

```sh
pkg delete pfSense-pkg-layer7
```

### Compatibilidade

- pfSense CE 2.7.x / 2.8.x / 25.x
- FreeBSD 14 / 15
