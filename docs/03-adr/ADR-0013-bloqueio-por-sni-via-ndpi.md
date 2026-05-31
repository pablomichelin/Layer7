# ADR-0013 - Bloqueio por SNI/Host via nDPI (Caminho A / A3)

## Status

Aceito

## Contexto

O bloqueio por DNS + IP e leaky em CDNs: servicos partilham IPs (ex.:
`googlevideo.com` em IPs Google partilhados), e o cliente pode usar DNS em
cache ou cifrado (DoH/DoT), escapando a observacao de DNS. Produtos como o
UniFi UDM Pro inspecionam o **SNI** (Server Name Indication) do TLS para
identificar o destino real por ligacao.

O Layer7 e **passivo** (observa via pcap; nao faz MITM nem inline drop do
ClientHello). O motor de classificacao e o **nDPI**, que ja extrai o SNI do
ClientHello e o Host do HTTP para `flow->host_server_name`.

## Problema

Como melhorar a eficacia/precisao do bloqueio por site, de forma segura, sem:

- escrever um parser de TLS ClientHello a mao no nucleo (frágil, risco alto);
- introduzir MITM/decifragem (fora do escopo e da arquitectura);
- alterar comportamento por defeito de instalacoes existentes.

## Decisao

### 1. Reutilizar o SNI extraido pelo nDPI (nao escrever parser proprio)

Em vez de um parser TLS proprio, usa-se `flow->host_server_name` que o nDPI ja
preenche com o **SNI (TLS)** e o **Host (HTTP)**. E um parser battle-tested,
mantido upstream, e cobre TLS/DTLS/QUIC. Decisao explicita de **nao** reinventar
um parser de TLS no daemon (menos superficie de bug/seguranca).

### 2. Opt-in, OFF por defeito (`sni_inspection`)

Novo toggle `layer7.sni_inspection` (bool, default `false`). Quando ligado, o
host do ClientHello desta ligacao e usado como hint de host para matching de
politicas (preferido sobre o DNS reverso), e tambem alimenta a cache de hints
para futuras ligacoes ao mesmo IP de destino. Aplica-se no reload (SIGHUP
reabre capturas).

### 3. Continua passivo e por destino

O matching por host alimenta o caminho ja existente: se o host casa uma
politica `block`, o **IP de destino** entra em `layer7_block_dst` (modelo por
destino, ADR/secao "Modelo de bloqueio por destino"). Nao ha inline drop do
ClientHello (passivo); as primeiras ligacoes de uma sessao podem passar antes
de o IP entrar na tabela — limitacao honesta partilhada com o caminho nDPI.

### 4. Validacao defensiva do host

Antes de usar, o host e validado (`sni_host_plausible`): tem ponto, apenas
caracteres de dominio, comprimento 4..79. Evita lixo de parsing.

## Alternativas consideradas

### A. Parser TLS ClientHello proprio em C

Rejeitada: duplica o que o nDPI ja faz bem; aumenta risco de bug/seguranca no
nucleo de captura.

### B. Inline/divert para RST no ClientHello (strict real)

Rejeitada nesta fase: exige sair do modelo passivo (divert socket / inline),
risco operacional alto. Pode ser estudada num ADR futuro se houver procura.

### C. Ligar por defeito

Rejeitada: muda comportamento de instalacoes existentes; mantem-se opt-in para
previsibilidade e validacao em laboratorio antes de producao.

## Consequencias

- bloqueio por site mais preciso em CDNs e robusto a DNS cache/cifrado;
- sem MITM, sem decifragem, sem parser proprio;
- comportamento por defeito inalterado (opt-in).

## Riscos / limitacoes honestas

- **TLS 1.3 ECH** (Encrypted Client Hello) cifra o SNI: nao se resolve sem MITM;
- passivo: primeiras ligacoes podem passar antes de o IP destino ser bloqueado;
- depende do nDPI classificar/extrair o host (alguns fluxos genericos podem nao
  expor host).

## Impacto em compatibilidade

- aditivo: campo `sni_inspection` (default false) em `layer7.json`;
- novo toggle na GUI (Definicoes); sem alteracao de regras PF.

## Impacto em documentacao

- `CORTEX.md`, `docs/02-roadmap/backlog.md` (BG-042),
  `docs/05-daemon/pf-enforcement.md`, `docs/03-adr/README.md`,
  `docs/changelog/CHANGELOG.md`,
  `docs/09-blocking/caminho-a-plano-de-implementacao.md`.

## Proximos passos

1. Validar em laboratorio com trafego TLS real (interface de captura adequada).
2. A4: contadores por site/SNI na vista unificada.
