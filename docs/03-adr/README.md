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
| [ADR-0002](ADR-0002-distribuicao-artefato-txz.md) | Distribuicao V1 — artefacto `.txz` | Historico / substituido na pratica | preservado por rastreabilidade; nao deve ser usado como referencia actual |
| [ADR-0003](ADR-0003-hierarquia-oficial-de-distribuicao.md) | Hierarquia oficial de distribuicao | Aceito | define `.pkg` como artefacto oficial, a hierarquia builder -> release -> instalacao e o estatuto do legado `.txz` |
| [ADR-0004](ADR-0004-cadeia-de-confianca-dos-artefatos.md) | Cadeia de confianca dos artefactos | Aceito | define checksum, assinatura, papeis de geracao/validacao e tratamento de builder suspeito |
| [ADR-0005](ADR-0005-pipeline-seguro-de-blacklists.md) | Pipeline seguro de blacklists | Aceito | define origem oficial, requisitos de HTTPS, mirror/cache e politica de rejeicao/degradacao |
| [ADR-0006](ADR-0006-fallback-e-degradacao-segura.md) | Fallback e degradacao segura | Aceito | define fail-open vs fail-closed e a fronteira entre disponibilidade e integridade |
| [ADR-0007](ADR-0007-publicacao-segura-license-server.md) | Publicacao segura do license server | Aceito | define HTTPS/TLS obrigatorio, edge proxy, canais/portas permitidos e politica de headers/publicacao |
| [ADR-0008](ADR-0008-autenticacao-e-sessao-license-server.md) | Autenticacao e sessao do license server | Aceito | define modelo de login, sessao stateful, cookie seguro e proibicao de token em `localStorage` |
| [ADR-0009](ADR-0009-protecao-superficie-administrativa-license-server.md) | Protecao da superficie administrativa do license server | Aceito | define rate limit, brute force protection, CORS, logging e politica de erro/admin surface |
| [ADR-0010](ADR-0010-integridade-transacional-e-validacao-crud-license-server.md) | Integridade transacional e validacao do CRUD do license server | Aceito | define validacao de payload, transacoes, delete seguro e falha fechada no CRUD/activate |

**Nota importante:** a distribuicao actual conhecida do projecto usa `.pkg`.
O ADR-0002 fica preservado por rastreabilidade, mas a referencia normativa
actual passa a ser a combinacao de ADR-0003 + ADR-0004.

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

## Proximos ADRs recomendados

| Proximo ID sugerido | Tema | Fase | Motivo |
|---------------------|------|------|--------|
| ADR-0011 | Modelo de estados do licenciamento e activacao | F3 | tornar activacao/revogacao/offline previsiveis e rastreaveis |
| ADR-0012 | Reorganizacao estrutural controlada do repositório | F6 | garantir que mover ficheiros nao destrua contexto |

---

## Regra pratica

Se a mudanca for suficientemente importante para:

- alterar uma restricao congelada;
- mudar a forma de distribuir, activar ou validar o produto;
- introduzir uma nova fronteira de confianca;
- mover estrutura com impacto amplo;

entao ela pede ADR.
