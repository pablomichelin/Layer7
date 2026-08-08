# Checklist — Portal Admin de Licenças

Usar **por bloco** / por versão. Copiar secção para o fim do ficheiro ao
fechar uma versão (ou referenciar em `historico/`).

---

## Checklist permanente (todo bloco)

- [ ] Li `portal/README.md` + `GOVERNANCE.md`
- [ ] Objectivo / impacto / risco / teste / rollback declarados
- [ ] Bloco pequeno e alinhado ao plano `ACTIVO`
- [ ] Sem abrir escala/MSP/billing sem GO
- [ ] `ACOES.md` actualizado
- [ ] Se UI mudou: `CHANGELOG.md` (+ bump `VERSION.md` se aplicável)
- [ ] Se live mudou: `ESTADO.md` actualizado
- [ ] Não toquei serviços alheios no host `244`
- [ ] Docs canónicos globais (CORTEX / manuais) actualizados **só se** o
      bloco os afectar

---

## Gates para `0.1.0` (P0)

- [ ] Frontend rebuild + deploy; SPA alinhada à API
- [ ] Versão visual visível na UI (`0.1.0`)
- [ ] Modal/fluxo pós-criação com chave + copiar
- [ ] Lista: copiar chave, Bound/Unbound, SKU, filtros expiry/cliente
- [ ] Dashboard: a expirar 30d + expiradas efectivas
- [ ] Política/procedimento `full` → `base` documentado e aplicado ou
      agendado com evidência
- [ ] `VERSION.md` = `0.1.0` + entrada em `CHANGELOG.md`
- [ ] `ESTADO.md` com datas de imagens pós-deploy

---

## Gates para `1.0.0` (operador único completo)

Ver lista em [`OBJECTIVOS.md`](OBJECTIVOS.md) — critérios de `1.0.0`.

---

## Checklist de deploy (`244`)

- [ ] Backup Postgres (`backup-postgres.sh` / runbook)
- [ ] Pull/sync código autorizado
- [ ] `docker compose build` dos serviços afectados
- [ ] `docker compose up -d` sem afectar outras stacks
- [ ] Health `https://license.systemup.inf.br/api/health`
- [ ] Login admin + smoke: listar licenças / criar em lab se aplicável
- [ ] Actualizar `ESTADO.md`
