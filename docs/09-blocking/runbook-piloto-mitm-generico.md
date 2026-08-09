# Runbook — piloto MITM controlado (genérico) (P2)

**Estado:** **Canónico ops** `2026-08-09` — documentação; **sem** activação neste bloco.  
**Bloco:** P2 do [`mapa-prontidao-mitm-piloto-2026-08-09.md`](mapa-prontidao-mitm-piloto-2026-08-09.md).  
**Escopo / GO:** [`GO-escopo-piloto-mitm-generico.md`](GO-escopo-piloto-mitm-generico.md) (P1 — D1–D9).  
**Distinto de:** teste ≤15 min ([`runbook-activacao-mitm-producao-1.9.46.md`](runbook-activacao-mitm-producao-1.9.46.md)).  
**Pré-requisito pacote:** `1.9.46` mínimo; **P3 (failsafe código)** obrigatório antes de janela > teste curto.  
**Proibido neste documento:** senhas, chaves privadas, activar hosts sem formulário P1 preenchido.

---

## Declaração

| Campo | Valor |
|-------|--------|
| **Objectivo** | Procedimento ops repetível para piloto controlado alinhado a D1–D9 |
| **Impacto** | Só docs até haver GO site + P3 + activação explícita |
| **Risco** | Baixo (docs); activação futura = médio/alto mitigado por escopo |
| **Teste** | Walkthrough documental §11; cruzamento com P1 e runbook teste |
| **Rollback** | Reverter commit; runtime: §9 |

---

## 0. Pré-condições (NO-GO se falhar)

> **Gate de activação (não é lacuna de engenharia):** sem cliente, responsáveis, SOURCE, DEST, SNI, janela e critérios de saída **nomeados**, a activação externa é **NO-GO** — independentemente do estado do código P3/`1.9.46`.

1. P1 formulário §3 **completo** + assinatura responsável (D5).  
2. Entitlement `mitm` válido no site.  
3. Pacote ≥ `1.9.46` (SHA verificado).  
4. **P3 failsafe código** disponível **ou** failsafe ops dual (cron/`at` + vigilância humana) documentado na ficha do site — janelas longas **sem** auto-disable = NO-GO.  
5. MITM actualmente **OFF**; zero rdr/tabelas mitm órfãs; GUI/SSH/NET saudáveis.  
6. Listas SOURCE / DEST / SNI / ALLOW explícitas (D6/D7) — nenhuma implícita.  
7. Política de metadados 30 dias comunicada (D4).

---

## 1. Papéis

| Papel | Responsabilidade |
|-------|------------------|
| Responsável piloto (D5) | Aprova escopo e duração; autoriza ON/OFF |
| Ops cliente | CA, GPO/MDM, rotação/revogação, endpoints |
| Ops appliance | Activação escopada, monitorização, break-glass, evidência |
| Suporte | Canal durante a janela (campo P1) |

---

## 2. CA e trust (D2 / D3)

### 2.1 Princípios

- CA **do cliente** (gerar ou importar conforme processo interno).  
- Chave privada: acesso mínimo; **nunca** git, tickets públicos, evidências Layer7, nem chat.  
- Distribuição do certificado CA (público) via **GPO e/ou MDM** só aos dispositivos do SOURCE escopo.  
- Rotação: planear validade; reemitir antes de expirar; revogar se compromisso.  
- Fim do piloto: remover trust GPO/MDM **ou** documentar retenção deliberada pós-piloto (raro; exige nota do responsável).

### 2.2 Checklist CA (ops cliente)

```text
[ ] CA criada/importada sob governação do cliente
[ ] Privkey armazenada (HSM/cofre) — path interno NÃO neste repo
[ ] Cert CA (.crt) exportado para GPO/MDM
[ ] GPO/MDM aponta só a OUs/grupos do escopo SOURCE
[ ] Thumbprint/SHA256 do CA público registado na ficha do site
[ ] Plano de rotação / revogação anexado (data próxima revisão)
[ ] Prova num endpoint piloto: trust OK sem --ignore-certificate-errors
```

### 2.3 Appliance

- Importar **apenas** o material necessário ao produto (conforme GUI MITM / docs pacote).  
- Não duplicar privkey em sítios não autorizados.  
- Preferir CA cliente; CA efémera do teste 15 min **não** é modelo de piloto.

---

## 3. Metadados e privacidade (D4)

| Regra | Valor |
|-------|--------|
| Conteúdo TLS decifrado em disco | **Proibido** |
| Logging | Metadados de decisão/evento (host/SNI, cliente, tempo, política, acção) |
| Retenção padrão | **30 dias** (apagado ou rotacionado após) |
| Export SIEM | Só metadados; contrato com o cliente se <30 ou >30 dias |
| Evidência git | Sem payloads; sem privkeys; screenshots sem dados pessoais desnecessários |

---

## 4. Activação (só com P1 preenchido + P3)

Ordem segura (resumo; detalhe de comandos = ficha do site + MANUAL da versão):

1. Backup `config.xml` + `layer7.json`.  
2. Confirmar MITM OFF / sem rdr / sem `:8443`.  
3. Armar **failsafe fim de janela** (P3 auto-disable **e/ou** job ops + alarme).  
4. Aplicar `source_cidr` + `dest_cidr` + SNI/block list + **allow explícito** (se houver).  
5. `quic_mode=block` (default piloto) salvo nota P1.  
6. `mitm.enabled=true` → verificar `mitm_effective=true` **e** rdr `from <mitm_src> to <mitm_dst>` (nunca `from any`).  
7. Anti-QUIC só no mesmo escopo src→dst.  
8. Smoke mínimo **dentro do escopo** (browser real, sem bypass TLS).  
9. Registar hora ON + thumb CA + responsável.

