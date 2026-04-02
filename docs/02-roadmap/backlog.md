# Backlog Canónico

Este backlog passa a ser a fila unica de priorizacao do projecto apos a F0.
Nao serve para listar “ideias soltas”; serve para orientar as proximas fases
com criterio de risco, beneficio e ordem de execucao.

---

## Legenda

- **Severidade:** `Critica`, `Alta`, `Media`, `Baixa`
- **Risco se adiado:** impacto principal de deixar o item para depois
- **Esforco:** `P`, `M`, `G`
- **Beneficio:** `Alto`, `Medio`, `Baixo`
- **Status:**
  - `Pronto apos F0`
  - `Planeado`
  - `Planeamento F1 concluido`
  - `Planeamento F2 concluido`
  - `Em execucao na F3.1`
  - `Em execucao na F3.2`
  - `Em execucao na F3.3`
  - `Bloqueado pela fase`
  - `Acompanhar`

**Regra de priorizacao:** severidade e fase sugerida prevalecem sobre
conveniencia local. Itens fora da fase actual nao devem ser puxados sem
reavaliacao formal.

## Checkpoint actual da F1

- F1.1 foi concluida em `2026-04-01` com o contrato operacional oficial de
  distribuicao em `.pkg`, URLs versionadas de release e scripts
  `install.sh`/`uninstall.sh` publicados no canal oficial.
- F1.2 foi concluida em `2026-04-01` com manifesto versionado, assinatura
  destacada Ed25519, public key de verificacao e separacao operacional entre
  builder, signer e publisher.
- F1.3 foi concluida em `2026-04-01` com origem oficial HTTPS de blacklists,
  manifesto dedicado, public key propria, mirror controlado, cache local e
  last-known-good materializados na trilha do consumidor.
- BG-020 foi materializado na F1.3, BG-021 foi materializado na F1.4 com
  matriz explicita de fallback/fail-closed por componente, e BG-022 ficou
  reduzido na trilha do consumidor, mas continua a exigir acompanhamento das
  dependencias externas.

## Checkpoint actual da F2

- O planejamento detalhado da F2 foi concluido em `2026-04-01` com ADRs de
  publicacao segura, autenticacao/sessao, protecao da superficie
  administrativa e integridade transacional/validacao do CRUD.
- A F2.1 foi concluida em `2026-04-01` com `443/TLS` como canal publico
  oficial, `8445` restrito a origin privado por defeito e documentacao
  operacional explicita para edge proxy, certificado e troubleshooting.
- A F2.2 foi concluida em `2026-04-01` com sessao administrativa stateful em
  backend, cookie `HttpOnly + Secure + SameSite=Strict`, expiracao
  ociosa/absoluta, renovacao controlada, logout com invalidacao real e
  remocao do JWT em `localStorage` da trilha activa.
- A F2.3 foi concluida em `2026-04-01` com same-origin only em producao,
  limiter dedicado no login, lockout temporario e auditoria minima para auth
  e mutacoes administrativas.
- A F2.4 foi concluida em `2026-04-01` com validacao forte de payload/query,
  transacoes explicitas em `activate` e mutacoes administrativas, e arquivo
  logico no painel em vez de delete fisico.
- A F2.5 foi concluida em `2026-04-01` com ownership minimo de segredos,
  bootstrap administrativo explicito, backup/restore minimo do PostgreSQL e
  runbooks essenciais; a F2 fica encerrada e a proxima fase elegivel passa a
  ser a F3.

## Checkpoint actual da F3

- A F3 foi aberta formalmente em `2026-04-01` com o documento canónico
  `docs/01-architecture/f3-arquitetura-licenciamento-ativacao.md`.
- A F3.1 mapeou o estado real do codigo: activacao publica em
  `POST /api/activate`, estado persistido em `licenses`, estado derivado por
  expiracao em leituras/listagens e grace local apenas no daemon.
- O primeiro endurecimento minimo desta fase passa a tornar a reactivacao do
  mesmo hardware mais idempotente no backend, sem rebind, sem mudanca de
  contrato e sem abrir trilhas paralelas.
- A F3.2 passa a formalizar a matriz real de fingerprint/binding em
  `docs/01-architecture/f3-fingerprint-e-binding.md`, com politica
  conservadora para reinstall, troca de NIC, clone de VM, restore, migracao
  de hypervisor e appliances com multiplas interfaces, sem alterar a formula
  do fingerprint.
- O unico endurecimento tecnico adicional aceite nesta subfase fica limitado
  a normalizacao defensiva do `hardware_id` persistido no servidor antes de
  comparar ou assinar o `.lic`, reduzindo falso bloqueio por drift de
  formato legacy sem abrir rebind automatico.
- A F3.3 passa a formalizar em
  `docs/01-architecture/f3-expiracao-revogacao-grace.md` a semantica real de
  expiracao, revogacao, validade offline e grace local, declarando sem
  maquilhagem o limite actual da revogacao e o risco de um `.lic` antigo
  continuar valido offline.
