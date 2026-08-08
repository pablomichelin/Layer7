# ADR-0029 — Adiamento IM7 (agente endpoint) e exclusão IM8 (TS/VDI)

**Estado:** Aceito — **IM7 implementação diferida**; **IM8 excluído** (limite honesto)  
**Data:** 2026-08-08  
**Aceite:** `2026-08-08` — passo **20.28** / sequência segura pós-20.27  
**Decisores:** Operador (pedido: sequência segura; alinhado à recomendação §9 da espec 20.27)  
**Plano:** [`../02-roadmap/plano-identity-mitm-addon.md`](../02-roadmap/plano-identity-mitm-addon.md)  
**Especificação IM7 (conservada):** [`../01-architecture/especificacao-agente-endpoint-20.27.md`](../01-architecture/especificacao-agente-endpoint-20.27.md)  
**ADR pai:** [`ADR-0027-identity-userid-multi-fonte.md`](ADR-0027-identity-userid-multi-fonte.md)  
**Posicionamento:** [`../00-overview/posicionamento-pme-identity-first.md`](../00-overview/posicionamento-pme-identity-first.md)  
**Gates:** GI8.1 / GI8.2

---

## Contexto

- IM3–IM6 entregaram Identity de **rede**: mapa daemon, LDAP, RADIUS, agente DC,
  políticas `ad_*`, precedência formal, GI7 unitário.
- O passo **20.27** especificou o agente endpoint (Windows, A1–A5, heartbeat)
  e recomendou **ADIAR salvo GO humano** para MSI — o valor PME já está no
  User-ID de rede.
- TS/VDI (IM8) exige user→porta; sem isso, o mesmo IP com vários users activa
  `multi_user` → não-match `ad_*` (ADR-0027 §4.1) — comportamento seguro já
  implementado.
- Implementar frota de agentes + TS agora aumenta superfície, suporte MSP e
  risco de overclaim (“tipo GlobalProtect”) sem necessidade para fechar o
  add-on Identity de rede.

---

## Decisão

### 1. IM7 — agente endpoint (**implementação diferida**)

1. **Não** implementar MSI/serviço endpoint no produto nesta fase (20.28 =
   ADIAR, não MVP).
2. A especificação **20.27** permanece **canónica** para reabertura.
3. `L7_ID_SRC_ENDPOINT` pode existir no enum; **sem** receiver/path/GUI de
   deploy de agente endpoint activos.
4. **Reabertura IM7:** GO humano explícito + cumprir checklist S1–S7 da espec
   20.27 + GI8.1 por implementação (não por exclusão).
5. GUI Identity deve declarar honestamente: produto = **User-ID de rede**;
   **sem** agente em cada PC nesta release.

### 2. IM8 — Terminal Server / VDI (**exclusão**)

1. **Não** implementar agente TS/user→porta nesta trilha.
2. Limite honesto permanente até novo ADR: **não suportado** multi-user no
   mesmo IP para políticas `ad_*`.
3. Comportamento runtime: estado `multi_user` + não-match `ad_*` + audit
   (ADR-0027 §4.1) — **inalterado**.
4. Passos **20.29–20.30** fecham-se por este ADR (desenho = exclusão
   documentada; sem código TS).
5. **Reabertura IM8:** ADR novo + GO; não basta “ligar” endpoint.

### 3. GI8

| Critério | Cumprimento |
|----------|-------------|
| GI8.1 | **PASS por adiamento** — este ADR + GUI H* |
| GI8.2 | **PASS por exclusão** — limite multi-user documentado |

### 4. O que isto NÃO muda

- RADIUS, agente DC, LDAP, `ad_users`/`ad_groups`, mapa, fail-mode.
- MITM (ADR-0026) permanece DEFER.
- Produção enforce `1.9.8`; lab/`latest` inalterado na lógica Identity de rede.

---

## Consequências

- Sequência segura: **IM7/IM8 fechados sem código novo de agente**.  
- Próximo foco de trilha: **IM9** (fecho documental / malha / release add-on
  Identity de rede) ou lab residual GI5–GI7.  
- Comercial: não vender “agente endpoint” nem “TS identity” até reabertura.  
- Spec 20.27 não é lixo — é pré-requisito de reopen.

---

## Alternativas rejeitadas

| Alternativa | Motivo |
|-------------|--------|
| MVP MSI imediato (20.28 GO) | Custo/frota sem GO; fora da sequência segura pedida |
| Excluir IM7 permanentemente | Rejeitado — espec existe; adiamento, não enterro |
| Implementar TS sem user→porta | Inseguro (política do user errado) |
| Overclaim GlobalProtect | Viola posicionamento PME / H* |

---

## Referências

- Plano § IM7–IM8; START-HERE passo 20.28  
- ADR-0027 §2 fontes; §4.1 multi_user  
- `docs/samples/identity-dc-agent/` (padrão operacional a reutilizar se reopen IM7)

---

## Histórico

| Data | Evento |
|------|--------|
| 2026-08-08 | Aceite — 20.28 ADIAR IM7 + exclusão IM8; GI8 PASS |
| 2026-08-08 | Pacote GUI H*: candidato **`1.9.29`** |
