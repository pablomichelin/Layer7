# Runbook — activação MITM produção (pós-`1.9.42`)

**Estado:** **etapa passiva FEITA** em `.254` (`1.9.42`, smoke PASS `20260809T175111Z`); activação MITM/rdr **ainda pendente** de GO + destino dedicado.  
**Pré-requisito pacote:** `1.9.42`  
`SHA256=6bd6ba374b398ec82cd43ea2246f16a3774f4377d3cac6411265472d3d3a4c4b`  
**Cliente de teste único:** `192.168.100.24/32` (VM Windows descartável)  
**Proibido:** `.234` / `.235` como clientes; senha Windows neste documento.  
**Evidência passiva:** [`../tests/evidence/20260809T175111Z-1.9.42-passive-254/`](../tests/evidence/20260809T175111Z-1.9.42-passive-254/)

---

## Objectivo

Activar intercept MITM **temporário e escopado** na `.254` só para origem `.24/32` e **um destino de teste dedicado**.

## Impacto

- Upgrade pacote `1.9.38`→`1.9.42` (ou `1.9.41`→`1.9.42`) se ainda não instalado  
- PF rdr selectivo nas ifaces Layer7 (`vmx0`, `vmx0.95`)  
- Helper `layer7-tlsproxy` em loopback `:8443` quando `mitm_effective`

## Riscos

- CA de teste no cliente `.24`  
- Destino demasiado amplo → ampliar blast radius (usar host/CIDR mínimo)  
- Esquecer fail-safe / rollback

## Abort criteria (parar já)

1. GUI **9999** inacessível  
2. Rdr sem `<layer7_mitm_src>` ou com `from any`  
3. Tráfego de origem ≠ `.24` a ser redireccionado  
4. Smoke OFF falha após disable  
5. Disco/serviço layer7d degradado

---

## Prechecks (read-only)

```sh
pkg query '%v' pfSense-pkg-layer7
# esperado após upgrade: 1.9.42
php -r '$c=parse_xml_config("/cf/conf/config.xml","pfsense"); echo ($c["system"]["webgui"]["port"]??"")."\n";'
# esperado: 9999
sockstat -l4 | egrep ':9999|:8443'
pfctl -s nat | egrep 'mitm|8443' || true
df -h /
```

Stage rollback local **antes** de qualquer escrita:

```sh
# ter disponível em /tmp:
# pfSense-pkg-layer7-1.9.38.pkg (baseline actual .254)
# pfSense-pkg-layer7-1.9.41.pkg (rollback lab)
# pfSense-pkg-layer7-1.9.42.pkg + .sha256
```

---

## Sequência prevista (só após GO)

### 1) Backup

```sh
cp -a /cf/conf/config.xml "/tmp/config.xml.bak-pre-mitm-19242-$(date -u +%Y%m%dT%H%M%SZ)"
cp -a /usr/local/etc/layer7.json "/tmp/layer7.json.bak-pre-mitm-19242-$(date -u +%Y%m%dT%H%M%SZ)" 2>/dev/null || true
```

### 2) Upgrade passivo (MITM OFF)

```sh
fetch -o /tmp/pfSense-pkg-layer7-1.9.42.pkg \
  https://github.com/pablomichelin/Layer7/releases/download/v1.9.42/pfSense-pkg-layer7-1.9.42.pkg
fetch -o /tmp/pfSense-pkg-layer7-1.9.42.pkg.sha256 \
  https://github.com/pablomichelin/Layer7/releases/download/v1.9.42/pfSense-pkg-layer7-1.9.42.pkg.sha256
sha256 -q /tmp/pfSense-pkg-layer7-1.9.42.pkg
# deve = 6bd6ba374b398ec82cd43ea2246f16a3774f4377d3cac6411265472d3d3a4c4b
IGNORE_OSVERSION=yes pkg add -f /tmp/pfSense-pkg-layer7-1.9.42.pkg
service layer7d onestatus
# confirmar mitm.enabled=false / source_cidr vazio / sem :8443
```

### 3) Smoke OFF (antes)

Confirmar: sem gate `/var/run/layer7/tlsproxy.product`, sem `layer7_mitm_*` com membros, GUI 9999 OK.

### 4) Activação escopada (janela curta)

Na GUI MITM **ou** JSON equivalente:

| Campo | Valor |
|-------|--------|
| CA | gerar/importar (lab) — chave só no appliance |
| `mitm.enabled` | true (intenção) |
| `intercept.source_cidr` | `192.168.100.24/32` **apenas** |
| `intercept.dest_cidr` | **um** destino de teste dedicado (IP/host IPv4) |
| `intercept.block_sni` | SNI do destino de teste |
| `quic_mode` | `bypass` |

Validar:

```sh
pfctl -s nat | egrep 'layer7_mitm|8443'
pfctl -t layer7_mitm_src -T show
pfctl -t layer7_mitm_dst -T show
# src deve mostrar só 192.168.100.24/32
# regra: from <layer7_mitm_src> to <layer7_mitm_dst> — NUNCA from any
```

### 5) Fail-safe temporizado

Antes do teste, preparar job **at**/cron de emergência (exemplo 15 min):

```sh
# desactivar intenção + reload (ajustar ao procedimento GUI/CLI do lab)
# e/ou: service layer7-tlsproxy onestop; limpar source/dest; filter_configure
```

### 6) Teste só em `.24`

- Instalar CA no Root store **só** na VM `.24`  
- Browser → destino de teste → página HTTPS esperada  
- Confirmar que outro host da LAN **não** é redireccionado

### 7) Smoke OFF (depois) + limpeza

```sh
# mitm.enabled=false; source_cidr/dest_cidr vazios; remover CA lab se efémera
service layer7-tlsproxy onestatus || true
pfctl -t layer7_mitm_src -T show; pfctl -t layer7_mitm_dst -T show
# GUI 9999 OK
```

---

## Rollback

**Config:** `mitm.enabled=false` + listas vazias + `filter_configure` + stop tlsproxy.  
**Pacote:**

```sh
# preferir baseline produção observada:
IGNORE_OSVERSION=yes pkg add -f /tmp/pfSense-pkg-layer7-1.9.38.pkg
# ou lab anterior:
IGNORE_OSVERSION=yes pkg add -f /tmp/pfSense-pkg-layer7-1.9.41.pkg
```

---

## Veredicto

| Item | Estado |
|------|--------|
| Pacote `1.9.42` com source scope | **GO** (publicado) |
| Instalação passiva `.254` + smoke OFF | **PASS** (`20260809T175111Z`) — MITM não activado |
| Activação MITM / rdr / CA em `.24` | **NO-GO** até GO humano + destino `/32` dedicado |
| Destino HTTPS | **pendente escolha** — ver evidência `16-TARGET-RECOMMENDATION.md` |
