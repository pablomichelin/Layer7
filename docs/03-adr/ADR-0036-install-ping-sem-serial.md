# ADR-0036 — Sinal de instalação e heartbeat sem serial

## Status

**Aceito** (`2026-08-22`) — implementado, overlay `.244`
`20260823T022826Z` (API + SPA Instalações). Portal `2.2.0`. Canal
`latest` publicado = `v1.9.73`. **BG-163** (`2026-08-25`): o cliente
`1.9.71` falhava em silêncio (não é falha do endpoint live). Pacote
`1.9.72` corrigiu o silêncio. **BG-165:** o cliente **não** inventa
`hardware_id` (fail-open: não envia se o fingerprint faltar).

## Contexto

O check-in (ADR-0021 / ADR-0032) só corre com chave guardada. Uma caixa
com o pacote instalado e **sem serial** é invisível no
`license.systemup.inf.br`. A marcação `30.17` é sidecar local e **não** é
telemetria de instalação.

O operador Systemup precisa de ver quem instalou, o IP público, os IPs e
nomes das interfaces e os identificadores do pfSense — mesmo sem licença.

## Decisão

1. Canal **novo e separado** do check-in:
   `POST /api/license/install-ping` (HTTPS, rate-limit, sem chave
   obrigatória, sem envelope assinado, **zero** efeito em enforce).
2. Obrigatório no pacote ≥ `1.9.71`; **fail-open** (N3 / air-gap: isolados
   não aparecem; o pacote continua). Independente de `check_in_enabled`.
3. Inventário proporcional (hostname/domínio, uniqueid, WAN, interfaces
   locais com descrição, versões). IP público autoritativo = hop TLS
   confiável (`getClientIp`), não o `X-Forwarded-For` do cliente.
4. Portal: página **Instalações** (lista + detalhe) e cartões no dashboard.
5. `30.17` **não** se reabre. EULA [`LICENSE`](../../LICENSE) §8 declara
   este inventário.

## Consequências

- Versões ≤ `1.9.69` (e `1.9.70` se publicada sem este bloco) não enviam
  o sinal até upgrade.
- Endpoint público pode receber lixo: schema + rate-limit + IP confiável.
  Root pode calar ou mentir no JSON (RR-1); `egress_ip` não é forjável.
- Deploy do endpoint no `.244` exige **GO** (P0-1). Sem isso o pacote
  novo faz ping para 404 e falha em silêncio.
- Cliente `1.9.71`: `require config.inc` em CLI, `curl` fora do PATH do
  daemon e retry só às 24 h — caixa instalada não aparece. Correcção no
  cliente (`1.9.72` / BG-163); o endpoint live **não** mudou.
- Cliente `1.9.73` (BG-165): se `layer7d --fingerprint` falhar e não
  houver `last_hardware_id`, o ping **não** é enviado (não cria segunda
  linha no portal). Fail-open mantido.

## Relação

- Não emenda ADR-0021 / ADR-0032 (check-in continua o único caminho que
  revoga).
- Contrato: [`../01-architecture/contrato-install-ping-bg162.md`](../01-architecture/contrato-install-ping-bg162.md)
- Backlog: BG-162
