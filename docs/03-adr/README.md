# ADR Index

## Finalidade

Os ADRs registam decisoes que mudam o rumo do projecto. Eles existem para
evitar que escolhas estruturais, de seguranca, de distribuicao ou de
operacao fiquem apenas “na conversa”.

Criar ADR quando a mudanca afectar:

- arquitectura do produto;
- cadeia de confianca e seguranca;
- distribuicao e artefactos;
- licenciamento;
- organizacao estrutural do repositório;
- operacao ou rollback de forma duradoura.

---

## Estado dos ADRs existentes

| ID | Titulo | Estado | Papel actual |
|----|--------|--------|--------------|
| [ADR-0001](ADR-0001-engine-classificacao-ndpi.md) | Engine de classificacao Layer 7 — nDPI | Aceito | decisao canónica e congelada |
| [ADR-0002](ADR-0002-distribuicao-artefato-txz.md) | Distribuicao V1 — artefacto `.txz` | Historico / substituido na pratica | preservado por rastreabilidade; nao deve ser usado como referencia actual |
| [ADR-0003](ADR-0003-hierarquia-oficial-de-distribuicao.md) | Hierarquia oficial de distribuicao | Aceito | define `.pkg` como artefacto oficial, a hierarquia builder -> release -> instalacao e o estatuto do legado `.txz` |
| [ADR-0004](ADR-0004-cadeia-de-confianca-dos-artefatos.md) | Cadeia de confianca dos artefactos | Aceito | define checksum, assinatura, papeis de geracao/validacao e tratamento de builder suspeito |
| [ADR-0005](ADR-0005-pipeline-seguro-de-blacklists.md) | Pipeline seguro de blacklists | Aceito | define origem oficial, requisitos de HTTPS, mirror/cache e politica de rejeicao/degradacao |
| [ADR-0006](ADR-0006-fallback-e-degradacao-segura.md) | Fallback e degradacao segura | Aceito | define fail-open vs fail-closed e a fronteira entre disponibilidade e integridade |
| [ADR-0007](ADR-0007-publicacao-segura-license-server.md) | Publicacao segura do license server | Aceito | define HTTPS/TLS obrigatorio, edge proxy, canais/portas permitidos e politica de headers/publicacao |
| [ADR-0008](ADR-0008-autenticacao-e-sessao-license-server.md) | Autenticacao e sessao do license server | Aceito | define modelo de login, sessao stateful, cookie seguro e proibicao de token em `localStorage` |
| [ADR-0009](ADR-0009-protecao-superficie-administrativa-license-server.md) | Protecao da superficie administrativa do license server | Aceito | define rate limit, brute force protection, CORS, logging e politica de erro/admin surface |
| [ADR-0010](ADR-0010-integridade-transacional-e-validacao-crud-license-server.md) | Integridade transacional e validacao do CRUD do license server | Aceito | define validacao de payload, transacoes, delete seguro e falha fechada no CRUD/activate |
| [ADR-0011](ADR-0011-fonte-de-identidade-de-dispositivo.md) | Fonte de identidade de dispositivo (Caminho A / A1) | Aceito | define DHCP leases + ARP como fonte de inventario, vendor OUI best-effort, alias em `device_aliases` e limites honestos (L2, MAC aleatorizado) |
| [ADR-0012](ADR-0012-politicas-por-dispositivo-mac-para-ip.md) | Politicas por dispositivo: resolucao MAC -> IP (Caminho A / A2) | Aceito | grupo aceita `device_macs`, pacote resolve para `device_ips` (DHCP/ARP), daemon le `device_ips` como src hosts; imposicao por IP; resync + static mapping; limite 64 |
| [ADR-0013](ADR-0013-bloqueio-por-sni-via-ndpi.md) | Bloqueio por SNI/Host via nDPI (Caminho A / A3) | Aceito | usa SNI/Host ja extraido pelo nDPI (sem parser proprio, sem MITM); toggle `sni_inspection` opt-in OFF; bloqueio por destino; limitacao ECH |
| [ADR-0014](ADR-0014-enforcement-escopado-por-politica.md) | Enforcement escopado por politica (Caminho B / E0) | Aceito; emenda `_27` | flag `enforcement_model`: `legacy_global` vs `scoped_hybrid`; app normal usa `pdst`, `psrc` somente em quarentena explícita |
| [ADR-0015](ADR-0015-logging-local-limitado-e-separado.md) | Logging local limitado e separado | Aceito | separa operação/tráfego, fixa limites de rotação e SQLite e mantém detalhe opt-in com bloqueios auditados |
| [ADR-0016](ADR-0016-allow-pf-por-marcacao-interna.md) | Allow PF por marcação interna, sem bypass do pfSense | Aceito; candidato `_28` | `pallow_N` + tag `L7ALLOW`; allow vence somente blocks Layer7 e nunca cria `pass quick` perante regras nativas |
| [ADR-0017](ADR-0017-pagina-bloqueio-utilizador-dns-sinkhole.md) | Página de bloqueio utilizador final (DNS sinkhole + HTTP local) | Aceito; `_35` | Unbound local-data + serviço `layer7-blockpage` + NAT rdr :80; opt-in OFF; sem MITM |
| [ADR-0018](ADR-0018-plano-dns-forcado-e-precedencia-bloqueio.md) | Plano DNS forçado (anti-bypass) e precedência de bloqueio sobre allowlist | Aceito; `_39`/`_40` | política manual prevalece sobre allowlist-seed; `block_page.force_dns` (rdr :53 global + anti-DoH); trade-off CDN declarado |
| [ADR-0019](ADR-0019-src-exclude-por-politica.md) | Exclusão de origem por política (`src_exclude_*`) | Aceito; `_50` | `layer7_pexc_N` + `L7ALLOW` em scoped; daemon não-match; trade-off legacy_global documentado |
| [ADR-0020](ADR-0020-isencao-vip-dns.md) | Isenção VIP no caminho DNS (sinkhole + DNS forçado) | Implementado; `_59` opção (a) | view Unbound `layer7-vip-exempt`; fallback rdr `from !<layer7_exc_allow_N>`; SSOT `vip-isentos` inalterado |
| [ADR-0021](ADR-0021-check-in-online-e-revogacao-remota.md) | Check-in online e revogação remota de licença | Aceito | BG-077; `POST /api/license/check-in` + daemon; S14 PASS (`2026-08-04`) |
| [ADR-0022](ADR-0022-compatibilidade-pfsense-ce-escopo-e-limitacao.md) | Compatibilidade pfSense CE — escopo e limitação (Onda E) | Aceito + GO Onda F | CE físico pendente; produção enforce = `_69` |
| [ADR-0023](ADR-0023-trust-chain-pacote-ativacao-faseada.md) | Trust chain pacote — ativação faseada (BG-028) | Aceito (fase 0) | Fase 1 pendente custódia chaves Ed25519 |
| [ADR-0024](ADR-0024-suporte-ipv6-ativacao-faseada.md) | Suporte IPv6 — ativação faseada (trilha V0–V6) | Aceito | Trilha IPv6 **FECHADA**; produção `1.9.8` |
| [ADR-0025](ADR-0025-entitlements-addon-identity-mitm.md) | Entitlements add-on Identity + MITM (SKU X/Y) | **Aceito** (rev.c; T1) | `features` CSV (P1–P6); gate daemon; legado `full`→`base`; check-in ∩ `.lic` |
| [ADR-0026](ADR-0026-mitm-tls-inspection-opt-in.md) | MITM TLS inspection opt-in (CA/certificado) | **Aceito — runtime shipped** (rev.q; `1.9.47`) | P3 PASS; activar piloto NO-GO até ficha+soak; ficha=gate ≠ gap eng.; Squid rejeitado |
| [ADR-0027](ADR-0027-identity-userid-multi-fonte.md) | Identity User-ID multi-fonte (sem captive) | **Aceito** (rev.c) | Mapa no daemon; RADIUS; agente DC A1–A7; fail-mode; NAT `multi-user` |
| [ADR-0028](ADR-0028-concorrencia-io-daemon-identity.md) | Concorrência e IO do daemon para Identity | **Aceito** | Sem IO bloqueante no hot path; threads + rwlock; baseline perf no 20.11a |
| [ADR-0029](ADR-0029-adiamento-agente-endpoint-exclusao-ts.md) | Adiamento IM7 agente endpoint + exclusão IM8 TS/VDI | **Aceito** (20.28) | Sequência segura; GI8 PASS; reopen IM7 com GO + espec 20.27 |
| [ADR-0030](ADR-0030-postura-anti-tamper-layer7d.md) | Postura anti-tamper do `layer7d` e remoção do modo dev de produção | **Aceito** (`2026-08-10`, `30.1b`) | Trilha anti-pirataria; R-A/R-G/RR-3; passos `30.4`/`30.5`/`30.7` |
| [ADR-0031](ADR-0031-entitlement-entrega-conteudo.md) | Entitlement na entrega de conteúdo (token de subscrição para blacklists/catálogos) | **Aceito** (`2026-08-10`, `30.1b`) | RR-1/RR-2/R-B/R-D; AP2; GO espelho `30.11` |
| [ADR-0032](ADR-0032-check-in-obrigatorio-e-assinado.md) | Check-in obrigatório por defeito e resposta assinada com anti-replay | **Aceito** (`2026-08-10`, `30.1b`) | **Emenda ADR-0021**; desenho `30.12` FECHADO ([contrato](../01-architecture/contrato-check-in-assinado-30.12.md)); GO `30.14`; BG-101 reaberto |
| [ADR-0033](ADR-0033-anti-rollback-relogio.md) | Anti-rollback de relógio e estado temporal suspeito | **Aceito** (`2026-08-10`, `30.1b`) | Emenda `f3-expiracao-revogacao-grace.md`; RR-4/R-J; passo `30.6` |

