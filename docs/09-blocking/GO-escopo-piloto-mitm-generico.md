# GO / Escopo piloto MITM — genérico e canónico (P1)

**Estado:** **ACEITE documental** `2026-08-09` (decisões humanas materializadas).  
**Bloco:** P1 do [`mapa-prontidao-mitm-piloto-2026-08-09.md`](mapa-prontidao-mitm-piloto-2026-08-09.md).  
**Tipo:** princípios de produto D1–D9 (histórico P1).  
**Ficha §3:** **RETIRADA como gate** (`2026-08-14`, [ADR-0035](../03-adr/ADR-0035-ambicao-paridade-ngfw-retirada-ficha.md)).  
**Não autoriza:** este ficheiro sozinho não liga MITM.  
**Não confunde com:** teste controlado ≤15 min ([`runbook-activacao-mitm-producao-1.9.46.md`](runbook-activacao-mitm-producao-1.9.46.md)).  
**Permanente / produção MITM sem janela:** continua **NO-GO**.  
**Runbook ops:** [`runbook-piloto-mitm-generico.md`](runbook-piloto-mitm-generico.md) (P2).  
**Pacote mín. lab/`latest`:** `1.9.46` (+ P3 failsafe quando existir).  
**ADR:** [`../03-adr/ADR-0026-mitm-tls-inspection-opt-in.md`](../03-adr/ADR-0026-mitm-tls-inspection-opt-in.md).

---

## Declaração do bloco

| Campo | Valor |
|-------|--------|
| **Objectivo** | Fixar decisões humanas do piloto controlado num SSOT auditável |
| **Impacto** | Só documentação; desbloqueia P2 (feito no mesmo commit) e requisitos de P3 |
| **Risco** | Baixo — sem mutação runtime |
| **Teste** | Checklist §6; links cruzados START-HERE/CORTEX/mapa |
| **Rollback** | Reverter commit docs |

---

## 1. Decisões humanas aprovadas (obrigatórias)

| # | Decisão | Norma canónica |
|---|---------|----------------|
| D1 | **Somente piloto controlado** | Janela com início/fim; nunca “ligar e esquecer”; ≠ permanente |
| D2 | **CA gerida pelo cliente** | CA emitida/importada sob governo do cliente; distribuição **GPO/MDM** aos endpoints do escopo |
| D3 | **Chave privada sob governança do cliente** | Privkey **fora** do git e fora de evidências; rotação e revogação são responsabilidade do cliente (procedimento no runbook P2) |
| D4 | **Somente metadados** | **Nunca** persistir conteúdo TLS decifrado; retenção padrão de metadados de eventos = **30 dias** |
| D5 | **Aprovação explícita de responsável** | Nome + data + escopo preenchido **antes** de activar; sem assinatura ⇒ NO-GO |
| D6 | **MITM OFF por defeito** | Activação só com origem **e** destino (CIDR/IP) **e** SNI (quando aplicável) **e** duração; proibido `from any` / dest aberto |
| D7 | **Excepções TLS só por allow explícito** | Bypass/allow list nomeada; **nunca** “passar” silencioso por falha de MITM |
| D8 | **Explicabilidade obrigatória no bloqueio** | Registo/UI deve permitir: domínio/URL (quando disponível), cliente, hora, política/regra, categoria/origem de lista, acção, sugestão de excepção |
| D9 | **Break-glass + desligamento automático** | Procedimento humano imediato OFF; **e** auto-disable no fim da janela (P3 implementa; até lá failsafe ops obrigatório) |

---

## 2. O que este GO autoriza vs proíbe

| Autoriza (após P2+P3+preenchimento site) | Proíbe |
|------------------------------------------|--------|
| Piloto **controlado** com janela e escopo preenchidos | MITM permanente / “sempre ON” |
| Escopo restrito origem+destino(+SNI) | Rdr `from any`, dest vazio, internet aberta |
| Trust CA via GPO/MDM do cliente | Commit de chave privada; CA “escondida” sem gov |
| Metadados ≤30 dias | Payload/decrypt em disco ou SIEM de corpo |
| Break-glass OFF imediato | Contornar TLS validation no cliente como “prova” |
| Excepções só allow explícito | Fail-open silencioso do intercept |

---

## 3. Formulário de escopo por site — **HISTÓRICO** (não é gate)

