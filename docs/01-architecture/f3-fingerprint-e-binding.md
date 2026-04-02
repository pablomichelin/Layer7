# F3.2 — Fingerprint e Binding do Appliance

## Finalidade

Este documento fecha a parte canónica da **F3.2** para fingerprint,
binding de licenca e previsibilidade operacional em cenarios reais de
appliance.

Objectivo desta subfase:

- descrever o comportamento real observado no codigo actual;
- explicitar o que o servidor e o daemon assumem sobre `hardware_id`;
- formalizar uma matriz conservadora de cenarios reais;
- reduzir ambiguidade operacional sem mudar a formula do fingerprint;
- aplicar apenas um endurecimento minimo e compativel, se existir.

**Estado desta subfase:** documentacao canónica consolidada em
`2026-04-01`, sem redesign do algoritmo de fingerprint e sem quebra de
compatibilidade das licencas ja emitidas.

---

## 1. Leitura objectiva do comportamento actual

### 1.1 Formula real do fingerprint no daemon

Observado em `src/layer7d/license.c`:

- o daemon le `kern.hostuuid` via `sysctlbyname("kern.hostuuid", ...)`;
- percorre `getifaddrs()` ate encontrar a **primeira** interface:
  - com `AF_LINK`;
  - sem `IFF_LOOPBACK`;
  - com `sdl_type == IFT_ETHER`;
  - com `sdl_alen == 6`;
- formata a MAC como `xx:xx:xx:xx:xx:xx` em lowercase;
- concatena `kern.hostuuid + ":" + mac`;
- calcula `SHA256()` desse texto;
- devolve o fingerprint em `64` hex lowercase.

Formula observada:

```text
hardware_id = SHA256(kern.hostuuid + ":" + primeira_mac_ethernet_nao_loopback)
```

### 1.2 Normalizacoes e fontes de dados

Observado no codigo actual:

- `kern.hostuuid`:
  - e usado como vem do sistema;
  - apenas `\n` e `\r` finais sao removidos;
  - nao ha lowercase, regex ou normalizacao estrutural adicional;
- `MAC`:
  - e renderizada em lowercase com `snprintf("%02x:%02x:...")`;
  - loopback e ignorado;
  - apenas interfaces vistas como `IFT_ETHER` entram no calculo;
- `hardware_id` enviado ao servidor:
  - o daemon envia o hash em lowercase;
  - o backend faz `trim()` e `toLowerCase()` no payload;
  - o backend exige regex `^[a-f0-9]{64}$`.

### 1.3 Dependencias do ambiente que influenciam o fingerprint

Observado no codigo e inferido de forma conservadora:

- qualquer mudanca em `kern.hostuuid` muda o fingerprint;
- qualquer mudanca na **primeira MAC Ethernet nao-loopback encontrada**
  muda o fingerprint;
- a ordem de enumeracao de interfaces por `getifaddrs()` importa;
- interfaces loopback sao ignoradas;
- interfaces nao-Ethernet sao ignoradas;
- appliances virtuais entram no mesmo contrato se a NIC virtual aparecer
  como `IFT_ETHER` com MAC de `6` bytes.

### 1.4 Onde o `hardware_id` entra no contrato do servidor

Observado em `license-server/backend/src/routes/activate.js`,
`crud-validation.js` e `crypto.js`:

1. `POST /api/activate` recebe `{"key","hardware_id"}`.
2. O backend normaliza o `hardware_id` do payload para lowercase e valida
   `64` hex.
3. Na primeira activacao valida:
   - fixa `licenses.hardware_id`;
   - fixa `activated_at`;
   - emite `.lic` assinado com esse `hardware_id`.
4. Depois do bind:
   - o servidor nao recalcula fingerprint;
   - trata `hardware_id` como valor opaco;
   - exige igualdade exacta para reactivacao.
5. O download administrativo do `.lic` tambem reemite a licenca a partir do
   `hardware_id` persistido.

