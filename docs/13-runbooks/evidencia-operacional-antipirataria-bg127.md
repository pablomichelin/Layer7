# Runbook — Evidência operacional anti-pirataria (BG-127)

**Estado:** activo com GO humano `2026-08-14` (só evidência; **não** reabre `30.19`)

**Backlog:** BG-127

**Gates:** GA2.6 · GA2.7 · GA3.7 · GA4.8 · GA5.9

**Fora:** GA6.7 (parecer jurídico **externo**)

**Arranque:** [`../00-overview/START-HERE-antipirataria.md`](../00-overview/START-HERE-antipirataria.md)

**Gates SSOT:** [`../09-blocking/plano-gates-antipirataria.md`](../09-blocking/plano-gates-antipirataria.md)

**Soak vivo `.254`:** `1.9.63` `mode=monitor` MITM **OFF** —
[`../tests/evidence/20260814T034904Z-20.36-soak-align-163-254/`](../tests/evidence/20260814T034904Z-20.36-soak-align-163-254/)

> **BLOCO 2 (`2026-08-14`) — só formalização.** Este runbook **não** foi
> executado. **Nenhum** gate de campo (GA2.6, GA2.7, GA3.7, GA4.8, GA5.9)
> está **PASS**. Não houve mudança de versão, código, package, release,
> serviço, licenças ou EULA. A campanha começa só quando um operador
> humano correr os passos P0–P6 fora do horário comercial.

Este ficheiro **não** substitui os runbooks de mecanismo
([`check-in-migration-30.14.md`](check-in-migration-30.14.md),
[`content-subscription-update.md`](content-subscription-update.md),
[`anti-rollback-relogio.md`](anti-rollback-relogio.md),
[`rollback.md`](rollback.md)). Orquestra a **prova em campo**.

---

## 0. Objectivo / impacto / risco / teste / rollback

| Campo | Valor |
|-------|--------|
| **Objectivo** | Recolher evidência viva dos residuais de campo sem reabrir engenharia AP0–AP4. |
| **Impacto** | Janela fora de horário em `.54` depois `.254`; possível mutação **temporária** de licença de **teste** no DUT. |
| **Risco** | Médio no soak `.254` (host de produção funcional). Mitigado: ordem `.54`→`.254`, read-only primeiro, licença de teste, ABORT imediato, rollback escrito. |
| **Teste** | Passos atómicos abaixo; cada um com evidência em `docs/tests/evidence/<run_id>/`. |
| **Rollback** | Secção 9 — executável sem suporte e **sem** segredo no git. |

---

## 1. Escopo e não-escopo

**No escopo:** prova operacional de GA2.6, GA2.7, GA3.7, GA4.8, GA5.9.

**Fora (ABORT se tentado):**

- reabrir AP0–AP4 / código / `PORTVERSION`
- alterar MITM (ligar intercept, rdr, tlsproxy)
- enfraquecer segurança (bypass, fail-open, desligar check-in de produção sem ticket)
- falsear resultados ou apagar dados/evidências
- `git reset` / `rebase` / `stash`
- publicar release **salvo** necessidade técnica **verificada** e GO próprio
- parecer EULA (GA6.7)
- IPv6 / promoção enforce para além de `1.9.8`

---

## 2. Pré-condições (todas obrigatórias)

Marcar cada linha antes do passo 1. Falha = **ABORT** da campanha.

- [ ] GO `2026-08-14` + item **BG-127** lidos; engenharia `30.19` permanece fechada
- [ ] Janela **fora do horário comercial** (ambos os hosts)
- [ ] Operador humano presente; este runbook **não** corre sozinho
- [ ] Inventário de acesso **fora do git** (`docs/08-lab/lab-inventory.md` gitignored, ou equivalente local)
- [ ] Snapshot/backup do `.lic` **de produção** do `.254` guardado **fora do git** (operador)
- [ ] Licença de **teste** disponível no license server para GA2.7 / GA5.9 — **não** revogar a licença de produção
- [ ] Soak `.254` confirmado `1.9.63` `mode=monitor` MITM OFF (evidência 20.36 ou re-baseline P0)
- [ ] `.234` / `.235` **intocados** salvo passo que os cite explicitamente (este runbook **não** os cita)
- [ ] Pasta de evidência criada (secção 5); `run_id` UTC
- [ ] Rollback (secção 9) ensaiado em papel: comandos e artefactos localizados

