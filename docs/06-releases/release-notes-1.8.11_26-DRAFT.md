# Release Notes — `pfSense-pkg-layer7` `1.8.11_26` (DRAFT)

**Estado:** candidato interno, não publicado, não aprovado para produção.

## Objectivo

Conter o crescimento de logs e tornar o comportamento previsível antes de
activar o Layer7. A release pública continua `1.8.11_24`.

## Entrega

- operação e tráfego em ficheiros separados;
- 5 MiB + três cópias por destino, configuráveis;
- detalhe desligado por default, com bloqueios sempre auditados;
- menos mensagens periódicas sem mudança;
- ingestão segura através de rotação;
- SQLite limitado a 100 MiB e retenção detalhada de 7 dias;
- GUI distingue fonte, consumo, limpar vista e limpar histórico.

Não inclui pesquisa ilimitada, filtros combinados avançados, exclusão
selectiva ou compressão. Esses itens ficam para L2/L3 em F7.

## Gates

1. `sh tests/run-local.sh`;
2. lint PHP e `test_logging_reports.php` no FreeBSD;
3. `make package` no builder FreeBSD 15;
4. instalar primeiro com `enabled=false` e `mode=monitor`;
5. observar ausência de idle/recheck repetitivo em `info`;
6. activar detalhe por pouco tempo numa interface e confirmar fonte/eventos;
7. confirmar rotação/limites e bloqueios auditados;
8. executar gates de captura e two-client já pendentes do `_25`.

## Risco e rollback

Risco principal: janela rotativa apagar detalhe antigo conforme configurado.
Exportar para colector remoto quando a retenção local não for suficiente.
Rollback: voltar ao modo passivo, reinstalar `_24`, restaurar configuração e
confirmar ausência de regras Layer7 `block`. Não publicar nem instalar no
appliance de produção antes da validação humana.
