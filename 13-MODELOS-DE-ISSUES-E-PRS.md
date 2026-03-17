# Modelos de Issues e PRs

## 1. Modelo de issue de feature

```md
# Título
[FEATURE] Nome curto da funcionalidade

## Objetivo
O que precisa ser criado?

## Contexto
Por que isso é necessário agora?

## Escopo
O que entra?
O que não entra?

## Critérios de aceite
- [ ]
- [ ]
- [ ]

## Impactos esperados
- Core
- GUI
- Package
- Docs
- Tests

## Riscos
Quais riscos existem?

## Rollback / saída segura
Como desfazer ou isolar?
```

---

## 2. Modelo de issue de bug

```md
# Título
[BUG] Nome curto do problema

## Ambiente
Versão do pacote:
Versão do pfSense CE:
Lab/produção:

## Sintoma
O que está acontecendo?

## Comportamento esperado
O que deveria acontecer?

## Passos para reproduzir
1.
2.
3.

## Evidências
Logs:
Screenshots:
Observações:

## Severidade
Crítico / Alto / Médio / Baixo

## Impacto
Quem é afetado?

## Workaround
Existe contorno?

## Hipótese inicial
Opcional
```

---

## 3. Modelo de ADR

```md
# ADR-XXXX - Título

## Status
Proposto / Aceito / Substituído / Obsoleto

## Contexto
Qual problema estamos resolvendo?

## Decisão
O que foi decidido?

## Consequências
O que isso melhora?
O que isso complica?
```

---

## 4. Modelo de PR

```md
# Objetivo
O que este PR entrega?

## Escopo
O que entra?
O que não entra?

## Arquivos principais
- 
- 
- 

## Testes executados
- [ ]
- [ ]
- [ ]

## Evidências
Logs / prints / notas

## Risco
Baixo / Médio / Alto

## Rollback
Como desfazer?

## Docs atualizadas?
- [ ] Sim
- [ ] Não (justificar)

## Checklist
- [ ] Código revisado
- [ ] Sem segredo commitado
- [ ] Docs atualizadas
- [ ] CORTEX atualizado
- [ ] Critérios de aceite atendidos
```

