# Harness — hang na activação MITM (seguro)

Reproduz o padrão de bloqueio observado em B+D (`20260809T185035Z`) **sem
escrever na `.254`**, e prova o padrão **corrigido** (`1.9.43+`).

## Hipótese sob teste (legado — ainda válido como regressão)

`layer7_mitm_sync_helper()` em `1.9.42` (fora de `LAYER7_TEST_ROOT`) fazia:

```php
@exec("/usr/sbin/service layer7-tlsproxy onerestart 2>/dev/null");
```

sem timeout. Se `service … onerestart` não retorna, o PHP activador fica
pendurado **antes** de `echo sync=` e **antes** de `filter_configure()`.

Isto alinha com a evidência: ecos até `effective_pre_sync=yes`, ausência de
`sync=` / `filter_configure=done`, ~152 s até SIGTERM.

## Correcção (`1.9.43`)

```php
layer7_exec_timeout("/usr/sbin/service layer7-tlsproxy onerestart",
    L7_CTRL_TIMEOUT_SERVICE);
```

Timeout finito, erro explícito, limpeza idempotente (`layer7_mitm_ctrl_cleanup`).
Prova: `run-local-timeout-fix.sh`.

**ETAPA 2 (D0 F1-bis):** o wrapper usa `timeout -k <L7_CTRL_TIMEOUT_KILL_GRACE>`
para `SIGKILL` se o filho ignorar `SIGTERM` (hang `sync_sec=141` com timeout 20).
Teste: `tests/functional/test_ctrl_exec_timeout.php` (filho `trap '' TERM`).

## O que este harness NÃO faz

- Não activa MITM na `.254`
- Não altera PF, rotas, licença, `.234`/`.235`
- Não chama `filter_configure` no appliance de produção
- Não substitui o pacote instalado
- **Não** usa `--ignore-certificate-errors`, `curl -k` nem `CERT_NONE` nos gates TLS  
  (ver [`docs/09-blocking/politica-tls-sem-bypass.md`](../../../docs/09-blocking/politica-tls-sem-bypass.md))

## Componentes

| Script | Alvo | Função |
|--------|------|--------|
| `run-local-hang.sh` | Mac/Linux local | Mock `service` dorme; prova bloqueio do `exec` legado |
| `run-local-timeout-fix.sh` | local | Mock dorme; prova `layer7_exec_timeout` regressa |
| `run-local-tls-ca-peer.sh` | local + openssl | CA como peer TLS (KU certSign/CRLSign) — bug class B+D |
| `run-local-tls-leaf-fix.sh` | local + cc | Gate D1: CA→leaf `serverAuth` + block page |
| `compare-ca-fingerprints.sh` | ficheiros / RO | A=CA tlsproxy · B=Root `.24` · C=issuer leaf |
| `inspect-leaf-chain.sh` | local+builder | Leaf/chain: issuer SAN EKU KU BC clock algo serial chain cache |
| `inspect-leaf-chain.sh` | local+builder | Leaf/chain: issuer SAN EKU KU BC clock algo serial chain cache |
| `ro-check-254.sh` | `.254` **só leitura** | Snapshot passivo (MITM/rdr/:8443/GUI) |
| `run-lab54-optional.sh` | lab `.54` | Opcional: hang mock no Ubuntu isolado |

## Uso rápido

```bash
# 1) Hang legado (default: mock dorme 8s; wrapper mata a 5s → PASS se ainda vivo)
sh tests/harness/mitm-activate-hang/run-local-hang.sh

# 1b) Padrao corrigido (timeout 2s → sync=fail_cleaned, sem SIGTERM externo)
sh tests/harness/mitm-activate-hang/run-local-timeout-fix.sh

# 2) TLS CA-as-peer (bug class)
sh tests/harness/mitm-activate-hang/run-local-tls-ca-peer.sh

# 2b) TLS leaf fix (D1)
sh tests/harness/mitm-activate-hang/run-local-tls-leaf-fix.sh

# 3) RO na .254 (exige SSH chave; zero mutação)
sh tests/harness/mitm-activate-hang/ro-check-254.sh
```

Variáveis úteis:

- `HANG_SLEEP=8` — segundos que o mock `onerestart` dorme
- `HANG_TIMEOUT=5` — segundos até o harness matar o PHP legado
- `CTRL_TIMEOUT=2` — timeout do padrão corrigido
- `SSH_254=root@192.168.100.254` — alvo RO

## Critérios PASS

**Hang (legado):** o processo PHP que chama `exec("service … onerestart")`
ainda está vivo após `HANG_TIMEOUT` e só termina após kill.

**Fix:** `run-local-timeout-fix.sh` imprime `PASS_FIXED=yes`,
`timed_out=yes`, `sync=fail_cleaned` e exit 0 sem kill externo.

**TLS:** peer com `CA:TRUE` e KU `Certificate Sign, CRL Sign`.

**RO `.254`:** inventário; exit 0.
