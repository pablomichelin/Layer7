# Runbook — Subscrição de conteúdo / update de blacklists (passo 30.10)

**Versão do mecanismo:** a partir de `1.9.53` (token); fix redirects em **`1.9.54`**  
**Gate:** GA4.5–4.7/4.9 **PASS** (local + parcial campo); **GA4.4 BLOCKED** —
aguarda e2e `.254` com `1.9.54`  
**Contrato:** [`../01-architecture/contrato-token-subscricao-conteudo-30.8.md`](../01-architecture/contrato-token-subscricao-conteudo-30.8.md)  
**Estado persistente:** `/var/db/layer7/content-subscription.json` (modo `0600`)  
**Validade nominal:** 30 dias · skew ±1 dia  
**Campo:** STOP em `1.9.53` por HTTP 302 —
[`../tests/evidence/20260811T110638Z-30.10-revalidate-254/`](../tests/evidence/20260811T110638Z-30.10-revalidate-254/);
produção observada `1.9.47`. Candidato **`1.9.54`** corrige `fetch_authed`
(seguir HTTPS 302 sem vazar Bearer cross-host). **Não** declarar GA4.4 PASS
sem nova janela `.254`.

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
| Log: `content subscription token OK` + `authenticated fetch failed (HTTP 302)` | Em **`1.9.53`**: bug conhecido (não seguia redirect). Em **`≥1.9.54`**: investigar Location/cadeia ou CDN |
| Snapshot activa / LKG inalterados após falha | Comportamento correcto (hold-active) |
| Enforce / modo Layer7 inalterado | Esperado (token ≠ licença de runtime) |

---

## Recuperação (erro honesto — R-J / N6)

Executável pelo operador **sem** contactar suporte:

1. Confirmar conectividade / DNS ao license server.
2. Forçar check-in (licença activa):
   ```sh
   # via GUI Definições (check-in periódico) ou, se disponível no lab:
   layer7d  # com check-in enabled — aguardar ciclo
   # confirmar ficheiro:
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
5. GUI: Blacklists → **Subscrição de conteúdo: OK**.

Se o relógio do appliance estiver errado (>1 dia fora da janela), corrigir NTP
primeiro (ver também runbook anti-rollback).

---

## Limites honestos

| Limite | Declaração |
|--------|------------|
| **R-A** | Root no appliance pode contornar verificação local (substituir script, copiar token, etc.). |
| **RR-1** | Enquanto o espelho anónimo de conteúdo corrente existir (`pré-30.11`), o token **não** fecha A-06 para quem bypassa o cliente. |
| **RR-2** | Um appliance licenciado pode descarregar listas e redistribuí-las internamente — resposta é atribuição/contratual (AP4), não bloqueio técnico aqui. |
| **D10** | Falha de token **nunca** reduz enforce. |

---

## Rollback de pacote

Instalar `.pkg` anterior (lab: `1.9.53`) mantém o mecanismo de token; para
cliente **sem** exigir token no update, usar `1.9.52` ou anterior. O ficheiro
`content-subscription.json` pode permanecer; versões antigas ignoram-no.

```sh
# exemplo — rollback lab a partir de 1.9.54 → 1.9.53
fetch -o /tmp/pfSense-pkg-layer7-1.9.53.pkg \
  https://github.com/pablomichelin/Layer7/releases/download/v1.9.53/pfSense-pkg-layer7-1.9.53.pkg
IGNORE_OSVERSION=yes pkg add -f /tmp/pfSense-pkg-layer7-1.9.53.pkg
```

Produção observada após STOP: `1.9.47`. Comandos completos:
`docs/10-license-server/MANUAL-INSTALL.md`.

---

## Referências

- ADR-0031, ADR-0005  
- Gates GA4 em [`../09-blocking/plano-gates-antipirataria.md`](../09-blocking/plano-gates-antipirataria.md)  
- Testes: `tests/functional/test_content_subscription_client.php`,
  `tests/functional/test_content_subscription_update.sh`
