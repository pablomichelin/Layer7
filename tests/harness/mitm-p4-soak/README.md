# Harness P4 soak — auth resiliente (P4.2)

Corrige o falso `AUTH_FAIL no_key_no_SSHPASS_no_passfile` do retry `170000Z`.

Diagnóstico: [`docs/09-blocking/diagnostico-p4-retry-health-ssh-fail-20260813.md`](../../../docs/09-blocking/diagnostico-p4-retry-health-ssh-fail-20260813.md)

## O que este harness NÃO faz

- Não activa MITM em `.254` / `.234` / `.235`
- Não altera PF, rotas, licença nem o pacote
- Não é GO de soak — o loop/rollback só correm com **GO lab** e `EV=` apontado a uma pasta de evidência nova

## Teste local (obrigatório neste bloco)

```sh
sh tests/harness/mitm-p4-soak/run-local-auth-fix.sh
sh tests/harness/mitm-p4-soak/p4-validate-local.sh
```

## Próximo soak (só com GO)

```sh
export EV="/caminho/docs/tests/evidence/<RUNID>-p4-retry-254"
export HARNESS="$PWD/tests/harness/mitm-p4-soak"
# NÃO ligar mitm.enabled aqui.
```

Estado local (não toca no appliance; PIDs em `07-soak-loop.pid` / `07-watchdog.pid`):

```sh
EV="docs/tests/evidence/<RUNID>" sh tests/harness/mitm-p4-soak/status-p4-soak.sh
```

Se loop/watchdog morrerem com MITM ainda ON: rollback imediato.

```sh
EV="docs/tests/evidence/<RUNID>" HARNESS="$PWD/tests/harness/mitm-p4-soak" bash tests/harness/mitm-p4-soak/p4-full-rollback.sh
```

Segredos: chave BatchMode, ou `SSHPASS` / ficheiro `0600` fora do git (`/tmp/l7-lab-254.pass`). **Nunca** `sshpass -p` literal.
