# Posicionamento de produto — Layer7 Identity-first para PME

**Classificação:** Canónico (trilha Identity + MITM)  
**Estado:** `ACEITE` (`2026-08-06` — GO operador: linha PME / Identity-first; MITM diferido)  
**Plano SSOT:** [`../02-roadmap/plano-identity-mitm-addon.md`](../02-roadmap/plano-identity-mitm-addon.md)  
**Arranque:** [`START-HERE-identity-mitm.md`](START-HERE-identity-mitm.md)  
**ADRs:** 0025 (SKU) · 0026 (**implementação MITM diferida**) · 0027 (Identity) · 0028 (daemon)  
**Spike MITM:** [`../09-blocking/spike-mitm-20.7.md`](../09-blocking/spike-mitm-20.7.md) — veredicto **DEFER (20.7a)**

Este documento congela a **ideia, o objectivo, o nicho e a barra de qualidade** do
add-on. Não substitui o plano de execução (passos 20.x); **orienta** cada passo
para o cliente PME/MSP.

---

## 1. Ideia central (uma frase)

> **Layer7 para PME = controlo de internet por pessoa (utilizador/grupo) no
> pfSense que a empresa já tem**, com políticas simples, licença clara e
> experiência utilizável por TI interno ou MSP — **sem** pretender ser um NGFW
> enterprise (Fortinet / Palo Alto / Check Point).

---

## 2. Objectivo de produto

| # | Objectivo | Medida de sucesso |
|---|-----------|-------------------|
| O1 | Entregar **políticas por utilizador/grupo AD** (e fontes de sessão) no mesmo `.pkg` | GI4–GI7 PASS; demo A vs B |
| O2 | Manter o produto base `1.9.8` **intocado** com add-on OFF | Smoke não-regressão a cada passo |
| O3 | Ser **instalável e operável** por PME/MSP em tempo curto (ordem de uma tarde para lab típico) | Docs + GUI sem jargão NGFW; wizards/testes de ligação |
| O4 | Comunicar **limites honestos** (User-ID de rede, NAT, QUIC, HTTPS sem MITM) | Textos GUI + MANUAL; sem overclaim |
| O5 | Monetizar add-on **Y = Identity** (e Y+ MITM só quando existir implementação) | SKU ADR-0025; upsell sem activar runtime |
| O6 | **Não** competir em paridade TLS/decrypt com NGFW enterprise | MITM diferido; sem Squid; sem promessa de ASIC |

---

## 3. Nicho e anti-nicho

### 3.1 Nicho alvo (sim)

| Segmento | Perfil típico | Dor |
|----------|---------------|-----|
| **PME** | ~20–200 postos; AD ou Wi‑Fi com RADIUS; um firewall pfSense | “O João não pode aceder a X”; IP/MAC não escala |
| **MSP / Systemup** | Vários clientes CE/Plus; quer um `.pkg` + SKU | NGFW caro demais / overkill; precisa de add-on vendável |
| **TI interno PME** | Pouca gente; precisa de GUI clara | Não quer Panorama/FortiManager |

### 3.2 Anti-nicho (não é o produto)

| Segmento | Porquê fora |
|----------|-------------|
| Grande empresa / datacenter | Contrata NGFW caro com decrypt TLS e hardware |
| Quem exige paridade App-ID/URL de milhares de apps | Fora de escopo desta trilha |
| Quem exige MITM universal de todo o HTTPS | Diferido; quando existir = selectivo + opt-in |
| Quem quer captive portal Layer7 | Usar captive nativo do pfSense |

### 3.3 Princípio de mercado (acordado `2026-08-06`)

- **“Já existe NGFW”** não impede o produto: o NGFW **não resolve** bem o
  nicho pfSense/PME/MSP com preço e ops simples.
- **Ir ao contrário da desistência** = atacar o nicho mal servido; **não** =
  copiar o motor TLS da Fortinet.
- Inovação = **combinação** (Identity + DPI + PF + licenciamento + um pacote) +
  **experiência** (GUI/docs perfeitos para empresa pequena/média).

---

## 4. Promessa ao cliente (copy canónico)

**Curta:**

> Controle quem na sua rede pode aceder a quê — por utilizador e grupo — no
> pfSense, sem trocar por um NGFW enterprise.

**Com honestidade:**