**Abort imediato** se qualquer item da §8 falhar.

---

## 5. Excepções TLS (D7)

| Permitido | Proibido |
|-----------|----------|
| Entrada explícita em allow/bypass (CIDR, IP, SNI/host) | “Deixar passar” porque MITM falhou |
| Documentar motivo + aprovador na ficha | Allow temporário sem registo |
| Remover allow no fim da janela (default) | Fail-open global |

Fluxo sugerido: utilizador reporta → ops propõe allow mínimo → responsável aprova → aplica → regista → reavalia no fim do piloto.

---

## 6. Explicabilidade do bloqueio (D8)

Todo bloqueio relevante do piloto deve ser reconstruível com:

| Campo | Obrigatório |
|-------|-------------|
| Domínio / URL / SNI | Quando disponível |
| Cliente (IP / identidade se houver) | Sim |
| Hora (UTC + local site) | Sim |
| Política / regra / perfil | Sim |
| Categoria ou origem de lista | Quando aplicável |
| Acção (`block` / outra) | Sim |
| Sugestão de excepção | Sim (allow candidato mínimo ou “fora de escopo”) |

Amostra de N eventos (ex. 5–10) deve ser anexável à evidência de saída do piloto **sem** payload.

> **Nota P3/GUI:** se algum campo ainda não estiver exposto na UI da versão instalada, o ops regista manualmente a partir de eventos/logs de metadados até o código cobrir — **não** inventar dados.

---

## 7. Monitorização durante a janela

Intervalo sugerido: início, a cada «N» horas (ficha), e antes do fim.

```text
[ ] mitm_effective == true só se dentro da janela
[ ] rdr e anti-QUIC ainda scoped (grep from any = ABORT)
[ ] :8443 só enquanto ON
[ ] GUI / SSH / Internet gestão OK
[ ] CPU/latência dentro do razoável vs baseline site
[ ] Zero indício de payload em disco
[ ] Contadores/eventos de metadados a crescer de forma esperada
[ ] Tempo restante até auto-disable conhecido
```

---

## 8. Break-glass (D9) — OFF imediato

Qualquer operador autorizado na ficha:

1. `mitm.enabled=false` (ou equivalente GUI) + reload/resync canónico da versão.  
2. Confirmar `mitm_effective=false`, tlsproxy parado, rdr/anti-QUIC ausentes, tabelas mitm vazias/ausentes.  
3. GUI/SSH/NET OK.  
4. Notificar responsável + suporte.  
5. Registar motivo e timestamp na evidência.  
6. **Não** reactivar sem nova aprovação D5.

Palavra de ordem: **preferir OFF indevido a ON fora de escopo**.

---

## 9. Fim de janela / desligamento automático (D9)

### 9.1 Automático (obrigatório para piloto)

- Preferência: **P3** `max_window` / auto-disable no produto.  
- Até P3: dual control ops (`at`/cron + segundo humano) com margem (ex. disable 1–2 min antes do fim nominal).

### 9.2 Cleanup canónico

```text
[ ] MITM OFF + effective false
[ ] Listas intercept vazias ou revertidas ao pré-piloto
[ ] Allow explícitos do piloto removidos (salvo decisão escrita)
[ ] tlsproxy parado; sem :8443
[ ] Sem rdr/anti-QUIC Layer7
[ ] Failsafe jobs cancelados
[ ] Trust GPO/MDM: remover ou justificar retenção
[ ] Metadados: retenção 30 dias em vigor (sem export de corpo)
[ ] Evidência de saída (PASS/FAIL/ABORT) arquivada sem segredos
[ ] Formulário P1 arquivado com resultado §5
```

---

## 10. Abort criteria (durante piloto)

1. Rdr ou QUIC fora do escopo / `from any`.  
2. Tráfego fora de SOURCE/DEST a ser interceptado.  
3. Detecção de payload decifrado persistido.  
4. Fail-open / excepção não explícita.  
5. Janela expirada sem disable.  
6. Sem aprovação D5 ou alteração de escopo sem reaprovação.  
7. Impacto grave em gestão (GUI/SSH) ou WAN crítica.  
8. Necessidade de bypass TLS no cliente para “fazer funcionar”.

→ Break-glass (§8) + resultado **ABORT**.

---

## 11. Teste documental deste runbook (P2)

- [x] Cobre D1–D9 do P1  
- [x] Separado do runbook teste 15 min  
- [x] CA cliente + GPO/MDM + privkey  
- [x] Metadados 30 dias; sem decrypt  
- [x] Allow só explícito  
- [x] Explicabilidade  
- [x] Break-glass + auto-disable  
- [x] Cleanup e abort  
- [x] Não activa hosts neste bloco  

---

## 12. Próximo bloco

**P3 — código:** **PASS** (`1.9.47`).  
**P4 soak lab:** **IN_PROGRESS** — evidência [`../tests/evidence/20260809T234042Z-p4-soak-254/`](../tests/evidence/20260809T234042Z-p4-soak-254/) (Phase C interna PASS; Skip≠abort — só recusa `example.com`).  
**P5:** aguarda **ficha de site de cliente** — **não** activar piloto externo nem permanente sem P5 PASS + GO humano.

---

## Histórico

| Data | Nota |
|------|------|
| 2026-08-09 | P2 criado — runbook genérico alinhado a P1 D1–D9 |
| 2026-08-09 | Nota gate activação: ficha incompleta ≠ débito eng. |
| 2026-08-09 | P4 soak retomado `234042Z` (Phase C interna; Skip≠abort); P5 bloqueado à ficha |
