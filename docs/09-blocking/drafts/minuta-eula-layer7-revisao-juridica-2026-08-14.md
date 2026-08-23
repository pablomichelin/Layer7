# Minuta-base (NÃO VINCULANTE) — EULA Layer7 — revisão jurídica `2026-08-14`

**Estado:** rascunho interno para revisão por **advogado brasileiro**.

**Não é:** parecer jurídico, consultoria legal, cláusula publicada, contrato
assinado, nem fecho do gate **GA6.7**.

**Não substitui:** a EULA publicada em [`../../../LICENSE`](../../../LICENSE).

**Não autoriza:** alteração de `LICENSE`, GUI, pacote, `PORTVERSION`, publish
ou comportamento de activação/revogação.

**Agenda canónica:** [`../eula-revisao-juridica-30.19.md`](../eula-revisao-juridica-30.19.md)
(decisão 6 / `30.19`).

**Destinatário desta minuta:** jurisconsulto / escritório designado pelo
operador — **não** o cliente final.

> **Aviso obrigatório.** Este texto foi preparado por agente de desenvolvimento
> a partir de factos de produto e da EULA inglesa já publicada. **Não** foi
> redigido por advogado. **Não** constitui opinião legal. Qualquer cláusula
> abaixo é **hipótese de trabalho** até revisão, alteração e aprovação
> expressas do jurídico humano. Campos comerciais e jurisdicionais estão
> marcados **`[PENDENTE]`** e **não** podem ser preenchidos pelo agente.

---

## 0. Objectivo / impacto / risco / teste / rollback

| Campo | Valor |
|-------|--------|
| Objectivo | Entregar minuta-base **não vinculante** para o advogado cobrir licença por appliance, activação/fingerprint, excesso de instalações, actualizações, revogação/suspensão com proporcionalidade, LGPD/dados técnicos, suporte/SLA, garantia/responsabilidade, PI/anti-circunvenção, auditoria razoável, vigência/rescisão, lei/foro, aceite e versionamento |
| Impacto | Apenas docs: este ficheiro + apontador na agenda `eula-revisao-juridica-30.19.md`. **Sem** alteração da EULA publicada, do produto ou do veredicto GA6.7 |
| Risco | Baixo (documental). Residual: um leitor tratar esta minuta como contrato ou como parecer. Mitigação: avisos de capa + `[PENDENTE]` + GA6.7 continua **parecer externo pendente** |
| Teste | Links relativos resolvem; agenda aponta para esta minuta; nenhum ficheiro de código/`LICENSE` alterado; GA6.7 **não** marcado concluído |
| Rollback | Apagar este ficheiro e reverter o apontador na agenda. `LICENSE` e produto intactos |

---

## 1. Como usar este documento

1. O operador envia **esta minuta** + a agenda
   [`../eula-revisao-juridica-30.19.md`](../eula-revisao-juridica-30.19.md) +
   ADRs 0030–0033 + fecho
   [`../../01-architecture/fecho-trilha-antipirataria-30.19.md`](../../01-architecture/fecho-trilha-antipirataria-30.19.md).
2. O advogado **reescreve, corta ou rejeita** o que entender. Nada aqui é
   texto final.
3. Só após **parecer escrito** e **GO jurídico humano** se actualiza a EULA
   publicada (`LICENSE` e superfícies que a reproduzam).
4. O agente **só** regista a data do parecer no CORTEX / ficha 30.1 **com
   ditado humano**. Este bloco **não** fecha GA6.7.

### 1.1 O que esta minuta **não** faz

- Não aconselha estratégia litigiosa nem quantifica indenização.
- Não afirma validade, abusividade ou conformidade com CDC, CC, LGPD, Marco
  Civil ou Lei do Software.
- Não cria SLA, preço, foro, CNPJ, cláusula penal nem `max_activations`.
- Não altera o facto técnico da fase 1: abuso multi-appliance = **alerta**,
  sem corte automático por número de activações
  ([`../decisoes-humanas-30.1.md`](../decisoes-humanas-30.1.md) §Decisão 7).

---

## 2. Factos de produto (insumo técnico — não jurídico)

Estes factos descrevem o comportamento **actual** do Layer7. O advogado deve
decidir o que entra no contrato; o agente **não** os transforma em direito.

