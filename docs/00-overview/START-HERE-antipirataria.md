# START HERE — Anti-pirataria e Anti-tamper 【**ENGENHARIA FECHADA** · `30.19` · **EVIDÊNCIA OPERACIONAL FECHADA** · BG-127 PASS】

> **Diagnóstico ACEITE** `2026-08-10` — [`modelo-ameacas-antipirataria.md`](../01-architecture/modelo-ameacas-antipirataria.md).
> **Engenharia AP0–AP4 FECHADA** em `30.19` (`20260812T025741Z`) — fecho
> documental GA6.7–6.12. **Não** reabrir código / PORTVERSION / AP0–AP4.
> Doc fecho: [`../01-architecture/fecho-trilha-antipirataria-30.19.md`](../01-architecture/fecho-trilha-antipirataria-30.19.md).
> Evidência fecho: [`../tests/evidence/20260812T025741Z-30.19-fecho/`](../tests/evidence/20260812T025741Z-30.19-fecho/).
> **GO `2026-08-14`:** ciclo de **evidência operacional FECHADO** — **BG-127 PASS**
> (`20260814T224213Z`; GA2.6 enforce + GA4.8 campo). Histórico PARTIAL:
> [`20260814T051611Z-bg127`](../tests/evidence/20260814T051611Z-bg127/) +
> [`20260814T053905Z-bg127`](../tests/evidence/20260814T053905Z-bg127/)
> (GA2.7 **PASS**; GA5.9 **PASS** `20260814T143406Z`).
> **GA6.7** = parecer jurídico **externo**.
> **`.254` vivo:** **`1.9.63`** `mode=monitor` MITM **OFF**
> ([`20260814T034904Z-20.36-soak-align-163-254`](../tests/evidence/20260814T034904Z-20.36-soak-align-163-254/)).
> Histórico e2e AP2: `.254` = `1.9.54` (`20260811T114320Z`) — **não** é o estado vivo.
> Hosts `.54` / `.254`: **fora do horário comercial**. Publicar **só** se
> necessidade técnica verificada.
> **P0-1 ACTIVO (`2026-08-14`):** proibido deploy integral do HEAD sobre o
> `.244`. Serving `30.11` **versionado no git**; freeze **não** encerrado —
> [`auditoria-licencas-auth-deploy-2026-08-14.md`](../09-blocking/auditoria-licencas-auth-deploy-2026-08-14.md)
> + [`bloqueio-deploy-integral-head-30.11.md`](../13-runbooks/bloqueio-deploy-integral-head-30.11.md).
> Snapshot/`.env`/SPA **fora do git**. **BG-128** aberto; **P0-2, P1-1, P1-2,
> P1-3, P1-4, P2-1, allowlist `30.11` e P1-5…P1-8 + P2-12 FEITOS** no git
> (`2026-08-14`; `c2b9fdb` + governação após gates; sem deploy /
> `PORTVERSION`). **P2-7+P2-8+P2-10 FEITOS** no git (`2026-08-14`; sem deploy).
> **P2-11 FEITO** no git (`2026-08-14`; sem deploy).
> **A1/A2/M2** FEITO no git (`28c97ad` + governação após gates; sem P2-13).
> **M1 FEITO** no git (`2026-08-14`; GUI/helper via `layer7d --fingerprint`; sem deploy).
> **P2-17 FEITO** no git (`2026-08-14`; `LAYER7_TEST_NOW` só com `LAYER7_TEST_ROOT`; sem deploy).
> **P2-3 FEITO** no git (`2026-08-14`; origin `X-Forwarded-Proto $scheme`; sem deploy).
> **P1-9 AVALIADO** no git (`2026-08-14`; residual pós-P2-3 não aberto no HEAD; sem mudança de runtime).
> **P2-2 FEITO** no git (`2026-08-14`; CSRF admin fail-closed `Origin` / `Sec-Fetch-Site`; sem deploy).
> **P2-13 AVALIADO** no git (`2026-08-14`; meia-noite local / DST / UTC sem correção única segura; sem mudança de runtime).
> **P2-4 FEITO** no git (`2026-08-14`; incremento atómico de `failure_count`; sem deploy).
> **P2-6 Bloco A FEITO** no git (`2026-08-14`; `.dockerignore` + `USER node` no backend; sem compose/healthcheck; sem deploy).
> **P2-6 Bloco B FEITO** no git (`2026-08-14`; `pg_isready` + `depends_on` `service_healthy`; sem Docker build/up; sem deploy).
> **P0-2 residual single-use/bind FEITO** no git (`2026-08-14`; `jti` + `admin_totp_challenges`; sem deploy).
> **P3-1 FEITO** no git (`2026-08-14`; sessão única atómica com lock do admin; sem deploy).
> **P3-2 FEITO** no git (`2026-08-14`; `GET /api/auth/session` inclui `a.totp_enabled`; sem deploy).
> **P3-3A FEITO** no git (`2026-08-14`; `/login` disabled e inexistente partilham 401 genérico + bcrypt + `registerLoginFailure`; sem deploy).
> **P3-3B** FEITO no git (`2026-08-14`; `POST`/`PUT /api/users` exige password >=12; `/login` não rejeita 10; sem deploy).
> **P3-3C** FEITO no git (`2026-08-14`; `verifyTotp` Buffer UTF-8 + guarda de comprimento + `timingSafeEqual`; sem deploy).
> **P3-4** FEITO no git (`2026-08-14`; `GET /api/auth/2fa/status` try/catch + 500 JSON `Erro interno.`; sem deploy).
> **P3-5** FEITO no git (`2026-08-14`; promoção atómica do `.lic` em Activate — tmp 0600 + verify + rename; sem deploy).
> **P3-6** FEITO no git (`2026-08-14`; gate PEM do port == SoT; sem deploy).
> **P3-8 AVALIADO** no git (`2026-08-14`; cut `30.11` `asset_count=0` + 404×4; sem mudança de runtime).
> **P3-9 AVALIADO** no git (`2026-08-14`; **BG-150**; opção A — **FEITO documental**; URLs **não** removidos; sem mudança de runtime).
> **P2-16 AVALIADO** no git (`2026-08-14`; **BG-151**; opção A — **FEITO documental**; rollback preferido = overlay `bbc74a5…`; tag `pre-30.13` **não** é padrão/`latest`; sem tag/retag/deploy).
> **P2-14 AVALIADO** no git (`2026-08-14`; **BG-152**; opção A — **FEITO documental**; bypass ABI `-f` = política BG-106; builder FreeBSD 16 **não** provado; sem código/`PORTVERSION`).
> **P3-7 AVALIADO** no git (`2026-08-14`; **BG-153**; opção A — **FEITO documental**; colisão TZ/expiry já provada em P2-13/REV-030; `timegm`/`gmmktime` **não** são correção; sem mudança de runtime).
> **P2-9 AVALIADO** neste bloco (`2026-08-14`; **BG-154**; opção A — cadeado + docs; upgrade **não** injecta `true`; sem mudança de runtime).
> Próximo código com GO = P0-1 rebuild api + smoke (sem P2-9; sem P2-7/8/10/11; sem M1/P2-17/P2-3; sem P1-9 runtime; sem P2-2; sem P2-13; sem P2-4; sem P2-6 Bloco A; sem P2-6 Bloco B; sem P3-1; sem P3-2; sem P3-3A; sem P3-3B; sem P3-3C; sem P3-4; sem P3-5; sem P3-6; sem P3-8; sem P3-9; sem P2-16; sem P2-14; sem P3-7; sem P0-2 residual).
> **Proibido neste ciclo:** MITM · enfraquecer segurança · falsear · apagar
> dados · reset/rebase/stash · reabrir engenharia · rsync/rebuild integral HEAD.
> **Honestidade:** root **pode** contornar verificação local (RR-5 / R-A).
> **Artefacto:** **`.pkg`** FreeBSD/pfSense.
> **Rev. plano:** `2026-08-10c` + fecho `30.19` + GO evidência `2026-08-14`.

