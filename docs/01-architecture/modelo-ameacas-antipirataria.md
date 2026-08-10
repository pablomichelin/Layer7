# Modelo de ameaças anti-pirataria e anti-tamper — Layer7

**Tipo:** relatório técnico de ameaças (**diagnóstico canónico** da trilha)
**Estado:** **ACEITE como diagnóstico** (`2026-08-10`)
**Data:** `2026-08-10`
**Âmbito:** licenciamento no cliente (`layer7d`), license server, distribuição
do `.pkg` e entrega de conteúdo (blacklists/catálogos)
**Código alterado:** **nenhum**. Este documento não implementa nada.

**Arranque da trilha:** [`../00-overview/START-HERE-antipirataria.md`](../00-overview/START-HERE-antipirataria.md)
**SSOT de execução:** [`../02-roadmap/plano-antipirataria-anti-tamper.md`](../02-roadmap/plano-antipirataria-anti-tamper.md)
**Gates:** [`../09-blocking/plano-gates-antipirataria.md`](../09-blocking/plano-gates-antipirataria.md)

> ### Autoridade deste documento
>
> Este relatório é o **diagnóstico** (achados A-01…A-10, perfis de atacante,
> estratégia e o que não fazer). **Não** é o SSOT de execução: o passo actual,
> o sequenciamento e o estado de cada item vivem no
> [`plano-antipirataria-anti-tamper.md`](../02-roadmap/plano-antipirataria-anti-tamper.md).
>
> **Equivalência de numeração:** as fases **H0–H4** das secções 8 a 10 deste
> relatório correspondem às ondas **AP0–AP4** do plano, com o mesmo conteúdo. Os
> ADRs e itens de backlog listados aqui como «propostos» já estão **registados**
> no plano §6, no índice de ADRs e no backlog canónico (BG-114…BG-123). Em caso de
> divergência entre este relatório e o plano, **o plano prevalece**.
>
> **Riscos residuais operacionais (RR-1…RR-5)** — dependência dos GOs `30.11`/`30.14`,
> redistribuição por appliance licenciado, `.pkg` antigos com bypass, evasões de
> anti-rollback, patch de AP3 — vivem no plano §0.1 e devem constar dos ADRs
> 0030–0033. Este diagnóstico não os substitui.
>
> Não substitui `f3-arquitetura-licenciamento-ativacao.md`,
> `f3-fingerprint-e-binding.md` nem `f3-expiracao-revogacao-grace.md`, que
> continuam a descrever o comportamento **implementado**.

---

## 1. Premissa fundamental

O Layer7 corre como daemon **root**, num pfSense que é **propriedade do
cliente** e ao qual o cliente tem acesso administrativo total, físico e
remoto. Isto impõe um limite teórico que nenhuma engenharia contorna:

> **Qualquer verificação executada exclusivamente no appliance pode ser
> removida por quem controla o appliance.**

A consequência prática é que o objectivo correcto **não** é tornar o bypass
impossível. É:

1. tornar o bypass **mais caro** do que o preço da licença;
2. tornar a cópia sem licença **inútil ao longo do tempo**, movendo valor
   para o lado do serviço;
3. tornar o abuso **detectável e atribuível**, para a via contratual.

Toda a estratégia da secção 6 deriva desses três objectivos, por essa ordem
de retorno sobre esforço.

---

## 2. Modelo de ameaça — quem são os atacantes reais

| # | Perfil | Motivação | Capacidade técnica | Probabilidade |
|---|--------|-----------|--------------------|---------------|
| T1 | **Integrador/revenda multi-cliente** | pagar 1 licença e instalar em N clientes | média (copiar ficheiros, seguir tutorial) | **Alta** |
| T2 | **Ex-cliente que cancelou** | continuar a usar após fim da subscrição | média (root, `date`, editar ficheiros) | **Alta** |
| T3 | **Cliente técnico curioso** | evitar pagar add-ons (Identity/MITM) | média-alta | Média |
| T4 | **Pirata/redistribuidor** | publicar «Layer7 crackado» num fórum | alta (Ghidra, patch binário) | Baixa-Média |
| T5 | **Concorrente** | copiar lógica de classificação/produto | alta | Baixa |