| Tema | Facto técnico actual | Fonte |
|------|----------------------|--------|
| Titular | Systemup Solucao em Tecnologia (`www.systemup.inf.br`) | `LICENSE`; CORTEX |
| Software | Pacote proprietário Layer7 para pfSense CE; **não** afiliado a Netgate / projecto pfSense | `LICENSE` §11 |
| Licença publicada | Não exclusiva, intransferível, limitada a **um** appliance; ligada ao fingerprint de hardware | `LICENSE` §1 |
| Sem chave válida | Modo **monitor-only** (sem enforcement de tráfego) | `LICENSE` §2 |
| Transferência | Exige autorização escrita prévia do Licenciador | `LICENSE` §2 |
| Grace pós-expiração | 14 dias em modo pleno (EULA publicada) | `LICENSE` §2 |
| Fingerprint | `hardware_id` = SHA-256(`kern.hostuuid` + `:` + primeira MAC Ethernet não-loopback); 64 hex | [`../../01-architecture/f3-fingerprint-e-binding.md`](../../01-architecture/f3-fingerprint-e-binding.md) |
| Activação | Bind write-once de `hardware_id` à chave, salvo fluxo formal de rebind | ADR-0010; F3.2 |
| Check-in | `POST` com `key` + `hardware_id`; resposta pode manter, expirar ou **revogar** (efeito imediato no enforce → monitor) | [ADR-0021](../../03-adr/ADR-0021-check-in-online-e-revogacao-remota.md); [ADR-0032](../../03-adr/ADR-0032-check-in-obrigatorio-e-assinado.md) |
| Check-in default | **ON** em instalações **novas**; upgrade existente **não** é regressivo | `30.14` / decisão 2 |
| Multi-appliance | Fase 1 = **só alerta**; **sem** `max_activations` | `30.15`; decisão 7 |
| Conteúdo (blacklists) | Download corrente exige token de subscrição (AP2); cópia local histórica permanece | [ADR-0031](../../03-adr/ADR-0031-entitlement-entrega-conteudo.md); [`../../01-architecture/contrato-token-subscricao-conteudo-30.8.md`](../../01-architecture/contrato-token-subscricao-conteudo-30.8.md) |
| Marcação local | Sidecar opaco `SHA256(license_id \|\| hardware_id)`; **sem** telemetria / PII cleartext | [`../../01-architecture/marcacao-cliente-30.17.md`](../../01-architecture/marcacao-cliente-30.17.md) |
| Sinal de instalação (sem serial) | Desde `1.9.71`: heartbeat/install para o license-server **mesmo sem chave**. Inventário de equipamento (FQDN, uniqueid, IPs/nomes de iface, versões). IP público = hop TLS no servidor. Fail-open. **Não** é a marca `30.17`. | [ADR-0036](../../03-adr/ADR-0036-install-ping-sem-serial.md); `LICENSE` §8 |
| Actualizações de pacote | Canal público GitHub Releases `pablomichelin/Layer7`; manifesto + `.sig` (F1.2) | ADR-0003; ADR-0023 |
| Suporte / SLA | **Não** há SLA publicado. Contacto comercial: canais contratuais do cliente; e-mail `contato@systemup.inf.br` | [`../../MANUAL-PRODUTO.md`](../../MANUAL-PRODUTO.md) §16; `LICENSE` §11 |
| Relatórios / eventos | Ficam **no appliance** (`reports.db`, logs locais). Tráfego, contas de pessoas e políticas **não** são transmitidos. Hostname, uniqueid e IPs de equipamento **são** enviados no sinal de instalação (`1.9.71+`) | `LICENSE` §8 (rev. BG-162); ADR-0036 |
| Add-ons | Identity / MITM são entitlements separados; MITM permanente **NO-GO** sem novo GO | ADR-0025; ADR-0035 |
| Residual anti-pirataria | Root continua capaz de contornar (RR-1…RR-5). Proibido overclaim «impossível de piratar» | [`../../01-architecture/fecho-trilha-antipirataria-30.19.md`](../../01-architecture/fecho-trilha-antipirataria-30.19.md) |

---

## 3. Campos comerciais e jurisdicionais — todos `[PENDENTE]`

O advogado e o operador **preenchem**. O agente **não** inventa valores.

