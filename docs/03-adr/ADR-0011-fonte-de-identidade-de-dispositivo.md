# ADR-0011 - Fonte de identidade de dispositivo (Caminho A / A1)

## Status

Aceito

## Contexto

O Caminho A aproxima a UX/eficacia do Layer7 a um UniFi UDM Pro. Um requisito
central e **identificar dispositivos** para inventario e, depois (A2), para
politicas por dispositivo. Hoje o produto so distingue por IP/CIDR/grupo de
IPs; nao ha identidade estavel (MAC/hostname/fabricante) nem inventario.

O pfSense CE/Plus nao expoe, por defeito, identidade forte de utilizador
(802.1x, LDAP/AD por sessao). O que esta disponivel de forma fiavel e barata:

- **DHCP leases** via API canonica `system_get_dhcpleases()` — unifica ISC
  dhcpd e Kea; devolve `ip`, `mac`, `hostname`, `descr`, `if`, `online`, `type`.
- **Tabela ARP** (`arp -an`) — vizinhos L2 activos (inclui clientes de IP
  estatico sem lease).
- **Base OUI** (quando presente no sistema: ntopng `EtherOUI.txt`, wireshark
  `manuf`, `/usr/share/misc/oui.txt`) para derivar fabricante do prefixo MAC.

## Problema

Definir, sem prometer paridade com UTM enterprise:

- qual a fonte de identidade da A1/A2;
- o que e fiavel e o que e limitacao honesta;
- onde persiste a identidade do operador (alias);
- que impacto tem no daemon e no enforcement.

## Decisao

### 1. Fonte primaria: DHCP leases + ARP (L2)

O inventario combina `system_get_dhcpleases()` (canonico, ISC+Kea) com a
tabela ARP. A chave de identidade e o **MAC** quando disponivel; caso contrario
o IP. Isto cobre o caso real de escolas/PMEs (rede plana adjacente ao firewall).

### 2. Fabricante por OUI, best-effort, sem dependencia rigida

O fabricante deriva do prefixo OUI do MAC **se** existir uma base OUI no
sistema. Nao se empacota uma base OUI completa (peso/drift); ausencia de base
=> fabricante vazio. E informativo, nunca usado para decisao de bloqueio.

### 3. Persistencia do alias do operador

O alias amigavel por dispositivo guarda-se em `layer7.json` no mapa
`device_aliases` (MAC -> alias). O **daemon ignora** esta chave; e estritamente
de apresentacao/UX. Nao entra no contrato de configuracao do daemon.

### 4. A1 e observacional (read-only)

A A1 **nao altera enforcement**. Apenas observa e apresenta. A ligacao a
politicas por dispositivo e tratada na A2 (ADR proprio se necessario), onde o
MAC sera resolvido para o IP actual via leases e a politica continuara a impor
por IP em PF.

### 5. Limitacoes honestas (documentadas)

- So ve dispositivos **adjacentes em L2** (mesmo segmento do firewall);
- MAC pode ser aleatorizado por privacidade no cliente (iOS/Android) — a
  identidade pode mudar; o alias segue o MAC observado;
- sem 802.1x/LDAP/captive identity nesta fase;
- ARP reflecte vizinhos recentes, nao um historico completo.

## Alternativas consideradas

### A. Empacotar base OUI completa no pacote

Rejeitada para A1: ~3MB, drift de manutencao. Pode reconsiderar-se como
download opcional no futuro.

### B. Parsing directo dos ficheiros de lease (ISC/Kea) em vez da API

Rejeitada: `system_get_dhcpleases()` ja abstrai ISC vs Kea e e o caminho
suportado; parsing proprio seria fragil entre versoes do pfSense.

### C. Identidade por utilizador (LDAP/802.1x) ja na A1

Rejeitada para A1: fora do alvo CE comum e exige integracao pesada; fica como
evolucao possivel (roadmap V2 Fase 17) com ADR proprio.

## Consequencias

- A1 entrega inventario util e seguro sem tocar no enforcement;
- A2 ganha uma base de identidade (MAC) para politicas por dispositivo;
- o produto expoe limites com honestidade, sem prometer identidade forte.

## Riscos

- MAC aleatorizado reduz estabilidade de identidade (mitigacao: alias por MAC
  observado + nota na GUI);
- base OUI ausente => sem fabricante (degradacao informativa aceitavel).

## Impacto em compatibilidade

- aditivo: nova chave `device_aliases` em `layer7.json` (ignorada pelo daemon);
- nova pagina GUI **Dispositivos**; sem alteracao de comportamento existente.

## Impacto operacional

- leitura de leases/ARP no carregamento da pagina (custo baixo; OUI resolvido
  em unica passagem apenas para os prefixos observados).

## Impacto em documentacao

Alinham-se a este ADR:

- `CORTEX.md`
- `docs/02-roadmap/backlog.md` (BG-040)
- `docs/09-blocking/caminho-a-plano-de-implementacao.md`
- `docs/03-adr/README.md` (indice)
- `docs/changelog/CHANGELOG.md`

## Proximos passos

1. A2: resolver MAC -> IP actual (leases) e permitir politica/grupo por
   dispositivo, mantendo imposicao por IP em PF.
2. Considerar counters por dispositivo na A4 (UX UDM).