- O unico endurecimento tecnico adicional aceite nesta subfase fica limitado
  a um helper minimo de estado efectivo da licenca no backend, reutilizado em
  `activate`, `licenses`, `customers` e `dashboard`, sem mudar schema,
  formato `.lic` ou algoritmo de fingerprint.

---

## Backlog priorizado

| ID | Item | Severidade | Componente | Fase sugerida | Risco se adiado | Esforco | Beneficio | Status | Observacoes |
|----|------|------------|------------|---------------|-----------------|---------|-----------|--------|-------------|
| BG-001 | Formalizar a cadeia de confianca entre repo, builder, chave publica embutida, servidor de licencas e artefacto publicado | Critica | seguranca/governanca | F1 | decisao tecnica continuar baseada em suposicoes sobre confianca | M | Alto | Planeamento F1 concluido | coberto por ADR-0004 e pelo documento consolidado de arquitectura F1; implementacao pendente |
| BG-002 | Governar a custodia da chave de producao e o tratamento dos ficheiros sensiveis locais no builder | Critica | builder/licencas | F1 | risco operacional e de seguranca concentrado em conhecimento implícito | M | Alto | Planeamento F1 concluido | politica de papeis, assinatura e tratamento de builder suspeito definida; execucao fica para F1 |
| BG-003 | Criar ADR que substitua a ambiguidade historica entre `.txz` e `.pkg` como artefacto de distribuicao | Critica | distribuicao/ADR | F1 | documentos historicos continuam a confundir instalacao e release | P | Alto | Planeamento F1 concluido | ADR-0003 passa a ser a referencia normativa; ADR-0002 fica historico |
| BG-004 | Hardening da stack do license server: segredos, fronteira HTTP/HTTPS, backup, restore e operacao administrativa | Critica | license-server | F2 | indisponibilidade ou exposicao do servidor comprometer activacao | M | Alto | Acompanhar | F2.1-F2.5 materializaram publicacao segura, sessao, superficie administrativa, CRUD, segredos/bootstrap e backup/restore; F3 herda apenas o que pertence ao modelo de licenciamento |
| BG-005 | Endurecer o endpoint de activacao e os controlos de abuso, auditoria e monitorizacao minima | Alta | license-server | F2 | abuso ou comportamento opaco em incidente | M | Alto | Planeamento F2 concluido | F2 definiu rate limit, logging e separacao entre activate publico e admin |
| BG-023 | Fechar a politica oficial de publicacao segura do license server com TLS, edge proxy e portas permitidas | Critica | license-server/publicacao | F2 | exposicao ambigua do painel e do endpoint publico | M | Alto | Acompanhar | materializado na F2.1 com `443/TLS` oficial, origin `8445` privado por defeito, headers minimos e runbook de borda/TLS |
| BG-024 | Substituir JWT em `localStorage` por sessao administrativa segura e fechar CORS/login/brute force | Critica | license-server/auth | F2 | roubo de sessao, abuso administrativo e superficie web permissiva | M | Alto | Acompanhar | F2.2 materializou sessao stateful com cookie seguro e logout real; F2.3 fechou same-origin, limiter dedicado, lockout temporario, politica minima de erro e auditoria administrativa |
| BG-025 | Endurecer validacao, transacoes, arquivo/delete seguro e atomicidade do CRUD do license server | Alta | license-server/crud | F2 | estado parcial, perda de auditoria e conflitos silenciosos | M | Alto | Acompanhar | materializado na F2.4 com validacao forte, transacoes explicitas em `activate`/mutacoes administrativas e arquivo logico no painel |
| BG-006 | Definir modelo de estados do licenciamento: activar, reactivar, renovar, revogar, expirar, grace e offline | Alta | licenciamento | F3 | suporte e troubleshooting continuarem dependentes de tentativa e erro | M | Alto | Em execucao na F3.3 | contrato canónico inicial foi aberto na F3.1, a matriz de fingerprint foi fechada na F3.2 e a semantica real de expiracao/revogacao/offline foi fechada na F3.3; faltam evidencias de appliance/lab |
| BG-007 | Validar robustez do hardware fingerprint em cenarios de mudanca de NIC, VM, reinstall e clock | Alta | licenciamento | F3 | activacoes legitimas falharem ou exigirem workaround manual | M | Alto | Em execucao na F3.2 | matriz canónica de cenarios e politica conservadora fechadas; validacao em appliance/lab continua pendente |
| BG-008 | Fechar lacunas de previsibilidade em activacao offline e revogacao sem quebrar comportamento actual | Alta | licenciamento | F3 | operador assumir garantias que o sistema ainda nao oferece | M | Alto | Em execucao na F3.3 | F3.3 declara o limite real da revogacao actual, a validade offline do `.lic` ja emitido e o bloqueio conservador de qualquer rebind administrativo antes de resolver esse risco |
| BG-009 | Consolidar confiabilidade de package/daemon em reboot, reload, upgrade, rollback e reinicio de servico | Alta | package/daemon | F4 | runtime continuar a divergir entre estado desejado e estado real | G | Alto | Planeado | exige evidencias em appliance |
| BG-010 | Hardening da trilha de blacklists UT1: download, cron, reload, fallback, except tables e forcing DNS | Alta | blacklists | F4 | subsistema seguir operacionalmente fragil apesar de funcional | G | Alto | Bloqueado pela fase | documentos da trilha ja existem e devem ser usados |
| BG-011 | Validar forcing DNS e anti-bypass em cenarios reais de VLAN/interface, excepcoes e tabelas PF | Alta | daemon/enforcement | F4 | bypass continuar a aparecer em combinacoes menos comuns | M | Alto | Planeado | derivado das correccoes recentes em `rdr` |
| BG-012 | Transformar os riscos principais em malha canónica de testes e regressao por componente | Critica | testes/governanca | F5 | cada nova mudanca voltar a depender de memoria humana | G | Alto | Planeado | unir smoke, builder e appliance |
| BG-013 | Fechar cobertura minima de testes para licenciamento, blacklists, package e rollback | Alta | testes | F5 | regressao funcional escapar entre fases tecnicas | G | Alto | Planeado | alinhar com checklist mestre |
| BG-014 | Criar trilha de evidencias e gates para mudancas sensiveis, com ligacao directa entre backlog, checklist e changelog | Media | governanca/testes | F5 | perda de rastreabilidade entre decisao e validacao | M | Medio | Planeado | reforca continuidade entre chats |
| BG-015 | Reorganizar fisicamente a documentacao e normalizar duplicidades de directorios e readmes | Media | estrutura/documentacao | F6 | legado continuar confuso e caro de manter | G | Medio | Bloqueado pela fase | so apos estabilidade tecnica e malha de regressao |
| BG-016 | Normalizar areas sobrepostas como `docs/04-tests` vs `docs/tests`, `docs/04-package` vs docs historicos e prompts antigos | Media | estrutura/documentacao | F6 | agentes continuarem a abrir documentos errados | M | Medio | Bloqueado pela fase | depende do mapa de equivalencia |
| BG-017 | Instituir checklist interno de release com verificacao de artefacto, docs sincronizadas e disponibilidade de download | Media | release-engineering | F7 | publicacoes continuarem dependentes de memoria operacional | M | Alto | Planeado | ligar changelog, release notes e manual install |
| BG-018 | Definir telemetria operacional minima para pacote, daemon e servidor de licencas | Media | observabilidade | F7 | troubleshooting e auditoria continuarem com visibilidade insuficiente | M | Medio | Planeado | sem analytics pesado |
| BG-019 | Rever e refrescar tutorial longo, guias comerciais e docs preservadas por compatibilidade | Baixa | documentacao/comercial | F7 | materiais antigos continuarem a coexistir com a base canónica | M | Medio | Acompanhar | so depois das fases tecnicas centrais |
| BG-020 | Formalizar pipeline seguro de blacklists com origem aprovada, HTTPS obrigatorio, checksum/assinatura e politica de espelhamento | Critica | blacklists/seguranca | F1 | feed continuar dependente de transporte inseguro ou de origem nao autenticada | M | Alto | Acompanhar | F1.3 materializou origem oficial, manifesto assinado, mirror controlado e last-known-good; F4 herda a robustez operacional do runtime |
| BG-021 | Definir politica de fallback e degradacao segura por componente, distinguindo disponibilidade de integridade | Critica | seguranca/operacao | F1 | produto continuar susceptivel a aplicar conteudo suspeito em nome de disponibilidade | M | Alto | Acompanhar | materializado na F1.4 em `install.sh`, `update-blacklists.sh` e docs canónicas; F5 herda a formalizacao de testes |
| BG-022 | Reduzir o risco das dependencias externas criticas de distribuicao e blacklists | Alta | distribuicao/dependencias | F1 | GitHub, UT1 e builder continuarem como pontos unicos de falha sem contrato formal | M | Alto | Acompanhar | F1.3 reduziu o risco no consumo de blacklists com origem oficial, mirror GitHub e cache/LKG local; operacao de publicacao e builder continuam monitorados |

---

## Itens explicitamente fora da fila imediata

Os itens abaixo continuam conhecidos, mas **nao entram agora**:

- console central multi-firewall;
- MITM/TLS inspection universal;
- analytics pesado;
- reestruturacao fisica precoce;
- refactor amplo de package/daemon/frontend sem gate especifico.

---

## Politica de manutencao do backlog

1. Todo item novo entra com componente, fase sugerida, risco, esforco,
   beneficio e status.
2. Nenhum item tecnico fora da fase actual deve ser executado sem ser puxado
   formalmente para a frente.
3. Quando um item mudar de fase, estado ou severidade, actualizar tambem:
   - `CORTEX.md`
   - `docs/02-roadmap/roadmap.md`
   - `docs/02-roadmap/checklist-mestre.md` se afectar gate
