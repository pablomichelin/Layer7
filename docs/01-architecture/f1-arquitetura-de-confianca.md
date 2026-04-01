# Arquitetura de Confianca da F1

## Finalidade

Este documento consolida a arquitectura e o contrato operacional fechados na
F1. Ele descreve as cadeias de confianca, os pontos de risco, as dependencias
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

- o fluxo oficial ja migrou para assets versionados de release, mas refs
  historicas em `main` ainda coexistem em material legado;
- coexistencia de repositório de origem e repositorio publico de distribuicao;
- legado `.txz` ainda presente em docs antigas;
- checksum, manifesto assinado e public key de verificacao passam a existir na
  F1.2 e a F1.4 integra essa validacao no `install.sh`; a visibilidade mais
  ampla em GUI updater/observabilidade continua para fases futuras;
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

- a operacao de publicacao da snapshot oficial ainda depende de disciplina do
  publisher e do mirror controlado;
- a indisponibilidade simultanea da origem oficial e do mirror continua a
  exigir intervencao operacional, embora a LKG preserve o ultimo estado seguro;
- a F1.4 passou a registar degradacao/fail-closed por ficheiro de estado no
  updater de blacklists, mas a observabilidade mais ampla continua para F7.

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

### Matriz final materializada na F1.4

| Componente | Condicao de falha | Politica | Estado seguro mantido | Rastro/log | Recuperacao do operador |
|------------|-------------------|----------|------------------------|------------|-------------------------|
| release trust chain | manifesto ausente/invalido, assinatura invalida, fingerprint divergente, checksum divergente | fail-closed | nenhum pacote novo e instalado | `install.sh` escreve `FAIL-CLOSED` no stdout/syslog (`layer7-install`) | baixar de novo o asset versionado oficial ou republicar release valida |
| install.sh / upgrade | trust chain valida, mas passos locais pos-install falham (PF, Unbound, start do servico) | degradacao segura | pacote ja validado fica instalado; operador completa verificacao local | `install.sh` escreve `DEGRADED` no stdout/syslog | validar tabelas PF, Unbound e `service layer7d onestatus` |
| uninstall.sh | pacote ausente ou residuos ja nao existem | degradacao segura/idempotente | sistema continua sem reinstalar conteudo novo | saida local do script | repetir limpeza ou remover residuos manualmente |
| manifesto/assinatura/checksum de release | divergencia em qualquer etapa | fail-closed | artefacto anterior permanece o unico confiavel | `verify-release.sh`/`install.sh` falham explicitamente | corrigir stage dir ou refazer signing/publicacao |
| pipeline de blacklists | snapshot nova falha em origem, assinatura, hash, tamanho ou estrutura | fail-closed para conteudo novo | snapshot activa validada permanece | `/var/log/layer7-bl-update.log` + `.state/fallback.state` | investigar origem/mirror e repetir update |
| mirror/cache de blacklists | origem primaria falha ou mirror falha | degradacao segura enquanto houver snapshot activa validada | snapshot activa e cache validas | `source_role`, `fallback.state`, log de update | restaurar disponibilidade da origem oficial/mirror |
| last-known-good de blacklists | novo conteudo rejeitado | degradacao segura permitida por restauro explicito | `.last-known-good/` | `fallback.state` com `mode=last-known-good` | `update-blacklists.sh --restore-lkg` |
| auto-update de blacklists | nenhuma origem oficial gera snapshot valida | degradacao segura se houver activa; fail-closed se nao houver estado seguro | activa validada ou LKG explicitamente restauravel | `fallback.state` + log | analisar log e usar `--restore-lkg` se necessario |
| release assets versionados | asset faltante no stage dir ou release inconsistente | fail-closed | release anterior continua referencia segura | `publish-release.sh` depende de `verify-release.sh` | regenerar stage dir e republicar |

---

## 7. Contratos operacionais da F1

### Contrato de distribuicao

- artefacto oficial: `.pkg`
- canal oficial: GitHub Releases do repositorio publico de distribuicao
- pacote minimo em F1.1: `.pkg` + `.pkg.sha256` + `install.sh` +
  `uninstall.sh` versionados
- pacote minimo em F1.2: todo o conjunto da F1.1 + manifesto versionado +
  assinatura destacada + public key de verificacao

### Contrato de verificacao

- hash do `.pkg` precisa bater com o manifesto
- assinatura destacada do manifesto precisa validar com chave publica oficial
- a chave publica de release e separada da chave de licenciamento

### Contrato de blacklists

- auto-update confiavel so por origem HTTPS aprovada
- sem aceitar feed nova sem autenticidade/integridade
- last known good obrigatoria

### Materializacao conservadora da F1.3

- **origem oficial primaria:** `https://downloads.systemup.inf.br/layer7/blacklists/ut1/current/layer7-blacklists-manifest.v1.txt`
- **mirror controlado:** `https://github.com/pablomichelin/Layer7/releases/download/blacklists-ut1-current/layer7-blacklists-manifest.v1.txt`
- **artefactos publicados:** `layer7-blacklists-manifest.v1.txt`,
  `layer7-blacklists-manifest.v1.txt.sig` e `layer7-blacklists-ut1.tar.gz`
- **chave publica pinned no pacote:** `/usr/local/share/pfSense-pkg-layer7/blacklists-signing-public-key.pem`
- **cache local do consumidor:** `/usr/local/etc/layer7/blacklists/.cache/<snapshot_id>/`
- **estado activo auditavel:** `/usr/local/etc/layer7/blacklists/.state/active-snapshot.state`
- **last-known-good:** `/usr/local/etc/layer7/blacklists/.last-known-good/`
- **restauro explicito da LKG:** `/usr/local/etc/layer7/update-blacklists.sh --restore-lkg`

---

## 8. Decisoes em aberto

Estas decisoes ficam abertas para implementacao, nao para filosofia:

- automatizacao completa da publicacao do publisher para origem primaria e
  mirror controlado;
- forma final de auditoria/alerta quando origem e mirror falham em simultaneo;
- integracao futura desta visibilidade de degradacao em observabilidade mais
  ampla e testes automatizados.

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

Ao fim da F1, fica claro e verificavel:

- qual e o artefacto oficial;
- quem gera, quem assina e quem publica;
- como o operador valida autenticidade e integridade;
- como blacklists entram no sistema sem aceitar feed suspeita;
- como o sistema se degrada com seguranca quando a validacao falha.
