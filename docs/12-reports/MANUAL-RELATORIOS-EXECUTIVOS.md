# Manual — Relatorios Executivos (v1.4.6+)

## Objetivo

Este manual descreve o uso do novo modulo de relatorios executivos do Layer7,
orientado a publico nao tecnico (diretoria e gestao).

## O que mudou na v1.4.3

- Base de dados local SQLite para historico detalhado de eventos.
- Ingestao incremental do log (`/var/log/layer7d.log`) com cursor.
- Filtros por periodo, dispositivo/IP, site e resultado.
- Resumo executivo automatico e exportacao HTML/CSV/JSON.
- Retencao configuravel com presets para controlar espaco em disco.

## Ajuste visual na v1.4.4

- Refino visual das telas para separar melhor quadros e acoes de guardar.
- Cada bloco de configuracao passou a ter delimitacao visual propria em telas criticas.
- Sem impacto funcional no motor de politicas, bloqueio, ingestao ou exportacao.

## Correcao operacional na v1.4.5

- A pagina de relatorios executa ingestao incremental ao abrir, reduzindo dependencia exclusiva do cron.
- Exportacoes (HTML/CSV/JSON) tambem disparam ingestao incremental antes da consulta.
- Parser de log agora aceita timestamp ISO e formato syslog, evitando perda de eventos por formato.

## Evolucao na v1.4.6

- Daemon registra eventos `dns_query` com `src`, `resolver` e `qname`.
- Relatorios passam a gravar consultas DNS e correlacionar bloqueios com o dominio tentado.
- Top sites e contagem de sites unicos passam a ignorar resolvedores publicos (ex.: `dns.google`).

## Onde configurar

No pfSense: **Services > Layer 7 > Definicoes > Relatorios**

- Activar recolha de dados para relatorios
- Retencao (7/15/30/60/90/180/365 dias ou custom)
- Intervalo de recolha (5/10/15/30/60 min)

## Onde consultar

No pfSense: **Services > Layer 7 > Relatorios**

Visoes principais:

1. Resumo executivo (frases prontas para apresentacao)
2. Indicadores principais (total, bloqueados, permitidos, indice de bloqueio)
3. Evolucao do periodo (timeline)
4. Dispositivos com maior incidencia
5. Sites mais tentados
6. Eventos detalhados (paginados)

## Filtros recomendados para diretoria

- Periodo: 7d ou 30d
- Resultado: `block`
- Dispositivo (IP): quando quiser investigar um caso especifico
- Site: quando houver pedido por dominio especifico

## Exportacao

- **HTML**: formato executivo para imprimir ou converter para PDF.
- **CSV**: trilha detalhada para auditoria.
- **JSON**: integracao com sistemas externos.

## Estrategia de retencao (espaco em disco)

Recomendacao inicial:

- Ambientes pequenos: 30 dias
- Ambientes medios: 60 dias
- Ambientes com alto volume: 15 ou 30 dias

O purge diario remove dados antigos da base e executa compactacao quando ha
remocao de registros.

## Ficheiros tecnicos

- `/usr/local/etc/layer7/reports/reports.db` — base SQLite
- `/usr/local/etc/layer7/reports/ingest.cursor` — offset de ingestao
- `/usr/local/etc/layer7/layer7-reports-collect.php` — colector incremental
- `/usr/local/etc/layer7/layer7-stats-collect.sh` — cron de recolha
- `/usr/local/etc/layer7/layer7-stats-purge.sh` — cron de limpeza

## Rollback rapido

Se houver qualquer problema no modulo executivo:

1. Desactivar recolha em **Definicoes > Relatorios**
2. Manter operacao normal de enforcement (nao e afectada)
3. Se necessario, remover apenas os artefactos de relatorio:
   - `rm -f /usr/local/etc/layer7/reports/reports.db`
   - `rm -f /usr/local/etc/layer7/reports/ingest.cursor`

O motor de politicas e bloqueio continua funcional sem dependencia do modulo de relatorios.