```text
docs/00-overview/START-HERE-antipirataria.md
```

### Continuidade em chat limpo

1. Ler este ficheiro + CORTEX + fecho `30.19` + **BG-127** + **BG-128**.
2. **Não** reabrir AP0–AP4. O GO `2026-08-14` autoriza evidência de campo.
   **P0-2, P1-1, P1-2, P1-3, P1-4, P2-1, allowlist `30.11` e P1-5…P1-8 +
   P2-12 FEITOS** no git (`c2b9fdb` + governação após gates). Código
   seguinte com GO = P0-1 rebuild api + smoke (sem P2-9; sem P2-7/8/10/11;
   sem M1/P2-17/P2-3; sem P1-9 runtime; sem P2-2; sem P2-13; sem P2-4; sem P2-6 Bloco A; sem P2-6 Bloco B; sem P3-1; sem P3-2; sem P3-3A; sem P3-3B; sem P3-3C; sem P3-4; sem P3-5; sem P3-6; sem P3-8; sem P3-9; sem P2-16; sem P2-14; sem P3-7; BG-128), sem `.244` neste bloco. **P2-7+P2-8+P2-10 FEITOS** no git.
   **P2-11 FEITO** no git. **A1/A2/M2** FEITO no git (`28c97ad` + governação após gates).
   **M1 FEITO** no git (`2026-08-14`; GUI/helper via `layer7d --fingerprint`).
   **P2-17 FEITO** no git (`2026-08-14`; `LAYER7_TEST_NOW` só com `LAYER7_TEST_ROOT`).
   **P2-3 FEITO** no git (`2026-08-14`; origin `X-Forwarded-Proto $scheme`).
   **P1-9 AVALIADO** no git (`2026-08-14`; residual pós-P2-3 não aberto no HEAD).
   **P2-2 FEITO** no git (`2026-08-14`; CSRF admin fail-closed).
   **P2-13 AVALIADO** no git (`2026-08-14`; meia-noite / DST / UTC sem correção única; sem runtime).
   **P2-4 FEITO** no git (`2026-08-14`; incremento atómico de `failure_count`).
   **P2-6 Bloco A FEITO** no git (`2026-08-14`; `.dockerignore` + `USER node` no backend; sem compose/healthcheck).
   **P2-6 Bloco B FEITO** no git (`2026-08-14`; `pg_isready` + `depends_on` `service_healthy`; sem Docker build/up).
   **P3-1 FEITO** no git (`2026-08-14`; sessão única atómica com lock do admin).
   **P3-2 FEITO** no git (`2026-08-14`; `GET /api/auth/session` inclui `a.totp_enabled`).
   **P3-3A FEITO** no git (`2026-08-14`; `/login` disabled e inexistente partilham 401 genérico + bcrypt + `registerLoginFailure`).
   **P3-3B** FEITO no git (`2026-08-14`; `POST`/`PUT /api/users` exige password >=12; `/login` não rejeita 10).
   **P3-3C** FEITO no git (`2026-08-14`; `verifyTotp` Buffer UTF-8 + guarda de comprimento + `timingSafeEqual`).
   **P3-4** FEITO no git (`2026-08-14`; `GET /api/auth/2fa/status` try/catch + 500 JSON).
   **P3-5** FEITO no git (`2026-08-14`; promoção atómica do `.lic` em Activate).
   **P3-6** FEITO no git (`2026-08-14`; gate PEM do port == SoT).
   **P3-8 AVALIADO** no git (`2026-08-14`; cut `30.11` `asset_count=0` + 404×4; sem mudança de runtime).
   **P3-9 AVALIADO** no git (`2026-08-14`; **BG-150**; opção A — **FEITO documental**; URLs **não** removidos; sem mudança de runtime).
   **P3-7 AVALIADO** no git (`2026-08-14`; **BG-153**; opção A — **FEITO documental**; `timegm`/`gmmktime` **não** são correção; sem mudança de runtime).
   **P2-9 AVALIADO** neste bloco (`2026-08-14`; **BG-154**; opção A — cadeado + docs; upgrade **não** injecta `true`; sem mudança de runtime).