| ID | Campo | Estado | Nota de produto (não decide o campo) |
|----|-------|--------|--------------------------------------|
| C-01 | Razão social exacta, CNPJ, sede e endereço para citações | `[PENDENTE]` | Branding usa «Systemup Solucao em Tecnologia» |
| C-02 | Qualificação do Licenciado (PJ, consumidor, integrador, MSP) | `[PENDENTE]` | Produto posicionado a PME / appliance |
| C-03 | Preço, moeda, tributos, forma e periodicidade de pagamento | `[PENDENTE]` | Fora desta minuta |
| C-04 | Prazo da licença (determinada / indeterminada / assinatura) | `[PENDENTE]` | Servidor guarda `expiry`; grace publicado = 14 dias |
| C-05 | Número de appliances / instalações incluídos por chave | `[PENDENTE]` | EULA actual = **um** appliance por chave |
| C-06 | Política comercial de rebind / substituição de hardware | `[PENDENTE]` | Rebind automático = fora de fila |
| C-07 | Consequência comercial de instalação excedente (alerta, corte, cláusula penal, perdas e danos) | `[PENDENTE]` | Fase 1 técnica = **só alerta**; sem `max_activations` |
| C-08 | Existência, níveis e créditos de **SLA** | `[PENDENTE]` | **Não há SLA publicado** hoje |
| C-09 | Canal e horário de suporte; o que está incluído no preço | `[PENDENTE]` | Contacto genérico no `LICENSE` |
| C-10 | Cap de responsabilidade e exclusões (e se CDC/CC limitam o cap) | `[PENDENTE]` | EULA actual: cap = valor pago; «AS IS» |
| C-11 | Lei aplicável | `[PENDENTE — advogado]` | EULA actual cita «laws of Brazil» sem diploma |
| C-12 | Foro / comarca / arbitragem | `[PENDENTE — advogado]` | EULA actual: «courts of Brazil» sem comarca |
| C-13 | Idioma vinculante (PT / EN / ambos) | `[PENDENTE]` | EULA publicada está em **inglês** |
| C-14 | Base legal LGPD, papéis (controlador/operador), encarregado e retenção | `[PENDENTE — advogado]` | Ver §8 |
| C-15 | Prazo e forma de aviso antes de revogação/suspensão | `[PENDENTE]` | Check-in técnico pode invalidar **de imediato** |
| C-16 | Política de alteração da EULA e versionamento oponível | `[PENDENTE]` | Ver §12 |
| C-17 | Relação desta EULA com proposta comercial, OS, NDA e add-ons | `[PENDENTE]` | EULA actual tem cláusula de integralidade |
| C-18 | Exportação, uso fora do Brasil, sanções | `[PENDENTE]` | Não tratado na EULA publicada |
| C-19 | Tratamento de listas de terceiros (UT1 / catálogos) | `[PENDENTE]` | Conteúdo tokenizado (AP2); licenças de origem distintas |
| C-20 | Multa / cláusula penal / honorários de sucumbência | `[PENDENTE]` | EULA actual **não** fixa penalidade pecuniária |

---

## 4. Minuta de cláusulas (hipótese de trabalho)

**Convenção:** texto em português para revisão. Trechos entre
`[PENDENTE — …]` **não** são proposta fechada. O advogado pode substituir
integralmente qualquer artigo.

### Preâmbulo

**CONTRATO DE LICENÇA DE USUÁRIO FINAL (EULA) — LAYER7 PARA PFSENSE CE**

**LICENCIADOR:** `[PENDENTE — razão social, CNPJ, sede]`, doravante
«Licenciador».

**LICENCIADO:** a pessoa `[PENDENTE — física e/ou jurídica]` que instala,
activa ou utiliza o Software, doravante «Licenciado».

Ao instalar, copiar, activar ou de outro modo utilizar o software Layer7 para
pfSense CE («Software»), o Licenciado declara ter lido e aceite este
Contrato. Se não concordar, **não** instale nem utilize o Software.

O Software **não** é afiliado à Netgate nem ao projecto pfSense. «pfSense» é
marca de terceiros (`LICENSE` §11).

---

### Artigo 1 — Licença por appliance / instalação

1.1. Sujeito a este Contrato, a uma chave válida e ao pagamento
`[PENDENTE — C-03]`, o Licenciador concede licença **não exclusiva**,
**intransferível** (salvo §1.4), **não sublicenciável** e **limitada** para
instalar e utilizar o Software em
`[PENDENTE — C-05: um appliance / N instalações]`.

1.2. **«Appliance»**, para este Contrato, significa uma instância de sistema
que executa pfSense CE (ou variante aceite pelo Licenciador) e que gera um
único `hardware_id` nos termos do Artigo 2. Reinstalação no **mesmo**
fingerprint conta como a **mesma** instalação. Mudança de `kern.hostuuid`
ou da MAC usada no cálculo **pode** constituir nova instalação
(F3.2 — facto técnico).

