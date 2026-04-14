# F3.11 - Start Here

## Estado actual (actualizado em 2026-04-14)

- `F3 continua aberta`
- `F3.11 alinhada no license-server live`
- Inventario real obtido (4 licencas)
- Sessao stateful + Bearer bridge funcionam no live
- Same-origin administrativo voltou a falhar fechado no live
- DR-02 resolvido (drift cosmetico 403 vs 409, logica correcta)
- Blocker F3 restante: DR-05 (cenarios do appliance)
- Baseline read-only mais recente do appliance:
  `20260414T123526Z-appliance254-permissions`

---

## O que ja foi provado

1. Login no live funciona (`pablo@systemup.inf.br`)
2. `/api/auth/session` funciona e a bridge Bearer administrativa tambem
   funciona para endpoints autenticados
3. Inventario real: 4 licencas (IDs 5, 6, 7, 8)
4. Appliance existe e funciona (192.168.100.254), com daemon vivo confirmado
   por processo/stats apesar de falso negativo em `service layer7d status`
5. Host live acessivel (192.168.100.244)
6. PostgreSQL live com `admin_sessions`, `admin_audit_log` e
   `admin_login_guards`
7. `POST /api/activate` no live rejeita hw diferente, revogada e expirada
   (com 403 em vez de 409 do repo — drift cosmetico, logica correcta);
   aceita reactivacao legitima com 200
8. `POST` e `OPTIONS` em `/api/auth/login` com `Origin` externo agora
   respondem `403` fail-closed

---

## O que falta para fechar a F3

1. **Fechar DR-05**: executar cenarios do appliance (snapshot/restore,
   offline/online, NIC/UUID) — roteiro completo e comandos no prompt
   [`docs/07-prompts/f3-prompt-continuacao-2026-04-03.md`](../07-prompts/f3-prompt-continuacao-2026-04-03.md)
   (secao DR-05) e em
   [`docs/01-architecture/f3-fecho-operacional-restante.md`](../01-architecture/f3-fecho-operacional-restante.md)
2. Consolidar evidencias e decidir fecho da F3

---

## Documentos de referencia

- [`../01-architecture/f3-fecho-operacional-restante.md`](../01-architecture/f3-fecho-operacional-restante.md)
- [`../01-architecture/f3-11-readiness-scorecard.md`](../01-architecture/f3-11-readiness-scorecard.md)
- [`../01-architecture/f3-11-drift-registry.md`](../01-architecture/f3-11-drift-registry.md)
- [`../01-architecture/f3-11-execution-master-register.md`](../01-architecture/f3-11-execution-master-register.md)