3. **P0-1 ACTIVO:** proibido deploy integral do HEAD. Serving versionado;
   freeze **não** encerrado. Snapshot/`.env` **fora do git**.
4. Residuais campo: ciclo **BG-127**; parecer EULA externo (GA6.7); RR-3 tags.
5. **Não** misturar com MITM/IPv6/promoção enforce sem GO próprio.

| Documento | Papel |
|-----------|--------|
| **Este ficheiro** | Arranque (engenharia fechada / evidência aberta) |
| [`fecho-trilha-antipirataria-30.19.md`](../01-architecture/fecho-trilha-antipirataria-30.19.md) | Fecho de engenharia GA6.7–6.12 |
| [`plano-antipirataria-anti-tamper.md`](../02-roadmap/plano-antipirataria-anti-tamper.md) | SSOT histórico da trilha |
| [`plano-gates-antipirataria.md`](../09-blocking/plano-gates-antipirataria.md) | Gates — engenharia PASS; campo = BG-127 |
| [`backlog.md`](../02-roadmap/backlog.md) | **BG-127** (ciclo evidência); **BG-128** (auditoria / P0-1) |
| [`auditoria-licencas-auth-deploy-2026-08-14.md`](../09-blocking/auditoria-licencas-auth-deploy-2026-08-14.md) | Achados P0–P3 + freeze P0-1 |
| [`20260814T200900Z-p38-cut-recheck`](../tests/evidence/20260814T200900Z-p38-cut-recheck/) | P3-8 fechado como evidência (`asset_count=0` + 404×4); **não** é P3-9 |
| [`nota-404-esperado-cut-30.11.md`](../09-blocking/nota-404-esperado-cut-30.11.md) | P3-9 / **BG-150** opção A — 404 **esperado**; URLs **não** removidos |
| [`20260814T204500Z-p39-404-esperado`](../tests/evidence/20260814T204500Z-p39-404-esperado/) | Evidência documental BG-150 (sem rede; URLs não removidos) |
| [`bloqueio-deploy-integral-head-30.11.md`](../13-runbooks/bloqueio-deploy-integral-head-30.11.md) | Runbook: proibido deploy integral HEAD |
| [`evidencia-operacional-antipirataria-bg127.md`](../13-runbooks/evidencia-operacional-antipirataria-bg127.md) | Runbook de campo (`.54`→`.254`) |
| [`CORTEX.md`](../../CORTEX.md) | SSOT operacional vivo |