1.3. Sem chave válida, o Software pode permanecer instalado em modo
**monitor-only**, sem enforcement. Isso **não** amplia a licença de uso
pleno.

1.4. Cessão, aluguer, hosting multi-tenant ou transferência para outro
appliance exigem `[PENDENTE — C-06: autorização escrita / rebind comercial]`.

1.5. Add-ons (incluindo Identity e, se algum dia licenciado, MITM) só se
consideram licenciados se o entitlement respectivo constar da chave / do
check-in. **Não** há autorização permanente de intercepção TLS neste
rascunho.

---

### Artigo 2 — Activação e fingerprint

2.1. A activação associa a chave a um `hardware_id` (fingerprint). O
Licenciado autoriza o envio ao servidor de licenças da **chave** e do
**fingerprint**, para verificação, bind e prevenção de abuso.

2.2. O fingerprint é derivado tecnicamente de identificadores de sistema
(`kern.hostuuid` e endereço MAC). A **qualificação jurídica** desses dados
(dado pessoal, dado técnico, dado anonimizado) é `[PENDENTE — C-14 / Q-08]`.

2.3. O bind é, em regra, **write-once**. Substituição de hardware, reinstall
com fingerprint diferente ou clonagem de VM podem recusar activação
(`409` / mismatch). Recuperação comercial =
`[PENDENTE — C-06]`.

2.4. O Licenciado **não** deve alterar, forjar ou substituir o fingerprint
para multiplicar instalações.

2.5. Check-in periódico (default **ON** em instalações novas) revalida o
estado da licença. Falha de rede segue a janela offline do produto
(`max_offline` / grace — valores operacionais; o prazo **contratual** de
aviso é `[PENDENTE — C-15]`).

---

### Artigo 3 — Limites de uso e instalações excedentes

3.1. O Licenciado **não** pode utilizar o Software em mais appliances do que
os licenciados `[PENDENTE — C-05]`.

3.2. São condutas **não autorizadas** (além das do Artigo 8):

- copiar a chave, o ficheiro `.lic` ou o conteúdo corrente para terceiros
  não licenciados (residual RR-2);
- redistribuir blacklists / catálogos obtidos com token de subscrição;
- operar vários appliances com a mesma chave sem autorização.

3.3. **Instalação excedente (fase 1 de produto).** O Licenciador pode
**detectar e alertar** (multi-appliance / check-in). **Não** está
implementado corte automático por `max_activations`. A consequência
**contratual** do excedente é `[PENDENTE — C-07]`:

- `[ ]` apenas notificação e regularização em prazo `[PENDENTE]`;
- `[ ]` suspensão / revogação após aviso `[PENDENTE — C-15]`;
- `[ ]` cláusula penal de `[PENDENTE — C-20]`;
- `[ ]` perdas e danos / tutela específica;
- `[ ]` outra, a redigir pelo advogado.

3.4. Alertas técnicos **não** substituem o contraditório contratual que o
advogado vier a exigir (proporcionalidade — Artigo 5).

---

### Artigo 4 — Actualizações e conteúdo

4.1. O Licenciador pode disponibilizar actualizações do pacote (releases
assinadas) e conteúdo de blacklists/catálogos. **Não** há, neste rascunho,
obrigação de manter canal, prazo de suporte de versão ou compatibilidade
com forks de pfSense — `[PENDENTE — C-08 / C-09]`.

4.2. Conteúdo **corrente** pode exigir token de subscrição ligado à licença
activa (AP2). Após expiração do token, o download corrente pode cessar; cópias
**já materializadas** no appliance podem continuar a ser usadas (facto
técnico D4 do contrato `30.8`). O efeito **contratual** sobre enforce e
listas é `[PENDENTE]`.

4.3. Actualizações podem alterar comportamento, retirar entitlements via
check-in (o check-in **não** acrescenta além do `.lic`) ou exigir nova
aceitação da EULA `[PENDENTE — C-16]`.

4.4. Releases antigas com controlos mais fracos podem permanecer
descarregáveis (residual RR-3). Isso **não** é licença para contornar
versões actuais.

---

### Artigo 5 — Revogação e suspensão — proporcionalidade

5.1. O Licenciador pode **suspender** ou **revogar** a licença nas hipóteses
`[PENDENTE — advogado]`, exemplificativamente: inadimplemento; fraude;
excesso de instalações não regularizado; violação de PI / anti-circunvenção;
ordem legal; risco grave à segurança do serviço de licenças.

5.2. **Proporcionalidade (a redigir pelo advogado).** O rascunho pede que o
parecer distinga, no mínimo:

