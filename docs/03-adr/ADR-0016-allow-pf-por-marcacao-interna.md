# ADR-0016 — Allow PF por marcação interna, sem bypass do pfSense

- **Estado:** Aceito; candidato `_28`, gate no appliance pendente
- **Data:** 2026-07-29
- **Fase:** Caminho B pré-produção / F4
- **Backlog:** BG-056

## Contexto

O policy engine já aplica precedência `exceptions -> policies -> default`,
mas uma decisão `allow` não removia o efeito de um destino previamente
presente em `layer7_block_dst`, `layer7_pdst_N` ou `layer7_bld_N`.

Uma regra PF `pass quick` antes dos blocks resolveria a precedência interna,
mas também encerraria a avaliação do pacote. Dependendo da posição do hook no
ruleset final, isso poderia autorizar tráfego que uma regra nativa do pfSense
deveria bloquear. A allowlist global e as excepções IP de blacklist antigas
tinham esse mesmo risco.

## Decisão

### 1. Allow não é uma autorização de firewall

O Layer7 usa `match ... tag L7ALLOW` para marcar o pacote sem alterar o
resultado `pass`/`block` do PF. Somente regras de bloqueio geridas pelo Layer7
incluem `! tagged L7ALLOW`.

Assim:

- o allow vence blocks do próprio Layer7;
- regras nativas, floating rules e políticas de segurança do pfSense continuam
  a avaliar o pacote;
- não existe `pass quick` geral criado por política, excepção ou allowlist.

### 2. Política allow recebe destino dinâmico por política

Cada política `allow` activa possui `layer7_pallow_N`. Quando a política vence
uma decisão DNS/SNI/nDPI, o daemon adiciona o destino observado à tabela com o
mesmo TTL limitado usado no cache de enforcement.

O pacote gera uma regra `match` restrita às origens e interfaces efectivas da
política. Política sem origem continua global somente para os destinos que o
daemon inseriu na sua própria `pallow_N`.

### 3. Excepção allow permanece escopada pela origem

Cada excepção `allow` activa gera uma tabela estática
`layer7_exc_allow_N` com os seus hosts/CIDRs. A regra apenas marca tráfego
externo dessa origem com `L7ALLOW`; ela não cria estado nem autoriza tráfego.

Alterar, desactivar ou apagar política/excepção força flush das tabelas
dinâmicas antes do resync, evitando herança por reordenação de índice.

### 4. Excepções de blacklist usam escopo PF negativo por regra

`except_ips` de cada regra UT1 é materializado em `layer7_blsrc_N`, junto das
origens positivas e com entradas negadas para os IPs exceptuados. O block
consulta essa tabela de origem efectiva. Isso evita `pass`, evita uma segunda
tag — o PF mantém somente uma por pacote — e impede que a excepção neutralize
outra política Layer7 para o mesmo destino.

### 5. Exception block é quarentena de origem

Uma excepção `block` casa apenas pela origem. O daemon passa a resolver esse
caminho para `layer7_block` e encerra estados do host, em vez de tentar
adicionar um destino ausente a `layer7_block_dst`.

## Consequências

- FP-017 fica corrigido em código, sujeito ao gate físico two-client.
- A allowlist histórica deixa de poder furar regras nativas do pfSense.
- `layer7-pfctl`, flush do daemon e resync passam a incluir
  `layer7_pallow_0..23`.
- O ruleset cresce de forma limitada: uma tabela/regra por política allow e
  uma tabela/regra por excepção allow, dentro dos limites já existentes.

## Limitações

- Uma política allow baseada somente em aplicação precisa observar DNS ou um
  fluxo classificável para aprender o destino. Se um block global anterior
  impedir até os primeiros pacotes, o modo recomendado é `scoped_hybrid` ou
  uma excepção explícita de origem; não se cria bypass global para contornar
  esta limitação.
- IPv6, ECH, DoH hardcoded e CDN partilhada continuam limitações separadas.
- A posição e a sintaxe interpretada no ruleset final ainda exigem
  `pfctl -nf`, `pfctl -sr` e tráfego two-client no appliance.

## Teste

- C: índice `pallow`, decisão allow preserva índice e exception block usa
  origem.
- PHP: tabelas/regras, ordem, ausência de `pass quick`, exclusão por
  `layer7_blsrc_N`, modo monitor sem regras.
- Builder FreeBSD: lint C/PHP/shell, build nDPI e pacote.
- Appliance: validar que A permitido continua sujeito a uma regra nativa do
  pfSense, enquanto B permanece bloqueado pelo Layer7 para o mesmo destino.

## Rollback

Desactivar o motor, executar `layer7-pfctl flush-all`, recarregar o filtro e
reinstalar `_24` em modo passivo. Restaurar o JSON exportado antes do teste.
Não publicar nem alterar o default para `scoped_hybrid` antes do gate.