> **ADR-0035:** este bloco **já não bloqueia** activação. Conservado para
> rastreabilidade. Operação = GUI + entitlement + `source_cidr`∧`dest_cidr`.

```text
SITE / CLIENTE:     «nome»
RESPONSÁVEL (D5):   «nome + cargo»     DATA APROVAÇÃO: «YYYY-MM-DD»
PACOTE Layer7:      «ex. 1.9.46+»      ENTITLEMENT mitm: «sim/não + ref. licença»
APPLIANCE:          «hostname / IP»
INÍCIO JANELA:      «YYYY-MM-DDTHH:MMZ»
FIM JANELA:         «YYYY-MM-DDTHH:MMZ»   (duração máxima acordada)
SOURCE CIDR(s):     «ex. 10.10.20.0/24»   (lista fechada)
DEST CIDR/IP(s):    «lista fechada»
SNI / hosts:        «lista fechada ou padrão documentado»
QUIC MODE:          block (default piloto) / «outro só com nota»
ALLOW EXPLÍCITO:    «CIDR/SNI/IP — ou nenhum»
BREAK-GLASS:        «contacto + procedimento»
SUPORTE DURANTE:    «contacto / canal»
CRITÉRIO SAÍDA:     «PASS / FAIL / ABORT — métricas»
```

**Campos vazios ⇒ já não proíbem activação** (ADR-0035).

### Gate de activação externa — **RETIRADO** (`2026-08-14`)

| Campo | Valor |
|-------|--------|
| **Objectivo** | Impedir ON sem ficha nomeada |
| **Impacto** | Ops/comercial; **não** implica débito de código |
| **Risco** | Baixo (docs); alto se ignorado em campo |
| **Teste documental** | Cliente + responsáveis + SOURCE + DEST + SNI + janela + critérios de saída **todos** preenchidos |
| **Rollback** | N/A — sem ficha não há activação a reverter |

**Supersedido por ADR-0035.** Os sete campos deixaram de ser gate.
P3 failsafe permanece **no produto** (feature, não papel).

---

## 4. Limites honestos (anti-overclaim)

- Não é paridade NGFW enterprise.  
- S6 ECH = **NA/limite** (comportamento previsível; sem claim de cobertura ECH).  
- Pinning de apps pode falhar — usar allow explícito (D7) ou excluir do escopo.  
- Lab Systemup: `.234`/`.235` **proibidos** salvo GO site específico adicional.  
- Produção enforce base permanece `1.9.8` até GO enforce separado — não misturar com piloto MITM.

---

## 5. Critérios de saída do piloto (template)

| Resultado | Condição mínima |
|-----------|-----------------|
| **PASS piloto** | Escopo respeitado; auto-disable/break-glass OK; metadados só; explicabilidade verificável em amostra; rollback OFF limpo |
| **FAIL** | Rdr fora de escopo; payload persistido; fail-open; sem aprovação D5; janela ultrapassada sem disable |
| **ABORT** | Qualquer abort do runbook P2 → OFF imediato + evidência |

---

## 6. Checklist P1 (teste documental)

- [x] D1–D9 registadas neste ficheiro  
- [x] Distinção teste ≤15 min / piloto / permanente  
- [x] Formulário site (§3)  
- [x] Ligação a runbook P2  
- [x] Sem autorização de activação neste bloco  
- [x] Campos §3 **já não são obrigatórios** (ADR-0035)

---

## 7. Relação com H1–H8 do mapa

| Mapa | Estado após P1 |
|------|----------------|
| H1 GO piloto controlado | **Norma aceite** (activação ainda requer site+P3+evidência) |
| H2 Escopo | **Template canónico** (§3) |
| H3 CA GPO/MDM | **D2/D3** |
| H4 SKU mitm | Campo no formulário |
| H5 Saída | §5 |
| H6 Limites | §4 |
| H7 Hosts sensíveis | Proibição + GO adicional |
| H8 Suporte | Campo no formulário |

---

## Histórico

| Data | Nota |
|------|------|
| 2026-08-09 | P1 criado — materializa D1–D9 aprovadas pelo coordenador humano |
| 2026-08-09 | Gate activação externa explícito (ficha ≠ gap eng.); aponta critérios P3 no mapa |
| 2026-08-14 | **ADR-0035** — ficha §3 deixou de ser gate; D1–D9 = princípios de produto |