| Gravidade (hipótese) | Efeito técnico possível hoje | Efeito contratual proposto |
|----------------------|------------------------------|----------------------------|
| Atraso de pagamento / questão comercial | Check-in pode revogar e cair para monitor | `[PENDENTE — aviso prévio C-15]` |
| Excedente de instalação (fase 1) | Alerta; sem corte por contagem | `[PENDENTE — C-07]` |
| Circunvenção / redistribuição | Revogação imediata possível no check-in | `[PENDENTE — se imediato é lícito]` |
| Pedido do Licenciado / fim de prazo | Expiração + grace 14 dias (EULA actual) | `[PENDENTE — C-04]` |
| Rede isolada / cliente honesto (R-J) | Janela offline; runbooks de recuperação | `[PENDENTE — não punir isolamento legítimo]` |

5.3. O facto técnico de a revogação no check-in **invalidar de imediato** o
`.lic` **não** decide se o contrato pode omitir aviso prévio. Isso é
`[PENDENTE — Q-05]`.

5.4. Após rescisão/revogação, o Licenciado cessa o uso pleno e remove chaves
e cópias não autorizadas, sem prejuízo de cópias exigidas por lei
`[PENDENTE]`.

---

### Artigo 6 — Privacidade, LGPD e dados técnicos

6.1. **Categorias observadas no produto (inventário técnico, não RIPD):**

| Dado | Onde | Transmitido ao Licenciador? | Finalidade técnica declarada |
|------|------|-----------------------------|------------------------------|
| Chave de licença | Activação / check-in | Sim | Verificação e bind |
| `hardware_id` (hash) | Activação / check-in / `.lic` / install-ping | Sim | Bind, abuso e inventário de instalação |
| Inventário de instalação (hostname, domínio, uniqueid, IPs de iface, versões, `install_id`) | `POST /api/license/install-ping` (`≥ 1.9.71`) | Sim | Contabilidade de instalação, suporte, anti-abuso. **Sem** serial obrigatório |
| IP público (`egress_ip`) | Servidor (hop TLS confiável; não XFF do cliente) | Sim (gravado no servidor) | Identificar a caixa atrás de CGNAT |
| Estado da licença / timestamps | License-server | Sim (servidor próprio) | Ciclo de vida |
| Token de conteúdo | Check-in → appliance | Emitido pelo servidor | Entitlement de download |
| Marca de atribuição | Sidecar **local** | Não (GA6.4) | Atribuição offline (RR-2) |
| Tráfego, SNI, políticas, relatórios | Appliance | Não, segundo EULA §8 | Operação local |
| Nome/e-mail/CNPJ de cliente | Painel do Licenciador (cadastro) | Cadastro comercial | `[PENDENTE — C-14]` |
| Payload MITM | Fora do default; MITM NO-GO permanente | N/A neste rascunho | Não licenciar aqui |

6.2. Papéis LGPD, bases legais (execução de contrato, legítimo interesse,
obrigação legal), encarregado, direitos do titular, transferência
internacional e retenção: **`[PENDENTE — C-14]`**.

6.3. A EULA publicada (`LICENSE` §8, emenda BG-162 / `2026-08-22`) **deixou
de** afirmar que não há PII. Lista o inventário de instalação/heartbeat
(hostname, domínio, uniqueid, IPs, IP público no servidor) e a finalidade
(suporte / anti-abuso). Fingerprint, hostname e IPs LAN podem ser dados
pessoais **ou** de pessoa jurídica conforme o caso. O agente **não**
qualifica. Residual jurídico: `[PENDENTE — C-14 / Q-08]`.

6.4. O Licenciado (administrador do firewall) é, em regra, quem trata dados
de **seus** utilizadores finais no appliance. Este rascunho **não** transfere
ao Licenciador a operação do pfSense do cliente.

6.5. Relatórios de erro via GUI (GitHub) são **opt-in** e não incluem `.lic`,
chaves, dumps nem IPs de clientes — facto de produto; base legal
`[PENDENTE]`.

---

### Artigo 7 — Suporte e SLA

7.1. **Estado actual:** não existe SLA publicado (disponibilidade, P1–P4,
créditos, RTO/RPO).

7.2. Hipótese: suporte limita-se a `[PENDENTE — C-08 / C-09]`. Na ausência
de acordo escrito de nível de serviço, o Licenciador **não** garante prazo
de resposta nem uptime do license-server, do GitHub ou das blacklists.

