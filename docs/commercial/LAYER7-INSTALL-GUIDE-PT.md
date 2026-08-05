# Layer7 para pfSense CE — Guia de Instalação

**Systemup Solução em Tecnologia** · [www.systemup.inf.br](https://www.systemup.inf.br)

> Guia de instalação para clientes. Licenciamento e activação: contactar a
> Systemup directamente — não está neste documento público.

---

## Requisitos

- pfSense CE 2.7.x ou 2.8.x
- Acesso SSH como **root**
- Interfaces de rede a monitorizar já configuradas

---

## Instalar (release mais recente)

```bash
# No pfSense — substituir VERSION pela tag mais recente em GitHub Releases:
fetch -o /tmp/install.sh \
  https://github.com/pablomichelin/Layer7/releases/download/vVERSION/install.sh \
  && sh /tmp/install.sh
```

Exemplo com versão específica:

```bash
fetch -o /tmp/install.sh \
  https://github.com/pablomichelin/Layer7/releases/download/v1.8.11_65/install.sh \
  && sh /tmp/install.sh --version 1.8.11_65
```

Depois abrir **Services → Layer 7** na GUI do pfSense.

---

## Actualizar

```bash
fetch -o /tmp/install.sh \
  https://github.com/pablomichelin/Layer7/releases/download/vNOVA_VERSION/install.sh \
  && sh /tmp/install.sh --version NOVA_VERSION
```

Ou usar **Services → Layer 7 → Definições → Verificar actualização** na GUI.

---

## Desinstalar

```bash
fetch -o /tmp/uninstall.sh \
  https://github.com/pablomichelin/Layer7/releases/download/vVERSION/uninstall.sh \
  && sh /tmp/uninstall.sh
```

---

## Verificar integridade do pacote

Cada release publica:

- `pfSense-pkg-layer7-VERSION.pkg`
- `pfSense-pkg-layer7-VERSION.pkg.sha256`
- Manifesto assinado (quando activo nessa release)

Downloads:  
[github.com/pablomichelin/Layer7/releases](https://github.com/pablomichelin/Layer7/releases)

---

## Após a instalação

1. Ir a **Services → Layer 7 → Definições**
2. Activar o serviço e seleccionar interfaces de captura
3. Começar em modo **Monitor** para observar tráfego sem bloquear
4. Criar políticas ou aplicar perfis rápidos
5. Para **Enforce** (bloqueio), contactar a **Systemup** para licença comercial

---

## Suporte

- **Website:** [www.systemup.inf.br](https://www.systemup.inf.br)
- **Avaliação do produto:** [Pacote de Avaliação](LAYER7-EVALUATION-PACK-PT.md)

O Layer7 não é afiliado à Netgate nem ao projecto pfSense.