---

## Estado actual

| Campo | Valor |
|-------|-------|
| Engenharia | **AP4 / `30.19` FECHADA** |
| Ciclo evidência | **FECHADO — PASS** — **BG-127** (`20260814T224213Z`) |
| Gates de campo | GA2.6 **PASS** (monitor + enforce); GA2.7 **PASS**; GA3.7 **PASS**; GA4.8 **PASS** campo; GA5.9 **PASS** |
| Fora deste ciclo | **GA6.7** (parecer EULA externo) |
| `.254` vivo | **`1.9.63`** `mode=monitor` MITM **OFF** (`20260814T034904Z-20.36-soak-align-163-254`; reconfirmado `20260814T053905Z`) |
| Histórico e2e AP2 | `.254` = `1.9.54` (`20260811T114320Z`) |
| lab/`latest` | **`1.9.63`** |
| Baseline enforce | **`1.9.8`** |
| Freeze deploy | **P0-1 ACTIVO** — serving `30.11` versionado no git; sem rsync/rebuild integral HEAD→`.244` |
| Próxima acção código | P0-1 rebuild api + smoke (sem P2-9; sem P2-7/8/10/11; sem M1/P2-17/P2-3; sem P1-9 runtime; sem P2-2; sem P2-13; sem P2-4; sem P2-6 Bloco A; sem P2-6 Bloco B; sem P3-1; sem P3-2; sem P3-3A; sem P3-3B; sem P3-3C; sem P3-4; sem P3-5; sem P3-6; sem P3-8; sem P3-9; sem P2-16; sem P2-14; sem P3-7; sem P0-2 residual). **P3-7 AVALIADO** no git (`2026-08-14`; **BG-153**; opção A — **FEITO documental**; `timegm`/`gmmktime` **não** são correção). **P2-14 AVALIADO** no git (`2026-08-14`; **BG-152**; opção A — **FEITO documental**; bypass ABI `-f` = política BG-106; builder FreeBSD 16 **não** provado). **P2-16 AVALIADO** no git (`2026-08-14`; **BG-151**; opção A — **FEITO documental**; rollback preferido = overlay `bbc74a5…`; tag `pre-30.13` **não** é padrão/`latest`). **P2-6 Bloco A FEITO** no git (`2026-08-14`; `.dockerignore` + `USER node` no backend; sem compose/healthcheck; sem deploy). **P2-6 Bloco B FEITO** no git (`2026-08-14`; `pg_isready` + `depends_on` `service_healthy`; sem Docker build/up; sem deploy). **P0-2 residual single-use/bind FEITO** no git (`2026-08-14`; `jti` + `admin_totp_challenges`; sem deploy). **P3-9 AVALIADO** no git (`2026-08-14`; **BG-150**; opção A — **FEITO documental**; URLs **não** removidos; sem mudança de runtime). **P3-8 AVALIADO** no git (`2026-08-14`; cut `30.11` `asset_count=0` + 404×4; sem mudança de runtime). **P3-6** FEITO no git (`2026-08-14`; gate PEM do port == SoT). **P3-5** FEITO no git (`2026-08-14`; promoção atómica do `.lic` em Activate). **P3-4** FEITO no git (`2026-08-14`; `GET /api/auth/2fa/status` try/catch + 500 JSON). **P3-3C** FEITO no git (`2026-08-14`; `verifyTotp` Buffer UTF-8 + guarda de comprimento + `timingSafeEqual`). **P3-3B** FEITO no git (`2026-08-14`; `POST`/`PUT /api/users` exige password >=12; `/login` não rejeita 10). **P3-3A FEITO** no git (`2026-08-14`; `/login` disabled e inexistente partilham 401 genérico + bcrypt + `registerLoginFailure`). **P3-2 FEITO** no git (`2026-08-14`; `GET /api/auth/session` inclui `a.totp_enabled`). **P3-1 FEITO** no git (`2026-08-14`; sessão única atómica com lock do admin). **P2-4 FEITO** no git (`2026-08-14`; incremento atómico de `failure_count`). **P2-13 AVALIADO** no git (`2026-08-14`; meia-noite / DST / UTC sem correção única; sem runtime). **P2-2 FEITO** no git (`2026-08-14`; CSRF admin fail-closed). **P1-9 AVALIADO** no git (`2026-08-14`; residual pós-P2-3 não aberto no HEAD). **P2-3 FEITO** no git (`2026-08-14`; origin `X-Forwarded-Proto $scheme`). **P2-17 FEITO** no git (`2026-08-14`; `LAYER7_TEST_NOW` só com `LAYER7_TEST_ROOT`). **M1 FEITO** no git (`2026-08-14`; GUI/helper via `layer7d --fingerprint`). **A1/A2/M2** FEITO no git (`28c97ad` + governação após gates). **P2-11 FEITO** no git. **P2-7+P2-8+P2-10 FEITOS** no git. **P0-2, P1-1, P1-2, P1-3, P1-4, P2-1, allowlist `30.11` e P1-5…P1-8 + P2-12 FEITOS** no git (`c2b9fdb` + governação após gates; sem deploy / `PORTVERSION`) |
| Residual campo BG-127 | **Nenhum** — campanha `20260814T224213Z` **PASS** |

