# ADR-0015 — Logging local limitado e separado

- **Estado:** Aceito
- **Data:** 2026-07-29
- **Fase:** F4.1, contenção operacional; evolução analítica permanece F7
- **Backlog:** BG-054

## Contexto

O appliance de produção observado em modo desactivado mantinha
`/var/log/layer7d.log` sem rotação. Em cerca de dois meses, 4.663 linhas
incluíam 2.490 mensagens repetitivas de idle e 1.656 rechecks de licença.
Quando o detalhe era ligado, cada consulta DNS entrava no mesmo log em
`info`. O SQLite tinha retenção por idade, mas o texto bruto era ilimitado e
a GUI não distinguia limpar a vista, a base e os ficheiros.

## Decisão

1. Separar operação (`layer7d.log`) de tráfego (`layer7-events.log`).
2. Fazer rotação interna antes do append, com tamanho e número de cópias
   limitados (`5 MiB`, três cópias por default).
3. Manter detalhe de tráfego desligado por default; bloqueios são auditoria
   essencial e continuam guardados mesmo assim.
4. Reduzir mensagens periódicas sem mudança para `debug` e agregar avisos PF
   esperados.
5. Fazer o colector atravessar rotações por inode e limitar também o SQLite
   (`100 MiB` por default), além da retenção por idade.
6. Dizer explicitamente na GUI que limpar visualização, limpar SQLite e
   expirar ficheiros rotativos são operações distintas.

## Consequências

- O uso local torna-se previsível mesmo se o operador esquecer o detalhe
  ligado.
- Com detalhe activo, a ingestão recebe decisão e confirmação PF sem contar
  duas vezes o KPI; com detalhe desligado, o histórico executivo leve
  continua pelas stats e os bloqueios permanecem na trilha rotativa.
- Configurações antigas que já tenham `event_log_enabled=true` preservam a
  escolha; campo ausente migra para o default leve `false`.
- As cópias não são comprimidas neste bloco. A previsibilidade vem do limite
  rígido; compressão e pesquisa profunda ficam para F7.
- Syslog UDP continua best-effort.

## Risco e mitigação

- **Perda de detalhe antigo:** inerente à janela limitada; ajustar limites ou
  exportar para colector remoto.
- **Rotação durante ingestão:** cursor guarda inode+offset e o colector procura
  o inode nas cópias antes do activo.
- **Pressão no SQLite:** remove os eventos mais antigos até alvo de 85% e
  executa compactação.
- **Regressão de enforcement:** não altera decisão nem regras PF; gate exige
  monitor passivo antes de qualquer enforce.

## Teste

- `test_log_store.c`: rotação e limite de cópias;
- `test_logging_reports.php`: parser, sem dupla contagem e cursor através de
  rotação;
- `test_config_parse.c`: limites e perfil de detalhe;
- lint, suite local e build FreeBSD;
- gate no appliance: disabled/monitor sem ruído repetitivo, bloqueio auditado,
  detalhe opt-in e limites observáveis.

## Rollback

Reinstalar a release pública `_24`, manter `enabled=false`/`mode=monitor` e,
se necessário, restaurar o JSON anterior. Os novos ficheiros podem permanecer:
`_24` não depende deles. Não apagar logs nem SQLite antes de copiar evidência
necessária para diagnóstico.