> O Layer7 Identity usa o mapa dinâmico utilizador↔IP (estilo User-ID de rede).
> Não é um agente em cada PC (fase posterior). Sem MITM activo, o bloqueio
> HTTPS continua alinhado à página HTTP/DNS (ADR-0017). Limites de NAT,
> Wi‑Fi partilhado e VDI estão documentados na interface.

**O que NÃO dizer:**

- “Somos iguais à Fortinet / Palo Alto.”
- “Desencriptamos todo o HTTPS.”
- “Exactidão GlobalProtect sem agente.”
- “Zero configuração.”

---

## 5. Arquitectura de valor (o que construímos)

```text
                    ┌─────────────────────────────────────┐
                    │  PME / MSP no pfSense                │
                    │  SKU: base | identity | (mitm depois)│
                    └─────────────────┬───────────────────┘
                                      │
          ┌───────────────────────────┼───────────────────────────┐
          ▼                           ▼                           ▼
   ┌─────────────┐          ┌──────────────────┐        ┌─────────────────┐
   │ Base estável│          │ IDENTITY (agora) │        │ MITM (diferido) │
   │ DPI+PF+DNS  │          │ user↔IP + LDAP   │        │ CA + intercept  │
   │ block HTTP  │          │ RADIUS + agente  │        │ selectivo       │
   │ UT1         │          │ políticas ad_*   │        │ só após novo GO │
   └─────────────┘          └──────────────────┘        └─────────────────┘
          ▲                           │
          │                           ▼
          └──────────── Políticas allow/block/monitor ────┘
                        (mesmo motor; origem dos IPs = mapa)
```

| Camada | Estado na trilha | Papel para PME |
|--------|------------------|----------------|
| Base | Produção `1.9.8` (+ canal lab `1.9.13`) | Continua a funcionar sozinha |
| Entitlements | IM1 **PASS** | Venda X/Y sem bypass de UI |
| Identity | **Caminho de valor** IM3–IM6 | Centro do produto PME |
| MITM | **DEFER 20.7a** | SKU reservado; sem runtime; sem bloquear Identity |
| Agente endpoint / TS | IM7–IM8 adiáveis | Exactidão extra; não bloqueia MVP PME |

---

## 6. Barra de qualidade — “perfeito para empresas usarem”

Cada entrega Identity **deve** satisfazer estes critérios de produto (além dos
gates GI). São obrigatórios na revisão de passo; falhar = não fechar o passo.

### 6.1 Utilizabilidade (UX)

| ID | Critério | Detalhe |
|----|----------|---------|
| U1 | Linguagem de empresa | Evitar jargão interno (IM3, GI4) na GUI; preferir “Mapa de utilizadores”, “Política por grupo AD” |
| U2 | Estados visíveis | GUI mostra: entitlement ON/OFF, mapa vazio/parcial/ok, fonte LDAP/RADIUS/DC, últimos erros **sem** secrets |
| U3 | Testes de ligação | Botão “Testar LDAP”, “Ver último accounting RADIUS”, “Estado do agente DC” antes de confiar na política |
| U4 | Defaults seguros | Tudo OFF; activar Identity exige entitlement + toggle explícito |
| U5 | Upsell claro | Sem entitlement: mensagem comercial curta + o que falta; **zero** runtime |
| U6 | Ajuda contextual | Cada ecrã crítico com 1–3 frases de “quando usar” + link doc |
| U7 | Tempo até valor | Fluxo documentado: licença Y → LDAP → uma fonte de sessão → uma política de grupo → prova A vs B |

### 6.2 Operação PME / MSP

| ID | Critério | Detalhe |
|----|----------|---------|
| P1 | Um pacote | Sem segundo `.pkg` nesta trilha |
| P2 | Rollback trivial | Toggle OFF / `.lic` só `base` / reinstalar baseline |
| P3 | Sem segredos no git | Bind AD, RADIUS secret, CA — só appliance |
| P4 | Logs úteis | Eventos de mapa (add/expire/conflict) sem passwords; rotação alinhada ao produto |
| P5 | Dual-stack | IPv4+IPv6 no mapa (herança trilha IPv6 fechada) |
| P6 | Topologia tipica PME | Docs para: LAN flat, AD no LAN, Wi‑Fi com RADIUS; aviso se IP AD ≠ IP firewall |

### 6.3 Honestidade e confiança

