# Arquitetura de Confianca da F1

## Finalidade

Este documento consolida a arquitectura da F1 sem implementar nada.
Ele descreve as cadeias de confianca, os pontos de risco, as dependencias
externas e os contratos que os ADRs da F1 tornaram canónicos.

Documentos normativos desta arquitectura:

- [`../03-adr/ADR-0003-hierarquia-oficial-de-distribuicao.md`](../03-adr/ADR-0003-hierarquia-oficial-de-distribuicao.md)
- [`../03-adr/ADR-0004-cadeia-de-confianca-dos-artefatos.md`](../03-adr/ADR-0004-cadeia-de-confianca-dos-artefatos.md)
- [`../03-adr/ADR-0005-pipeline-seguro-de-blacklists.md`](../03-adr/ADR-0005-pipeline-seguro-de-blacklists.md)
- [`../03-adr/ADR-0006-fallback-e-degradacao-segura.md`](../03-adr/ADR-0006-fallback-e-degradacao-segura.md)

---

## 1. Visao geral

A F1 nao muda a logica funcional do produto. Ela define **quem pode ser
confiado para quê** e **o que fazer quando essa confianca falha**.

As duas cadeias principais sao:

1. `builder -> artefacto -> publicacao -> instalacao -> validacao`
2. `upstream blacklist -> snapshot aprovada -> download -> validacao -> aplicacao`

---

## 2. Cadeia de confianca dos artefatos

### Fluxo alvo

```text
Repo de origem
  -> commit/tag definido
  -> builder gera .pkg candidato
  -> staging de release calcula manifesto/hash
  -> signer separado assina manifesto
  -> canal publico publica .pkg + sha256 + manifesto + assinatura
  -> instalador/update valida manifesto, assinatura e checksum
  -> pfSense instala apenas artefacto validado
```

### Pontos de confianca

- repositório de origem e o ponto de verdade do que deve ser construido;
- builder compila, mas nao assina nem e trust anchor final;
- signer de release e a autoridade criptografica do artefacto publicado;
- canal publico so distribui o conjunto assinado;
- operador/instalador valida antes de aplicar.

### Pontos de risco actuais

- URLs mutaveis ainda usadas em `main` para install/uninstall;
- coexistencia de repositório de origem e repositorio publico de distribuicao;
- legado `.txz` ainda presente em docs antigas;
- checksum existe, mas assinatura ainda nao esta contratualmente implementada;
- builder possui ficheiros sensiveis locais e precisa de politica formal.

---

## 3. Cadeia de confianca das blacklists

### Fluxo alvo

```text
Upstream UT1 (conteudo)
  -> aquisicao controlada fora do cliente
  -> snapshot aprovada Layer7/Systemup
  -> manifesto + checksum + assinatura
  -> origem oficial HTTPS
  -> download pelo produto
  -> validacao de origem, assinatura, hash e estrutura
  -> activacao da nova snapshot
  -> preservacao da ultima versao valida
```

### Pontos de confianca

- UT1 e autoridade de conteudo, nao de transporte confiavel para auto-update;
- a origem confiavel de consumo automatizado deve ser controlada pelo Layer7;
- o produto so promove snapshot aprovada e assinada;
- a ultima versao valida e parte da estrategia de disponibilidade segura.

### Pontos de risco actuais

- feed default em HTTP;
- ausencia de autenticidade do feed;
- dependencia de origem externa unica;
- ausencia de contrato formal de mirror/cache;
- possibilidade de confundir “feed indisponivel” com “feed suspeito”.

---

## 4. Superficies de ataque

| Superficie | Risco |
|------------|-------|
| builder | compilar artefacto adulterado ou com contexto operacional contaminado |
| publicacao sem manifesto assinado | asset publico sem prova forte de autenticidade |
| URLs mutaveis de `main` | trocar script sem versionamento de release |
| GitHub como unica dependencia publica | indisponibilidade ou dependencia excessiva de um unico provedor |
| feed UT1 via HTTP | adulteracao de conteudo em transito |
| fallback automatico para origens nao oficiais | disponibilidade sobrepondo-se a integridade |
| mistura de chaves de licenca e release | ampliacao de blast radius de compromisso |

---

## 5. Dependencias externas criticas

| Dependencia | Papel actual | Risco principal | Estrategia de reducao |
|-------------|--------------|-----------------|-----------------------|
| GitHub Releases | distribuicao publica do `.pkg` | dependencia concentrada de um unico canal | manifesto assinado, espelhamento controlado, URLs versionadas |
| `raw.githubusercontent.com` em `main` | install/uninstall actuais | mutabilidade do ramo | migrar para assets/tag versionados |
| UT1 | fonte tematica das blacklists | ausencia de autenticidade/HTTPS no canal actual | consumir apenas snapshots aprovadas em origem confiavel |
| builder 192.168.100.12 | geracao do candidato a release | compromisso do ambiente de build | separacao builder/signer e politica de quarentena |
| servidor de licencas | activacao online | indisponibilidade e fronteira de confianca incompleta | F2/F3 com hardening e politica de degradacao segura |

---

## 6. Politica de fallback na arquitectura

### Integridade acima de disponibilidade

Artefacto novo, licenca nova e snapshot nova de blacklist **nao podem** ser
aceites se a validacao falhar.

### Degradacao segura permitida

- manter a ultima release validada escolhida explicitamente;
- manter a ultima blacklist valida;
- continuar com politicas manuais quando blacklists falham;
- continuar com licenca local ja valida quando o servidor esta indisponivel.

### Degradacao proibida

- aceitar pacote sem prova;
- aceitar feed HTTP ou manifestos invalidos;
- trocar automaticamente para origem nao oficial;
- promover output de builder suspeito.

---

## 7. Contratos operacionais da F1

### Contrato de distribuicao

- artefacto oficial: `.pkg`
- canal oficial: GitHub Releases do repositorio publico de distribuicao
- pacote minimo: `.pkg` + `.pkg.sha256` + manifesto + assinatura

### Contrato de verificacao

- hash do `.pkg` precisa bater com o manifesto
- assinatura destacada do manifesto precisa validar com chave publica oficial
- a chave publica de release e separada da chave de licenciamento

### Contrato de blacklists

- auto-update confiavel so por origem HTTPS aprovada
- sem aceitar feed nova sem autenticidade/integridade
- last known good obrigatoria

---

## 8. Decisoes em aberto

Estas decisoes ficam abertas para implementacao, nao para filosofia:

- formato final do manifesto de release;
- mecanismo operacional da assinatura Ed25519;
- local exacto do espelho oficial de snapshots de blacklists;
- forma final de distribuir a chave publica no instalador e GUI updater;
- nivel de automacao permitido antes da assinatura final.

---

## 9. Trade-offs assumidos

| Escolha | Ganho | Custo |
|---------|-------|-------|
| signer separado do builder | reduz concentracao de risco | adiciona passo operacional |
| manifesto assinado | aumenta auditabilidade | exige tooling e disciplina |
| proibir feed HTTP directo | elimina risco obvio de adulteracao | exige origem aprovada intermediaria |
| last known good | melhora disponibilidade segura | requer persistencia e rastreabilidade |
| URLs versionadas | facilita auditoria | reduz “comodidade” de usar sempre `main` |

---

## 10. Resultado esperado da F1

Ao fim da F1 implementada no futuro, deve ficar claro e verificavel:

- qual e o artefacto oficial;
- quem gera, quem assina e quem publica;
- como o operador valida autenticidade e integridade;
- como blacklists entram no sistema sem aceitar feed suspeita;
- como o sistema se degrada com seguranca quando a validacao falha.