---

## 3. Segredos — nunca no git

| Material | Onde vive | Na evidência |
|----------|-----------|--------------|
| Password SSH / GUI | inventário local gitignored | **não** copiar |
| Token de conteúdo / Bearer | appliance `/var/db/layer7/` | só `status=ok/ausente` + comprimento, **nunca** o token |
| Chave privada Ed25519 / `.lic` | license-server / backup operador | só fingerprint / `valid=` / path |
| Cookie de sessão do portal | browser do operador | **não** anexar |

Redigir qualquer dump que contenha `Authorization`, `Bearer`, `license_key`,
PEM privado ou password. Se um ficheiro vazar segredo: **não** commitar;
apagar só a cópia local do dump (não apagar evidência já canónica).

---

## 4. Critérios globais PASS / FAIL / ABORT

| Veredicto | Quando |
|-----------|--------|
| **PASS** | Critério do gate observado **e** evidência bruta no `run_id`; MITM continua OFF; N3/N4 intactos |
| **FAIL** | Critério do gate não observado, com evidência honesta; rollback executado |
| **ABORT** | Pré-condição falhou; horário comercial; MITM mudou; host errado; sem licença de teste; perda de acesso; dúvida de segurança; pedido para falsear |

**Regra:** ABORT **não** se converte em PASS. FAIL **não** se apaga.

Um gate pode ficar **DEFERRED** (escrito) se o DUT correcto não existir — ver
§6 sobre `.54`. **Não** marcar PASS por unidade/PHP local (já existe; este
ciclo é campo).

---

## 5. Recolha de evidências

```text
docs/tests/evidence/<UTC>Z-bg127-<gate>-<host>/
  README.md          # run_id, host, gate, veredicto, operador
  00-meta.txt        # utc, pkg, mode, mitm_effective, check_in
  01-pre.txt         # estado antes do passo
  02-cmd.txt         # comandos (sem segredos)
  03-post.txt        # estado depois
  99-verdict.txt     # PASS | FAIL | ABORT | DEFERRED
```

`UTC` = `date -u +%Y%m%dT%H%M%SZ`. Um `run_id` por passo atómico (ou um
directório de campanha com subpastas por passo). **Não** reutilizar o
`20260814T034904Z-20.36-soak-align-163-254` — essa é a baseline viva, não
este ciclo.

---

## 6. Ordem obrigatória: `.54` → `.254`

| Ordem | Host | Papel neste ciclo |
|-------|------|-------------------|
| 1 | `192.168.100.54` | Ubuntu lab **descartável** ([`lab-topology.md`](../08-lab/lab-topology.md)). **Não** é DUT de `layer7d`. Pré-flight + testemunha de rede. |
| 2 | `192.168.100.254` | pfSense soak — **único DUT** dos gates GA2.6–GA5.9. |

**Proibido** inverter a ordem. **Proibido** tratar `.54` como appliance
Layer7: GA2.6/2.7/3.7/4.8/5.9 em `.54` = **N/A** (não é FAIL nem PASS).

Se o pré-flight `.54` falhar (host down, MITM/PoC a escutar `:443` sem GO,
rota inesperada para produção): **ABORT** — não abrir o `.254`.

---

## 7. Passos atómicos

Cada passo: pré-check → comando → evidência → veredicto → só então o
próximo. Parar no primeiro ABORT/FAIL bloqueante.

### P0 — Baseline read-only (ambos)

**Pré:** secção 2.

**`.54` (primeiro):**

```sh
ssh root@192.168.100.54 'hostname; date -u; ip -4 addr show; ss -lnt | grep -E ":443|:8443" || true'
```

Esperado: Ubuntu lab; **sem** listener de intercept de produção. PoC local
em `/opt/layer7-poc/` pode existir — **não** ligar nem apontar ao `.254`.

**`.254` (depois, só se `.54` OK):**

```sh
ssh root@192.168.100.254 'hostname; date -u; pkg query %n-%v pfSense-pkg-layer7; layer7d -V; service layer7d status; service layer7-tlsproxy status || true'
ssh root@192.168.100.254 'layer7d --license-status'
```

