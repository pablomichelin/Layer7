# ADR Index

## Finalidade

Os ADRs registam decisoes que mudam o rumo do projecto. Eles existem para
evitar que escolhas estruturais, de seguranca, de distribuicao ou de
operacao fiquem apenas “na conversa”.

Criar ADR quando a mudanca afectar:

- arquitectura do produto;
- cadeia de confianca e seguranca;
- distribuicao e artefactos;
- licenciamento;
- organizacao estrutural do repositório;
- operacao ou rollback de forma duradoura.

---

## Estado dos ADRs existentes

| ID | Titulo | Estado | Papel actual |
|----|--------|--------|--------------|
| [ADR-0001](ADR-0001-engine-classificacao-ndpi.md) | Engine de classificacao Layer 7 — nDPI | Aceito | decisao canónica e congelada |
| [ADR-0002](ADR-0002-distribuicao-artefato-txz.md) | Distribuicao V1 — artefacto `.txz` | Historico / precisa de substituicao | nao deve ser usado como referencia actual para distribuicao publica |

**Nota importante:** a distribuicao actual conhecida do projecto usa `.pkg`.
O ADR-0002 fica preservado por rastreabilidade, mas ja nao representa a
realidade operacional actual.

---

## Ciclo de vida de um ADR

- `Proposto` -> ainda em discussao
- `Aceito` -> decisao valida e aplicavel
- `Substituido` -> trocado por ADR posterior
- `Historico` -> preservado para rastreabilidade, sem valor normativo actual

Sempre que um ADR nascer, actualizar tambem:

- este indice;
- `CORTEX.md` se a decisao mudar fase, risco ou estado global;
- backlog e roadmap se a decisao alterar ordem ou gate.

---

## ADRs recomendados para nascer de imediato

| Proximo ID sugerido | Tema | Fase | Motivo |
|---------------------|------|------|--------|
| ADR-0003 | Cadeia de confianca entre repo, builder, chaves e artefacto | F1 | fechar o maior risco de governanca tecnica actual |
| ADR-0004 | Artefacto oficial de distribuicao e verificacao (`.pkg`) | F1/F7 | substituir a ambiguidade historica do ADR-0002 |
| ADR-0005 | Fronteira operacional e hardening do license server | F2 | formalizar segredos, TLS, backup e isolamento |
| ADR-0006 | Modelo de estados do licenciamento e activacao | F3 | tornar activacao/revogacao/offline previsiveis |
| ADR-0007 | Reorganizacao estrutural controlada do repositório | F6 | garantir que mover ficheiros nao destrua contexto |

---

## Regra pratica

Se a mudanca for suficientemente importante para:

- alterar uma restricao congelada;
- mudar a forma de distribuir, activar ou validar o produto;
- introduzir uma nova fronteira de confianca;
- mover estrutura com impacto amplo;

entao ela pede ADR.
