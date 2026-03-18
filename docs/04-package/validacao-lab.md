# Validação em lab — `pfSense-pkg-layer7`

**Objetivo:** obter **evidência objetiva** de que o port gera um `.txz` instalável no pfSense CE, que os ficheiros aparecem no disco, que o serviço pode arrancar e que a página (se acessível) responde.

**Política do projeto:** o pacote **só será instalado no pfSense quando estiver totalmente completo** (ver `00-LEIA-ME-PRIMEIRO.md` regra 8, `CORTEX.md`). Este documento descreve o procedimento de validação para quando esse momento chegar.

**Regra:** colar saídas reais nas caixas abaixo. Sem outputs, o gate **não** está fechado.

---

## 1. Pré-requisitos — host builder (FreeBSD)

- [ ] FreeBSD ou ambiente com **BSD `make`** + `cc` (toolchain base).
- [ ] Clone completo do repositório Layer7, com:
  - `package/pfSense-pkg-layer7/Makefile`
  - `src/layer7d/main.c` acessível como `package/pfSense-pkg-layer7/../../src/layer7d/main.c`
- [ ] Utilizador com permissão para compilar e gerar pacote.

**Nota:** Windows sem `make`/ports **não** serve como builder para este port.

---

## 2. Pré-requisitos — pfSense lab

- [ ] VM/appliance pfSense CE (versão anotada: `____________`).
- [ ] Snapshot antes da instalação (recomendado).
- [ ] Acesso SSH ou consola como admin.
- [ ] Caminho para copiar o `.txz` gerado no builder (SCP, datastore, etc.).

---

## 3. Passo a passo — build

**Antes do `make package`:** alinhar plist a `files/` (em Windows: `.\scripts\package\check-port-files.ps1`):

```sh
sh scripts/package/check-port-files.sh
```

**Opcional (antes do port):** no clone, com `cc` + `make`:

```sh
sh scripts/package/smoke-layer7d.sh
```

Execute no **builder** (ajuste o caminho ao teu clone):

```sh
cd /caminho/para/Layer7/package/pfSense-pkg-layer7
make clean 2>/dev/null || true
make package
```

**Output completo do build (colar):**

```
(colar aqui)
```

**Caminho absoluto do `.txz` gerado:**

```
(colar aqui)
```

**Onde procurar o `.txz`:** depende do `make` do host; a partir do diretório do port:

```sh
ls -la *.txz 2>/dev/null || true
find . -maxdepth 5 -name 'pfSense-pkg-layer7*.txz' 2>/dev/null
```

Anote o caminho completo para `pkg add` no pfSense.

---

### Troubleshooting (build)

| Sintoma | Ação |
|--------|------|
| `layer7d: fontes em falta` | Garantir clone completo; `src/layer7d/main.c` e `config_parse.c` devem existir relativamente ao port. |
| `LICENSE` em falta | Deve existir `package/pfSense-pkg-layer7/LICENSE`. |
| `cc: not found` | Instalar toolchain no builder ou usar VM FreeBSD documentada em `docs/08-lab/`. |
| `check-port-files: FALHOU` | Alinhar `pkg-plist` com ficheiros em `files/` (ver `scripts/package/check-port-files.sh`). |

---

## 4. Passo a passo — instalação no pfSense

Transfira o `.txz` para o pfSense. Depois:

```sh
cd /root
# ou o diretório onde está o .txz
ls -la pfSense-pkg-layer7*.txz
pkg add ./pfSense-pkg-layer7-VERSÃO.txz
```

**Output de `pkg add` (colar):**

```
(colar aqui)
```

---

## 5. Verificação — metadados do pacote

```sh
pkg info pfSense-pkg-layer7
pkg info -l pfSense-pkg-layer7
pkg info | grep -i layer7
```

**Outputs (colar):**

```
(colar aqui)
```

---

## 6. Passo a passo — serviço

```sh
cp /usr/local/etc/layer7.json.sample /usr/local/etc/layer7.json
service layer7d onestart
service layer7d status
ps auxww | grep layer7d | grep -v grep
sockstat -4 -6 2>/dev/null | grep layer7d || true
```

**Outputs (colar):**

```
(colar aqui)
```

**Logs (pfSense — exemplo com `clog`; ajustar se a tua versão usar outro caminho):**

```sh
clog /var/log/system.log 2>/dev/null | tail -n 80
```

```
(colar aqui)
```

**Paragem de teste:**

```sh
service layer7d onestop
```

**Notas:**

- `daemon_start` é registado no arranque **mesmo sem** `/usr/local/etc/layer7.json`; o sample só é necessário para testar parse completo via SIGHUP/reload.
- Arranque no boot: `sysrc layer7d_enable=YES` (após validar manualmente com `onestart`).

### 6b. PF — `pfctl` (opcional, código ≥ 0.0.12)

O `layer7d` compila com **`layer7_pf_exec_table_add`/`delete`** (`/sbin/pfctl`); o **loop ainda não chama** estas funções (falta nDPI). Para ganhar confiança no appliance:

1. Criar tabela **`layer7_block`** no ruleset PF (vazia + regra que a use).
2. Como root: `pfctl -t layer7_block -T add 10.0.0.99` → `pfctl -t layer7_block -T show` → `… -T delete 10.0.0.99`.
3. Registar OK/NOK abaixo.

```
(pfctl show / notas)
```

**Versão do binário:** `layer7d -V` (deve alinhar com o pacote / **Diagnostics**).

