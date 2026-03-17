# Categorias (V1)

## Fonte de verdade

Nomes de categoria vêm do **nDPI** (`ndpi_category_get_name`). Não duplicar enum próprio na V1 salvo alias opcional.

## Uso em política

Campo `match.ndpi_category[]` aceita lista de strings **exatamente** como retornadas pelo nDPI para o build usado no appliance (pode variar entre versões nDPI).

## Mapeamento futuro (backlog)

- Grupos amigáveis (“Redes sociais” → várias categorias nDPI).
- Lista curada no pacote com **versão** da tabela nDPI.

## Risco

Upgrade nDPI pode renomear categoria: políticas podem “parar de bater”. Mitigação V1: documentar na release notes; V2: migração ou aliases.

## Política recomendada inicial (lab)

- 1 regra `monitor` amplo (`category` vazio) para baseline.
- Regras específicas por `ndpi_app` (ex.: `BitTorrent`) em `block` em lab isolado.
