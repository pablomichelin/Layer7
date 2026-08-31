# Manual do Produto Layer7 (pfSense CE)

> **Hub público + guia do operador** — SSOT de navegação do produto para
> clientes e operadores (árvore interna `docs/`).  
> **Espelho no canal público** `pablomichelin/Layer7`:
> [`docs/commercial/LAYER7-MANUAL-PRODUTO-PT.md`](https://github.com/pablomichelin/Layer7/blob/main/docs/commercial/LAYER7-MANUAL-PRODUTO-PT.md)
> (versão sanitizada: sem SSOTs internos / license server).  
> **Pacote de referência (canal `latest`):** **`1.9.80`**
> **SHA256:** `f7186ee3c58d6ad948b322e45098adaf06f03b0400eab29ed1dd112c2c908782`
> **Release:** <https://github.com/pablomichelin/Layer7/releases/latest>
> **Data de alinhamento:** `2026-08-31`
---

## 0. Como usar este manual

Este ficheiro é o **ponto de entrada público** do produto. Não substitui os
manuais canónicos por área: **liga** a eles e só copia blocos de comando
quando a fonte operacional actual os publica.

| Papel | Documento |
|-------|-----------|
| **Este ficheiro** | Hub + guia operador (instalação, modos, GUI, limites, suporte) |
| Instalação / upgrade / rollback / desinstalação | [`10-license-server/MANUAL-INSTALL.md`](10-license-server/MANUAL-INSTALL.md) |
| Licenças (painel, activação, SKU) | [`10-license-server/MANUAL-USO-LICENCAS.md`](10-license-server/MANUAL-USO-LICENCAS.md) |
| Relatórios executivos | [`12-reports/MANUAL-RELATORIOS-EXECUTIVOS.md`](12-reports/MANUAL-RELATORIOS-EXECUTIVOS.md) |
| Logging / observabilidade | [`14-logging/README.md`](14-logging/README.md) |
| Runbooks operacionais | [`13-runbooks/README.md`](13-runbooks/README.md) |
| Identity + MITM (trilha activa) | [`00-overview/START-HERE-identity-mitm.md`](00-overview/START-HERE-identity-mitm.md) |
| Estado vivo do projecto | [`../CORTEX.md`](../CORTEX.md) |
| Tutorial longo (legado) | [`tutorial/guia-completo-layer7.md`](tutorial/guia-completo-layer7.md) — **preservado; não é SSOT** |

**Regra:** se um comando ou versão deste hub divergir de
[`MANUAL-INSTALL.md`](10-license-server/MANUAL-INSTALL.md), **vence o
MANUAL-INSTALL**. Actualizar este hub no mesmo bloco da release.

---

## 1. Estado do produto (honesto)

| Canal | Versão | Papel |
|-------|--------|--------|
| **`latest` / lab / updater GUI** | **`1.9.80`** | Único pacote público para download (BG-164) |
| **Produção enforce (pin de política)** | **`1.9.8`** | Referência estável até GO — **sem** download público |
| Rollback público | **`1.9.80`** | Canal latest-only; `1.9.79` só na tag git |

**MITM (TLS inspection):**

- Default **OFF**; `intercept_ready` pode existir no pacote sem activar intercept.
- Rdr exige `source_cidr` **e** `dest_cidr` — **proibido** `from any`.
- Janela com failsafe (`max_window` / `deadline_unix`) a partir de `1.9.73` (P3).
- **P4 soak:** veredicto **ABORT** (não conta como soak/piloto).
- **P5:** aguarda **ficha de site de cliente** — sem ficha nomeada, activação
  externa é **NO-GO**.
- Intercept **permanente** em produção: **NO-GO** (decisão humana mantida).
- Squid como motor MITM: **rejeitado**.

Fontes: [`mapa-prontidao-mitm-piloto-2026-08-09.md`](09-blocking/mapa-prontidao-mitm-piloto-2026-08-09.md),
[`START-HERE-identity-mitm.md`](00-overview/START-HERE-identity-mitm.md),
[`CORTEX.md`](../CORTEX.md).

**Não confundir:**

- Canal `latest` ≠ pin de produção enforce.
- `features=full` no `.lic` legado **não** implica entitlement MITM/Identity
  (ADR-0025 — tokens `base` / `identity` / `mitm`).

---

## 2. O que é o Layer7

Produto de classificação e política Layer 7 para **pfSense CE**
(Systemup Solução em Tecnologia):

- Classificação por aplicação / protocolo / categoria (nDPI);
- Políticas `monitor`, `allow`, `block`, `tag` (e enforcement PF quando em
  modo enforce);
- GUI nativa no padrão pfSense (**Services > Layer 7**);
- Blacklists UT1 com pipeline assinado (HTTPS → snapshot → cache → LKG);
- Licenciamento online/offline via license server;
- Relatórios e logs locais;
- Add-on Identity (User-ID de rede) — trilha **fechada** no âmbito documentado;
- Add-on MITM (TLS proxy) — **opt-in**, com gates e **não** pronto para piloto
  externo (ver secção 10).

Charter resumido: [`00-overview/product-charter.md`](00-overview/product-charter.md).

---

## 3. Versão, download e integridade

Fonte canónica: secção **Links da versao actual** em
[`MANUAL-INSTALL.md`](10-license-server/MANUAL-INSTALL.md).

**Canal público `1.9.80` (único pacote para download):**

- Release: <https://github.com/pablomichelin/Layer7/releases/tag/v1.9.80>
- Pacote: <https://github.com/pablomichelin/Layer7/releases/download/v1.9.80/pfSense-pkg-layer7-1.9.80.pkg>
- SHA256 ficheiro: <https://github.com/pablomichelin/Layer7/releases/download/v1.9.80/pfSense-pkg-layer7-1.9.80.pkg.sha256>
- **SHA256 esperado:** `f7186ee3c58d6ad948b322e45098adaf06f03b0400eab29ed1dd112c2c908782`
- Latest: <https://github.com/pablomichelin/Layer7/releases/latest>

> Caminho oficial: **`install.sh`** assinado (F1.2). Pacotes anteriores
> **não** estão no canal público (BG-164). Pin enforce `1.9.8` é política
> interna, sem URL.

**Comandos rápidos (integridade)** — copiados de MANUAL-INSTALL:

```sh
fetch -o /tmp/pfSense-pkg-layer7-1.9.80.pkg https://github.com/pablomichelin/Layer7/releases/download/v1.9.80/pfSense-pkg-layer7-1.9.80.pkg
```

```sh
fetch -o /tmp/pfSense-pkg-layer7-1.9.80.pkg.sha256 https://github.com/pablomichelin/Layer7/releases/download/v1.9.80/pfSense-pkg-layer7-1.9.80.pkg.sha256 && sha256 -q /tmp/pfSense-pkg-layer7-1.9.80.pkg | tee /tmp/l7-actual.sha256 && cat /tmp/pfSense-pkg-layer7-1.9.80.pkg.sha256
```

Os dois hashes devem coincidir com
`f7186ee3c58d6ad948b322e45098adaf06f03b0400eab29ed1dd112c2c908782`.

**Nota ABI (pfSense Plus / FreeBSD 16 vs builder 15):** os comandos usam
`IGNORE_OSVERSION=yes` e `pkg add -f` (BG-106). Isto é aceite operacional;
não prova sozinho compatibilidade CE física (ADR-0022).

---

## 4. Instalação / upgrade / rollback / desinstalação

Fonte completa: [`MANUAL-INSTALL.md`](10-license-server/MANUAL-INSTALL.md)
(secções 1–6). Executar como **root**. Em **Diagnostics > Command Prompt**,
usar o **comando único** (uma linha).

### 4.1 Instalar (primeira vez) — `1.9.80`

**Comando único oficial:**

```sh
fetch -o /tmp/install.sh https://github.com/pablomichelin/Layer7/releases/download/v1.9.80/install.sh && sh /tmp/install.sh
```

**Comando único manual (alternativa):**

```sh
fetch -o /tmp/pfSense-pkg-layer7-1.9.80.pkg https://github.com/pablomichelin/Layer7/releases/download/v1.9.80/pfSense-pkg-layer7-1.9.80.pkg && IGNORE_OSVERSION=yes pkg add -f /tmp/pfSense-pkg-layer7-1.9.80.pkg && sysrc layer7d_enable=YES && service layer7d onestart && layer7d -V
```

**Passo a passo (SSH/Console):**

```sh
fetch -o /tmp/pfSense-pkg-layer7-1.9.80.pkg https://github.com/pablomichelin/Layer7/releases/download/v1.9.80/pfSense-pkg-layer7-1.9.80.pkg
```

```sh
IGNORE_OSVERSION=yes pkg add -f /tmp/pfSense-pkg-layer7-1.9.80.pkg
```

```sh
sysrc layer7d_enable=YES
```

```sh
service layer7d onestart
```

```sh
layer7d -V
```

```sh
service layer7d onestatus
```

### 4.2 Actualizar (upgrade) — para `1.9.80`

**Comando único:**

```sh
service layer7d onestop && fetch -o /tmp/pfSense-pkg-layer7-1.9.80.pkg https://github.com/pablomichelin/Layer7/releases/download/v1.9.80/pfSense-pkg-layer7-1.9.80.pkg && IGNORE_OSVERSION=yes pkg add -f /tmp/pfSense-pkg-layer7-1.9.80.pkg && service layer7d onestart && layer7d -V
```

Após upgrade, recompilar o ruleset PF uma vez:

```sh
/etc/rc.filter_configure_sync
```

```sh
pfctl -sr | grep -i layer7
```

Políticas, excepções, grupos, blacklists e licença são preservados no upgrade.
Antes de upgrades de risco: **Export** da configuração Layer7 na GUI
(Definicoes) — o backup XML do pfSense **não** inclui
`/usr/local/etc/layer7/` (nota em MANUAL-INSTALL).

### 4.3 Reinstalar (mesma versão)

```sh
service layer7d onestop && pkg delete -y pfSense-pkg-layer7 && fetch -o /tmp/pfSense-pkg-layer7-1.9.80.pkg https://github.com/pablomichelin/Layer7/releases/download/v1.9.80/pfSense-pkg-layer7-1.9.80.pkg && IGNORE_OSVERSION=yes pkg add -f /tmp/pfSense-pkg-layer7-1.9.80.pkg && sysrc layer7d_enable=YES && service layer7d onestart
```

### 4.4 Desinstalar

- GUI: **Services > Layer 7 > Remoção** (escrever **REMOVER**).
- Manual: ver MANUAL-INSTALL secção **6** (`service layer7d onestop`,
  `pkg delete -y pfSense-pkg-layer7`, limpeza de residuais e tabelas PF).
- Runbook resumido: [`13-runbooks/rollback.md`](13-runbooks/rollback.md).

**Reinstalar após desinstalação (comando único actual):**

```sh
fetch -o /tmp/pfSense-pkg-layer7-1.9.80.pkg https://github.com/pablomichelin/Layer7/releases/download/v1.9.80/pfSense-pkg-layer7-1.9.80.pkg && IGNORE_OSVERSION=yes pkg add -f /tmp/pfSense-pkg-layer7-1.9.80.pkg && sysrc layer7d_enable=YES && service layer7d onestart && layer7d -V
```

### 4.5 Rollback de versão

1. Parar o serviço: `service layer7d onestop`
2. Reinstalar **`1.9.80`** (único pacote no canal público).
3. Pacotes anteriores e pin enforce `1.9.8` só no builder/arquivo interno.
4. Restaurar JSON exportado se necessário; `filter_configure_sync`;
   confirmar `layer7d -V` e MITM OFF se aplicável.

---

## 5. Controlo do serviço

| Acção | Comando |
|-------|---------|
| Iniciar | `service layer7d onestart` |
| Parar | `service layer7d onestop` |
| Reiniciar | `service layer7d onerestart` |
| Status | `service layer7d onestatus` |
| Reload | `service layer7d reload` |
| Habilitar no boot | `sysrc layer7d_enable=YES` |
| Desabilitar no boot | `sysrc layer7d_enable=NO` |

---

## 6. Licenças

Manual completo: [`MANUAL-USO-LICENCAS.md`](10-license-server/MANUAL-USO-LICENCAS.md).

**Canal público do painel / activação online:**
`https://license.systemup.inf.br`

### 6.1 No appliance (comandos de MANUAL-INSTALL)

```sh
layer7d --fingerprint
```

```sh
layer7d --activate CHAVE
```

```sh
layer7d --license-status
```

GUI: **Services > Layer 7 > Definições > Licença**
(registar / revogar; código case-sensitive).

### 6.2 Offline

Copiar o `.lic` para `/usr/local/etc/layer7.lic`, depois:

```sh
service layer7d onerestart
```

```sh
layer7d --license-status
```

### 6.3 SKU / features (ADR-0025)

Tokens emitíveis: `base`, `identity`, `mitm`. Default de emissão: `base`.
Legado `full` normaliza para `base`. Identity/MITM exigem **reemissão
explícita**. Detalhe no MANUAL-USO-LICENCAS e ADRs 0025–0027.

### 6.4 Operação do license server (admin)

Índice: [`10-license-server/README.md`](10-license-server/README.md).  
Portal visual: [`10-license-server/portal/README.md`](10-license-server/portal/README.md).  
Runbooks: publicação segura, auth/sessão, segredos/bootstrap, backup/restore
em [`13-runbooks/`](13-runbooks/README.md).

---

## 7. GUI — mapa do operador

Menu base: **Services > Layer 7**.

| Área típica | Função |
|-------------|--------|
| Estado / Dashboard | Serviço, versão, resumo |
| Definições | Modo, interfaces, licença, logs, relatórios, actualização |
| Políticas | Regras L7 (monitor/allow/block/tag) |
| Excepções / Lista VIP | Isenções por host/CIDR; lista em texto simples (`IP, nome`); reservas DHCP das interfaces |
| Dispositivos / perfis | Inventário e perfis (Caminho A) |
| Blacklists | UT1 + categorias locais / regras |
| Identity | User-ID (quando entitlement) |
| MITM | Opt-in TLS inspection (gates — ver §10) |
| Relatórios | Visão executiva e eventos |
| Diagnósticos | Tabelas PF, reparação, pistas |
| Remoção | Desinstalação controlada |

Validação de campos (referência interna):
[`04-package/gui-validation.md`](04-package/gui-validation.md).

**Actualização pela GUI:** Definições > Sistema > Actualização consulta
`api.github.com/repos/pablomichelin/Layer7/releases/latest` (só releases
não-prerelease com `.pkg`).

---

## 8. Monitor vs enforce

| Modo | Comportamento esperado |
|------|------------------------|
| **monitor** (recomendado pós-install) | Classifica e observa; **não** aplica bloqueio PF Layer7 |
| **enforce** | Aplica decisões de política / blacklists conforme configuração |

**Boas práticas:**

1. Instalar com serviço a correr e modo **monitor**.
2. Validar licença, captura, políticas e blacklists.
3. Só passar a **enforce** com pin de produção adequado (`1.9.8` até GO)
   e janela de mudança controlada.
4. Não misturar promoção enforce com activação MITM.

Detalhe de enforcement PF: [`05-daemon/pf-enforcement.md`](05-daemon/pf-enforcement.md)
(suplementar). Matriz de limitações DPI:
[`09-blocking/matriz-limitacoes-dpi.md`](09-blocking/matriz-limitacoes-dpi.md).

---

## 9. Listas e políticas

### 9.1 Políticas L7

Criar/editar em **Services > Layer 7 > Políticas**: acção, apps/categorias,
prioridade, âmbito (origem/destino conforme modelo). Em enforce, o daemon
alimenta tabelas PF (`layer7_*`). Preferir políticas explícitas e excepções
VIP para isenções críticas.

### 9.2 Blacklists UT1

Plano / directrizes: [`11-blacklists/PLANO-BLACKLISTS-UT1.md`](11-blacklists/PLANO-BLACKLISTS-UT1.md),
[`11-blacklists/DIRETRIZES-IMPLEMENTACAO.md`](11-blacklists/DIRETRIZES-IMPLEMENTACAO.md).

Após instalar `1.8.11_13`+ (inclui `1.9.73`), primeira sync:

```sh
/usr/local/etc/layer7/update-blacklists.sh --download
```

Restauro last-known-good:

```sh
/usr/local/etc/layer7/update-blacklists.sh --restore-lkg
```

Verificação (MANUAL-INSTALL §11b):

```sh
tail -n 30 /var/log/layer7-bl-update.log
```

```sh
cat /usr/local/etc/layer7/blacklists/.state/fallback.state 2>/dev/null
```

Snapshot oficial (prerelease GitHub, não rouba `latest`):
tag `blacklists-ut1-current` — **cortada** em `30.11`; os quatro URLs
de *download* devolvem **404 esperado**. Primary exige token. Isto
**não** é o canal do pacote nem motivo para reupload GA4.11. O
espelho no cliente é legado / fallback e **não** se remove neste
bloco. Links e fingerprint em MANUAL-INSTALL; nota
[`09-blocking/nota-404-esperado-cut-30.11.md`](09-blocking/nota-404-esperado-cut-30.11.md).

### 9.3 DNS forçado / anti-QUIC

Regras de blacklist com *Forçar DNS local* geram `rdr` no ruleset principal
(não no sub-anchor legado). Verificar com `pfctl -s nat`. Anti-QUIC é
configurável por interface; no âmbito MITM o escopo é o das listas MITM
(ver runbooks MITM).

---

## 10. Identidade

Trilha **Identity (User-ID de rede)** — estado e arranque:

- [`00-overview/START-HERE-identity-mitm.md`](00-overview/START-HERE-identity-mitm.md)
- Plano: [`02-roadmap/plano-identity-mitm-addon.md`](02-roadmap/plano-identity-mitm-addon.md)
- Posicionamento PME: [`00-overview/posicionamento-pme-identity-first.md`](00-overview/posicionamento-pme-identity-first.md)
- ADRs 0027 / 0029; mapa: [`01-architecture/identity-mitm-mapa-rastreabilidade.md`](01-architecture/identity-mitm-mapa-rastreabilidade.md)

**Resumo operador:** Identity de rede (RADIUS / agente DC, etc.) está no
âmbito fechado documentado; **captive portal pfSense está fora de escopo**.
Requer entitlement `identity` no `.lic`. Não activar MITM “porque Identity
existe” — são eixos ortogonais.

---

## 11. MITM — gates, limites e NO-GO

### 11.1 Princípios (produto)

| Regra | Estado |
|-------|--------|
| Default | **OFF** |
| Escopo rdr | `source_cidr` ∧ `dest_cidr` — **nunca** `from any` |
| Janela | Failsafe `max_window` / `deadline_unix` (P3 / `1.9.73`) |
| Teste controlado | ≤15 min, escopo mínimo — runbook `1.9.46` |
| Piloto externo | Exige ficha site (P5) + P1–P4; **P4 = ABORT** |
| Permanente | **NO-GO** |
| Validação TLS | Proibido suavizar / `--ignore-certificate-errors` |

### 11.2 Documentos canónicos (ler antes de qualquer activação)

| Documento | Papel |
|-----------|--------|
| [`mapa-prontidao-mitm-piloto-2026-08-09.md`](09-blocking/mapa-prontidao-mitm-piloto-2026-08-09.md) | Veredicto prontidão |
| [`GO-escopo-piloto-mitm-generico.md`](09-blocking/GO-escopo-piloto-mitm-generico.md) | Escopo D1–D9 / ficha |
| [`runbook-piloto-mitm-generico.md`](09-blocking/runbook-piloto-mitm-generico.md) | Ops piloto genérico |
| [`runbook-activacao-mitm-producao-1.9.46.md`](09-blocking/runbook-activacao-mitm-producao-1.9.46.md) | Teste curto controlado |
| [`politica-tls-sem-bypass.md`](09-blocking/politica-tls-sem-bypass.md) | Política TLS |
| [`GO-produto-20.10.md`](09-blocking/GO-produto-20.10.md) | GO produto |
| ADR-0026 | Opt-in MITM |

### 11.3 Estado actual (2026-08-09)

- P1+P2+P3: documentação/código de janela **PASS** no âmbito lab.
- **P4 soak ABORT** — não usar como prova de estabilidade multi-hora.
- **P5** bloqueado à espera de ficha de site de cliente (nomeada).
- **Não activar** piloto externo nem intercept permanente com base neste hub.

Este manual **não** reproduz procedimentos de activação MITM com IPs de lab
nem credenciais. Operadores autorizados seguem os runbooks acima **após**
GO humano e ficha completa.

---

## 12. Diagnóstico

Comandos (MANUAL-INSTALL §8):

```sh
layer7d -V
```

```sh
service layer7d onestatus
```

```sh
layer7d --fingerprint
```

```sh
layer7d --license-status
```

```sh
tail -50 /var/log/system.log | grep layer7
```

```sh
pfctl -s Tables | grep layer7
```

```sh
pfctl -t layer7_block -T show
```

```sh
pfctl -t layer7_block_dst -T show
```

```sh
ls -la /usr/local/sbin/layer7d
```

```sh
ls -l /usr/local/etc/layer7.json /usr/local/etc/layer7.lic
```

GUI: **Services > Layer 7 > Diagnósticos** — incluindo **Reparar tabelas PF**.

Troubleshooting PF: MANUAL-INSTALL §10.  
Segurança WebGUI em testes: [`13-runbooks/pfsense-webgui-safety.md`](13-runbooks/pfsense-webgui-safety.md).

---

## 13. Logs, explicabilidade e relatórios

### 13.1 Destinos de log

Fonte: [`14-logging/README.md`](14-logging/README.md) (ADR-0015).

| Destino | Conteúdo |
|---------|----------|
| `/var/log/layer7d.log` | Operação (ciclo de vida, config, licença, erros) |
| `/var/log/layer7-events.log` | Tráfego detalhado + auditoria de bloqueios |
| `/usr/local/etc/layer7/reports/reports.db` | Histórico pesquisável (SQLite) |

Defaults: detalhe OFF; bloqueios continuam auditados; rotação interna limitada.

```sh
ls -lh /var/log/layer7d.log* /var/log/layer7-events.log* 2>/dev/null
du -h /usr/local/etc/layer7/reports/reports.db 2>/dev/null
tail -n 100 /var/log/layer7d.log
tail -n 100 /var/log/layer7-events.log
```

### 13.2 Relatórios executivos

Manual: [`12-reports/MANUAL-RELATORIOS-EXECUTIVOS.md`](12-reports/MANUAL-RELATORIOS-EXECUTIVOS.md).  
GUI: **Services > Layer 7 > Relatórios** e Definições > Relatórios.  
Exportação CSV / HTML / JSON na página de Relatórios.

---

## 14. Backup e restauro (appliance)

1. **Export Layer7** na GUI (Definições) — configuração operacional.
2. Preservar `/usr/local/etc/layer7.lic` (e, se necessário, cópia de
   `layer7.json`) fora do appliance.
3. O **backup XML do pfSense não inclui** `/usr/local/etc/layer7/`.
4. Antes de upgrades de risco: export + anotar `layer7d -V` e modo
   (monitor/enforce; MITM deve estar OFF salvo janela autorizada).
5. License server (PostgreSQL):  
   [`13-runbooks/license-server-backup-restore.md`](13-runbooks/license-server-backup-restore.md).

---

## 15. Segurança

- Não publicar senhas, chaves privadas, CA privadas nem cookies de sessão.
- MITM: CA do **cliente**; privkey fora de git/tickets/chats; trust só no
  escopo SOURCE (ver runbook piloto).
- TLS: sem bypass de validação (política canónica).
- Licenças: painel só em HTTPS canónico; origem admin endurecida (F2).
- Releases: verificar SHA256 antes de `pkg add`.
- Blacklists: só snapshots assinadas; fail-closed se fingerprint divergir.
- WebGUI pfSense: cuidado com Command Prompt e sessões admin
  ([`pfsense-webgui-safety.md`](13-runbooks/pfsense-webgui-safety.md)).

Cadeia de integração / ADRs: ADR-0003 (distribuição), ADR-0004 (confiança),
ADR-0005 (blacklists), ADR-0006 (fallback).

---

## 16. Runbooks e suporte

| Necessidade | Documento |
|-------------|-----------|
| Índice runbooks | [`13-runbooks/README.md`](13-runbooks/README.md) |
| Rollback pacote | [`13-runbooks/rollback.md`](13-runbooks/rollback.md) |
| Install canónico | [`10-license-server/MANUAL-INSTALL.md`](10-license-server/MANUAL-INSTALL.md) |
| License server ops | runbooks `license-server-*` em `13-runbooks/` |
| Validação lab (interno) | [`04-package/validacao-lab.md`](04-package/validacao-lab.md) |
| Changelog | [`changelog/CHANGELOG.md`](changelog/CHANGELOG.md) |
| Releases | [`06-releases/README.md`](06-releases/README.md) |
| Estado / gates | [`../CORTEX.md`](../CORTEX.md) |

**Suporte comercial / técnico:** contactar a Systemup pelos canais
contratuais do cliente. Para abertura de incidente interno de engenharia,
usar o handoff e hierarquia em
[`00-overview/handoff-chat-novo.md`](00-overview/handoff-chat-novo.md)
(equipa de produto — não substitui suporte ao cliente).

**Reportar erro (GUI):** em **Services → Layer 7 → Diagnósticos**, bloco
**Reportar erro** (`1.9.48`). Fluxo: (1) descrever o sintoma,
(2) rever metadados seguros, (3) **Abrir issue no GitHub** ou
**Copiar URL (sem redirect)**. Nada é enviado até clicar. **Não** inclui
`.lic`, chaves, logs, dumps nem IPs de clientes. No GitHub: login →
completar reprodução → Submit. Fluxo completo:
[`00-overview/pack-produto-layer7.md`](00-overview/pack-produto-layer7.md#como-funciona-reportar-erro).

---

## 17. Caminhos importantes (referência)

| Caminho | Descrição |
|---------|-----------|
| `/usr/local/sbin/layer7d` | Daemon |
| `/usr/local/etc/layer7.json` | Configuração |
| `/usr/local/etc/layer7.lic` | Licença |
| `/usr/local/etc/layer7/blacklists/` | Blacklists UT1 + estado |
| `/usr/local/etc/layer7/reports/reports.db` | Relatórios SQLite |
| `/var/log/layer7d.log` | Log operacional |
| `/var/log/layer7-events.log` | Eventos / auditoria |
| `/var/run/layer7d.pid` | PID |

Lista completa: MANUAL-INSTALL §11.

---

## 18. Relação com o tutorial legado

[`tutorial/guia-completo-layer7.md`](tutorial/guia-completo-layer7.md)
permanece **preservado por compatibilidade** (contexto longo / clientes
antigos). **Não** é SSOT de instalação, versões nem MITM.

| Documento | Papel |
|-----------|--------|
| **`docs/MANUAL-PRODUTO.md` (este)** | Hub público SSOT de navegação do produto |
| `docs/10-license-server/MANUAL-INSTALL.md` | SSOT de comandos de pacote |
| `docs/tutorial/guia-completo-layer7.md` | Preservado; pode estar desactualizado |

---

## 19. Checklist rápido pós-install

```text
[ ] SHA256 do .pkg confere com 2155daca…9df833 (1.9.73)
[ ] layer7d -V e service layer7d onestatus OK
[ ] Licença activa (layer7d --license-status)
[ ] Modo monitor até validação
[ ] Export da configuração Layer7 guardado
[ ] Blacklists: sync saudável se forem usadas
[ ] MITM OFF (salvo GO + ficha + runbook)
[ ] filter_configure_sync após upgrade se regras PF em falta
```

---

## 20. Manutenção deste hub

Ao publicar nova versão do pacote:

1. Actualizar banner e secções 1, 3 e 4 a partir de MANUAL-INSTALL.
2. Actualizar veredicto MITM a partir de START-HERE / mapa de prontidão.
3. Actualizar checkpoint em `CORTEX.md` e linha em `docs/README.md`.
4. Não inventar comandos — só espelhar fontes canónicas.
