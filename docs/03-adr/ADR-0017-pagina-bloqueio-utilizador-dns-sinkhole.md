# ADR-0017 — Página de bloqueio para o utilizador final (DNS sinkhole + HTTP local)

## Status

Aceito

## Contexto

Com enforcement PF activo (`block drop`), o utilizador final vê páginas a
meio carregar, timeouts ou loading infinito — sem mensagem clara de que o
acesso foi bloqueado pela política de rede. O administrador vê eventos na GUI;
o utilizador final não.

O produto rejeita MITM HTTPS universal (Caminho A/B, `pf-enforcement.md`).
Precisamos de UX tipo Squid/SquidGuard **sem** migrar para proxy Squid.

## Problema

Como informar o utilizador final de forma clara quando um site/serviço está
bloqueado, mantendo o enforcement PF existente, rollback simples e limitações
honestas para HTTPS, CDN, QUIC e DoH?

## Decisão

### 1. Opção A — DNS sinkhole + serviço HTTP local no pfSense

Adoptar **DNS sinkhole via Unbound** + **servidor HTTP leve local**:

1. Domínios de políticas `block` activas (e opcionalmente blacklists UT1,
   com limite configurável) resolvem para o **IP portal** (IP LAN do pfSense
   ou IPv4 customizável nas Definições).
2. Regras NAT `rdr` redireccionam TCP **porta 80** destinada ao IP portal
   para `127.0.0.1:8099`.
3. Serviço `layer7-blockpage` (PHP built-in server, router dedicado) serve
   HTML informativo; o header `Host:` identifica o site bloqueado.
4. Enforcement PF (`layer7_block_dst`, blacklists, scoped) **mantém-se**
   inalterado quando a página está desactivada; com página activa, o
   sinkhole cobre HTTP; IPs CDN ainda podem ser bloqueados por PF (drop).

### 2. Opt-in, OFF por defeito

`layer7.block_page.enabled` default `false`. Não altera comportamento de
instalações existentes até o operador activar nas Definições.

### 3. Sem MITM / sem certificado próprio

Não se gera CA nem se intercepta TLS. Comportamento documentado:

| Cenário | UX esperada |
|---------|-------------|
| HTTP (`http://site`) | Página «Acesso bloqueado» |
| HTTPS (`https://site`) | Erro de certificado ou timeout (sem página HTML legível) |
| CDN / IP directo | PF drop silencioso se não sinkhole |
| QUIC / DoH | Limitações anti-bypass existentes; página não garantida |

### 4. Rollback

Desactivar toggle nas Definições ou reinstalar versão anterior (`_34` ou
anterior). Remove overrides Unbound (marcador dedicado), para o serviço
HTTP e limpa `rdr` do anchor `natrules/layer7_nat`.

## Alternativas consideradas

### B. PF `reject` com RST + página só HTTP

Rejeitada como solução principal: não entrega HTML; UX insuficiente.

### C. Proxy Squid / SquidGuard

Fora de scope V1; registado no backlog como evolução futura se necessário.

## Consequências

- UX clara em **HTTP** para domínios sinkhole (ex.: `http://youtube.com`).
- Integração reutiliza padrão Unbound já usado (anti-DoH): `custom_options`
  base64 no `config.xml`.
- Blacklists UT1: sinkhole limitado a N domínios (default 256) por perf
  Unbound; documentar no UI.
- Novo serviço `layer7-blockpage` e ficheiros em
  `/usr/local/www/layer7-blockpage/`.

## Riscos / limitações honestas

- **HTTPS:** sem MITM, browsers mostram erro TLS — não prometer página bonita
  em HTTPS.
- **YouTube / CDN:** `googlevideo.com` pode estar sinkhole + PF; player HTTPS
  continua problemático; vídeos via IP directo = drop PF.
- **Escala Unbound:** listas enormes de blacklist exigem limite; políticas
  por perfil (dezenas de hosts) são o caso principal.
- **Porta 80 no IP portal:** `rdr` captura HTTP no IP LAN; não conflita com
  GUI pfSense (443) se portal = IP LAN e serviço escuta só em localhost.

## Referências

- `docs/05-daemon/pf-enforcement.md`
- `docs/09-blocking/blocking-master-plan.md` (princípio «sem prometer magia»)
- BG-062 no backlog
