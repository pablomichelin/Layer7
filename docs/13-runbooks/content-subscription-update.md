# Runbook — Subscrição de conteúdo / update de blacklists (passo 30.10)

**Versão do mecanismo:** a partir de `1.9.53`  
**Gate:** GA4.4–GA4.9  
**Contrato:** [`../01-architecture/contrato-token-subscricao-conteudo-30.8.md`](../01-architecture/contrato-token-subscricao-conteudo-30.8.md)  
**Estado persistente:** `/var/db/layer7/content-subscription.json` (modo `0600`)  
**Validade nominal:** 30 dias · skew ±1 dia

---

## O que faz

1. No check-in **activo**, o `layer7d` grava o envelope
   `content_subscription` emitido pelo license server (passo 30.9).
2. `update-blacklists.sh` **só** contacta URLs de conteúdo **corrente** se o
   token local for válido (assinatura Ed25519 da pubkey de licença, `scope=content`,
   `hardware_id` local, janela `iat`/`exp` ± skew).
3. Apresenta `Authorization: Bearer <base64url(envelope)>` (e header fallback
   `X-Layer7-Content-Token`).
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

Instalar `.pkg` anterior (lab: `1.9.52`) volta a actualizar **sem** exigir
token no cliente. O ficheiro `content-subscription.json` pode permanecer; versões
antigas ignoram-no.

```sh
# exemplo — rollback lab a partir de 1.9.53
fetch -o /tmp/pfSense-pkg-layer7-1.9.52.pkg \
  https://github.com/pablomichelin/Layer7/releases/download/v1.9.52/pfSense-pkg-layer7-1.9.52.pkg
IGNORE_OSVERSION=yes pkg add -f /tmp/pfSense-pkg-layer7-1.9.52.pkg
```

---

## Referências

- ADR-0031, ADR-0005  
- Gates GA4 em [`../09-blocking/plano-gates-antipirataria.md`](../09-blocking/plano-gates-antipirataria.md)  
- Testes: `tests/functional/test_content_subscription_client.php`,
  `tests/functional/test_content_subscription_update.sh`
