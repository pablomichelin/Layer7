# Release Notes — 1.8.11_11 (RASCUNHO / PRÉ-PUBLICAÇÃO)

> **Naming:** o ficheiro mantém o nome `release-notes-1.8.11_10-DRAFT.md` até
> reorganização F6/F7; o **port** e os artefactos abaixo reflectem **`1.8.11_11`**
> (`PORTREVISION=11`).

> **Estado:** rascunho. O **`.pkg`** correspondente ainda **não** foi publicado
> em GitHub Releases. A referência de instalação pública continua a listada em
> `docs/10-license-server/MANUAL-INSTALL.md` em **Links da versão actual**.
> Após a tag e upload, alinhar este ficheiro (tag, datas, checksum) e
> `MANUAL-INSTALL` no **mesmo** bloco de entrega; ver também
> `docs/changelog/CHANGELOG.md` ([Unreleased] / pacote de trabalho `1.8.11_11`).
> Evidencia de lab exigida antes de declarar F4.1–F4.3 fechadas: `CORTEX.md`
> (*Próximos passos*, ponto 7), `checklist-mestre` (gates F4) e
> `validacao-lab` **10a** / **10b** / **11** com a `test-matrix` alinhada.

**Data (branch):** 2026-04-24  
**Tag prevista (exemplo):** `v1.8.11_11` (confirmar com convenção de tags do repositório)  
**Artefato (port `package/pfSense-pkg-layer7`):** `PORTVERSION=1.8.11`, `PORTREVISION=11`  
**Nome de pacote FreeBSD típico:** `pfSense-pkg-layer7-1.8.11_11.pkg` (ajustar ao nome exacto do build)

**Checksum / manifesto / assinatura (F1.2+):** preencher após o stage de release
(`pfSense-pkg-layer7-1.8.11_11.pkg.sha256`, `release-manifest.v1.txt`, etc.); ver
[`RELEASE-SIGNING.md`](RELEASE-SIGNING.md).

---

## Resumo

Entrega acumulada de estabilidade **F4.1–F4.3** no branch de trabalho: daemon/rc.d,
reloader de blacklists, e geração **NAT** do trilho **DNS forçado** (`force_dns`)
com deduplicação, ordem estável de interfaces e CIDRs por regra.

---

## Destaques (F4.3 / BG-011 — `force_dns`)

- Deduplicação de pares **(interface, CIDR)** entre regras de blacklist com
  `force_dns` (a partir de `1.8.11_8`).
- Ordem **alfabética** das interfaces na emissão de `rdr` (a partir de
  `1.8.11_9`).
- Por regra, CIDRs IPv4 **válidos**, **únicos** e **ordenados**, validados de
  forma coerente antes do cruzamento com interfaces (a partir de `1.8.11_10`).
- Manutenção **`1.8.11_11`:** `layer7.inc` — fallback de nome de interface em
  `force_dns` reutiliza `layer7_pf_ifname_for_rules()` (DRY; sem mudança de
  comportamento).

Ver detalhe operacional e comandos de verificação: `docs/10-license-server/MANUAL-INSTALL.md`
(addenda F4.3) e `docs/04-package/validacao-lab.md` (secção **11** e notas
`1.8.11_8`–`10`). A mesma secção **11** inclui um **cenário de lab sugerido**
multi-interface / VLAN para aproximar a evidência **BG-011** / matriz **6.7**
a combinações com vários segmentos.

---

## Instalação (modelo; substituir `v…` pela tag real)

Alinhar ao [`release-notes-template.md`](release-notes-template.md) e ao
`install.sh` publicado; exemplo genérico:

```sh
fetch -o /tmp/install.sh "https://github.com/pablomichelin/pfsense-layer7/releases/download/v1.8.11_11/install.sh" && sh /tmp/install.sh
```

(Confirmar `REPO_OWNER/REPO_NAME` e padrão de tag no release real.)

---

## Rollback

```sh
pkg delete pfSense-pkg-layer7
```

Reinstalar a versão anterior com o `install.sh` da tag desejada. Ver
[`docs/05-runbooks/rollback.md`](../05-runbooks/rollback.md).

---

## Compatibilidade (preencher na publicação)

- **pfSense CE:** (lab / versão alvo)
- **FreeBSD builder:** 15.0-RELEASE (referência conhecida do projecto)

---

## Itens a fechar antes de publicar (checklist mínima)

- [ ] Build e `.pkg` no builder; ficheiro e checksum alinhados ao manifesto
- [ ] Roteiros **10a** / **10b** / **11** do `validacao-lab` executados no lab (evidência)
- [ ] `CHANGELOG.md`: mover itens de interesse de [Unreleased] para a secção da tag
- [ ] `MANUAL-INSTALL.md`: **Links da versão actual** e comandos com a nova tag
- [ ] CORTEX: última release publica vs branch (se alterar o SSOT)
- [ ] Remover do título/introdução o estado *RASCUNHO* (ou arquivar este ficheiro
  e deixar só a nota de release final por tag)
