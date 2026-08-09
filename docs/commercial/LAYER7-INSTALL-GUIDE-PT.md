# Layer7 para pfSense CE — Guia de Instalação

**Systemup Solução em Tecnologia** · [www.systemup.inf.br](https://www.systemup.inf.br)

> Guia de instalação para clientes.  
> **Versão de referência:** **`1.9.47`**  
> **SHA256:** `2155daca7f80eb0c90af4f736d71131d01d22b63942831aa1c0191240f9df833`  
> Manual completo: [LAYER7-MANUAL-PRODUTO-PT.md](LAYER7-MANUAL-PRODUTO-PT.md)  
> Licenciamento e activação: contactar a Systemup — não está detalhado neste repositório público.

---

## Requisitos

- pfSense CE 2.7.x ou 2.8.x (validar no vosso ambiente)
- Acesso SSH como **root** (ou Diagnostics → Command Prompt)
- Interfaces de rede a monitorizar já configuradas

---

## Instalar — `1.9.47`

> Nesta release o caminho oficial é **fetch + `pkg add`**.  
> `install.sh` **não** está anexado a `v1.9.47`.

**Comando único:**

```sh
fetch -o /tmp/pfSense-pkg-layer7-1.9.47.pkg https://github.com/pablomichelin/Layer7/releases/download/v1.9.47/pfSense-pkg-layer7-1.9.47.pkg && IGNORE_OSVERSION=yes pkg add -f /tmp/pfSense-pkg-layer7-1.9.47.pkg && sysrc layer7d_enable=YES && service layer7d onestart && layer7d -V
```

**Integridade (obrigatório antes de confiar no pacote):**

```sh
fetch -o /tmp/pfSense-pkg-layer7-1.9.47.pkg.sha256 \
  https://github.com/pablomichelin/Layer7/releases/download/v1.9.47/pfSense-pkg-layer7-1.9.47.pkg.sha256 \
  && sha256 -q /tmp/pfSense-pkg-layer7-1.9.47.pkg | tee /tmp/l7-actual.sha256 \
  && cat /tmp/pfSense-pkg-layer7-1.9.47.pkg.sha256
```

Esperado: `2155daca7f80eb0c90af4f736d71131d01d22b63942831aa1c0191240f9df833`.

Depois abrir **Services → Layer 7** na GUI do pfSense.

---

## Actualizar para `1.9.47`

```sh
service layer7d onestop && fetch -o /tmp/pfSense-pkg-layer7-1.9.47.pkg https://github.com/pablomichelin/Layer7/releases/download/v1.9.47/pfSense-pkg-layer7-1.9.47.pkg && IGNORE_OSVERSION=yes pkg add -f /tmp/pfSense-pkg-layer7-1.9.47.pkg && service layer7d onestart && layer7d -V
```

```sh
/etc/rc.filter_configure_sync
pfctl -sr | grep -i layer7
```

Ou usar **Services → Layer 7 → Definições → Verificar actualização** na GUI
(consulta `releases/latest`).

---

## Desinstalar

```sh
service layer7d onestop
sysrc -x layer7d_enable
pkg delete -y pfSense-pkg-layer7
```

---

## Rollback lab (`1.9.46`)

```sh
service layer7d onestop && fetch -o /tmp/pfSense-pkg-layer7-1.9.46.pkg https://github.com/pablomichelin/Layer7/releases/download/v1.9.46/pfSense-pkg-layer7-1.9.46.pkg && IGNORE_OSVERSION=yes pkg add -f /tmp/pfSense-pkg-layer7-1.9.46.pkg && service layer7d onestart && layer7d -V
```

---

## Após a instalação

1. Ir a **Services → Layer 7 → Definições**
2. Activar o serviço e seleccionar interfaces de captura
3. Começar em modo **Monitor** para observar tráfego sem bloquear
4. Criar políticas ou aplicar perfis rápidos
5. Para **Enforce** (bloqueio), contactar a **Systemup** para licença comercial
6. Manter **MITM OFF** salvo orientação comercial explícita

---

## Suporte

- **Manual completo:** [LAYER7-MANUAL-PRODUTO-PT.md](LAYER7-MANUAL-PRODUTO-PT.md)
- **Website:** [www.systemup.inf.br](https://www.systemup.inf.br)
- **Avaliação:** [Pacote de Avaliação](LAYER7-EVALUATION-PACK-PT.md)
- **Releases:** [github.com/pablomichelin/Layer7/releases](https://github.com/pablomichelin/Layer7/releases)

O Layer7 não é afiliado à Netgate nem ao projecto pfSense.