Conclusao observada:

- o **servidor assume** que o daemon e a fonte de verdade do fingerprint;
- o **daemon assume** que o `hardware_id` assinado no `.lic` tem de bater de
  forma exacta com o fingerprint local recalculado.

### 1.5 Como isso impacta emissao e validacao do `.lic`

Observado no codigo:

- o servidor assina um JSON com:
  - `hardware_id`
  - `expiry`
  - `customer`
  - `features`
  - `issued`
- o daemon verifica a assinatura Ed25519;
- depois compara `hardware_id` do `.lic` com `strcmp()` contra o fingerprint
  local;
- se o `hardware_id` nao bater, a licenca fica invalida;
- se a `expiry` tiver passado, o daemon ainda tolera grace local de `14`
  dias para um `.lic` ja emitido.

### 1.6 Fragilidades observadas no comportamento actual

Observado directamente no codigo:

1. **Dependencia de ordem de interfaces**
   o algoritmo usa a primeira MAC Ethernet encontrada, nao uma seleccao
   canonica ordenada.

2. **Dependencia forte de `kern.hostuuid`**
   qualquer reinstall, clone, restore ou migracao que altere esse valor muda o
   fingerprint.

3. **Ausencia de tolerancia estrutural**
   o servidor nao conhece NIC, hostuuid ou appliance; conhece apenas a string
   `hardware_id`.

4. **Comparacao local exacta no daemon**
   o `.lic` e aceite apenas se o `hardware_id` assinado bater exactamente com
   o fingerprint local recalculado.

5. **Sem identidade composta multi-NIC**
   appliances com varias NICs continuam dependentes da interface que aparecer
   primeiro no `getifaddrs()`.

---

## 2. Matriz de cenarios reais do appliance

As decisoes abaixo distinguem:

- **observado no codigo**: o que o software faz hoje;
- **inferencia conservadora**: o que tende a acontecer num appliance real
  dado o algoritmo actual.

| Cenario | Fingerprint tende a mudar? | Comportamento actual provavel | Risco operacional | Decisao/documentacao F3.2 |
|---------|----------------------------|-------------------------------|-------------------|---------------------------|
| Reinstalacao sem troca de hardware | **Talvez**. So permanece estavel se `kern.hostuuid` e a MAC efectiva usada continuarem iguais. | Se o fingerprint mantiver, reactivacao valida. Se mudar, `409` no servidor e mismatch local no `.lic`. | Medio | Tratar como **reativacao legitima apenas quando o fingerprint ficar identico**. Sem garantia automatica por ser "mesma maquina". |
| Troca de placa de rede | **Sim**, se a NIC trocada for a MAC efectiva usada pelo algoritmo ou se alterar a primeira NIC elegivel. | Activacao futura tende a falhar com `409`. `.lic` antigo tende a falhar no daemon. | Alto | Tratar como **mudanca que exige accao administrativa ou nova licenca**. Sem tolerancia automatica. |
| Reordenacao de interfaces | **Talvez**. O hash muda se a primeira NIC elegivel passar a ser outra. | Mesmo hardware fisico pode parecer appliance diferente. | Alto | Formalizar como risco real do algoritmo actual. Nenhuma promessa de estabilidade sem preservar a primeira NIC efectiva. |
| Clone de VM | **Quase sempre sim** se o clone gerar novo `hostuuid` ou nova MAC. | O clone tende a competir com o bind existente e a falhar com `409`. | Alto | Tratar como **reativacao suspeita** por defeito. Clone nao e reactivacao legitima automatica. |
| Restore de snapshot | **Talvez nao** se snapshot restaurar o mesmo `hostuuid` e a mesma MAC. | Pode continuar a activar e validar normalmente no mesmo bind. | Medio | Aceitavel apenas quando o fingerprint resultante permanecer igual. |
| Migracao de hypervisor | **Talvez / Sim**. Depende de preservacao de UUID e MAC pela plataforma. | Migracoes "transparentes" podem funcionar; migracoes que regeneram UUID/MAC tendem a falhar. | Alto | Documentar como **nao garantido**. Requer validacao do fingerprint antes de assumir compatibilidade. |
| Troca de motherboard / UUID | **Sim** se `kern.hostuuid` mudar. | O servidor ve novo hardware e bloqueia reactivacao automatica. | Alto | Tratar como **mudanca que exige nova licenca ou accao administrativa explicita**. |
| Restore do sistema com MAC diferente | **Sim**. | Mesmo com disco/config iguais, o bind tende a deixar de bater. | Alto | Considerar **mudanca incompatível com bind actual**. |
| Appliance fisico com multiplas NICs | **Talvez**. Depende de qual NIC aparece primeiro e se isso permanece estavel. | Pode funcionar por longos periodos, mas reorder/substituicao de NIC pode mudar o fingerprint sem alterar o resto do appliance. | Medio/Alto | Documentar que o bind efectivo depende da primeira NIC Ethernet elegivel observada pelo daemon. |
| Appliance virtual com MAC regenerado | **Sim**. | Reinstall/reactivacao posterior tende a falhar com `409`; `.lic` antigo tende a invalidar localmente. | Alto | Tratar como **mudanca que exige accao administrativa**. |