**Nota de calibração:** o dinheiro perde-se em **T1 e T2**, não em T4/T5. As
defesas devem ser dimensionadas para T1/T2 primeiro. Investir em ofuscação
pesada é dimensionar para T4, que é o cenário menos provável e o de menor
impacto financeiro.

---

## 3. Postura actual — o que já protege (e protege bem)

| # | Controlo | Evidência | Eficácia |
|---|----------|-----------|----------|
| P1 | **Assinatura Ed25519**; chave privada só no license server | `src/layer7d/license.c:311-346`; `license-server/backend/src/crypto.js:1-17` | **Forte** — ninguém forja licenças |
| P2 | **Binding 1:1** a `SHA256(kern.hostuuid + ":" + primeira MAC)`, dentro do payload assinado | `src/layer7d/license.c:128-145`, `366-370` | **Forte** contra cópia simples do `.lic` |
| P3 | Servidor **recusa** reactivação noutro hardware (HTTP 409) | `license-server/backend/src/activation-policy.js:24-34` | **Forte** contra T1 ingénuo |
| P4 | **Enforce fail-closed** sem licença válida | `src/layer7d/main.c:2399-2409` | Forte no comportamento, **frágil na localização** (ver A-02) |
| P5 | Activação **revalida** o `.lic` recebido antes de aceitar | `src/layer7d/license.c:644-662` | Forte — servidor falso não emite licença |
| P6 | Registo de activações e check-ins com IP/UA | `license-server/backend/migrations/001-init.sql:39-48` | Média — dados existem, falta alerta |
| P7 | Painel admin endurecido (bcrypt, TOTP, RBAC, rate limit, auditoria) | ADR-0008, ADR-0009, ADR-0010 | Forte |
| P8 | **Fonte fechado** — repo de código privado; só o `.pkg` é público | `origin` = `pfsense-layer7` (privado); `layer7` = `Layer7` (público) | Média |
| P9 | **EULA proprietária** proibindo cópia, RE e redistribuição | `LICENSE` (114 linhas) | Base legal necessária |

**Leitura:** a criptografia está correcta. O problema não é o algoritmo — é
que a decisão baseada nele acontece toda dentro de um binário que o
adversário possui.

---

## 4. Achados — superfície de ataque

Severidade segundo a legenda do backlog. «Custo de exploração» é o esforço
para um atacante do perfil indicado.

### A-01 — Backdoor de desenvolvimento embutida no binário `layer7d`

**Severidade: Crítica. Custo de exploração: minutos (T4), horas (T2/T3).**

Se os 32 bytes da chave pública forem todos zero, a verificação de licença é
**inteiramente saltada** e o daemon assume licença válida permanente:

```
src/layer7d/license.c:29-50    l7_ed25519_pubkey[32] + is_dev_key()
src/layer7d/license.c:261-272  if (is_dev_key()) { info->valid = 1; ... return 0; }
```

Um patch de 32 bytes no binário instalado produz licença universal
(`features=base`), sem `.lic`, sem servidor e **sem expiração**.

Agravantes:

- o binário **não é strippado** — o port instala com `INSTALL_PROGRAM` e
  compila só com `-O2` (`package/pfSense-pkg-layer7/Makefile:67-78`, `98`),
  pelo que os símbolos `is_dev_key` e `layer7_license_check` aparecem em
  `nm`/Ghidra;
- o comentário no fonte descreve o mecanismo, e o fonte pode fugir;
- é o **caminho mais curto** conhecido para bypass total.

### A-02 — Ponto único de decisão do enforce

**Severidade: Alta. Custo de exploração: horas (T4).**

Toda a ligação entre licença e bloqueio é uma comparação isolada:

```
src/layer7d/main.c:2406-2407
	if (ge && !s_lic.valid)
		ge = 0;
```

Neutralizar essas instruções activa enforce sem licença nenhuma. Um único
ponto de falha é exactamente o que um atacante procura primeiro.

### A-03 — Relógio para trás não é detectado

**Severidade: Alta. Custo de exploração: trivial (T2).**

A validade usa apenas `time(NULL)`/`mktime`, sem relógio monotónico e sem
marca persistente da data máxima já observada
(`src/layer7d/license.c:401-417`). Combinado com o grace de 14 dias
(`src/layer7d/license.h:14`), permite prolongar indefinidamente uma licença
expirada com um comando `date`. Limitação já reconhecida em
`f3-expiracao-revogacao-grace.md`.

### A-04 — Revogação remota desligada por defeito

**Severidade: Alta (comercial). Custo de exploração: nenhum — é o default.**

`check_in_enabled` vem `false` no pacote
(`package/pfSense-pkg-layer7/files/usr/local/etc/layer7.json.sample:8`).
Consequência directa: **revogar uma licença no painel não tem efeito** num
appliance já instalado; ele funciona até `expiry + 14 dias`. Este é o achado
com maior impacto financeiro sobre T2, e é puramente de configuração.

Relacionado com **BG-101**, hoje classificado `Documentado` como decisão de
design da ADR-0021. Este relatório propõe reabrir essa classificação.

### A-05 — Resposta de check-in não é assinada

**Severidade: Alta. Custo de exploração: médio (T2/T3).**

Quando o check-in está activo, o cliente aceita um JSON simples com
`status: "active"`, sem assinatura e sem nonce:

```
src/layer7d/license.c:1040-1048   json_find_string(response_body, "status", ...) != "active"
```

Com `/etc/hosts` e um servidor local (ou uma CA instalada na trust store do
FreeBSD, dado que **não há pinning** — o transporte é `curl` via `system()`),
um cliente neutraliza a revogação remota. O servidor falso **não** consegue
emitir licença nova (A-01 à parte), mas consegue manter viva uma existente e
manipular `check_in_interval_hours`/`max_offline_hours`.

### A-06 — Conteúdo (blacklists) actualiza sem licença

**Severidade: Alta (estratégica). Custo de exploração: nenhum.**

Os feeds são obtidos anonimamente, incluindo espelho **público** no GitHub:

```
package/pfSense-pkg-layer7/files/usr/local/etc/layer7/update-blacklists.sh:30-31
PRIMARY_MANIFEST_URL="https://downloads.systemup.inf.br/layer7/blacklists/ut1/current/..."
MIRROR_MANIFEST_URLS="https://github.com/pablomichelin/Layer7/releases/download/blacklists-ut1-current/..."
```

Não há chave, token nem `hardware_id` no pedido. **Uma cópia pirata mantém-se
tão actualizada e tão eficaz como uma instalação legítima, indefinidamente.**
Este é o achado mais importante do relatório em termos estratégicos: é a
alavanca que hoje não está a ser usada.

### A-07 — Estado de licença falsificável para desbloquear a GUI

**Severidade: Média. Custo de exploração: trivial.**

Com o daemon parado, root pode forjar `/var/db/layer7/layer7-stats.json` e,
por fallback sem verificação criptográfica em `layer7_entitlements()`
(`package/pfSense-pkg-layer7/files/usr/local/pkg/layer7.inc`, ~`6393-6432`),
desbloquear a UX de Identity/MITM. O enforce do daemon **não** é afectado, e
o gate MITM pode ainda ser contornado escrevendo
`/var/run/layer7/tlsproxy.product` e arrancando o serviço à mão. Impacto: uso
de add-ons não pagos.

Mitigação parcial já existente: `/var/db/layer7-checkin.json` só permite
**reduzir** features no daemon.

### A-08 — Sem detecção de abuso multi-appliance

