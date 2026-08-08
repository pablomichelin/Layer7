# Logging Layer7

## Objectivo

Manter observabilidade útil sem permitir crescimento ilimitado no appliance.
A decisão canónica está no
[ADR-0015](../03-adr/ADR-0015-logging-local-limitado-e-separado.md).

## Destinos locais

| Destino | Conteúdo | Default |
|---------|----------|---------|
| `/var/log/layer7d.log` | ciclo de vida, configuração, licença, captura, erros e avisos | sempre activo |
| `/var/log/layer7-events.log` | tráfego detalhado e auditoria de bloqueios | detalhe OFF; bloqueios sempre auditados |
| `/usr/local/etc/layer7/reports/reports.db` | histórico pesquisável derivado dos eventos | máximo 100 MiB |

Os dois logs de texto são gravados directamente pelo daemon, modo `0600`.
Cada ficheiro activo tem, por defeito, 5 MiB e três cópias numeradas
(`.1` a `.3`). Portanto, o limite nominal combinado é 40 MiB:
`2 destinos × 5 MiB × (1 activo + 3 cópias)`.

Os campos `layer7.log_file_max_mb` (`1–100`) e `layer7.log_file_keep`
(`1–10`) alteram estes limites. A rotação é interna, determinística e não
depende de `newsyslog`.

## Perfil leve por defeito

- `reports.event_log_enabled=false`: não grava cada consulta/fluxo.
- bloqueios efectivos continuam em `layer7-events.log` e no syslog local;
- mensagens repetitivas de idle, recheck de licença sem mudança e stats
  periódicas ficam em `debug`;
- stats JSON são actualizadas a cada minuto, mas o resumo operacional normal
  aparece no máximo uma vez por hora;
- falhas esperadas ao limpar tabelas PF opcionais são agregadas em `debug`.
- quando um domínio é entregue pelo DNS sinkhole ao portal local, a decisão
  DNS fica auditável como `enforce_block outcome=sinkhole`; os fluxos
  seguintes para o IP local do firewall não voltam ao motor de política e só
  aparecem em `debug`. Isto evita falso `flow_decide` (por exemplo, host DoH
  com app SSH) e tempestade de logs.

Ao activar o detalhe, `reports.event_interfaces` limita as interfaces
guardadas; lista vazia significa todas. O detalhe pode crescer rapidamente e
deve ser usado com janela curta.

## Níveis operacionais

| Valor | O que passa em `/var/log/layer7d.log` |
|-------|----------------------------------------|
| `error` | erros graves |
| `warn` | erros e avisos |
| `info` | acima + mudanças de estado e resumos horários |
| `debug` | diagnóstico repetitivo |

`debug_minutes` força `debug` temporariamente após reload. Não altera os
limites físicos.

## Relatórios, retenção e limpeza

O colector lê primeiro a cauda legada de `layer7d.log` e depois
`layer7-events.log`. Se ocorrer rotação entre recolhas, localiza o inode nas
cópias `.1` a `.10`, consome o restante e termina no ficheiro activo.

- retenção por idade: default de 7 dias para eventos detalhados;
- limite físico do SQLite: `reports.event_max_mb`, default 100 MiB,
  intervalo `25–1000`;
- **Limpar histórico** remove eventos do SQLite e avança os cursores;
- **Limpar visualização** na página Eventos só limpa o buffer do browser;
- logs de texto não são apagados pela GUI; expiram pela rotação limitada.

## Syslog

Mensagens operacionais continuam no `LOG_DAEMON`. Bloqueios auditáveis também
vão ao syslog local. Com `syslog_remote=true`, mensagens operacionais e
eventos guardados são enviados por UDP RFC 3164 ao colector configurado.
O transporte remoto é best-effort e não substitui uma trilha SIEM confiável.

## Operação segura

```sh
ls -lh /var/log/layer7d.log* /var/log/layer7-events.log* 2>/dev/null
du -h /usr/local/etc/layer7/reports/reports.db 2>/dev/null
tail -n 100 /var/log/layer7d.log
tail -n 100 /var/log/layer7-events.log
```

Não usar `rm` nos logs durante operação normal. Para reduzir volume:

1. desligar **Log detalhado** em Settings > Relatórios;
2. restringir interfaces;
3. reduzir MiB/cópias e janela do SQLite;
4. guardar e confirmar reload do daemon.

## Limites conhecidos e evolução

O bloco `_26` é contenção L1: separação, menos ruído, rotação, limite do
SQLite e transparência de uso. Filtros combinados avançados, exclusão
selectiva, pesquisa além da cauda e exportação de auditoria pertencem ao
bloco L2/L3 futuro (F7) e não são declarados concluídos aqui.
