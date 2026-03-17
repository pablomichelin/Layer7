# Plano de Documentação e GitHub

## 1. Objetivo

Definir quais documentos existem, quando são atualizados e qual papel cada um cumpre.

---

## 2. Documentos obrigatórios do repositório

### Na raiz
- `README.md`
- `AGENTS.md`
- `CORTEX.md`
- `LICENSE`

### Em `docs/`
- charter do produto
- arquitetura
- roadmap
- ADRs
- testes
- runbooks
- releases
- prompts
- topologia de lab

---

## 3. Documentos obrigatórios por fase

## Fase 0
- escopo V1
- não objetivos
- definição de pronto

## Fase 1
- topologia de lab
- builder notes

## Fase 2
- resultados da PoC
- tabela de detecção

## Fase 3
- modelo de evento
- modelo de política

## Fase 4/5
- package structure
- daemon design

## Fase 6
- enforcement design

## Fase 7
- guia da GUI

## Fase 8/9
- test plan
- release checklist

## Fase 10/11
- release notes
- rollback notes
- operação

---

## 4. CORTEX.md como SSOT

O `CORTEX.md` deve conter sempre:
- status atual;
- fase atual;
- última entrega;
- próximos 3 passos;
- riscos ativos;
- bugs relevantes;
- decisões congeladas;
- itens adiados;
- comandos úteis;
- observações do lab.

Sem isso, o projeto perde memória.

---

## 5. AGENTS.md

O `AGENTS.md` deve orientar qualquer IA usada no Cursor a:
- respeitar o escopo;
- executar um bloco por vez;
- não fazer refactor global sem pedido;
- atualizar docs;
- preservar runbook;
- gerar mudanças pequenas e testáveis.

---

## 6. ADRs

Criar ADR para decisões que mudam o rumo do projeto.

Formato sugerido:
- contexto
- decisão
- consequências
- status

---

## 7. Changelog

Toda release deve registrar:
- o que entrou;
- o que saiu;
- o que mudou;
- impactos;
- observações de compatibilidade.

---

## 8. GitHub Issues

Separar issues por rótulo:
- `phase`
- `bug`
- `enhancement`
- `docs`
- `build`
- `gui`
- `core`
- `policy`
- `release`
- `tech-debt`

---

## 9. Pull Requests

Todo PR precisa informar:
- objetivo;
- escopo;
- risco;
- testes;
- docs atualizadas;
- rollback.

---

## 10. GitHub Projects

Recomendado usar colunas:
- backlog
- ready
- in progress
- review
- blocked
- done

---

## 11. Política de releases

Nunca publicar release sem:
- tag;
- changelog;
- docs atualizadas;
- instrução de instalação;
- instrução de rollback.