### 6c. CLI **`layer7d -e`** (decisão + PF, sem nDPI)

Confirma o caminho **política → `pfctl`** no appliance (útil antes do loop nDPI).

1. Copiar temporariamente o sample de enforce (ou ajustar `layer7.json`):

   ```sh
   cp /usr/local/etc/layer7.json /usr/local/etc/layer7.json.bak
   cp /caminho/no/builder/layer7-enforce-smoke.json /usr/local/etc/layer7.json
   # ou: usar apenas o bloco policies/mode enforce do sample
   ```

2. **Dry-run** (não altera tabelas PF):

   ```sh
   /usr/local/sbin/layer7d -n -c /usr/local/etc/layer7.json -e 10.0.0.100 BitTorrent
   ```

   Esperado: linha com `dry-run: pfctl -t layer7_block -T add 10.0.0.100` (ou tabela configurada).

3. Opcional — **executar** `pfctl` (só se a tabela existir no ruleset, ver §6b):

   ```sh
   /usr/local/sbin/layer7d -c /usr/local/etc/layer7.json -e 10.0.0.100 BitTorrent
   ```

4. Restaurar config: `mv /usr/local/etc/layer7.json.bak /usr/local/etc/layer7.json` e `service layer7d onerestart` se necessário.

**Nota:** o sample `samples/config/layer7-enforce-smoke.json` está no repositório; no pfSense pode colar o conteúdo ou copiar via SCP.

---

## 7. Verificação — GUI / HTTP

**URL direta (substituir IP):**

`https://IP_DO_PFSENSE/packages/layer7/layer7_status.php`

- [ ] Abre sem erro PHP (sim / não): `____`
- Evidência (screenshot ou código HTTP/curl): *(opcional colar)*

**URL exceções (opcional):** `https://IP/packages/layer7/layer7_exceptions.php`  
**Políticas — adicionar (≥0.0.14):** em **Policies**, formulário no fim; validar com **Estado**. **Remover (≥0.0.23):** dropdown + botão Remover + confirmar. **Editar (≥0.0.25):** botão Editar na linha → gravar → lista.  
**Exceções — adicionar (≥0.0.16):** em **Exceptions**, formulário host (IPv4) ou CIDR; validar com `layer7d -t`. **Remover (≥0.0.24):** dropdown + Remover + confirmar. **Editar (≥0.0.26):** Editar na linha → gravar.  
**Diagnostics (≥0.0.18):** tab **Diagnostics**. **Events (≥0.0.22):** tab **Events** (syslog / futuro event-model). **Syslog remoto (≥0.0.19):** Settings → host + porta; no coletor confirmar receção UDP 514 (ou porta definida).

**Menu Services:**

- [ ] Entrada “Layer7” (ou similar) visível: **OK** / **NOK** / **N/A (não verificado)**

**Nota:** em algumas versões o menu só aparece após registo correto do pacote; **NOK** não invalida sozinho o pacote se a URL direta funcionar — registar na conclusão.

---

## 8. Remove (opcional mas recomendado)

```sh
pkg delete -y pfSense-pkg-layer7
pkg info pfSense-pkg-layer7 2>&1
```

**Output (colar):**

```
(colar aqui)
```

---

## 9. Critérios objetivos de aprovação / reprovação

### Aprovação mínima (gate “pacote + daemon de smoke”)

| Critério | Obrigatório |
|----------|-------------|
| `make package` termina sem erro | Sim |
| `.txz` existe e é instalável | Sim |
| `pkg add` sem erro fatal | Sim |
| `/usr/local/sbin/layer7d` existe e é executável | Sim |
| `service layer7d onestart` → processo em `ps` | Sim |
| Logs mostram `daemon_start` | Sim |
| `service layer7d onestop` → processo termina | Sim |

### Reprovação (exemplos)

- Build falha (fonte em falta, `cc` erro, etc.).
- `pkg add` falha ou não instala `sbin/layer7d`.
- Serviço não arranca ou morre de imediato sem log útil.
- Erro PHP fatal na URL da página *(registar; pode ser bug de integração)*.

---

## 10. Rollback

1. `pkg delete pfSense-pkg-layer7` (se instalado).
2. Restaurar **snapshot** da VM antes do teste, **ou**
3. Remover manualmente ficheiros órfãos se o delete falhar (listar o que ficou e abrir issue).

---

## 11. Conclusão do operador

- Data: `____________`
- Versão pfSense CE: `____________`
- **Resultado:** APROVADO / REPROVADO
- Notas:

```
(colar aqui)
```

---

## Checklist executável (cópia rápida)

Usar também [`checklist-validacao-lab.md`](checklist-validacao-lab.md).

| # | Item | OK |
|---|------|-----|
| 1 | Build `make package` sem erro | [ ] |
| 2 | Ficheiro `.txz` gerado | [ ] |
| 3 | `pkg add` OK | [ ] |
| 4 | `pkg info pfSense-pkg-layer7` OK | [ ] |
| 5 | Ficheiros instalados coerentes com `pkg info -l` | [ ] |
| 6 | `service layer7d onestart` OK | [ ] |
| 7 | `service layer7d status` OK | [ ] |
| 8 | `ps` mostra `layer7d` | [ ] |
| 9 | Logs com `daemon_start` | [ ] |
| 10 | URL `/packages/layer7/layer7_status.php` OK | [ ] |
| 11 | Menu GUI (se aplicável) OK/NOK anotado | [ ] |
| 12 | `pkg delete` OK (teste de remove) | [ ] |