**Trilha anti-pirataria (ADR-0030…0033):** **`Aceito`** no **`30.1b`** (`2026-08-10`);
trilha **FECHADA** em **`30.19`** (`2026-08-12`) — fecho
[`../01-architecture/fecho-trilha-antipirataria-30.19.md`](../01-architecture/fecho-trilha-antipirataria-30.19.md)
com GO humano (recomendações do plano). Ficha:
[`../09-blocking/decisoes-humanas-30.1.md`](../09-blocking/decisoes-humanas-30.1.md).
GA0 completo. Próximo passo de execução: **`30.2`**. Plano rev. `2026-08-10c`
([`../02-roadmap/plano-antipirataria-anti-tamper.md`](../02-roadmap/plano-antipirataria-anti-tamper.md),
arranque [`../00-overview/START-HERE-antipirataria.md`](../00-overview/START-HERE-antipirataria.md)).

**Nota importante:** a distribuicao actual conhecida do projecto usa `.pkg`.
O ADR-0002 fica preservado por rastreabilidade, mas a referencia normativa
actual passa a ser a combinacao de ADR-0003 + ADR-0004.

---

## Ciclo de vida de um ADR

- `Proposto` -> ainda em discussao
- `Aceito` -> decisao valida e aplicavel
- `Substituido` -> trocado por ADR posterior
- `Historico` -> preservado para rastreabilidade, sem valor normativo actual