---

## 3. Politica conservadora oficial da F3.2

### 3.1 Unidade oficial de binding nesta fase

Durante a F3.2, a unidade oficial de binding continua a ser:

- a string exacta de `hardware_id` produzida pelo daemon actual;
- sem reinterpretacao do servidor;
- sem multiplo fingerprint por licenca;
- sem rebind automatico;
- sem tolerancia ampla.

### 3.2 Definicoes oficiais

#### Primeira activacao valida

- licenca `active`, nao arquivada, nao revogada e nao expirada para activacao
  online;
- `hardware_id` valido em formato `64` hex;
- `hardware_id` ainda nao vinculado na licenca;
- servidor fixa o bind e emite `.lic`.

#### Reativacao legitima

- mesma `license_key`;
- mesmo `hardware_id` ja vinculado;
- licenca ainda valida para activacao online.

Efeito oficial:

- pode reemitir `.lic`;
- nao rebinda;
- nao legitima mudanca de appliance.

#### Reativacao suspeita

- mesma `license_key` com `hardware_id` diferente do bind actual;
- ou cenario operacional que costume gerar fingerprint novo sem mudanca
  comercial explicitamente aprovada, como clone de VM ou migracao com UUID/MAC
  regenerados.

Efeito oficial:

- falha fechada no servidor com `409`;
- nao existe rebind automatico.

#### Mudanca aceitavel de ambiente

Mudanca so e considerada aceitavel sem accao administrativa quando o
fingerprint resultante continuar exactamente igual ao bind existente.

Exemplos:

- reinstall que preserva `kern.hostuuid` e a MAC efectiva;
- restore de snapshot que preserva o mesmo fingerprint.

#### Mudanca que exige nova licenca ou accao administrativa

Qualquer mudanca que altere o fingerprint efectivo:

- troca de NIC;
- regeneracao de MAC;
- mudanca de `kern.hostuuid`;
- clone de VM para nova instancia;
- migracao de hypervisor que altere UUID/MAC;
- troca parcial de hardware que mude a identidade observada pelo daemon.

### 3.3 Compatibilidade oficial desta fase

Decisoes explicitas de compatibilidade:

1. **Nao mudar a formula do fingerprint agora.**
2. **Nao rebinding automatico.**
3. **Nao quebrar o contrato `.lic` existente.**
4. **Nao alterar grace, expiracao ou revogacao fora da clareza documental ja
   fechada.**
5. **Aceitar apenas uma normalizacao defensiva de formato no servidor quando
   ela nao mudar o fingerprint real, apenas a representacao persistida.**

