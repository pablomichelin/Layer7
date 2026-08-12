# Runbook — Reposição do espelho público de conteúdo corrente (GA4.11)

**Trilha:** Anti-pirataria / Anti-tamper · passo **`30.11`**  
**Gate:** GA4.11 — procedimento de reposição do espelho pronto e testável  
**Estado deste doc:** procedimento **documentado** (pré-cut); o cut do espelho
**não** está autorizado até GO explícito do gestor  
**Rollback comercial:** repor assets na release rolling GitHub — **não** exige
alterar o appliance `.254` nem o enforce

---

## Objectivo

Se a retirada/limitação do espelho anónimo `blacklists-ut1-current` causar
impacto indevido em clientes legítimos (ou se o primary autenticado falhar),
**repor** o conteúdo corrente no espelho público GitHub sem tocar no enforce.

## Pré-condições

1. Cópia íntegra do snapshot a repor (mesmo `snapshot_id` / checksums).
2. Acesso `gh` ao repo `pablomichelin/Layer7` (operador humano).
3. Primary `https://downloads.systemup.inf.br` continua a exigir token em
   `/layer7/...` (401 sem Bearer) — repor o espelho **não** desliga o primary.

## Fonte canónica do snapshot (lab)

No origin license-server (host de conteúdo):

`/opt/layer7-license/content/blacklists/ut1/current/`

Ficheiros mínimos:

| Ficheiro | Papel |
|----------|--------|
| `layer7-blacklists-manifest.v1.txt` | Manifesto |
| `layer7-blacklists-manifest.v1.txt.sig` | Assinatura Ed25519 (ADR-0005) |
| `layer7-blacklists-ut1.tar.gz` | Snapshot |
| `blacklists-signing-public-key.pem` | Pubkey de blacklists (pública) |

Checksums de referência observados em reconciliação local↔`.244`
(`2026-08-11`, snapshot `ut1-2026-04-25`):

| Ficheiro | bytes | cksum (BSD) |
|----------|------:|-------------|
| `layer7-blacklists-manifest.v1.txt` | 823 | `1058493783` |
| `layer7-blacklists-manifest.v1.txt.sig` | 64 | `3207485136` |
| `blacklists-signing-public-key.pem` | 113 | `2359272370` |
| `layer7-blacklists-ut1.tar.gz` | 31169229 | `2797006844` |

## Procedimento de reposição

Executar **só** com GO humano de rollback (distinto do GO de cut):

```sh
# No host com a cópia íntegra (ex.: origin) — NÃO commitar o tarball no git
cd /opt/layer7-license/content/blacklists/ut1/current

# Verificar integridade local antes do upload
cksum layer7-blacklists-manifest.v1.txt \
  layer7-blacklists-manifest.v1.txt.sig \
  blacklists-signing-public-key.pem \
  layer7-blacklists-ut1.tar.gz

# Upload com clobber na release rolling (prerelease)
gh release upload blacklists-ut1-current \
  layer7-blacklists-manifest.v1.txt \
  layer7-blacklists-manifest.v1.txt.sig \
  blacklists-signing-public-key.pem \
  layer7-blacklists-ut1.tar.gz \
  --repo pablomichelin/Layer7 \
  --clobber
```

Se a release tiver sido tornada draft/privada por engano, restaurar visibilidade
pública da tag `blacklists-ut1-current` **antes** do upload (painel GitHub ou
`gh release edit`).

## Teste mínimo pós-reposição

1. Anónimo (sem token):
   ```sh
   curl -sSIL -o /dev/null -w "%{http_code}\n" \
     "https://github.com/pablomichelin/Layer7/releases/download/blacklists-ut1-current/layer7-blacklists-manifest.v1.txt"
   # esperado: cadeia até 200 e tamanho ~823
   ```
2. Primary continua a exigir token:
   ```sh
   curl -sS -o /dev/null -w "%{http_code}\n" \
     "https://downloads.systemup.inf.br/layer7/blacklists/ut1/current/layer7-blacklists-manifest.v1.txt"
   # esperado: 401
   ```
3. Enforce / modo no appliance: **inalterados** (não é necessário reinstalar `.pkg`).

## O que este rollback **não** faz

- Não altera licenças, check-in nem enforce.
- Não apaga blacklists locais nos appliances.
- Não reverte o código do primary autenticado no license-server.
- Não é substituto de comunicação a clientes quando GA4.12 aplicável;
  no cut `30.11` actual GA4.12 está **N/A** (ver prep-cut §1).

## Ligação

- Plano: [`../02-roadmap/plano-antipirataria-anti-tamper.md`](../02-roadmap/plano-antipirataria-anti-tamper.md) § `30.11`
- Gates: [`../09-blocking/plano-gates-antipirataria.md`](../09-blocking/plano-gates-antipirataria.md) GA4.11
- Update com token: [`content-subscription-update.md`](content-subscription-update.md)
