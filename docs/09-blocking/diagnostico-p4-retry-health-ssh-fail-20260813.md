# Diagnóstico — P4 retry `health_ssh_fail` (`170000Z`)

**Estado:** causa-raiz **fechada** (ops / orquestrador). **Não** é falha do motor TLS.  
**Não autoriza** activar MITM nem novo soak.  
**Evidência soak:** [`../tests/evidence/20260813T170000Z-p4-retry-254/`](../tests/evidence/20260813T170000Z-p4-retry-254/)  
**Verify MITM OFF:** [`../tests/evidence/20260813T223009Z-p4-postfail-verify-254/`](../tests/evidence/20260813T223009Z-p4-postfail-verify-254/)  
**Harness:** [`../../tests/harness/mitm-p4-soak/`](../../tests/harness/mitm-p4-soak/)

---

## Declaração

| Campo | Valor |
|-------|--------|
| **Objectivo** | Explicar o abort sample=14 e impedir o mesmo falso `AUTH_FAIL no_key` no próximo soak |
| **Impacto** | Scripts de lab (Mac); **zero** runtime no `.pkg`; MITM permanece OFF |
| **Risco** | Baixo |
| **Teste** | `sh tests/harness/mitm-p4-soak/run-local-auth-fix.sh` |
| **Rollback** | Reverter o harness; evidência `170000Z` intacta |

---

## Linha temporal

| UTC | Evento |
|-----|--------|
| 17:05:18 | 1.º loop: probe SSH falhou → `DEADLINE_INVALID` → rollback `.254` **não correu** (`rb254_exit=9`) |
| 17:06:01 | 2.º loop: `health_1 FAIL` (mesmo `AUTH_FAIL no_key`) |
| 17:06:51 | 3.º loop: health 2–13 **OK** (`ssh_key_batchmode_254`, tries=1, intervalo 900 s) |
| 19:56:02 | health 13 OK |
| 20:11:42 | health 14 FAIL ×3 → ABORT |
| 20:11:42 | Rollback: 1.º SSH `.254` **AUTH_FAIL** (MITM **não** desligado) |
| 20:12:00 | Pós-estado: **o mesmo** BatchMode `-T` **funcionou**; `mitm_effective=true` |
| 22:30:09 | P4.1 cron + deadline → MITM OFF verificado |

Conclusão: a chave SSH **existia**. O helper mentiu «não há chave».

---

## Causa-raiz

`p4_ssh_254` fazia um **probe sem `-T`** (`ssh … "true"`) e, se falhasse, emitia:

```text
AUTH_FAIL no_key_no_SSHPASS_no_passfile
```

sem tentar a sessão real (`ssh -T`, a mesma que o health usa).

No pfSense, sessão **com TTY** pode cair no menu de texto (opção 8) e o `true` não corre até ao `ConnectTimeout=8`. A sessão **sem TTY** (`-T` / `RequestTTY=no`) entrega shell directo.

Prova no próprio abort: ~18 s depois do `AUTH_FAIL`, o pós-estado autenticou com `auth_method=ssh_key_batchmode_254`.

Efeito em cadeia:

1. Health aborta o soak (falso positivo).
2. Rollback usa o mesmo probe → 1.º SSH falha → `layer7_mitm_failsafe_rollback` **não corre**.
3. MITM fica ON até o cron P4.1 / deadline.

**Não** foi MaxStartups permanente, nem perda do ficheiro de senha, nem falha de rdr/tlsproxy (health 13: `effective=true`, `LISTEN=1`, escopo lab).

---

## Correcção (P4.2)

Harness canónico em `tests/harness/mitm-p4-soak/` (já **não** viver só na pasta de evidência):

1. Probe **com** `-T` e `RequestTTY=no` (iguais à sessão real); stdin `/dev/null`.
2. Mensagens: `ssh_transient` / `ssh_probe_failed` / `publickey_denied` — **proibido** `no_key` quando a chave pode existir.
3. Retry do probe com backoff antes de desistir.
4. Rollback `.254` retenta o SSH (para o failsafe correr de facto).
5. Health: mais retries; um sample SSH falhado **não** aborta até N consecutivos.

Este bloco **não** activa MITM. Novo soak continua a exigir **GO lab**.