Campos JSON **específicos** + tokens de MITM OFF (iguais à evidência 20.36).
Não usar `grep` genérico de `"enabled"` / `mitm` no JSON (casa nós aninhados).

```sh
ssh root@192.168.100.254 <<'EOF'
php -r '
$j = json_decode(file_get_contents("/usr/local/etc/layer7.json"), true);
$l7 = (isset($j["layer7"]) && is_array($j["layer7"])) ? $j["layer7"] : array();
$mitm = (isset($l7["mitm"]) && is_array($l7["mitm"])) ? $l7["mitm"] : array();
echo "mode=" . (isset($l7["mode"]) ? $l7["mode"] : "") . "\n";
echo "layer7_enabled=" . (!empty($l7["enabled"]) ? "true" : "false") . "\n";
echo "mitm.enabled=" . (!empty($mitm["enabled"]) ? "true" : "false") . "\n";
echo "check_in_enabled=" . (array_key_exists("check_in_enabled", $l7) ? (!empty($l7["check_in_enabled"]) ? "true" : "false") : "absent") . "\n";
require_once("config.inc");
require_once("/usr/local/pkg/layer7.inc");
$m = layer7_mitm_from_config(layer7_load_or_default());
echo "mitm_effective=" . (layer7_mitm_effective($m) ? "true" : "false") . "\n";
'
sockstat -l | grep -E "[:.]8443([[:space:]]|$)" && echo STILL_8443 || echo NO_8443
(pfctl -sn; pfctl -sr) 2>/dev/null | grep -i mitm | grep -E "rdr|layer7_mitm" && echo HAS_MITM_RULES || echo NO_MITM_RDR
EOF
```

Esperado alinhado à 20.36: pacote `1.9.63`; `layer7d` a correr;
`mode=monitor`; `layer7_enabled=true`; `mitm.enabled=false`;
`mitm_effective=false`; tlsproxy parado; `NO_8443`; `NO_MITM_RDR`.
Os tokens `mitm_effective=false`, `NO_8443` e `NO_MITM_RDR` têm de
aparecer no stdout do bloco acima — não inferir de `service` / `pkg`.

| Resultado | Veredicto |
|-----------|-----------|
| Baseline `.54` OK e `.254` = `1.9.63` monitor / MITM OFF | **PASS** P0 → P1 |
| Drift de pacote/modo/MITM no `.254` | **ABORT** (não «corrigir» MITM neste ciclo) |
| `.54` inalcançável | **ABORT** |

---

### P1 — Pré-flight `.54` (obrigatório; DUT N/A)

**Pré:** P0 PASS.

1. Confirmar que `.54` **não** encaminha teste de licença para o soak.
2. Confirmar que este ciclo **não** instala `pfSense-pkg-layer7` no `.54`.
3. Guardar `01-pre.txt` / `03-post.txt` (idênticos se read-only).

| Resultado | Veredicto |
|-----------|-----------|
| Host lab estável; sem mutação de produção | **PASS** P1 → P2 |
| Tem tentado usar `.54` como DUT Layer7 | **ABORT** |

Gates GA2.6+ neste host: **N/A** (declarar no `99-verdict.txt`).

---

### P2 — GA2.6 (N1) no `.254`

**Critério:** licença **válida** ⇒ comportamento do modo **actual** inalterado
(políticas, daemon vivo, sem crash). Referência histórica de engenharia:
idêntico ao contrato N1 vs `1.9.48`.

**Pré:** P1 PASS; `.lic` de produção válido; **não** mudar `mode`.

```sh
ssh root@192.168.100.254 'layer7d --license-status'
ssh root@192.168.100.254 'service layer7d status'
ssh root@192.168.100.254 'pfctl -sr | grep -i layer7 || true'
```

| Observado | Veredicto |
|-----------|-----------|
| `valid=1`; daemon vivo; `mode` igual ao P0; MITM OFF | **PASS** GA2.6 (modo actual) |
| Licença válida mas daemon cai / modo muda / MITM liga | **FAIL** |
| Pedido para passar a `enforce` sem GO próprio de janela | **ABORT** / **DEFERRED** — este GO **não** autoriza flip de enforce |