7.3. Canais: `[PENDENTE]`. Contacto histórico de produto:
`contato@systemup.inf.br` / `https://www.systemup.inf.br`.

7.4. Isolamento de rede, relógio incorrecto ou troca de NIC são cenários de
**cliente honesto** (R-J): o contrato deve prever recuperação
`[PENDENTE — sem exigir suporte pago para o que o runbook já cobre]`.

---

### Artigo 8 — Garantia e limitação de responsabilidade

8.1. A EULA publicada oferece o Software **«AS IS»**, sem garantia de
comerciabilidade, adequação ou ausência de erros. **Manter, suavizar ou
substituir** essa cláusula é `[PENDENTE — Q-10]`, especialmente se houver
Licenciado consumidor (CDC) ou relação de adesão.

8.2. Hipótese de cap: responsabilidade total do Licenciador limitada a
`[PENDENTE — C-10; EULA actual = valor pago pela licença]`.

8.3. Hipótese de exclusão: lucros cessantes, perda de dados, interrupção de
negócio, danos indirectos — `[PENDENTE — validade no Brasil]`.

8.4. **Não excluir** (a confirmar pelo advogado): dolo, culpa grave, danos
decorrentes de norma de ordem pública, e o que o CDC/CC tornarem nulos.

8.5. O Software opera em firewall de terceiros (pfSense). O Licenciador
**não** licencía o pfSense e **não** responde por falhas do SO, do hardware
ou de regras PF criadas pelo administrador — `[PENDENTE — redacção]`.

8.6. Limitação técnica honesta: DPI, ECH/QUIC/CDN, IPv6 e MITM têm
fronteiras documentadas. Overclaim de «bloqueio 100%» ou «anti-pirataria
absoluta» é **proibido** no produto e **não** deve constar da EULA final.

---

### Artigo 9 — Propriedade intelectual e anti-circunvenção

9.1. O Software, marcas Systemup/Layer7, chaves públicas de verificação,
documentação e conteúdo próprio permanecem do Licenciador ou de seus
licenciantes. Este Contrato **não** transfere titularidade.

9.2. O Licenciado **não** pode, salvo permissão legal **irrenunciável**
`[PENDENTE — Lei 9.609/1998, interoperabilidade, engenharia reversa lícita]`:

- copiar, modificar, traduzir ou criar obras derivadas;
- fazer engenharia reversa, descompilação ou desmontagem;
- remover avisos de titularidade;
- contornar, desactivar ou adulterar verificação de licença, check-in,
  anti-rollback, assinatura de release ou token de conteúdo;
- publicar ou revender o Software ou o conteúdo corrente.

9.3. Medidas técnicas de protecção (pubkey no binário, check-in assinado,
token, marcação local) são **licitas na medida em que o parecer o confirmar**.
O residual de um utilizador com root (RR-1…RR-5) **não** legitima a
circunvenção.

9.4. Listas de terceiros (ex. UT1) conservam as licenças de origem
`[PENDENTE — C-19]`.

---

### Artigo 10 — Auditoria razoável

10.1. Âmbito técnico já existente (insumo da decisão 6 / `30.15`):

- registo de activações e `hardware_id`;
- check-ins;
- sinal de instalação / heartbeat sem serial (ADR-0036; inventário de
  equipamento, não de utilizadores finais);
- alertas de uso em múltiplos appliances;
- atribuição local de conteúdo (`30.17`), sem phone-home da marca.

10.2. Hipótese contratual: o Licenciador pode usar esses **registos
remotos já gerados pelo produto** para verificar conformidade, com
finalidade de licenciamento e prevenção de fraude — **não** de profiling
de utilizadores finais do Licenciado.

10.3. Auditoria **adicional** (acesso ao appliance, logs de tráfego, visita
on-site, perícia): `[PENDENTE — Q-03]`. Este rascunho sugere:

- aviso prévio de `[PENDENTE]` dias;
- horário comercial;
- mínimo necessário;
- confidencialidade;
- **sem** exigir captura de payload nem dados de utilizadores finais;
- custo a cargo de quem `[PENDENTE]`.

10.4. Recusa injustificada de auditoria razoável pode constituir
inadimplemento `[PENDENTE]`.

---

### Artigo 11 — Vigência e rescisão

11.1. Vigência: `[PENDENTE — C-04]` (até expiração da chave / prazo
indeterminado / renovação automática).

11.2. Rescisão pelo Licenciado: `[PENDENTE — aviso, reembolso, uso até ao
fim do período pago]`.

