# Rascunho — Comunicação a clientes (GA4.12) · passo `30.11`

**Estado:** **HISTÓRICO / NÃO EMITIR** — gate GA4.12 marcado **N/A**
(`2026-08-12`): gestor dispensou comunicação externa (sem destinatários;
decisão interna; impacto futuro → janela de manutenção ops por e-mail).  
Rastreio: [`../09-blocking/prep-cut-30.11-espelho.md`](../09-blocking/prep-cut-30.11-espelho.md) §1.  
**Trilha:** Anti-pirataria / Anti-tamper · AP2  
**Canal / audiência:** **nenhum externo** (decisão humana)  
**Canal sugerido (proposta legada):** e-mail / portal de suporte Systemup  
**Idioma:** PT-BR

---

## Assunto (proposta)

Layer7 — alteração na actualização de blacklists (acesso autenticado)

## Corpo (proposta)

Olá,

A partir de **\<DATA_CORTE\>**, a actualização das blacklists **correntes** do
Layer7 passa a exigir uma instalação com **licença activa** e
**subscrição de conteúdo** válida (obtida automaticamente no check-in).

### O que muda

- O espelho público anónimo no GitHub (`blacklists-ut1-current`) deixará de
  servir o conteúdo **corrente**.
- Appliances com Layer7 **≥ 1.9.54**, licença activa e check-in OK continuam a
  actualizar pelo caminho oficial autenticado
  (`downloads.systemup.inf.br`), sem acção manual na maioria dos casos.

### O que **não** muda

- O firewall / modo enforce **não** é desligado por falta de actualização de
  listas.
- As blacklists **já instaladas** no appliance **mantêm-se**.
- Licenças e binding de hardware permanecem iguais.

### O que deve verificar (checklist rápido)

1. Pacote Layer7 **≥ 1.9.54**.
2. Licença válida e check-in a funcionar.
3. Em **Serviços → Layer7 → Blacklists**, a **Subscrição de conteúdo** deve
   aparecer como **OK**.
4. Se a subscrição estiver ausente/expirada: forçar check-in e voltar a
   actualizar as blacklists (ver runbook interno de subscrição de conteúdo).

### Suporte

Se após a data de corte o update de blacklists falhar mas o restante do
produto estiver normal, contacte o suporte Systemup com:

- versão do pacote (`pkg query %v pfSense-pkg-layer7`);
- estado da subscrição na GUI;
- excerto de `/var/log/layer7-bl-update.log` (sem colar tokens).

Obrigado,  
Equipa Systemup

---

## Notas internas (não enviar ao cliente)

| Item | Valor |
|------|--------|
| Gate | GA4.12 — emitir **antes** do cut |
| Rollback espelho | [`content-mirror-rollback-ga4.11.md`](content-mirror-rollback-ga4.11.md) |
| Limite honesto | Root pode contornar verificação local (R-A); redistribuição interna continua possível (RR-2) |
| Proibido na mensagem | Prometer “impossível de contornar”; mencionar fail-closed; expor tokens |
| `DATA_CORTE` | Preencher só com GO gestor + janela de transição definida |

## Checklist de emissão (operador)

**Supersedido:** GA4.12 = **N/A** — não emitir este rascunho para o cut `30.11`.

- [x] Decisão humana: sem destinatários externos (`2026-08-12`)  
- [x] Gate GA4.12 → **N/A** (prep-cut §1)  
- [ ] Só com novo GO: emitir aviso se ops abrir janela de manutenção  
- [ ] GO de cut do espelho (GA4.10 / GA4.15) — **ainda pendente**
