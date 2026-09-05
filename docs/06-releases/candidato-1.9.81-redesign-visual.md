# Candidato `1.9.81` — redesign visual BG-174 (nao publicado)

**Data preparação:** 2026-09-05
**Estado:** candidato documental + `PORTVERSION` no Makefile — **sem** build, **sem** GitHub Release, **sem** commit (aguarda diff review).

## Objetivo

Empacotar o redesign visual integral das 15 páginas GUI (V1–V15) com
paridade de formulários/handlers preservada nos gates locais.

## Impacto

- **Produto:** apenas metadado `PORTVERSION=1.9.81` + views PHP já
  alteradas nesta sessão (sem mudança funcional declarada).
- **Canal público:** permanece `1.9.80` até publish governado.
- **Updater GUI:** continuará a reportar `1.9.80` até release.

## Risco

| Área | Risco |
|------|-------|
| Visual / UX | Médio — layouts nativos; validação appliance pendente |
| Formulários | Baixo — gates FormData baseline/candidato PASS por página |
| Bloqueio funcional | **Não resolvido** — fora de escopo desta release |
| CSRF / CE | Não homologado nesta preparação |

## Testes mínimos (já executados localmente)

- Gates V1–V15: freeze + native_view + render + payload (+ js quando aplicável).
- V15 final: **131** `PASS:` cumulativos após fix `confirm` + limpeza visual.
- Prefixo `layer7_settings.php` byte-idêntico até `$pgtitle` (**24306** bytes).

## Rollback

1. **Antes de publish:** não instalar candidato; manter `1.9.80`.
2. **Após publish (futuro):** reinstalar o artefacto assinado **`1.9.80`**
   preservado (`.pkg` + `.pkg.sha256` do arquivo interno / último publicado
   antes de `1.9.81`). **Proibido** rebuild ou republicar a mesma
   `PORTVERSION` (BG-164 / ADR-0003).
3. **Código:** delta visual reversível por baseline `tests/functional/baseline-v*-*/`.

## Artefactos (preencher pós-build)

| Campo | Valor |
|-------|-------|
| Tag git (prevista) | `v1.9.81` |
| Source commit | `TBD-pos-commit` |
| SHA256 `.pkg` | `TBD-pos-build` |
| Builder | `192.168.100.12` (`/usr/local/bin/php`) |

## Release notes (resumo operador)

- Redesign visual de **15** páginas Layer7 alinhadas ao pfSense CE.
- Formulários, scopes, submitters e handlers **preservados** (gates).
- **Não** inclui correção do defeito de bloqueio em investigação.
- **Não** instalar em firewall de produção sem GO explícito e revisão visual.

## Pendências reais pós-preparação

- [ ] Diff review + commit (gerente)
- [ ] Build isolado FreeBSD + `verify-prod-pubkey.sh`
- [ ] Publicar `v1.9.81` em `pablomichelin/Layer7` (retirar `1.9.80`)
- [ ] Preencher SHA256 real no `MANUAL-INSTALL.md`
- [ ] Revisão visual no appliance (GO firewall)
