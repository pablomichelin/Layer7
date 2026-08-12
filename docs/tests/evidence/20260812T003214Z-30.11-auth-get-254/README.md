# Evidência — GET autenticado primary `30.11` (`.254`)

**RUNID:** `20260812T003214Z`  
**Host:** `192.168.100.254` (produção Systemup)  
**Pacote:** `1.9.54`  
**Passo:** `30.11` preflight — smoke autenticado do primary  
**Veredicto:** **PASS**  
**Cut do espelho:** **não executado**

## Escopo

GET HTTPS (apenas) a:

- `https://downloads.systemup.inf.br/layer7/blacklists/ut1/current/layer7-blacklists-manifest.v1.txt`
- `…/layer7-blacklists-manifest.v1.txt.sig`

Bearer usado **só no appliance** (ficheiro local `0600`). Token **não**
impresso, exportado nem gravado na evidência.

## Proibido cumprido

- sem `update-blacklists` completo  
- sem alteração de serviços / Cloudflare / GitHub / DNS / `.254` config  
- sem commit/push neste bloco de teste  

## Resultados

| Pedido | HTTP | size |
|--------|------|------|
| manifesto + Bearer | **200** | **823** |
| `.sig` + Bearer | **200** | **64** |
| manifesto sem token (controlo) | **401** | 60 |

Corpo controlo (sem credencial): `content_subscription_required` / `missing`.  
Manifesto autenticado: `snapshot_id=ut1-2026-04-25`.

## Ficheiros

| Ficheiro | Conteúdo |
|----------|----------|
| `00-verdict.txt` | `VERDICT=PASS` + códigos |
| `00-meta.txt` | escopo / host |
| `01-auth-get.txt` | saída bruta (sem token) |

## Implicação para gates

| Gate | Nota |
|------|------|
| Primary autenticado (pré-cut) | **PASS** — esta evidência |
| GA4.10 (cut espelho anónimo) | **PENDENTE** — espelho GitHub ainda público |
| GA4.11 / GA4.12 / GA4.15 | docs/rascunho prontos; cut/emissão **não** feitos |