Sempre que um ADR nascer, actualizar tambem:

- este indice;
- `CORTEX.md` se a decisao mudar fase, risco ou estado global;
- backlog e roadmap se a decisao alterar ordem ou gate.

---

## Proximos ADRs recomendados

**Nota (BG-112 / INV-081):** os IDs **ADR-0011** e **ADR-0012** já estão
**Aceitos** (ver tabela acima) — **não** reutilizar. Em especial, ADR-0012 é
*Politicas por dispositivo: resolucao MAC -> IP*, **não** reorganização F6.

**Nota (`2026-08-10`) — reatribuição declarada de ID:** o ID `ADR-0030` estava aqui
sugerido para a higiene estrutural F6 residual. Como esse ADR é **condicional** («só
se moves futuros precisarem de decisão normativa») e **nunca foi criado**, o ID
`ADR-0030` passou a ser usado pela trilha anti-pirataria (tabela acima) e a sugestão
F6 passa a **`ADR-0034`**. Nenhum ADR existente foi renumerado; nenhum ID `Aceito` foi
reutilizado. Conflito registado em vez de silenciado.

| Proximo ID sugerido | Tema | Fase | Motivo |
|---------------------|------|------|--------|
| ADR-0034 | Reorganizacao estrutural controlada / higiene residual (se GO exigir ADR formal além do plano BG-112) | F6 residual | só se moves futuros precisarem de decisão normativa para além de `f6-plano-higiene-estrutural-residual.md`; **era** `ADR-0030` antes de `2026-08-10` |
| — | Modelo de estados do licenciamento/activacao | F3 | já coberto por docs canónicos F3 (`f3-arquitetura-licenciamento-ativacao.md` e relacionados); **não** abrir ADR-0011 duplicado |

---

## Regra pratica

Se a mudanca for suficientemente importante para:

- alterar uma restricao congelada;
- mudar a forma de distribuir, activar ou validar o produto;
- introduzir uma nova fronteira de confianca;
- mover estrutura com impacto amplo;

entao ela pede ADR.