**Severidade: Média (comercial). Custo de exploração: n/a.**

O servidor guarda `activations_log` mas **não há alerta nem contador** para
«a mesma chave tentou activar de 5 `hardware_id`/IPs distintos». A detecção
de T1 é hoje reactiva e manual. Não existe `max_activations` na schema
(`license-server/backend/migrations/001-init.sql:21-37`).

### A-09 — Divergência entre fonte no repo e binário em produção

**Severidade: Média (governação). Custo: n/a.**

`src/layer7d/license.c` e `src/layer7d/Makefile` têm alterações locais **não
commitadas** no builder (`AGENTS.md`, secção *Ficheiros locais sensíveis*),
incluindo a chave pública de produção. Implicação: **auditar o git não
garante o que está em campo**. Qualquer gate anti-tamper tem de ser verificado
sobre o `.pkg` publicado, não sobre o repositório.

### A-10 — Release sem cadeia de assinatura completa

**Severidade: Baixa (não é bypass). Custo: n/a.**

A `v1.9.48` publica apenas `.pkg` + `.pkg.sha256`; o manifesto assinado
previsto no contrato F1.2 (`docs/06-releases/RELEASE-SIGNING.md`, ADR-0023)
não acompanha a release. Isto afecta integridade/proveniência do download,
não o licenciamento.

### Resumo ordenado por facilidade de exploração

| Ordem | Achado | Perfil | Efeito |
|-------|--------|--------|--------|
| 1 | **A-04** default sem check-in | T2 | revogação inócua |
| 2 | **A-06** conteúdo grátis | T1, T2, T4 | cópia continua útil |
| 3 | **A-03** clock rollback | T2 | licença nunca expira |
| 4 | **A-07** GUI/stats forjados | T3 | add-ons não pagos |
| 5 | **A-01** dev key | T4, T3 | **bypass total** |
| 6 | **A-02** gate único | T4 | bypass do enforce |
| 7 | **A-05** check-in não assinado | T2 | anula revogação |
| 8 | **A-08** sem detecção | T1 | abuso invisível |

---

## 5. Matriz atacante × eficácia actual

| Atacante | Barreira actual | Resultado hoje |
|----------|-----------------|----------------|
| T1 multi-cliente | binding + 409 no servidor | **Contido** se não patchar; A-01/A-06 quebram |
| T2 ex-cliente | expiry + grace 14d | **Não contido** — A-03 + A-04 |
| T3 add-ons | entitlements no daemon | **Parcialmente** — A-07 na GUI |
| T4 pirata | fonte privado | **Não contido** — A-01 |
| T5 concorrente | fonte privado + EULA | Parcialmente — binário reversível |

---

## 6. Estratégia proposta — quatro camadas

Ordenadas por **retorno sobre esforço**, não por dificuldade técnica.

### Camada 1 — Mover o valor para o serviço *(prioridade máxima)*

**Objectivo:** fazer com que uma instalação sem subscrição válida **degrade
sozinha**, sem depender de nenhuma verificação local que possa ser removida.

**Mudança:** a entrega de conteúdo (blacklists UT1, catálogos, futuras
assinaturas de aplicação) passa a exigir um **token de subscrição** obtido no
check-in — assinado pelo servidor, ligado ao `hardware_id`, de validade curta
(dias). Remover o espelho público anónimo do GitHub para o conteúdo
corrente. O manifesto continua assinado como hoje (a assinatura garante
integridade; o token passa a garantir **entitlement**).

**Porquê primeiro:** é a única defesa **estruturalmente** sólida contra
T1/T2/T4 ao mesmo tempo. Um `layer7d` patchado com dev key continua a correr,
mas em semanas está a classificar com dados obsoletos — e o cliente volta
por vontade própria. Não é preciso ganhar a batalha do binário se o binário
sem subscrição valer pouco.

