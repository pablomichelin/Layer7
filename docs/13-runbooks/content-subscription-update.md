# Runbook — Subscrição de conteúdo / update de blacklists (passo 30.10)

**Versão do mecanismo:** a partir de `1.9.53` (token); fix redirects em **`1.9.54`**  
**Gate:** GA4.4–4.7/4.9 **PASS** (local + e2e campo `1.9.54`)  
**Contrato:** [`../01-architecture/contrato-token-subscricao-conteudo-30.8.md`](../01-architecture/contrato-token-subscricao-conteudo-30.8.md)  
**Estado persistente:** `/var/db/layer7/content-subscription.json` (modo `0600`)  
**Validade nominal:** 30 dias · skew ±1 dia  
**Campo:** e2e PASS —
[`../tests/evidence/20260811T114320Z-30.10-e2e-154-254/`](../tests/evidence/20260811T114320Z-30.10-e2e-154-254/);
produção observada **`1.9.54`**. Primary CDN `downloads.systemup.inf.br`:
DNS/público **PASS**; smoke autenticado campo **PASS**
(`20260812T003214Z` — manifesto/`.sig` **200/200**; sem token **401**).
O cut `30.11` **já foi executado**: a tag `blacklists-ut1-current` está
vazia; GET anónimo nos quatro URLs GitHub devolve **404 esperado**
(P3-9 opção A). Não é incidente. Primary **exige token** (401 sem token).
Isto **não** é o canal do pacote nem motivo para reupload GA4.11. O
espelho no cliente é **legado / fallback de runtime** e **não** se
remove neste bloco. hold-active / LKG / enforce intactos. Nota:
[`../09-blocking/nota-404-esperado-cut-30.11.md`](../09-blocking/nota-404-esperado-cut-30.11.md).
Prep: [`../09-blocking/prep-cut-30.11-espelho.md`](../09-blocking/prep-cut-30.11-espelho.md).

---

## O que faz

1. No check-in **activo**, o `layer7d` grava o envelope
   `content_subscription` emitido pelo license server (passo 30.9).
2. `update-blacklists.sh` **só** contacta URLs de conteúdo **corrente** se o
   token local for válido (assinatura Ed25519 da pubkey de licença, `scope=content`,
   `hardware_id` local, janela `iat`/`exp` ± skew).
3. Apresenta `Authorization: Bearer <base64url(envelope)>` (e header fallback
   `X-Layer7-Content-Token`) no host actual; em redirect HTTPS cross-host as
   credenciais são omitidas (máx. 5 hops; Location não-HTTPS recusada).
4. A verificação do **manifesto** assinado (ADR-0005) continua obrigatória após
   o download — entitlement ≠ integridade.

**Não faz:** apagar blacklists locais; reduzir enforce; kill-switch; fail-closed
por rede (R-C, R-D, R-E).

---

## Sintomas

| Sintoma | Significado típico |
|---------|-------------------|
| GUI Blacklists → Subscrição **Ausente** / **Expirada** / **Inválida** | Sem token utilizável para update corrente |
| Log `/var/log/layer7-bl-update.log`: `content subscription not valid` | Update abortado **antes** do fetch |
| Log: `content subscription token OK` + `authenticated fetch failed (HTTP 302)` | Em **`1.9.53`**: bug conhecido. Em **`≥1.9.54`**: investigar Location/cadeia ou CDN |
| Log: primary DNS fail + mirror `validated snapshot` / `update complete` | Esperado enquanto CDN primary sem DNS — caminho mirror OK |
| Snapshot activa / LKG inalterados após falha | Comportamento correcto (hold-active) |
| Enforce / modo Layer7 inalterado | Esperado (token ≠ licença de runtime) |

---

## Recuperação (erro honesto — R-J / N6)

Executável pelo operador **sem** contactar suporte:

1. Confirmar conectividade / DNS ao license server.
2. Forçar check-in (licença activa):
   ```sh
   layer7d --check-in
   ls -l /var/db/layer7/content-subscription.json
   # esperado: mode 0600, ficheiro presente
   ```
3. Validar token local (sem rede):
   ```sh
   /usr/local/etc/layer7/update-blacklists.sh --check-subscription
   # esperado: content_subscription=ok
   ```
4. Correr update:
   ```sh
   /usr/local/etc/layer7/update-blacklists.sh --download
   ```
5. (30.17) Confirmar marca local de atribuição (sem telemetria):
   ```sh
   cat /usr/local/etc/layer7/blacklists/.l7-content-attribution
   # ou re-stamp: update-blacklists.sh --stamp-attribution
   ```
6. GUI: Blacklists → **Subscrição de conteúdo: OK**.

Se o relógio do appliance estiver errado (>1 dia fora da janela), corrigir NTP
primeiro (ver também runbook anti-rollback).

---

## Limites honestos

| Limite | Declaração |
|--------|------------|
| **R-A** | Root no appliance pode contornar verificação local (substituir script, copiar token, etc.). |
| **RR-1** | Enquanto o espelho anónimo de conteúdo corrente existir (`pré-30.11`), o token **não** fecha A-06 para quem bypassa o cliente. |
| **RR-2** | Um appliance licenciado pode descarregar listas e redistribuí-las internamente — resposta é atribuição (`30.17` sidecar opaco; ver `marcacao-cliente-30.17.md`) + contratual, não bloqueio técnico aqui. |
| **D10** | Falha de token **nunca** reduz enforce. |

---

## Rollback de pacote

Instalar `.pkg` anterior (lab: `1.9.53`) mantém o mecanismo de token; para
cliente **sem** exigir token no update, usar `1.9.52` ou anterior. O ficheiro
`content-subscription.json` pode permanecer; versões antigas ignoram-no.

Pacotes anteriores **não** estão no canal público (BG-164). Rollback
público = reinstalar `1.9.72`. Artefacto antigo só no builder/arquivo.

Produção observada pós-e2e: `1.9.54`. Comandos completos:
`docs/10-license-server/MANUAL-INSTALL.md`.

---

## Referências

- ADR-0031, ADR-0005  
- Gates GA4 em [`../09-blocking/plano-gates-antipirataria.md`](../09-blocking/plano-gates-antipirataria.md)  
- Testes: `tests/functional/test_content_subscription_client.php`,
  `tests/functional/test_content_subscription_update.sh`
- Evidência e2e: [`../tests/evidence/20260811T114320Z-30.10-e2e-154-254/`](../tests/evidence/20260811T114320Z-30.10-e2e-154-254/)