---

## 4. Gaps reais entre teoria e operacao

### 4.1 Gaps confirmados

- a documentacao anterior explicava o bind, mas nao explicitava que ele
  depende da **primeira NIC Ethernet** observada;
- "mesmo appliance" nao implica "mesmo fingerprint";
- reinstall, restore e migracao so sao seguros quando preservam UUID e MAC
  efectivos;
- o servidor sozinho nao distingue reactivacao legitima de clone de VM se o
  `hardware_id` mudar.

### 4.2 Falsos bloqueios possiveis

- reorder de interfaces em appliance com multiplas NICs;
- troca de NIC sem perceber que aquela era a primeira elegivel;
- migracao/restore que regenere MAC ou `hostuuid`;
- dado legacy persistido com `hardware_id` equivalente mas com casing/trim
  diferente do valor canónico.

### 4.3 Rebind indevido que a F3.2 evita

- clone de VM tratado como se fosse reactivacao legitima;
- segunda activacao em hardware diferente apos bind inicial;
- limpeza operacional apressada que esconda mudanca real de appliance.

---

## 5. Melhoria tecnica minima e segura aplicada

### 5.1 Mudanca

Nesta subfase foi aceite apenas um endurecimento defensivo de baixo risco no
servidor:

- o `hardware_id` persistido passa a ser **normalizado de forma canónica
  (trim + lowercase)** antes de comparacao e antes da emissao oficial do
  `.lic`, quando essa normalizacao nao muda o valor semantico do fingerprint;
- a activacao passa a poder corrigir apenas o formato legacy do bind
  persistido, sem trocar o fingerprint real nem abrir rebind automatico.

### 5.2 Objectivo

Eliminar fragilidade boba de representacao (`casing`/espacos residuais) entre:

- bind persistido no banco;
- comparacao do backend;
- `hardware_id` assinado no `.lic`;
- comparacao exacta do daemon.

### 5.3 Impacto

- preserva compatibilidade com licencas e activacoes actuais;
- nao altera a formula do fingerprint;
- nao altera o contrato publico de `POST /api/activate`;
- reduz risco de falso bloqueio por drift de formato em dados legacy.

### 5.4 Risco

- baixo;
- concentrado apenas na representacao textual do `hardware_id`;
- sem alterar auth, admin, TLS, CRUD administrativo, grace, revogacao ou
  runtime do daemon.

### 5.5 Teste minimo exigido

- checagem de sintaxe dos ficheiros JS alterados;
- revisao de diff objectivo;
- validacao documental cruzada.

### 5.6 Rollback

- reverter o commit desta subfase;
- nenhum dado de licenca precisa de migracao estrutural;
- o algoritmo base do fingerprint permanece o mesmo.

---

## 6. Fora de escopo nesta subfase

- mudar a formula do fingerprint;
- suportar multiplos fingerprints por licenca;
- tolerancia automatica ampla para appliance "parecido";
- redesign de expiracao, grace, revogacao ou offline;
- refactor amplo do daemon, package ou frontend;
- abrir F4, F5, F6 ou F7.

---

## 7. Proximos passos seguros dentro da F3

1. Validar manualmente em appliance/lab os cenarios pendentes da matriz:
   reinstall, troca de NIC, clone de VM, restore e migracao.
2. Recolher evidencia objectiva de quando `kern.hostuuid` e a primeira MAC
   elegivel mudam em pfSense CE real.
3. So depois decidir se a F3 precisa de:
   - runbook de rebind manual controlado; ou
   - tolerancia adicional muito restrita e explicitamente aprovada.

### Regra de prudencia para a fase seguinte

Sem evidencia de lab, a politica oficial continua a ser:

- **se o fingerprint mudou, o appliance mudou para efeitos de licenciamento**;
- qualquer excepcao exige decisao administrativa explicita, nao heuristica do
  software.