**Risco:** appliances legitimamente offline deixam de actualizar conteúdo.
Mitigação: token com validade generosa (ex. 30 dias), degradação suave
(conteúdo antigo continua a funcionar, apenas não actualiza) e aviso claro
na GUI. **Nunca** transformar isto em fail-closed do enforce.

**Esforço:** M–G. **Benefício:** Alto.

### Camada 2 — Fechar o barato no binário

**Objectivo:** subir o custo de bypass de «minutos» para «horas com
ferramentas» e eliminar o embaraço de A-01.

**Mudanças propostas:**

1. **Eliminar `is_dev_key()` do caminho de produção.** O modo de
   desenvolvimento passa a existir só sob `#ifdef L7_DEV_BUILD`, flag
   **ausente** do `Makefile` do port. O binário de produção deixa de conter
   a lógica de bypass. *(Corrige A-01.)*
2. **Strip do binário** no `INSTALL_PROGRAM` do port, removendo o mapa de
   símbolos que aponta para as funções de licença.
3. **Distribuir a decisão de licença** por vários pontos, com resultados que
   se cruzam, em vez do `if` único de `refresh_enforce_cfg()`. *(Mitiga
   A-02.)*
4. **Anti-rollback de relógio:** persistir o maior timestamp já observado e
   tratar retrocessos significativos como estado suspeito (degradação para
   monitor + evento de auditoria, nunca crash). *(Corrige A-03.)*
5. **Assinar o estado de entitlements** consumido pela GUI, eliminando o
   fallback sem verificação. *(Corrige A-07.)*

**Risco:** mexer em `license.c` toca no caminho crítico do enforce, e A-09
significa que o ficheiro divergente do builder tem de ser reconciliado antes.
Exige gate no appliance e rollback para o `.pkg` anterior.

**Esforço:** M. **Benefício:** Alto (1, 2 e 4 são P e de ganho imediato).

### Camada 3 — Check-in obrigatório e verificável

**Objectivo:** dar poder comercial real à revogação.

**Mudanças propostas:**

1. `check_in_enabled: true` como **default** em instalações novas, com
   política de migração explícita para as existentes. *(Corrige A-04.)*
2. **Resposta de check-in assinada** (Ed25519, mesma chave) com **nonce**
   anti-replay e `hardware_id` no payload assinado. O cliente rejeita
   respostas não assinadas. *(Corrige A-05.)*
3. Reduzir a janela `max_offline_hours` por defeito, mantendo tolerância
   generosa para redes intermitentes.
4. **Alerta no servidor** para a mesma chave a activar/check-in de múltiplos
   `hardware_id` ou IPs, e decisão sobre `max_activations`. *(Corrige
   A-08.)*

**Risco:** o mais alto dos quatro em termos de suporte. Muda comportamento de
clientes instalados e cria dependência de rede que hoje não existe. Exige
runbook, comunicação a clientes e um caminho de excepção para instalações
verdadeiramente isoladas.

**Esforço:** M–G. **Benefício:** Alto.

### Camada 4 — Rastreabilidade e via contratual

**Objectivo:** tornar o abuso atribuível, para que a EULA seja executável.

**Mudanças propostas:** marcação por cliente no conteúdo entregue e/ou no
artefacto, de modo a que uma cópia encontrada em campo seja atribuível à
origem; completar a cadeia de assinatura de release da ADR-0023 *(A-10)*;
revisão da EULA por advogado quanto a auditoria e penalidade por instalação
excedente.

**Nota:** contra o mercado-alvo (PME com CNPJ e integradores identificáveis),
esta camada tem frequentemente **mais** poder dissuasor do que qualquer
medida técnica. Deve ser tratada como parte da estratégia, não como anexo.

**Esforço:** P–M (técnico) + externo (jurídico). **Benefício:** Médio-Alto.

---

## 7. O que explicitamente **não** se recomenda

