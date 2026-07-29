# Release Notes — `pfSense-pkg-layer7` `1.8.11_27` (DRAFT)

## Estado

Candidato interno, não publicado e não aprovado para produção. O appliance
`192.168.100.254` não foi alterado durante o desenvolvimento.

## Objectivo

Corrigir falhas no caminho captura -> nDPI -> decisão -> PF que explicam
bloqueio ausente, bloqueio tardio e quarentena acidental do cliente.

## Correcções

- hash bidireccional entrega ida e volta ao mesmo fluxo nDPI;
- app/categoria normal usa `pdst`; `psrc` exige quarentena explícita;
- estado PF já estabelecido é encerrado de forma selectiva após o block;
- allow manual/excepção prevalece sobre blacklist;
- SNI blacklist recebe TTL;
- self-heal exige a tabela scoped alvo;
- QNAME DNS original é preservado em respostas com CNAME;
- fluxo classificado não impede o sweep de expiração.

## Gates obrigatórios

- suite local C/PHP/shell;
- build FreeBSD com nDPI e validação do `.pkg`;
- instalação primeiro passiva;
- monitor/captura em interface real;
- gate two-client, app normal vs quarentena, state kill, TTL e rollback.

## Rollback

Reinstalar a release pública `_24` em passivo, executar
`layer7-pfctl flush-all`, resync do filtro e confirmar tabelas dinâmicas
vazias. Não apagar logs do gate.
