# ADR-0031 — Entitlement na entrega de conteúdo (token de subscrição)

**Estado:** Aceito  
**Data:** 2026-08-10  
**Aceite:** `2026-08-10` — passo **`30.1b`**; GO humano («concordo com tudo» / recomendações do plano); ficha [`../09-blocking/decisoes-humanas-30.1.md`](../09-blocking/decisoes-humanas-30.1.md) — dec. 3 = Sim (espelho; GO execução `30.11`)  
**Trilha:** Anti-pirataria / Anti-tamper (AP2 — maior valor estratégico)  
**Plano:** [`../02-roadmap/plano-antipirataria-anti-tamper.md`](../02-roadmap/plano-antipirataria-anti-tamper.md)  
**Diagnóstico:** [`../01-architecture/modelo-ameacas-antipirataria.md`](../01-architecture/modelo-ameacas-antipirataria.md) (A-06)  
**Gates:** GA4 (passos `30.8`–`30.11`)  
**Backlog:** BG-117  
**RR obrigatórios neste texto:** RR-1, RR-2, R-B, R-D

---

## Contexto

Hoje os feeds de blacklists/catálogos actualizam-se **anonimamente**, incluindo
espelho público no GitHub (achado **A-06**). Uma cópia sem licença mantém-se tão
actualizada e eficaz como uma instalação legítima, indefinidamente.

A ordem de valor da trilha (**R-B**) coloca AP2 como defesa estruturalmente
sólida: em vez de tentar “proteger o binário”, move valor para o lado do
serviço — a cópia sem subscrição **degrada sozinha** ao longo do tempo.

---

## Decisão

### 1. Token de subscrição assinado

1. O license server emite um token Ed25519 de curta validade, ligado ao
   `hardware_id`, para licenças activas.
2. Licença revogada/expirada **não** recebe token.
3. O cliente (`update-blacklists.sh` e caminho equivalente) apresenta o token
   para obter conteúdo **corrente**.
4. Detalhe de formato, armazenamento e janelas: passo documental `30.8` antes
   de qualquer código.

### 2. Conteúdo ≠ enforce (**R-D**)

1. Sem token válido: o appliance **não actualiza** conteúdo novo.
2. Conteúdo já presente **mantém-se**; enforce **não** desliga.
3. Degradação é suave e visível na GUI — nunca abrupta.
4. Conteúdo histórico pode continuar a ser servido sem token (recomendado no
   plano; a fechar em `30.8`).

### 3. Nunca fail-closed por rede (**R-C** — coordenação)

Indisponibilidade do servidor de conteúdo ou do license server **não** reduz
enforce, não para o daemon e não apaga blacklists locais (**N3**, **N4**).

### 4. Retirada do espelho público corrente (`30.11`)

Sem retirar/limitar o espelho anónimo de conteúdo **corrente**, o token é
decorativo: o pirata continua a actualizar pelo caminho público. Este passo
exige **GO humano próprio** (decisão n.º 3 do §5).

### 5. Limites e riscos residuais (obrigatório)

| Regra / RR | Declaração |
|------------|------------|
| **R-B** | AP2 é a prioridade de valor da trilha; higiene AP1 não a substitui. |
| **R-D** | Falta de token impede actualizar; nunca apaga conteúdo nem desliga enforce. |
| **RR-1** | Sem GO em `30.11` (espelho) — e, na prática comercial, sem `30.14` — a trilha entrega **higiene**, não protecção contra T1/T2. Se o GO do espelho for **Não**, o veredicto deve declarar AP2 = higiene parcial. |
| **RR-2** | Um integrador com **1 licença legítima** pode descarregar blacklists nesse appliance e re-servi-las internamente a N cópias. O token trava o download **anónimo**, não a redistribuição de ficheiros. Resposta: marcação por cliente (`30.17`) + via contratual (AP4) — **não** bloqueio técnico neste ADR. |

---

## Consequências

### Positivas

- Cópia pirata perde eficácia com o tempo (listas obsoletas).
- Alavanca comercial sem kill-switch e sem fail-closed.
- Alinhado a R-B / R-D.

### Negativas / riscos

- Alto risco de suporte se a janela offline for curta demais (`30.10`).
- Retirar o espelho (`30.11`) afecta quem depende dele hoje — exige comunicação.
- Redistribuição interna (**RR-2**) continua possível.
- Sem GO `30.11`, AP2 não protege de facto (**RR-1**).

---

## Alternativas consideradas

| Alternativa | Rejeitada porque |
|-------------|------------------|
| Continuar espelho anónimo + “confiar no binário” | A-06 permanece; AP2 seria teatro |
| Apagar blacklists sem token | Viola **R-D** / **N4**; puniria cliente offline |
| Fail-closed se o feed falhar | Viola **R-C** / **N3** |
| Tentar impedir redistribuição técnica total | Inviável sob root; resposta é **RR-2** + AP4 |

---

## Implementação prevista

- `30.8` (desenho) → `30.9` (servidor) → `30.10` (cliente) → `30.11` (GO + espelho).
- **Desenho `30.8`:** [`../01-architecture/contrato-token-subscricao-conteudo-30.8.md`](../01-architecture/contrato-token-subscricao-conteudo-30.8.md)
  (TTL 30d; histórico sem token; RR-2 explícito; zero código).
- Rollback: `.pkg` anterior + repor espelho (procedimento pronto).
- Gate GA4; critério GA4.14 exige declaração explícita de RR-2 neste ADR/desenho
  (**cumprido** neste ADR §5 + contrato `30.8` §7).

## Referências

- Plano §0.1 (RR-1, RR-2), §2 AP2, §5 decisões 1 e 3
- ADR-0005 (pipeline de blacklists) — complementar, não substituído
- Ficha [`../09-blocking/decisoes-humanas-30.1.md`](../09-blocking/decisoes-humanas-30.1.md)
