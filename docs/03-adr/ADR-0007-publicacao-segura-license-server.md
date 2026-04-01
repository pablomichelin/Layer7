# ADR-0007 - Publicacao segura do license server

## Status

Aceito

## Contexto

O repositório já contém uma implementação funcional do license server em
`license-server/` com:

- `docker-compose.yml` a publicar `8445:80` no host;
- Nginx interno sem TLS e sem headers mínimos de hardening;
- documentação histórica a assumir que o HTTPS público é terminado por um
  ISPConfig de borda e que a comunicação interna segue em HTTP;
- backend, frontend e PostgreSQL segregados apenas por rede Docker bridge.

Hoje o serviço funciona, mas a publicação segura ainda depende de convenções
operacionais implícitas:

- quem pode chegar à porta `8445`;
- quando HTTP interno é aceitável;
- quais portas podem existir expostas;
- quais headers e redirecionamentos são obrigatórios;
- como distinguir canal público, canal privado e rede interna Docker.

## Problema

É preciso definir uma política oficial e auditável para:

- publicação do painel administrativo e do endpoint de activação;
- fronteira entre edge proxy, host do license server e containers;
- portas/canais aceitáveis;
- requisitos mínimos de HTTPS/TLS;
- redirecionamento HTTP -> HTTPS;
- headers mínimos esperados;
- comportamento seguro em falha de publicação.

## Decisão

### 1. Canal público permitido

O **único canal público permitido** para operadores humanos e clientes pfSense
passa a ser:

- `https://license.systemup.inf.br` em **443/TCP**

Não é permitido expor publicamente:

- `8445/TCP` no host;
- `3001/TCP` da API;
- `5432/TCP` do PostgreSQL;
- qualquer acesso directo à rede Docker.

### 2. Papel do edge proxy

O reverse proxy de borda (ISPConfig/Nginx/Apache equivalente) passa a ser o
**ponto oficial de terminação TLS pública**.

Responsabilidades normativas do edge proxy:

- terminar TLS público;
- publicar certificado válido e renovado;
- redirecionar `http://` para `https://`;
- encaminhar apenas para o origin privado autorizado;
- injectar/normalizar `X-Forwarded-Proto`, `X-Forwarded-For` e `Host`;
- aplicar headers mínimos de segurança;
- negar hosts inesperados.

### 3. Papel do Nginx interno do stack

O Nginx em `license-server/nginx/nginx.conf` permanece como proxy interno do
stack e ponto de fan-out entre:

- `/api/*` -> backend;
- `/*` -> frontend.

Ele **não é** o canal público de confiança. A publicação oficial continua
ancorada no edge proxy.

### 4. Portas e redes aceitáveis

Política oficial:

- `443/TCP` público: permitido
- `80/TCP` público: permitido apenas para redirecionar para `443/TCP`
- `8445/TCP` no host do license server: permitido apenas como **origin
  privado**, nunca como exposição pública directa
- `3001/TCP` e `5432/TCP`: apenas rede interna Docker

O origin privado (`8445`) deve aceitar tráfego apenas de:

- edge proxy de borda explícito; ou
- rede administrativa privada controlada para troubleshooting

Qualquer outro acesso deve ser bloqueado na rede/host.

### 5. Política de TLS

TLS é **obrigatório** para todo acesso público administrativo e para toda
activação online vinda do pfSense quando o canal público for usado.

Política oficial:

- sem fallback automático para HTTP público;
- certificados válidos e não expirados;
- suites/ciphers delegados ao edge proxy conforme baseline segura do sistema;
- HSTS activado no canal público administrativo.

O hop interno edge proxy -> `8445` pode permanecer HTTP **apenas** quando:

- o tráfego fica numa rede privada controlada;
- `8445` não está exposto à internet;
- há allowlist de origem;
- o risco residual fica documentado como dependência operacional da F2.

### 6. Headers mínimos esperados

No canal público administrativo, o edge proxy deve publicar no mínimo:

- `Strict-Transport-Security`
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: DENY`
- `Referrer-Policy: same-origin`
- `Content-Security-Policy` conservadora para a SPA administrativa

Para respostas de autenticação/sessão:

- `Cache-Control: no-store`

### 7. Compatibilidade operacional

Compatibilidade permitida:

- o stack Docker actual pode continuar a usar Nginx interno + backend + web;
- o edge proxy actual pode continuar a terminar TLS;
- `8445` pode continuar a existir como origin privado durante a F2.

Compatibilidade não permitida ao fim da F2:

- acesso humano directo por `http://192.168.100.244:8445` como caminho
  normativo de administração;
- acesso público directo ao origin sem TLS;
- exposição simultânea de múltiplos canais públicos concorrentes.

### 8. Política de falha

- certificado inválido, host inesperado ou downgrade para HTTP público:
  **fail-closed**
- origin `8445` indisponível atrás do edge proxy:
  erro explícito de publicação; sem fallback para canal alternativo público
- indisponibilidade temporária do painel:
  degradada operacionalmente, mas sem abrir canal inseguro

## Alternativas consideradas

### A. Expor `8445` publicamente com HTTP ou HTTPS local

Rejeitada. Mantém ambiguidade de canal oficial e amplia superfície exposta.

### B. Mover todo o TLS para dentro do stack Docker

Rejeitada para a F2. Aumenta complexidade operacional e duplica funções que já
existem na borda.

### C. Aceitar HTTP interno e público por conveniência

Rejeitada. Contradiz o objectivo de hardening e mantém downgrade fácil.

## Consequências

- o canal oficial do license server fica inequívoco;
- o edge proxy passa a ser parte explícita da fronteira de confiança;
- o host deixa de poder ser tratado como “painel acessível directamente”;
- a operação ganha regra clara para incidente e troubleshooting.

## Riscos

- o hop HTTP interno entre edge e origin continua a ser dependência operacional
  se não houver segmentação forte;
- headers e redirecionamento mal configurados no edge podem gerar falsa
  sensação de segurança;
- a migração do hábito de usar `192.168.100.244:8445` directamente pode exigir
  disciplina operacional.

## Impacto em compatibilidade

- não altera o formato do `.lic`;
- não altera a API funcional do daemon;
- altera o caminho normativo de acesso administrativo;
- torna o acesso HTTP directo um modo legado/transitório.

## Impacto operacional

- exige ACL/firewall para `8445`;
- exige revisão do vhost/reverse proxy de borda;
- exige runbook simples de certificado, redirect e troubleshooting do origin;
- exige prova clara de que `3001` e `5432` não ficam expostos.

## Impacto em documentação

Devem alinhar-se a este ADR:

- `CORTEX.md`
- `docs/02-roadmap/roadmap.md`
- `docs/02-roadmap/backlog.md`
- `docs/02-roadmap/checklist-mestre.md`
- `docs/01-architecture/f2-arquitetura-license-server.md`
- `docs/02-roadmap/f2-plano-de-implementacao.md`
- `docs/10-license-server/MANUAL-USO-LICENCAS.md`

## Próximos passos

1. Aplicar esta política ao desenho consolidado da F2.
2. Implementar publicação segura e hardening do edge/origin na primeira
   subfase executável da F2.
3. Produzir runbook mínimo para publicação, certificado e teste de exposição.