| Medida | Porque não |
|--------|-----------|
| Ofuscação pesada / packers / VM de código | Custo de manutenção e risco de instabilidade num daemon root num firewall, muito acima do retorno; dimensionada para T4 (o menos provável) |
| Anti-debug (`ptrace`, deteção de breakpoints) | Trivialmente contornável, ruído em diagnóstico legítimo, risco de falso positivo em campo |
| Fail-closed do produto por falha de rede | Transforma indisponibilidade do license server em **paragem de firewall do cliente**; inaceitável |
| Kill-switch remoto que desliga o enforce | Risco reputacional e legal desproporcionado; a degradação de conteúdo (Camada 1) obtém o efeito comercial sem o risco |
| CRL offline | Já avaliada e rejeitada na ADR-0021; não reabrir sem motivo novo |
| Tornar o repo público | Removeria P8 sem benefício |

---

## 8. Plano faseado proposto

Cada fase é um bloco pequeno, com gate próprio e rollback ao `.pkg` anterior.
**Nenhuma fase inicia sem GO humano explícito.**

| Fase | Conteúdo | Depende de | Gate mínimo |
|------|----------|-----------|-------------|
| **H0** | Este relatório + ADRs + backlog; reconciliar A-09 (alinhar `license.c`/`Makefile` do builder com o repo) | — | revisão humana; `strings` do `.pkg` em campo confirma pubkey de produção |
| **H1** | Camada 2 itens 1, 2 e 4 (dev key, strip, anti-rollback) | H0 | suite C + build FreeBSD + appliance `.254`: licença válida PASS, expirada → monitor, `date` para trás → monitor + evento |
| **H2** | Camada 1 (token de subscrição no conteúdo) | H1 | update de blacklists com token válido PASS; sem token → não actualiza mas **não** perde enforce; offline 30d PASS |
| **H3** | Camada 3 (check-in default + resposta assinada + nonce + alertas) | H2 | revogação no painel corta appliance em ≤ intervalo; servidor falso rejeitado; runbook de migração aprovado |
| **H4** | Camada 2 item 3 (decisão distribuída) + Camada 4 | H3 | sem regressão de enforce; cadeia de release ADR-0023 completa |

**Regra de ouro do plano:** nunca misturar uma fase destas com promoção de
enforce ou com a trilha MITM. São caminhos críticos independentes.

---

## 9. ADRs propostos (a criar após GO)

| ADR | Título proposto | Substitui/emenda |
|-----|-----------------|------------------|
| ADR-0030 | Postura anti-tamper do binário `layer7d` e remoção do modo dev de produção | — |
| ADR-0031 | Entitlement na entrega de conteúdo (token de subscrição para blacklists/catálogos) | emenda ADR-0004 / trilha blacklists |
| ADR-0032 | Check-in obrigatório por defeito e resposta assinada com anti-replay | **emenda ADR-0021** |
| ADR-0033 | Anti-rollback de relógio e tratamento de estado temporal suspeito | emenda `f3-expiracao-revogacao-grace.md` |

## 10. Itens de backlog propostos (a registar após GO)

Numeração seguinte à actual (`BG-113` é o último atribuído).

| ID | Item | Sev. | Área | Fase | Esforço | Benef. | Achado |
|----|------|------|------|------|---------|--------|--------|
| BG-114 | Remover modo dev (`is_dev_key`) do binário de produção; gate por `L7_DEV_BUILD` | **Critica** | daemon/licenciamento | F3/hardening | P | Alto | A-01 |
| BG-115 | Strip do `layer7d` no port; remover símbolos de licença | Alta | package/build | F7 | P | Alto | A-01 |
| BG-116 | Anti-rollback de relógio (marca persistente + degradação para monitor) | Alta | daemon/licenciamento | F3/hardening | M | Alto | A-03 |
| BG-117 | Token de subscrição na entrega de blacklists/catálogos; remover espelho público corrente | Alta | blacklists + license server | F3/F4 | G | Alto | A-06 |
| BG-118 | Check-in `true` por defeito + política de migração | Alta | package + licenciamento | F3 | M | Alto | A-04 (reabre **BG-101**) |
| BG-119 | Resposta de check-in assinada com nonce; rejeitar não assinada | Alta | daemon + license server | F3 | M | Alto | A-05 |
| BG-120 | Estado de entitlements assinado para a GUI; eliminar fallback sem verificação | Media | package/GUI | F3 | M | Medio | A-07 |
| BG-121 | Alerta de abuso multi-appliance no license server (+ decisão sobre `max_activations`) | Media | license server | F2/F3 | M | Alto | A-08 |
| BG-122 | Distribuir decisão de licença (remover ponto único de `refresh_enforce_cfg`) | Media | daemon | F3/hardening | M | Medio | A-02 |
| BG-123 | Completar cadeia de assinatura de release nas publicações (manifesto + `.sig`) | Baixa | release | F7 | P | Medio | A-10 |