11.3. Rescisão pelo Licenciador: Artigo 5 + `[PENDENTE — C-15 / C-07]`.

11.4. Sobrevivência: PI, limitação de responsabilidade, confidencialidade,
auditoria de factos já ocorridos, foro e obrigações de cessar uso —
`[PENDENTE — lista final]`.

11.5. A EULA publicada prevê cessação **automática sem aviso** por
incumprimento. Manter esse automatismo é `[PENDENTE — Q-05]`.

---

### Artigo 12 — Aceite e versionamento

12.1. **Aceite.** Hipóteses a escolher pelo advogado `[PENDENTE — C-16]`:

- `[ ]` clickwrap na GUI (Definições / activação);
- `[ ]` aceite no `pkg add` / instalador;
- `[ ]` aceite na proposta comercial / OS;
- `[ ]` uso = aceite (shrinkwrap), se lícito no caso concreto.

12.2. A EULA **publicada** está em inglês e no ficheiro `LICENSE` do
repositório/pacote. Se o idioma vinculante for o português,
`[PENDENTE — C-13]` deve resolver conflito PT/EN.

12.3. **Versionamento.** Cada EULA oponível deve ter `versão`, `data de
vigência` e `URL` ou hash do texto. Alterações materiais exigem
`[PENDENTE — novo aceite / aviso de N dias / recusa = não renovar]`.

12.4. Esta minuta tem identificador de rascunho
`minuta-eula-layer7-revisao-juridica-2026-08-14` e **não** é versão
oponível ao mercado.

12.5. Integralidade: a EULA final + documentos `[PENDENTE — C-17]`
substituem entendimentos anteriores **apenas** após o jurídico o redigir.
Este rascunho **não** derroga a `LICENSE` vigente.

---

### Artigo 13 — Lei e foro `[PENDENTE — advogado]`

13.1. Lei aplicável: `[PENDENTE — C-11]`.

*Nota de produto:* a EULA actual remete genericamente às leis do Brasil.

13.2. Foro / arbitragem: `[PENDENTE — C-12]`.

*Nota de produto:* a EULA actual remete aos «tribunais do Brasil» **sem**
comarca. **Não** preencher cidade, cláusula de eleição de foro ou câmara
arbitral sem o advogado.

13.3. Se o Licenciado for consumidor, a eleição de foro e as limitações
dos Artigos 8 e 11 devem ser reescritas `[PENDENTE — Q-01]`.

---

## 5. Perguntas obrigatórias ao advogado

Respostas **escritas** destas perguntas são o insumo mínimo do parecer.
**Sem** essas respostas, GA6.7 permanece residual (parecer externo
pendente). O agente **não** as responde.

| # | Pergunta | Porquê é obrigatória |
|---|----------|----------------------|
| Q-01 | O Licenciado-tipo (PME com CNPJ / integrador) é relação **B2B** ou pode haver **consumidor** (CDC)? Que cláusulas de adesão são de risco? | Define validade de «AS IS», cap, foro e rescisão automática |
| Q-02 | A licença **por um appliance / um fingerprint** é oponível? Como redigir reinstall, VM, HA, lab e substituição por avaria sem criar direito a instalações ilimitadas? | Núcleo comercial + F3.2 |
| Q-03 | Que **auditoria** (check-in, `hardware_id`, alertas `30.15`, marca `30.17`) é lícita e proporcional? Precisa de cláusula expressa? On-site é necessário ou é excessivo? | Âmbito da decisão 6 |
| Q-04 | Que **consequência** lícita cabe a instalação excedente / redistribuição (RR-2) **sem** `max_activations` na fase 1? Cláusula penal vs perdas e danos vs regularização? | Âmbito da decisão 6 / 7 |
| Q-05 | A **revogação imediata** via check-in (enforce → monitor, remoção do `.lic`) exige aviso prévio, contraditório ou proporcionalidade graduada? Em que hipóteses o imediato é defensável? | Artigo 5; ADR-0021/0032 |
| Q-06 | Check-in **default ON** em instalações novas e migração de bases antigas: o contrato deve exigir consentimento adicional ou basta execução contratual? | Decisão 2 / `30.14` |
| Q-07 | Token de conteúdo (AP2) e corte do espelho público: o Licenciado tem direito adquirido a listas correntes após fim da subscrição? | ADR-0031; RR-1/RR-2 |
| Q-08 | `hardware_id`, chave, logs de activação/check-in, **inventário de instalação (FQDN, uniqueid, IPs LAN, IP público)** e cadastro (nome, e-mail, CNPJ) são **dados pessoais**? Quem é controlador? Que base legal, retenção, RIPD e aviso de privacidade são exigíveis? A emenda `LICENSE` §8 (BG-162) alinha o texto ao produto; o advogado confirma se basta. | LGPD; GA6.3/6.4; ADR-0036 |
| Q-09 | Deve existir **SLA**? Se não, como excluir uptime de license-server/GitHub/CDN sem cláusula abusiva? | Não há SLA hoje |
| Q-10 | A limitação de responsabilidade e a exclusão de garantia da EULA inglesa sobrevivem no Brasil? Que redacção mínima (vícios, dolo, serviço essencial de firewall) é recomendada? | Artigo 8 |
| Q-11 | Engenharia reversa / interoperabilidade: o que **não** pode ser proibido na Lei do Software e no CC? Como redigir anti-circunvenção sem nulidade? | Artigo 9 |
| Q-12 | Qual **lei e foro** (ou arbitragem) e em que **comarca**? A EULA deve passar a português vinculante? Como versionar e obter aceite oponível (GUI vs `LICENSE` no pacote)? | Artigos 12–13; C-11…C-16 |
| Q-13 | Há obrigação de aviso prévio / proporcionalidade na **suspensão por atraso** distinta da **revogação por fraude**? | Evitar tratamento único para R-J e abuso |
| Q-14 | Listas de terceiros (UT1) e marcas pfSense/Netgate: que ressalvas de licença de terceiros são necessárias? | C-19; `LICENSE` §11 |
| Q-15 | Após o parecer, qual o processo para **substituir** a EULA publicada (inglês no `LICENSE`) sem vício de aceite dos clientes já activos? | C-16; não fechar GA6.7 sem isto |

