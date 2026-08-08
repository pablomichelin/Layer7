# Plano: Ficha Cliente 360° (navegação operador)

| Campo | Valor |
|-------|-------|
| **ID** | `PORTAL-PLAN-002` |
| **Estado** | `ACTIVO` |
| **Criado** | `2026-08-08` |
| **Baseline** | portal visual **`1.0.0`** |
| **Alvo** | portal visual **`1.2.0`** (guia) |
| **Código** | `license-server/` |
| **Live** | `192.168.100.244:/opt/layer7-license` |
| **URL** | `https://license.systemup.inf.br` |

## 1. Objectivo

Completar o ciclo **Cliente ↔ Licença** para o operador único: ver o
cliente, ver as licenças que usa, saltar para o detalhe da licença e
voltar — sem SQL e sem “adivinhar” o botão Ver.

**Não** é portal do cliente final nem MSP (IDEA-020/021).

## 2. Diagnóstico (estado real)

Já existe (código actual):

| Peça | Estado |
|------|--------|
| Rota `/customers/:id` (`CustomerDetail.jsx`) | Existe |
| API `GET /customers/:id` devolve `licenses[]` | Existe |
| Botão **Ver** na lista de clientes | Existe (pouco visível) |
| Nome do cliente no detalhe da licença | Texto plano — **sem link** |
| Clique na linha da lista | **Não** usa `onRowClick` |
| Tabela de licenças na ficha | Chave truncada; sem Bound/SKU; Ver pequeno |
| Criar licença já pré-seleccionando o cliente | **Não** |

Conclusão: a capacidade pedida **não nasce do nada** — falta fechar a
navegação e enriquecer a ficha em cima do contrato que já temos.

## 3. Fora de escopo

- Self-service / MSP / multi-tenant
- Billing, quotas, seats
- Redesign de marca (IDEA-030)
- 2FA (IDEA-031)
- Novas mutações perigosas (rebind/replace) nesta ficha — só atalhos para
  fluxos já governados no detalhe da licença

## 4. Ordem fixa (não saltar)

```text
C0 → 1.1.0   Navegação descoberta (clique + links cruzados)
C1 → 1.2.0   Ficha 360 útil (licenças ricas + nova licença no contexto)
C2 → 1.2.x   Lista clientes: resumo operacional (opcional / se C1 OK)
```

Regra: **sempre C0 antes de C1**. C2 só depois de C1.

### C0 — Navegação descoberta (`1.1.0` guia)

| # | Item | IDEA |
|---|------|------|
| C0.1 | Lista Clientes: `onRowClick` → detalhe; nome clicável | IDEA-015 |
| C0.2 | Detalhe Licença: nome do cliente → `/customers/:id` | IDEA-015 |
| C0.3 | Ficha Cliente: linha da licença clicável → `/licenses/:id` | IDEA-015 |
| C0.4 | Lista Licenças: coluna Cliente → ficha do cliente | IDEA-015 |
| C0.5 | Smoke: lista→ficha→licença→voltar ao cliente | — |

Impacto: só UX de navegação; sem mudança de contrato de negócio.
Risco: baixo.

### C1 — Ficha 360 (`1.2.0` guia)

| # | Item | IDEA |
|---|------|------|
| C1.1 | Tabela de licenças: chave completa + copiar, Bound/Unbound, SKU, status efectivo | IDEA-016 |
| C1.2 | Contadores no topo (activas / a expirar 30d / revogadas) | IDEA-016 |
| C1.3 | CTA **Nova licença** com `customer_id` pré-preenchido (query/`state`) | IDEA-016 |
| C1.4 | Doc MANUAL-USO: secção “Ver cliente e as suas licenças” | IDEA-016 |

### C2 — Lista operacional (opcional, `1.2.x`)

| # | Item |
|---|------|
| C2.1 | Colunas CNPJ/tags (já no schema) na lista |
| C2.2 | Contagem “activas” vs total (se API list já permitir ou extensão mínima) |

## 5. Versionamento

| Bloco | Versão guia |
|-------|-------------|
| C0 | `1.1.0` |
| C1 | `1.2.0` |
| C2 | `1.2.1` ou `1.3.0` se feature lista relevante |

## 6. Testes mínimos

| Bloco | Teste |
|-------|-------|
| C0 | Clique lista → ficha com licenças; link cliente na licença; health |
| C1 | Contadores coerentes com lista; criar licença com customer pré-seleccionado |
| Deploy | `rsync --exclude .env`; restart nginx; SPA `vX.Y.Z` |

## 7. Rollback

Imagens Docker anteriores; reverter commit do bloco.

## 8. Progresso

| Bloco | Estado | Versão | Data |
|-------|--------|--------|------|
| C0 | **FEITO** (GO `2026-08-08`) | `1.1.0` | 2026-08-08 |
| C1 | Pendente | → `1.2.0` | — |
| C2 | Pendente | → `1.2.x` | — |

## 9. Próxima acção

**C1 → 1.2.0** — ficha 360 (licenças ricas + nova licença no contexto).
Não saltar.
