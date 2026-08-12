# Marcação por cliente (atribuição) — passo `30.17`

**Estado:** **FECHADO** após gate-control (`20260812T024419Z`) — código em
`7f30b56`; fecho documental só após bateria
[`../tests/evidence/20260812T024408Z-30.17-gate-control/`](../tests/evidence/20260812T024408Z-30.17-gate-control/)  
**Plano:** [`../02-roadmap/plano-antipirataria-anti-tamper.md`](../02-roadmap/plano-antipirataria-anti-tamper.md)  
**Gates:** GA6.3 · GA6.4 (**PASS** pós-bateria)  
**RR:** RR-2 (redistribuição a partir de appliance licenciado)  
**Código:** `update-blacklists.sh` (cliente) — **sem** license-server / CDN / telemetria

---

## Objectivo / impacto / risco / teste / rollback

| Campo | Valor |
|-------|--------|
| Objectivo | Tornar conteúdo local atribuível à licença/appliance de origem (RR-2) |
| Impacto | Sidecar local após update autenticado; não altera tar/manifesto assinados |
| Risco | Médio (privacidade/percepção) — mitigado por marca opaca e sem PII cleartext |
| Teste | Unit mark + `--stamp-attribution` + regressão content-subscription |
| Rollback | Remover sidecars / reverter commit / `.pkg` anterior |

---

## Desenho (caminho seguro)

1. Após verify do token de subscrição e **promote** (ou `--stamp-attribution`), o cliente grava:
   - `blacklists/.state/content-attribution.json` (0600)
   - `blacklists/.l7-content-attribution` (viaja com cópia da árvore)
   - cópia em `.last-known-good/` e `.cache/<snapshot>/` quando existirem
2. **Não** modifica `domains`, tar.gz, manifesto nem `.sig` (ADR-0005 intacto).
3. **Não** contacta rede para reportar a marca (**GA6.4**).
4. Lookup de atribuição é **offline** no operador Systemup: `license_id` → registo
   interno do license-server (DB já existente; este passo **não** altera o servidor).

### Algoritmo

```text
mark = SHA256_hex("l7-attr-v1:" || license_id || ":" || hardware_id)
```

Campos persistidos: `v`, `scheme`, `mark`, `license_id`, `hardware_id`,
`snapshot_id`, `marked_at_utc`, `policy=local_only_no_telemetry`.

---

## Avaliação de privacidade (GA6.3)

| Dado | Incluído? | Justificação |
|------|-----------|--------------|
| Nome / email do cliente | **Não** | PII; proibido na marca |
| `customer` do `.lic` | **Não** | Cleartext comercial |
| `license_id` (inteiro) | **Sim** | Identificador opaco interno; necessário para lookup |
| `hardware_id` (SHA256 fingerprint) | **Sim** | Já no token assinado; não é nome civil |
| Telemetria / phone-home | **Não** | GA6.4; política do produto |
| Domínios / tráfego | **Não** | Fora de escopo |

**Minimização:** só IDs já presentes no token verificado localmente.  
**Finalidade:** atribuição comercial/forense offline (EULA / RR-2), não profiling.  
**Retenção:** ficheiros locais no appliance; removíveis com uninstall / limpeza
manual das blacklists.  
**Direitos:** operador pode apagar os sidecars sem afectar enforce (R-D).

---

## Limites honestos (R-A / RR-2)

- Root pode apagar ou forjar o sidecar — a marca **encarece e atribui cópias
  ingénuas**, não prova forense absoluta.
- Quem redistribuir **só** ficheiros `domains` sem a árvore/sidecar perde a marca
  (residual aceite; resposta complementar = via contratual AP4 / EULA).
- Não impede redistribuição técnica (ADR-0031 / RR-2).

---

## Operação

```sh
# Re-stamp local (sem rede), token válido:
/usr/local/etc/layer7/update-blacklists.sh --stamp-attribution

# Inspect:
cat /usr/local/etc/layer7/blacklists/.l7-content-attribution
```

---

## Fora de escopo deste passo

`30.18` (assinatura de release) · license-server deploy · GitHub Release ·
`.254` · MITM · IPv6 · ofuscação · telemetria
