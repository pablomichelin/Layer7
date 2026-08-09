# Plano: Gestão de técnicos com permissões

| Campo | Valor |
|-------|-------|
| **ID** | `PORTAL-PLAN-004` |
| **Estado** | `CONCLUIDO` |
| **Criado** | `2026-08-08` |
| **Fecho** | `2026-08-08` |
| **Baseline** | portal visual **`1.9.0`** |
| **Entrega** | portal visual **`2.0.0`** |
| **Código** | `license-server/` |

## Objectivo

Permitir ao owner criar técnicos e seleccionar permissões função a função.

## Ordem

```text
U0 → schema + sessão + catálogo
U1 → API /users + requirePermission
U2 → UI Utilizadores + gates → 2.0.0
```

## Progresso

| Bloco | Estado |
|-------|--------|
| Docs | FEITO |
| U0 | FEITO |
| U1 | FEITO |
| U2 | FEITO |

## Resultado

Plano **CONCLUIDO** com entrega agregada em `2.0.0`.
Fora de escopo mantido: MSP, self-service, billing, roles nomeados opacos.