---

## Progresso compacto

```text
ANTI-PIRATARIA — ENGENHARIA FECHADA / EVIDÊNCIA OPERACIONAL FECHADA (BG-127 PASS)
- Diagnóstico: ACEITE 2026-08-10 (A-01..A-10)
- Engenharia: 30.19 FECHADO (20260812T025741Z)
- Evidência fecho: 20260812T025741Z-30.19-fecho
- Ciclo evidência: BG-127 PASS (20260814T224213Z)
- Gates campo: GA2.6 PASS (monitor+enforce); GA2.7 PASS; GA3.7 PASS; GA4.8 PASS; GA5.9 PASS
- Auditoria 2026-08-14: P0-1 ACTIVO (serving 30.11 versionado; freeze NÃO encerrado); BG-128 ABERTO; P0-2, P1-1, P1-2, P1-3, P1-4, P2-1, allowlist 30.11 e P1-5…P1-8 + P2-12 FEITOS no git (c2b9fdb + governação após gates); P2-7+P2-8+P2-10 FEITOS no git; P2-11 FEITO no git; A1/A2/M2 FEITO no git (28c97ad + governação após gates); M1 FEITO no git (GUI/helper via layer7d --fingerprint); P2-17 FEITO no git (LAYER7_TEST_NOW só com LAYER7_TEST_ROOT); P2-3 FEITO no git (origin X-Forwarded-Proto $scheme); P1-9 AVALIADO no git (residual pós-P2-3 não aberto no HEAD; sem mudança de runtime); P2-2 FEITO no git (CSRF admin fail-closed Origin/Sec-Fetch-Site); P2-13 AVALIADO no git (meia-noite local / DST / UTC sem correção única segura; sem mudança de runtime); P2-4 FEITO no git (incremento atómico de failure_count; sem deploy); P2-6 Bloco A FEITO no git (.dockerignore + USER node no backend; sem compose/healthcheck; sem deploy); P2-6 Bloco B FEITO no git (pg_isready + depends_on service_healthy; sem Docker build/up; sem deploy); P0-2 residual single-use/bind FEITO no git (jti + admin_totp_challenges; sem deploy); P3-1 FEITO no git (sessão única atómica com lock do admin; sem deploy); P3-2 FEITO no git (GET /api/auth/session inclui a.totp_enabled; sem deploy); P3-3A FEITO no git (POST /login disabled e inexistente partilham 401 genérico + bcrypt + registerLoginFailure; sem deploy); P3-3B FEITO no git (POST/PUT /api/users exige password >=12; /login não rejeita 10; sem deploy); P3-3C FEITO no git (verifyTotp Buffer UTF-8 + guarda de comprimento + timingSafeEqual; sem deploy); P3-4 FEITO no git (GET /2fa/status try/catch + 500 JSON; sem deploy); P3-5 FEITO no git (promoção atómica do .lic em Activate; sem deploy); P3-6 FEITO no git (gate PEM do port == SoT; sem deploy); P3-8 AVALIADO no git (cut 30.11 asset_count=0 + 404x4; sem mudança de runtime); P3-9 AVALIADO no git (BG-150; opção A — FEITO documental; URLs não removidos; sem mudança de runtime); P2-16 AVALIADO no git (BG-151; opção A — FEITO documental; rollback preferido = overlay bbc74a5; tag pre-30.13 não é padrão/latest); P2-14 AVALIADO no git (BG-152; opção A — FEITO documental; bypass ABI -f = política BG-106; builder FreeBSD 16 não provado); P3-7 AVALIADO no git (BG-153; opção A — FEITO documental; timegm/gmmktime não são correção; sem mudança de runtime); próximo código com GO = P0-1 rebuild api + smoke (sem P2-9; sem P2-7/8/10/11; sem M1/P2-17/P2-3; sem P1-9 runtime; sem P2-2; sem P2-13; sem P2-4; sem P2-6 Bloco A; sem P2-6 Bloco B; sem P3-1; sem P3-2; sem P3-3A; sem P3-3B; sem P3-3C; sem P3-4; sem P3-5; sem P3-6; sem P3-8; sem P3-9; sem P2-16; sem P2-14; sem P3-7)
- GA6.7: parecer EULA externo (fora do BG-127)
- .254 vivo: 1.9.63 mode=monitor MITM OFF (20260814T034904Z-20.36-soak-align-163-254)
- Histórico e2e: .254=1.9.54 (20260811T114320Z)
```