Smoke de **enforce** activo (texto original GA2.6 «enforce idêntico») exige
**GO de janela** separado. Sem esse GO: não falhar o ciclo inteiro — marcar
GA2.6 **PASS parcial** (modo monitor) + **DEFERRED** enforce.

---

### P3 — GA2.7 (N2) no `.254`

**Critério:** licença ausente/inválida ⇒ **monitor**, daemon vivo, **zero**
regras PF Layer7 de **block**.

**Pré:** P2 concluído; **licença de teste** (não a de produção); backup do
`.lic` de produção **fora do git**.

1. Instalar / activar **só** a licença de teste (procedimento do operador no
   portal — credenciais **não** entram no git).
2. Invalidar **essa** licença de teste (revogar no painel **ou** remover o
   `.lic` de teste). **Não** revogar a licença de produção.
3. Observar:

```sh
ssh root@192.168.100.254 'layer7d --license-status; service layer7d status'
ssh root@192.168.100.254 'pfctl -sr | grep -E "layer7.*block|layer7_pblock" || true'
```

| Observado | Veredicto |
|-----------|-----------|
| `valid=0`; daemon vivo; zero block Layer7; MITM OFF | **PASS** GA2.7 |
| Daemon morre; enforce/block permanece; crash | **FAIL** |
| Sem licença de teste / risco de tocar na de produção | **ABORT** |

4. **Restaurar imediatamente** o `.lic` de produção (secção 9.2) e repetir
   `--license-status` até `valid=1` e modo = P0.

---

### P4 — GA3.7 no `.254`

**Critério:** licença dentro da validade **e** relógio correcto ⇒ estado
inalterado (N1 temporal). **Não** reabre `30.6`. **Não** forçar `date -s`
neste ciclo (isso é o runbook
[`anti-rollback-relogio.md`](anti-rollback-relogio.md), e no soak é
perigoso).

**Pré:** P3 restaurado; NTP/hora correcta.

```sh
ssh root@192.168.100.254 'date -u; layer7d --license-status'
```

| Observado | Veredicto |
|-----------|-----------|
| Hora correcta; `valid=1`; `clock_suspect=0`; modo = P0 | **PASS** GA3.7 |
| Relógio correcto mas estado muda | **FAIL** |
| Relógio do appliance já suspeito | **ABORT** — corrigir pela N6 do runbook anti-rollback **depois** de ABORT, não no mesmo passo |

---

### P5 — GA4.8 no `.254`

**Critério:** offline **prolongado dentro da janela** do token real ⇒ PASS
**sem intervenção**. Prova local PHP já existe; este passo é **campo**.

**Pré:** token de conteúdo presente e `ok` (não colar o token).

```sh
ssh root@192.168.100.254 '/usr/local/etc/layer7/update-blacklists.sh --check-subscription'
```

1. Registar `content_subscription=ok` e a janela (`iat`/`exp` **sem** serializar o Bearer).
2. Cortar **só** o caminho de update (não derrubar o firewall): por exemplo
   bloquear temporariamente o host de conteúdo **no DUT de teste**, ou
   esperar dentro da janela sem correr update — **não** expirar o token de
   produção à mão.
3. Confirmar: enforce/modo inalterados (N4); snapshot/LKG intactos; daemon vivo.
4. Restaurar conectividade de conteúdo.

| Observado | Veredicto |
|-----------|-----------|
| Dentro da janela: sem intervenção; modo/enforce intactos; token continua válido | **PASS** GA4.8 campo |
| Offline dentro da janela exige intervenção ou apaga conteúdo / corta enforce | **FAIL** |
| Sem token real no appliance | **ABORT** / **DEFERRED** — não inventar token |

Duração: usar a janela **já configurada** (contrato 30.8: validade nominal
30d ±1d). **Não** alongar para «vários dias» nesta campanha se a janela
comercial não o permitir — declarar duração real no `00-meta.txt`.

---

### P6 — GA5.9 no `.254`

**Critério:** revogação no painel corta o efeito em **≤ intervalo
configurado** (`check_in_interval_hours` / política do servidor; defaults
históricos 168h — **ler o valor real** no appliance/servidor, não assumir).