---

## 6. Pacote a enviar ao escritório

| Item | Caminho |
|------|---------|
| Esta minuta (não vinculante) | este ficheiro |
| Agenda e âmbito GA6.7 | [`../eula-revisao-juridica-30.19.md`](../eula-revisao-juridica-30.19.md) |
| Decisão 6 e 7 | [`../decisoes-humanas-30.1.md`](../decisoes-humanas-30.1.md) |
| Fecho de engenharia `30.19` | [`../../01-architecture/fecho-trilha-antipirataria-30.19.md`](../../01-architecture/fecho-trilha-antipirataria-30.19.md) |
| EULA **publicada** (inglês) | [`../../../LICENSE`](../../../LICENSE) |
| ADR anti-pirataria | [0030](../../03-adr/ADR-0030-postura-anti-tamper-layer7d.md), [0031](../../03-adr/ADR-0031-entitlement-entrega-conteudo.md), [0032](../../03-adr/ADR-0032-check-in-obrigatorio-e-assinado.md), [0033](../../03-adr/ADR-0033-anti-rollback-relogio.md) |
| Check-in / revogação | [ADR-0021](../../03-adr/ADR-0021-check-in-online-e-revogacao-remota.md) |
| Sinal de instalação sem serial | [ADR-0036](../../03-adr/ADR-0036-install-ping-sem-serial.md); [`../../01-architecture/contrato-install-ping-bg162.md`](../../01-architecture/contrato-install-ping-bg162.md) |
| Fingerprint | [`../../01-architecture/f3-fingerprint-e-binding.md`](../../01-architecture/f3-fingerprint-e-binding.md) |
| Expiração / grace | [`../../01-architecture/f3-expiracao-revogacao-grace.md`](../../01-architecture/f3-expiracao-revogacao-grace.md) |
| Privacidade da marca | [`../../01-architecture/marcacao-cliente-30.17.md`](../../01-architecture/marcacao-cliente-30.17.md) |
| Token de conteúdo | [`../../01-architecture/contrato-token-subscricao-conteudo-30.8.md`](../../01-architecture/contrato-token-subscricao-conteudo-30.8.md) |
| Gates (GA6.7 residual) | [`../plano-gates-antipirataria.md`](../plano-gates-antipirataria.md) |

---

## 7. Veredicto deste bloco (agente)

| Afirmação | Valor |
|-----------|--------|
| Isto é parecer jurídico? | **Não** |
| Isto fecha GA6.7? | **Não** — residual continua parecer **externo** |
| Isto altera a EULA publicada? | **Não** |
| Isto cria SLA, foro ou penalidade? | **Não** — tudo `[PENDENTE]` |
| Próximo passo | Humano: designar advogado e enviar o pacote da §6 |