| ID | Critério | Detalhe |
|----|----------|---------|
| H1 | User-ID de rede | Label explícito até existir agente (IM7) |
| H2 | multi-user / NAT | Estado `multi-user` → não aplicar `ad_*` ao user errado |
| H3 | Fail-mode LDAP | Cache TTL; depois não-match `ad_*`; **nunca** fechar a LAN toda |
| H4 | HTTPS sem MITM | Continua ADR-0017; texto na GUI Identity/MITM |
| H5 | Sem overclaim NGFW | Posicionamento §3–§4 obrigatório em materiais comerciais internos |

### 6.4 Não-regressão (empresa já em produção)

| ID | Critério | Detalhe |
|----|----------|---------|
| N1 | Base OFF = `1.9.8` | Smoke permanente (plano §6) |
| N2 | Zero threads Identity OFF | ADR-0028 |
| N3 | Políticas IP/MAC antigas | Inalteradas com Identity ON |

---

## 7. O que entra no MVP PME (fecho mínimo comercial Identity)

**Inclui (IM3–IM6 + docs):**

1. Mapa user↔IP no daemon (TTL, N IPs, diagnóstico).  
2. LDAP/LDAPS + cache + fail-mode.  
3. ≥1 fonte de sessão canónica (RADIUS accounting **ou** agente DC); idealmente ambas no plano completo.  
4. Políticas `ad_user` / `ad_group` no motor first-match.  
5. GUI + MANUAL + limites honestos.  
6. Entitlement `identity` gate real.

**Não inclui no MVP PME (mas ficam no plano como fases):**

- MITM / CA / block page HTTPS via decrypt (DEFER).  
- Agente endpoint em cada Windows (IM7).  
- TS/VDI multi-user no mesmo IP (IM8).  
- SIEM / consola multi-firewall.  
- Captive portal Layer7.

---

## 8. MITM neste posicionamento (resumo)

| Decisão | Valor |
|---------|-------|
| Data | `2026-08-06` |
| Squid / pfSense-pkg-squid | **REJEITADO** (operador: não é caminho habilitado) |
| Paridade NGFW TLS | **Fora** do objectivo PME |
| Runtime MITM agora | **Não** — DEFER formal 20.7a |
| Token `mitm` no SKU | Pode existir; **sem código activo** |
| Futuro | Helper próprio (`layer7-tlsproxy` ou equivalente) só com **novo GO** + spike S1–S8; selectivo; opt-in |
| Identity | **Avança já** (IM3+) |

Detalhe técnico: ADR-0026 + `spike-mitm-20.7.md`.

---

## 9. Modelo comercial (espelho ADR-0025)

| SKU | Entitlements | Para quem |
|-----|--------------|-----------|
| Standard (X) | `base` | PME que só precisa do Layer7 actual |
| Identity (Y) | `base,identity` | **Oferta principal PME** — “por pessoa” |
| Identity + MITM (Y+) | `base,identity,mitm` | Futuro; quando MITM deixar de estar diferido |
| MITM only | `base,mitm` | Opcional futuro; baixa prioridade vs Y |
| Legado | `full` → `base` (T1) | Sem auto-add-on |

Preços: decisão comercial externa ao repo.

---

## 10. Ordem de execução alinhada a este posicionamento

```text
IM0 → IM1 (feitos)
  → 20.7a DEFER MITM (formal — este GO)
  → IM3 mapa daemon (20.11a baseline → 20.12–20.15)
  → IM4 LDAP
  → IM5 fontes sessão
  → IM6 políticas ad_*
  → IM7–IM8 adiáveis
  → IM9 fecho / release Identity
  → (MITM só com novo GO de investimento)
```

**Proibido por este posicionamento:**

- Bloquear Identity à espera de MITM.  
- Implementar Squid como caminho de produto.  
- Prometer paridade NGFW em materiais.  
- Activar Identity/MITM por defeito em upgrade.

---

## 11. Checklist de revisão de passo (colar no PR/commit doc)

Antes de marcar um passo 20.x Identity como PASS:

- [ ] Objectivos O1–O6 não violados  
- [ ] Critérios U*/P*/H*/N* aplicáveis ao passo satisfeitos ou adiados com nota  
- [ ] Gates GI do passo  
- [ ] START-HERE + plano §0 + CORTEX actualizados  
- [ ] Nenhum overclaim na GUI  
- [ ] Rollback descrito  

---

## 12. Histórico

| Data | Evento |
|------|--------|
| 2026-08-06 | Criação — GO operador: nicho PME/MSP; Identity-first; MITM DEFER; barra “perfeito para empresas usarem” |
| 2026-08-05–06 | Contexto prévio: Squid rejeitado; estimativa MITM próprio = meses; NGFW = segundo produto |