---

## 11. Decisões que exigem validação humana

Registadas por exigirem GO explícito, nos termos da secção *Quando parar e
pedir validação humana* do `AGENTS.md`:

1. **Aceitar dependência de rede?** A Camada 3 cria uma dependência
   operacional que hoje não existe. É uma decisão **comercial**, não técnica.
2. **Política de migração de clientes instalados** para check-in obrigatório
   — e se existem clientes genuinamente isolados a acomodar.
3. **Remover o espelho público de blacklists** — afecta quem hoje depende
   dele, incluindo instalações legítimas.
4. **Reconciliar A-09** antes de qualquer alteração a `license.c`: é
   necessário decidir se a chave pública de produção passa a viver no repo
   privado (versionada) ou continua como alteração local do builder.
5. **Reabrir BG-101**, hoje classificado como decisão de design e não bug.
6. **Revisão jurídica da EULA** quanto a auditoria e penalidades.

---

## 12. Riscos da própria mitigação

| Risco | Fase | Mitigação |
|-------|------|-----------|
| Regressão no enforce ao mexer em `license.c` | H1, H4 | bloco pequeno, suite C, gate no appliance, rollback ao `.pkg` anterior |
| Cliente legítimo perde conteúdo por falha de rede | H2 | degradação suave, token de validade longa, aviso na GUI, nunca tocar no enforce |
| Cliente legítimo bloqueado por relógio errado (não malicioso) | H1 | degradar para monitor com evento claro, nunca parar o daemon; documentar recuperação |
| Aumento de chamadas de suporte | H3 | runbook + comunicação antecipada + caminho de excepção |
| Falso sentido de segurança | todas | manter este documento honesto: root continua a poder patchar; a defesa real é a Camada 1 |

---

## 13. Referências

- `src/layer7d/license.c`, `src/layer7d/license.h`, `src/layer7d/main.c`
- `package/pfSense-pkg-layer7/Makefile`, `pkg-plist`,
  `files/usr/local/etc/layer7.json.sample`,
  `files/usr/local/etc/layer7/update-blacklists.sh`
- `license-server/backend/src/` (`crypto.js`, `activate.js`, `check-in.js`,
  `activation-policy.js`), `migrations/001-init.sql`
- [`f3-arquitetura-licenciamento-ativacao.md`](f3-arquitetura-licenciamento-ativacao.md),
  [`f3-fingerprint-e-binding.md`](f3-fingerprint-e-binding.md),
  [`f3-expiracao-revogacao-grace.md`](f3-expiracao-revogacao-grace.md),
  [`f1-arquitetura-de-confianca.md`](f1-arquitetura-de-confianca.md),
  [`f2-arquitetura-license-server.md`](f2-arquitetura-license-server.md)
- ADR-0003, ADR-0004, ADR-0021, ADR-0023, ADR-0025
- [`../06-releases/RELEASE-SIGNING.md`](../06-releases/RELEASE-SIGNING.md)
- `LICENSE` (EULA), `AGENTS.md` (ficheiros sensíveis do builder)