**Pré:** `check_in_enabled=true` **na licença de teste** (não forçar ON na
instalação de produção se estiver OFF — upgrade preserva; ver
[`check-in-migration-30.14.md`](check-in-migration-30.14.md)).

1. Ligar o DUT à **licença de teste** com check-in activo.
2. Forçar um check-in (sem flags secretas):

```sh
ssh root@192.168.100.254 '/usr/local/sbin/layer7d --check-in'
```

3. No portal, **revogar a licença de teste** (não a de produção).
4. Esperar ≤ intervalo **ou** forçar `--check-in` de novo.
5. Observar: licença de teste deixa de armar enforce; daemon vivo; N3 se a
   rede falhar a meio = **não** derrubar firewall.

| Observado | Veredicto |
|-----------|-----------|
| Corte dentro do intervalo; daemon vivo; MITM OFF | **PASS** GA5.9 |
| Revogação ignorada com check-in ON e resposta assinada | **FAIL** |
| Sem licença de teste / check-in OFF na base de produção e sem opt-in | **ABORT** / **DEFERRED** |

6. Restaurar `.lic` de produção (secção 9.2).

---

## 8. Limites do teste (honestos)

| Limite | Consequência |
|--------|----------------|
| `.54` não corre `layer7d` | Gates de campo **só** no `.254` |
| `.254` está em **monitor** (20.36) | GA2.6 enforce activo = DEFERRED sem GO de janela |
| Root no appliance (R-A / RR-5) | Contorno local **possível**; proibido overclaim |
| Relógio congelado / apagar `clock-mark.json` (RR-4) | Fora deste ciclo |
| Redistribuição de conteúdo (RR-2) | Fora deste ciclo |
| pfSense **CE** físico (R-L / ADR-0022) | Lab Plus **não** prova CE — declarar no veredicto |
| Publicar `.pkg` | **Não** faz parte deste runbook |

---

## 9. Rollback executável

Executar no **primeiro** ABORT/FAIL, e **sempre** no fim da campanha.

### 9.1 MITM / modo (não negociável)

```sh
ssh root@192.168.100.254 'service layer7-tlsproxy status || true'
# esperado: not running. Se estiver a correr sem GO: parar e ABORT a campanha.
# Não ligar MITM para «voltar ao normal».
```

Confirmar `mode` = valor do P0 (`monitor` na baseline 20.36).

### 9.2 Licença de produção

Restaurar o `.lic` a partir do **backup do operador** (path local, fora do
git). Não documentar o path secreto aqui.

```sh
ssh root@192.168.100.254 'layer7d --license-status; service layer7d restart; layer7d --license-status'
```

Esperado: `valid=1`; modo = P0.

### 9.3 Pacote (só se alguém o tiver mudado — este runbook **não** manda)

Soak: reinstalar `1.9.63` (SHA publicado no CORTEX) **ou**, se o soak tiver
sido partido, rollback soak histórico `1.9.59` — ver CORTEX. Comandos em
[`../10-license-server/MANUAL-INSTALL.md`](../10-license-server/MANUAL-INSTALL.md).
`IGNORE_OSVERSION=yes pkg add -f` no Plus/16 (BG-106).

### 9.4 `.54`

Nada a reverter se P1 foi read-only. Se alguém tiver ligado listener/PoC:
derrubar **só** no `.54` (lab descartável). **Não** tocar em `.234`/`.235`.

### 9.5 Git

Não fazer reset/rebase/stash. Evidência nova = ficheiros em
`docs/tests/evidence/`. Segredos = **não** adicionar.

---

## 10. Veredicto da campanha

| Condição | Campanha |
|----------|----------|
| P0+P1 PASS e todos os gates executados PASS (GA2.6 enforce pode ficar DEFERRED) | **PASS** BG-127 (declarar DEFERRED se houver) |
| Qualquer FAIL de gate com rollback limpo | **FAIL** — actualizar gates; **não** fechar BG-127 |
| Qualquer ABORT | **ABORT** — estado = P0 ou rollback 9 |

Actualizar no **mesmo** bloco documental (depois da campanha, não antes):
`plano-gates-antipirataria.md`, CORTEX (secção anti-pirataria), BG-127,
checklist-mestre. **Não** antecipar PASS neste runbook.
