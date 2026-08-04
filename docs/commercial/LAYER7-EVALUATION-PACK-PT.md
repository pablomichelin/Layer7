# Layer7 para pfSense CE — Pacote de Avaliação

**Systemup Solução em Tecnologia** · [www.systemup.inf.br](https://www.systemup.inf.br)

> Documento público de avaliação. Descreve **o que o produto faz**, não a
> implementação interna. Para análise de arquitectura ou segurança em profundidade,
> contactar a Systemup (NDA pode aplicar-se).

---

## 1. Resumo executivo

O **Layer7** é um add-on comercial de controlo de aplicações para **pfSense CE**.
Identifica tráfego de rede pela aplicação real (YouTube, BitTorrent, TikTok, VPN,
etc.) através de Deep Packet Inspection (nDPI) e permite **monitorizar**, **permitir**
ou **bloquear** com políticas por interface, sub-rede, grupo de dispositivos e
horário — integrado na GUI do pfSense, **sem MITM**.

| | |
|--|--|
| **Empresa** | Systemup Solução em Tecnologia |
| **Produto** | Layer7 para pfSense CE |
| **Servidor de licenças** | [license.systemup.inf.br](https://license.systemup.inf.br) |
| **Downloads públicos** | [GitHub Releases](https://github.com/pablomichelin/Layer7/releases) |
| **Modo monitor** | Gratuito (só observação, sem bloqueio) |
| **Modo enforce** | Licença anual válida obrigatória |

O Layer7 **não** é afiliado à Netgate nem ao projecto pfSense. pfSense® é marca
registada da Electric Sheep Fencing LLC d/b/a Netgate.

---

## 2. Problema que resolve

| Cenário | Sem Layer7 | Com Layer7 |
|---------|------------|------------|
| Funcionário com BitTorrent | Difícil detectar (portas aleatórias) | Detecta e bloqueia por aplicação |
| Alunos no TikTok/Instagram | Bloqueio DNS é fácil de contornar | Identifica pela app, não só IP/DNS |
| Bypass por VPN | Firewall não distingue | Detecta WireGuard, OpenVPN, Tailscale, etc. |
| Visibilidade de banda | Limitada | Dashboard com top apps e clientes |

---

## 3. Capacidades principais

- **350+ aplicações** classificadas em tempo real (redes sociais, streaming, P2P, jogos, VPN, IA, etc.)
- **Acções de política:** Monitor, Allow, Block, Tag
- **Perfis rápidos (1 clique):** YouTube, Facebook, Instagram, TikTok, WhatsApp, combo Redes Sociais, Streaming, Jogos, VPN/Proxy, Ferramentas IA, e mais
- **Blacklists por categoria web** (70+ categorias, estilo SquidGuard)
- **Agendamento** por dia e faixa horária (incluindo overnight)
- **Grupos de dispositivos** reutilizáveis nas políticas
- **Simulação de política** antes de activar regras
- **Relatórios** com histórico e exportação (CSV, HTML, JSON)
- **Anti-bypass DNS** (opções DoT/DoQ/DoH)
- **Bloqueio QUIC selectivo** para melhor identificação web
- **Backup/restore** de configuração (JSON)
- **Verificação de actualização** pela GUI do pfSense
- **Gestão de frota** para actualização em massa (cenário MSP)
- **Interface bilingue:** Português e Inglês

---

## 4. Compatibilidade

| Item | Suporte |
|------|---------|
| **pfSense CE** | 2.7.x e 2.8.x |
| **FreeBSD** | 14 e 15 |
| **Interfaces** | Até 8 simultâneas |
| **Políticas** | Ilimitadas |
| **Regras blacklist** | Até 8 simultâneas |
| **Idiomas** | Português, Inglês |

Contactar a Systemup para compatibilidade pfSense Plus no vosso ambiente.

---

## 5. Modelo de licenciamento

| Modo | Bloqueio | Licença |
|------|----------|---------|
| **Monitor** | Não | Não obrigatória (grátis) |
| **Enforce** | Sim | Licença anual por firewall (vinculada ao hardware) |

- Activação via `license.systemup.inf.br`
- Validação offline com ficheiro `.lic` assinado Ed25519
- **14 dias de grace** após expiração para licença já emitida
- Sem licença válida: sistema permanece em **modo só monitor**

---

## 6. Preços (USD)

Licença anual por firewall. Instalação inicial é taxa única (1.º ano).

| Plano | Perfil | Licença / ano | Instalação inicial | **Total ano 1** |
|-------|--------|---------------|--------------------|-----------------|
| **Starter** | Escritório pequeno (~30 users) | $349 | $550 | **$899** |
| **Professional** | PME (30–150 users) | $649 | $750 | **$1.399** |
| **Business** | Sites maiores (150–500 users) | $999 | $950 | **$1.949** |
| **Education** | Escolas (verificadas) | $499 | $650 | **$1.149** |
| **Enterprise** | Crítico / ISP / hospital | $1.499 | $1.450 | **$2.949** |

**Renovação (ano 2+):** só licença (sem reinstalação, salvo appliance novo).

| Extra | Preço |
|-------|-------|
| Trial enforce 30 dias | Mediante pedido |
| Instalação on-site | +$800 (viagem à parte) |
| Serviço gerido (Premium) | desde $349/mês |
| Pré-pagamento 3 anos | 15% desconto |

Preços indicativos. Contactar Systemup para volume MSP e propostas formais.

---

## 7. Instalação (visão geral)

A instalação usa `.pkg` assinado das GitHub Releases e script de uma linha.
Passos operacionais completos: [MANUAL-INSTALL.md](../10-license-server/MANUAL-INSTALL.md).

```bash
# No pfSense (SSH root) — substituir VERSION pela tag da release mais recente:
fetch -o /tmp/install.sh \
  https://github.com/pablomichelin/Layer7/releases/download/vVERSION/install.sh \
  && sh /tmp/install.sh
```

Depois abrir **Services → Layer 7** na GUI do pfSense.

Tempo médio de instalação: **menos de 5 minutos** (sem desenho de políticas).

---

## 8. Comparação com alternativas comuns

| | **ntopng** | **pfBlockerNG** | **Layer7** |
|--|------------|-----------------|------------|
| **Foco principal** | Análise de tráfego | Listas DNS/IP | Enforcement por aplicação |
| **Bloquear por app** | Não é o core | Limitado | Sim |
| **GUI nativa pfSense** | Ferramenta separada | Parcial | Sim (integrado) |
| **Perfis rápidos** | Não | Não | Sim |
| **Agendamento** | Não | Limitado | Sim |

O Layer7 complementa ferramentas de visibilidade; foi feito para **aplicar
políticas** no pfSense, não para substituir plataformas forenses completas.

---

## 9. Processo de avaliação

| Passo | Acção |
|-------|-------|
| 1 | Rever este pack + [Visão Geral do Produto](LAYER7-PRODUCT-OVERVIEW-PT.md) |
| 2 | Instalar em lab pfSense (modo monitor é grátis) |
| 3 | Pedir **trial enforce 30 dias** se precisarem testar bloqueio |
| 4 | Opcional: revisão segurança/arquitectura com NDA |

**O que partilhamos sem NDA:** capacidades, compatibilidade, instalação, licenciamento, preços.

**O que requer NDA ou âmbito fechado:** arquitectura interna, detalhes de enforcement,
implementação do license server.

---

## 10. Contacto

| | |
|--|--|
| **Website** | [www.systemup.inf.br](https://www.systemup.inf.br) |
| **Licenciamento** | [license.systemup.inf.br](https://license.systemup.inf.br) |
| **Downloads** | [github.com/pablomichelin/Layer7/releases](https://github.com/pablomichelin/Layer7/releases) |

Para licenças de avaliação, propostas ou parceria MSP, contactar a Systemup pelo
site ou representante.

---

*Versão do documento: 2026-08-04 · Systemup Solução em Tecnologia*
