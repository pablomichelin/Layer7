# Validação em lab — `pfSense-pkg-layer7`

**Objetivo:** obter **evidência objetiva** de que o port gera um `.txz` instalável no pfSense CE, que os ficheiros aparecem no disco, que o serviço pode arrancar e que a página (se acessível) responde.

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

---

## 7. Verificação — GUI / HTTP

**URL direta (substituir IP):**

`https://IP_DO_PFSENSE/packages/layer7/layer7_status.php`

- [ ] Abre sem erro PHP (sim / não): `____`
- Evidência (screenshot ou código HTTP/curl): *(opcional colar)*

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
