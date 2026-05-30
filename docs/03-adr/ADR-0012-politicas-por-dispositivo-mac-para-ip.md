# ADR-0012 - Politicas por dispositivo: resolucao MAC -> IP (Caminho A / A2)

## Status

Aceito

## Contexto

O Caminho A quer permitir politicas por **dispositivo** (estilo "client rules"
do UniFi UDM Pro), nao apenas por IP/CIDR. A identidade de dispositivo (A1,
ADR-0011) ja existe via DHCP leases + ARP.

Restricao da plataforma: o **PF do pfSense impoe por IP**, nao por MAC (o PF
nao faz match por MAC em L3). O proprio pfSense materializa "regra por
dispositivo" atraves de **static mapping DHCP -> IP** e depois regras por IP.

## Problema

Como expor politicas por dispositivo sem:

- mudar o contrato do daemon (que decide por IP de origem);
- poluir os campos editaveis pelo operador com dados derivados;
- prometer match por MAC que o PF nao suporta.

## Decisao

### 1. Identidade no grupo, imposicao por IP

Um **grupo** passa a aceitar `device_macs` (lista de MACs escolhidos pelo
operador). O pacote resolve cada MAC para o **IP actual** (DHCP leases online
+ ARP) e grava em `device_ips` no proprio grupo. A imposicao continua por IP,
reutilizando o mecanismo existente de `src_hosts` do daemon.

### 2. Campo derivado separado (`device_ips`)

- `device_macs`: gerido pelo operador (GUI), persistido.
- `device_ips`: **derivado**, regenerado pelo pacote; nunca editado a mao.
- `hosts`/`cidrs`: continuam a ser os IPs/sub-redes manuais do operador.

O daemon, em `parse_group`, le `device_ips` **alem** de `hosts` e anexa-os aos
hosts de origem do grupo (retrocompativel: configs sem `device_ips` mantem o
comportamento). Assim os campos do operador nunca sao poluidos por IPs
resolvidos.

### 3. Re-resolucao (drift de IP dinamico)

IPs dinamicos podem mudar. A re-resolucao acontece:

- ao gravar o grupo (add/edit) e ao atribuir dispositivos na aba Dispositivos;
- por botao **"Resync IPs dos dispositivos"** (`layer7_devices_resync()`).

Para estabilidade, recomenda-se **DHCP static mapping** para dispositivos com
politica (IP estavel). Documentado na GUI e nos manuais.

### 4. Capacidade

`L7_MAX_GROUP_HOSTS` e `L7_MAX_SRC_HOSTS` subiram de 16 para **64** para
acomodar uma turma de dispositivos. Para escala maior (rede inteira), usar
**grupo por CIDR/sub-rede** (recomendado; escala sem limite pratico).

### 5. Fail-safe

Se os dispositivos de um grupo estiverem offline / sem lease/ARP, `device_ips`
fica vazio. Um grupo so com dispositivos offline nao gera hosts => nao bloqueia
nada (nao se bloqueia o que nao se consegue localizar). Comportamento seguro e
documentado.

## Alternativas consideradas

### A. Match por MAC no daemon/PF

Rejeitada: o PF nao faz match por MAC em L3; exigiria mecanismo proprio fora do
modelo suportado pelo pfSense. Alto risco, baixo retorno.

### B. Escrever IPs resolvidos directamente em `hosts`

Rejeitada: poluiria o campo editavel do operador; na recarga da GUI os IPs
derivados seriam confundidos com IPs manuais e poderiam fossilizar.

### C. Resolucao continua por daemon (ler leases no C)

Rejeitada para A2: meteria logica de DHCP/ARP no daemon C (mais risco). A
resolucao em PHP no pacote reutiliza a API canonica `system_get_dhcpleases()`.

## Consequencias

- politicas por dispositivo reais, reutilizando enforcement por IP ja validado;
- daemon ganha leitura de `device_ips` (mudanca minima, retrocompativel);
- UX: atribuir dispositivos a grupos a partir da aba Dispositivos.

## Riscos

- drift de IP dinamico entre resyncs (mitigacao: static mapping + botao resync);
- limite de 64 IPs por grupo/origem (mitigacao: usar CIDR para escala).

## Impacto em compatibilidade

- aditivo: `device_macs`/`device_ips` nos grupos; configs antigas inalteradas;
- `L7_MAX_GROUP_HOSTS`/`L7_MAX_SRC_HOSTS` 16 -> 64 (apenas aumenta capacidade).

## Impacto em documentacao

- `CORTEX.md`, `docs/02-roadmap/backlog.md` (BG-041),
  `docs/09-blocking/caminho-a-plano-de-implementacao.md`,
  `docs/03-adr/README.md`, `docs/changelog/CHANGELOG.md`,
  `docs/05-daemon/pf-enforcement.md` (nota sobre device_ips no grupo).

## Proximos passos

1. A4: contadores por dispositivo e vista unificada.
2. Considerar agendamento opcional do resync (cron) se houver procura.
