# Layer7 para pfSense CE — Manual do Produto (público)

**Systemup Solução em Tecnologia** · [www.systemup.inf.br](https://www.systemup.inf.br)

> Guia público do operador. Fiel ao estado publicado em GitHub Releases.  
> **Pacote de referência (canal `latest`):** **`1.9.74`**  
> **SHA256:** `bb4cc7810b26d2246ffd71912d04b0c83299eb826f09b7a324a83dfa42084542`  
> **Release:** <https://github.com/pablomichelin/Layer7/releases/tag/v1.9.74>  
> **Latest:** <https://github.com/pablomichelin/Layer7/releases/latest>  
> **Alinhamento:** `2026-08-26`

Licenciamento comercial, activação e suporte de enforce: **contactar a Systemup**
(não há detalhe de license server neste repositório público).

---

## 0. Como usar este manual

| Documento | Papel |
|-----------|--------|
| **Este ficheiro** | Hub + guia operador (instalação, modos, GUI, limites, suporte) |
| [Guia de Instalação](LAYER7-INSTALL-GUIDE-PT.md) | Atalho de install/upgrade/uninstall |
| [Visão Geral](LAYER7-PRODUCT-OVERVIEW-PT.md) | Funcionalidades e casos de uso |
| [Pacote de Avaliação](LAYER7-EVALUATION-PACK-PT.md) | Avaliação comercial |
| [GitHub Releases](https://github.com/pablomichelin/Layer7/releases) | `.pkg` + `.sha256` oficiais |

**Regra:** os comandos abaixo correspondem à release **`1.9.74`**. Em cada
nova release, actualizar este manual no mesmo bloco da publicação.

---

## 1. Estado do produto (honesto)

| Canal | Versão | Papel |
|-------|--------|--------|
| **`latest` / updater GUI** | **`1.9.74`** | Único pacote público para download |
| **Produção enforce (pin de política)** | **`1.9.8`** | Referência estável até GO — **não** está no canal público |

**MITM (inspecção TLS):**

- Default **OFF** no pacote.
- Só com escopo explícito origem **e** destino (proibido origem aberta/`from any`).
- Janela temporal com auto-desactivação (failsafe) a partir de `1.9.73`.
- Piloto externo / intercept permanente em produção: **não** está liberado
  neste canal sem GO comercial e ficha de site com a Systemup.
- Squid **não** é o caminho MITM do produto.

**Não confundir:** ter o pacote `1.9.73` instalado **não** activa MITM nem
equivale a pin de produção enforce `1.9.8`.

---

## 2. O que é o Layer7

Add-on de classificação e política Layer 7 para **pfSense CE**:

- Identificação de aplicações/protocolos (DPI / nDPI);
- Políticas `monitor`, `allow`, `block`, `tag`;
- GUI nativa (**Services → Layer 7**);
- Blacklists por categoria (pipeline assinado quando activo na release);
- Relatórios e logs locais;
- Modo **Monitor** (observação) e **Enforce** (bloqueio com licença);
- Add-ons Identity / MITM sujeitos a entitlement e gates (MITM opt-in).

O Layer7 **não** é afiliado à Netgate nem ao projecto pfSense.

---

## 3. Download e integridade

Cada release publica pelo menos:

- `pfSense-pkg-layer7-<versão>.pkg`
- `pfSense-pkg-layer7-<versão>.pkg.sha256`

**Canal público `1.9.74`:**

- Pacote: <https://github.com/pablomichelin/Layer7/releases/download/v1.9.74/pfSense-pkg-layer7-1.9.74.pkg>
- SHA256: <https://github.com/pablomichelin/Layer7/releases/download/v1.9.74/pfSense-pkg-layer7-1.9.74.pkg.sha256>
- **SHA256 esperado:** `9ea84e54115280c53f3b77f5359bd99e652839a8aebf8a5eb22d9b1ecf0352af`

> Caminho oficial: **`install.sh`** assinado (F1.2). O canal público
> publica **apenas** esta release de pacote.

**Verificar integridade:**

```sh
fetch -o /tmp/pfSense-pkg-layer7-1.9.74.pkg \
  https://github.com/pablomichelin/Layer7/releases/download/v1.9.74/pfSense-pkg-layer7-1.9.74.pkg
```

```sh
fetch -o /tmp/pfSense-pkg-layer7-1.9.74.pkg.sha256 \
  https://github.com/pablomichelin/Layer7/releases/download/v1.9.74/pfSense-pkg-layer7-1.9.74.pkg.sha256 \
  && sha256 -q /tmp/pfSense-pkg-layer7-1.9.74.pkg | tee /tmp/l7-actual.sha256 \
  && cat /tmp/pfSense-pkg-layer7-1.9.74.pkg.sha256
```

Os dois hashes devem coincidir com
`9ea84e54115280c53f3b77f5359bd99e652839a8aebf8a5eb22d9b1ecf0352af`.

**Nota ABI:** em alguns ambientes (ex. pfSense Plus / FreeBSD 16 vs builder 15)
usa-se `IGNORE_OSVERSION=yes` e `pkg add -f`. Isto é aceite operacional; não
substitui validação no vosso ambiente.

---

## 4. Instalação / upgrade / reinstall / desinstalação / rollback

Executar como **root** (SSH ou Diagnostics → Command Prompt). Em Command
Prompt preferir o **comando único** (uma linha).

### 4.1 Instalar (primeira vez) — `1.9.74`

```sh
fetch -o /tmp/install.sh https://github.com/pablomichelin/Layer7/releases/download/v1.9.74/install.sh && sh /tmp/install.sh
```

Alternativa manual:

```sh
fetch -o /tmp/pfSense-pkg-layer7-1.9.74.pkg https://github.com/pablomichelin/Layer7/releases/download/v1.9.74/pfSense-pkg-layer7-1.9.74.pkg && IGNORE_OSVERSION=yes pkg add -f /tmp/pfSense-pkg-layer7-1.9.74.pkg && sysrc layer7d_enable=YES && service layer7d onestart && layer7d -V
```

Passo a passo:

```sh
fetch -o /tmp/pfSense-pkg-layer7-1.9.74.pkg \
  https://github.com/pablomichelin/Layer7/releases/download/v1.9.74/pfSense-pkg-layer7-1.9.74.pkg
IGNORE_OSVERSION=yes pkg add -f /tmp/pfSense-pkg-layer7-1.9.74.pkg
sysrc layer7d_enable=YES
service layer7d onestart
layer7d -V
service layer7d onestatus
```

### 4.2 Actualizar para `1.9.74`

```sh
service layer7d onestop && fetch -o /tmp/pfSense-pkg-layer7-1.9.74.pkg https://github.com/pablomichelin/Layer7/releases/download/v1.9.74/pfSense-pkg-layer7-1.9.74.pkg && IGNORE_OSVERSION=yes pkg add -f /tmp/pfSense-pkg-layer7-1.9.74.pkg && service layer7d onestart && layer7d -V
```

Após upgrade, recompilar o ruleset PF uma vez:

```sh
/etc/rc.filter_configure_sync
pfctl -sr | grep -i layer7
```

Políticas e configuração Layer7 são tipicamente preservadas no upgrade.
Antes de upgrades de risco: **Export** da configuração Layer7 na GUI
(Definições). O backup XML do pfSense **não** inclui por completo os
ficheiros sob `/usr/local/etc/layer7/`.

Alternativa GUI: **Services → Layer 7 → Definições → Verificar actualização**
(consulta `releases/latest` deste repositório).

### 4.3 Reinstalar a mesma versão

```sh
service layer7d onestop && pkg delete -y pfSense-pkg-layer7 && fetch -o /tmp/pfSense-pkg-layer7-1.9.74.pkg https://github.com/pablomichelin/Layer7/releases/download/v1.9.74/pfSense-pkg-layer7-1.9.74.pkg && IGNORE_OSVERSION=yes pkg add -f /tmp/pfSense-pkg-layer7-1.9.74.pkg && sysrc layer7d_enable=YES && service layer7d onestart
```

### 4.4 Desinstalar

```sh
service layer7d onestop
sysrc -x layer7d_enable
pkg delete -y pfSense-pkg-layer7
```

### 4.5 Rollback

O canal público **não** disponibiliza pacotes anteriores. Reinstalar
`1.9.73` (secção 4.1). Qualquer pin enforce antigo é arquivo interno
Systemup — contactar suporte; **não** há URL público.

---

## 5. Serviço e verificação mínima

```sh
service layer7d onestatus
layer7d -V
```

GUI: **Services → Layer 7**. Em Definições: activar serviço, escolher
interfaces de captura, começar em **Monitor**.

---

## 6. Licenças

- **Monitor:** observação sem bloqueio (avaliação típica).
- **Enforce:** requer licença comercial Systemup.
- Activação, renovação, SKU e portal de licenças: **contactar a Systemup**
  — fora do âmbito deste repositório público.

Não partilhe ficheiros `.lic` privados em tíquetes públicos.

---

## 7. GUI (mapa do operador)

Áreas habituais em **Services → Layer 7**:

| Área | Uso |
|------|-----|
| Dashboard / estado | Visão geral, serviço, contadores |
| Definições | Enable, interfaces, modo, export/import, verificar actualização |
| Políticas | Regras por app/categoria/host, acção, horário, grupos |
| Perfis rápidos | Atalhos 1-clique para cenários comuns |
| Excepções / allowlist | Destinos ou apps a preservar |
| Blacklists | Categorias web (quando activo) |
| Relatórios | Histórico e exportação |
| Logs / diagnóstico | Operação e tráfego (conforme ecrã da versão) |
| Identity / MITM | Só se a licença e o pacote expuserem o módulo; MITM default OFF |

---

## 8. Monitor vs Enforce

| Modo | Comportamento |
|------|----------------|
| **Monitor** | Classifica e regista; **não** aplica bloqueio PF Layer7 |
| **Enforce** | Aplica políticas de bloqueio (requer licença válida) |

Recomendação: validar políticas em **Monitor** (ou simulação, se disponível)
antes de Enforce. Promoção enforce em produção deve seguir o vosso GO interno
e o pin acordado com a Systemup (`1.9.8` permanece referência estável).

---

## 9. Listas e políticas

- Políticas por aplicação, categoria, hosts/domínios, interface, CIDR, grupo
  e horário.
- Acções: Monitor / Allow / Block / Tag.
- Blacklists UT1: actualização por script/pipeline assinado quando activo na
  release; restauro last-known-good conforme documentação da versão.
- Anti-bypass DNS / QUIC: opções na GUI — usar com critério (impacto em apps
  legítimas).

---

## 10. Identidade (User-ID)

Mapa de utilizador/grupo a partir de fontes de rede (ex. RADIUS accounting /
integrações documentadas na versão). Políticas por utilizador/grupo dependem
de entitlement Identity. Captive portal pfSense **não** é o caminho do
add-on Identity do Layer7. Detalhe comercial e implantação: contactar Systemup.

---

## 11. MITM — gates e limites

| Regra | Estado |
|-------|--------|
| Default | **OFF** |
| Escopo | Origem **e** destino explícitos; proibido origem aberta |
| Janela | Failsafe / auto-disable (`1.9.73`) |
| Payload TLS | Não persistir conteúdo TLS; auditoria de metadados apenas |
| Piloto externo | **Não** liberado sem GO + ficha de site com Systemup |
| Permanente | **NO-GO** sem decisão humana explícita |

Não usar bypass TLS no cliente (`--ignore-certificate-errors`) como
“validação” de MITM.

---

## 12. Diagnóstico rápido

```sh
service layer7d onestatus
layer7d -V
pkg query %v pfSense-pkg-layer7
pfctl -sr | grep -i layer7
```

Se MITM estiver **intencionalmente** OFF (estado normal):

- não deve haver rdr/anti-QUIC Layer7 MITM activos;
- o helper TLS não deve estar a escutar em produção sem GO.

Problemas de classificação vs bloqueio: confirmar modo (Monitor/Enforce),
licença, políticas activas e excepções antes de alargar escopo.

---

## 13. Logs e explicabilidade

- Logs de operação do daemon e, quando activos, histórico de bloqueios /
  classificações na GUI.
- Preferir evidência de **metadados** (quem/quando/escopo/modo) — não
  conteúdo de sessão TLS.
- Relatórios: exportação conforme ecrãs da versão (CSV/HTML/JSON quando
  disponíveis).

---

## 14. Backup

1. **Export** Layer7 na GUI (Definições) antes de upgrade/reinstall.  
2. Backup do sistema pfSense (não substitui o export Layer7).  
3. Guardar à parte qualquer material de licença fornecido pela Systemup.  
4. Após restore: verificar serviço, modo, políticas e (se aplicável) estado MITM = OFF.

---

## 15. Segurança (operador)

- Manter MITM OFF salvo GO e escopo mínimo.
- Não publicar chaves privadas de CA, `.lic` ou credenciais.
- Validar SHA256 de cada `.pkg` antes de `pkg add`.
- Preferir janelas curtas e rollback testado em mudanças de risco.
- Separar canal `latest` (lab/avaliação) de pin de produção.

---

## 16. Runbooks e suporte

| Situação | Acção |
|----------|--------|
| Instalação / upgrade | Secção 4 deste manual + [Guia de Instalação](LAYER7-INSTALL-GUIDE-PT.md) |
| Licença / enforce | Contactar Systemup |
| Avaliação | [Pacote de Avaliação](LAYER7-EVALUATION-PACK-PT.md) |
| Releases / checksums | <https://github.com/pablomichelin/Layer7/releases> |
| Website | <https://www.systemup.inf.br> |

---

## 17. Checklist pós-install

- [ ] SHA256 do `.pkg` verificado  
- [ ] `layer7d` a correr (`onestatus` / `layer7d -V`)  
- [ ] GUI **Services → Layer 7** acessível  
- [ ] Interfaces de captura seleccionadas  
- [ ] Modo inicial **Monitor** (salvo GO enforce)  
- [ ] MITM **OFF** (salvo GO + ficha)  
- [ ] Export de configuração guardado  

---

## 18. Manutenção deste documento

Actualizar no **mesmo** bloco de cada release pública:

1. Versão / SHA256 / links  
2. Comandos de install/upgrade/reinstall/rollback  
3. Limites MITM / Identity se o contrato mudar  
4. Entrada no [README](../../README.md) do repositório  

---

Layer7 é um produto Systemup Solução em Tecnologia.  
Não afiliado à Netgate / pfSense®.
