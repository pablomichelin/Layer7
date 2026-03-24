# Layer7 para pfSense CE — Visão Geral do Produto

> **Um produto [Systemup](https://www.systemup.inf.br) Solução em Tecnologia**

---

## O que é o Layer7?

O **Layer7** é uma solução de controlo de aplicações para **pfSense CE** (Community Edition) que permite identificar, monitorizar e bloquear tráfego de rede com base na aplicação — não apenas por porta ou IP.

Enquanto um firewall tradicional trabalha apenas com regras de IP e porta (camadas 3 e 4), o Layer7 opera na **Camada 7 (Aplicação)**, identificando o software real que está a gerar o tráfego: YouTube, BitTorrent, TikTok, WhatsApp, VPN, jogos, streaming, e mais de 350 aplicações conhecidas.

---

## Problema que resolve

| Cenário | Sem Layer7 | Com Layer7 |
|---------|-----------|------------|
| Funcionário a usar BitTorrent | Impossível detectar (usa portas aleatórias) | Detecta e bloqueia automaticamente |
| Alunos a aceder TikTok/Instagram | Bloqueio por DNS é facilmente contornado | Identifica pela aplicação, independente do IP/DNS |
| Visitantes a usar VPN para bypass | Firewall não consegue distinguir | Detecta WireGuard, OpenVPN, Tailscale e bloqueia |
| Saber o que está a consumir banda | Nenhuma visibilidade | Dashboard em tempo real com top apps e clientes |

---

## Principais funcionalidades

### 1. Identificação de aplicações em tempo real

O Layer7 utiliza tecnologia de **Deep Packet Inspection (DPI)** para classificar cada fluxo de rede pela aplicação real. Reconhece mais de **350 aplicações e protocolos**, incluindo:

- **Redes sociais:** Facebook, Instagram, TikTok, Twitter/X, LinkedIn, Telegram, WhatsApp
- **Streaming de vídeo:** YouTube, Netflix, Disney+, Twitch, Amazon Prime
- **Streaming de áudio:** Spotify, Apple Music, Deezer
- **Downloads P2P:** BitTorrent, eDonkey, Gnutella
- **Jogos:** Steam, Xbox Live, PlayStation Network
- **Videoconferência:** Zoom, Microsoft Teams, Google Meet
- **VPN/Proxy:** WireGuard, OpenVPN, Tailscale, iCloud Private Relay
- **Produtividade:** Microsoft 365, Google Workspace
- **AI/Chat:** ChatGPT, ferramentas de IA
- **E centenas mais...**

### 2. Políticas granulares de controlo

Crie regras precisas de bloqueio ou monitorização com combinações de:

- **Aplicação específica** — bloquear BitTorrent mas permitir tudo o resto
- **Por interface de rede** — regras diferentes para LAN, WiFi, Visitantes
- **Por sub-rede ou IP** — bloquear redes sociais só para a rede dos alunos
- **Por grupo de dispositivos** — criar grupos nomeados ("Funcionários", "Visitantes", "Diretoria") e aplicar regras por grupo
- **Por horário** — bloquear YouTube apenas em horário de trabalho (8h-18h, seg-sex)
- **Por site/domínio** — bloquear domínios específicos manualmente

### 3. Quatro tipos de acção

| Acção | Descrição |
|-------|-----------|
| **Monitor** | Apenas observa e regista. Não interfere no tráfego. |
| **Allow** | Permite explicitamente o tráfego (útil em excepções). |
| **Block** | Bloqueia o tráfego da aplicação identificada. |
| **Tag** | Marca o tráfego para regras avançadas do firewall. |

### 4. Perfis rápidos (1 clique)

15 perfis pré-configurados para os cenários mais comuns:

| Perfil | O que bloqueia |
|--------|---------------|
| YouTube | Todo o tráfego YouTube |
| Facebook | Facebook e Messenger |
| Instagram | Instagram e Stories |
| TikTok | TikTok e Musical.ly |
| WhatsApp | WhatsApp e WhatsApp Web |
| Twitter/X | Twitter e TweetDeck |
| LinkedIn | LinkedIn e LinkedIn Learning |
| Netflix | Netflix streaming |
| Spotify | Spotify streaming |
| Twitch | Twitch live streaming |
| Redes Sociais | Todas as redes sociais (combo) |
| Streaming | Todos os serviços de streaming (combo) |
| Jogos | Steam, Xbox, PlayStation, etc. |
| VPN/Proxy | WireGuard, OpenVPN, Tailscale, etc. |
| AI Tools | ChatGPT e ferramentas de IA |

Basta seleccionar o perfil, escolher a interface e os IPs, e a política é criada automaticamente.

### 5. Blacklists por categorias web (estilo SquidGuard)

Sistema de blacklists baseado em categorias web com milhões de domínios classificados:

- **+70 categorias** disponíveis (pornografia, gambling, malware, phishing, publicidade, redes sociais, jogos, etc.)
- **Regras por sub-rede** — bloquear categorias diferentes para redes diferentes
- **Excepções por IP** — permitir acesso para IPs específicos (ex: o director)
- **Até 8 regras simultâneas** com combinações diferentes
- **Actualização automática** das listas por agendamento
- **Whitelist global** de domínios sempre permitidos

**Exemplo prático:**
> Bloquear *gambling* e *pornografia* para toda a rede 192.168.10.0/24, excepto o IP 192.168.10.1 (director).

### 6. Dashboard operacional em tempo real

Painel com visão completa do que está a acontecer na rede:

- **Contadores em tempo real** — fluxos classificados, bloqueados, permitidos
- **Top 10 aplicações bloqueadas** — quais apps estão a ser mais bloqueadas
- **Top 10 clientes** — quais dispositivos mais activam regras de bloqueio
- **Estado do serviço** — versão, modo, uptime
- **Estado da licença** — cliente, validade, hardware ID

### 7. Módulo de relatórios com histórico

Relatórios detalhados com dados históricos para análise e auditoria:

- **Visão geral de tráfego** — gráfico temporal de classificações e bloqueios
- **Top apps bloqueadas** — gráfico de barras com as aplicações mais bloqueadas
- **Top clientes bloqueados** — quais dispositivos geram mais bloqueios
- **Blacklists por categoria** — gráfico donut com distribuição por categoria
- **Top domínios bloqueados** — tabela com os domínios mais bloqueados
- **Relatório por política** — estatísticas de cada regra individual
- **Consulta por IP** — timeline completa de eventos de um dispositivo
- **Filtro de período** — 1h, 6h, 24h, 7 dias, 30 dias, ou período personalizado
- **Exportação** — CSV, HTML (para impressão), JSON

### 8. Excepções flexíveis

Proteja dispositivos e redes críticas das regras:

- Excepcionar por **IP individual** (ex: o computador do administrador)
- Excepcionar por **sub-rede** (ex: rede de gestão 10.0.0.0/24)
- Excepcionar por **interface** (ex: porta dedicada para servidores)
- As excepções são **avaliadas antes** de qualquer política

### 9. Agendamento por horário

- Definir **dias da semana** e **faixa horária** para cada política
- Suporte a **horário overnight** (ex: 22:00 às 06:00)
- Políticas sem horário ficam **sempre activas**

**Exemplo:** Bloquear redes sociais de segunda a sexta, das 8h às 18h, mas permitir ao fim-de-semana.

### 10. Grupos de dispositivos

- Criar **grupos nomeados** (ex: "Vendas", "RH", "TI", "Visitantes")
- Associar **IPs e sub-redes** a cada grupo
- Usar os grupos nas políticas em vez de listar IPs manualmente
- Reutilizável em múltiplas políticas

### 11. Teste de política (simulação)

- **Simular uma situação** antes de activar: "O que aconteceria se o IP 192.168.1.50 acedesse ao YouTube?"
- Veredicto visual colorido: **vermelho** (bloqueado), **verde** (permitido), **azul** (monitorizado)
- Tabela detalhada mostrando cada política avaliada e porquê casou ou não

### 12. Anti-bypass DNS

Protecção multi-camada contra tentativas de contornar o bloqueio via DNS alternativo:

- Bloqueio automático de **DNS sobre TLS (DoT)** e **DNS sobre QUIC (DoQ)**
- Detecção e bloqueio de **DNS sobre HTTPS (DoH)**
- Bloqueio de **iCloud Private Relay**
- Configuração automática com 1 clique

### 13. Bloqueio QUIC selectivo

- Toggle para bloquear o protocolo **QUIC (UDP 443)** usado pelo Chrome/Edge
- Força fallback para **TCP/TLS** onde a inspecção é mais eficaz
- Melhora significativamente a taxa de identificação de aplicações web

### 14. Backup e restore de configuração

- **Exportar** toda a configuração (políticas, excepções, grupos, definições) para ficheiro JSON
- **Importar** configuração de outro firewall ou backup anterior
- Ideal para replicar configurações entre firewalls

### 15. Internacionalização (PT/EN)

- Interface disponível em **Português** e **Inglês**
- Selector de idioma na página de definições
- Todas as páginas e mensagens traduzidas

### 16. Actualização pela GUI

- Botão para **verificar nova versão** disponível
- **Instalação automática** da actualização sem perder configurações
- Sem necessidade de acesso SSH

### 17. Gestão de frota (50+ firewalls)

Para empresas com múltiplas filiais/unidades:

- **Actualização em massa** de todos os firewalls por SSH
- **Sincronização de regras** customizadas sem recompilação
- Suporte a **actualização paralela** (ex: 4 firewalls ao mesmo tempo)
- **Dry-run** para pré-visualizar antes de executar

### 18. Licenciamento seguro

- Licença vinculada ao **hardware específico** do firewall
- Verificação criptográfica **Ed25519** offline (sem "phone home")
- Período de graça de **14 dias** após expiração
- Sem licença válida: funciona em modo **monitorização** (sem bloqueio)

---

## Interface gráfica (GUI)

O Layer7 integra-se nativamente no pfSense, acessível em **Services > Layer 7** com **12 páginas**:

| Página | Função |
|--------|--------|
| **Estado (Dashboard)** | Visão geral com contadores, top apps, top clientes, botão reiniciar |
| **Definições** | Configurações globais: on/off, modo, interfaces, idioma, QUIC, syslog |
| **Políticas** | Criar, editar, remover regras. Perfis rápidos. Selecção de apps/categorias |
| **Grupos** | Gerir grupos de dispositivos nomeados |
| **Categorias nDPI** | Explorar todas as 350+ aplicações organizadas por categoria |
| **Teste** | Simular uma situação e ver o veredicto antes de activar |
| **Excepções** | Proteger IPs e sub-redes das regras |
| **Blacklists** | Categorias web (estilo SquidGuard), regras por sub-rede, whitelist |
| **Relatórios** | Gráficos históricos, top apps/clientes/domínios, exportação |
| **Events** | Monitor ao vivo dos eventos do sistema |
| **Diagnósticos** | Ferramentas de diagnóstico, estado das tabelas, anti-DoH |
| **Licença** | Estado da licença, hardware ID, activação |

---

## Casos de uso típicos

### Empresa / Escritório
- Bloquear redes sociais em horário de trabalho para a rede dos funcionários
- Permitir acesso completo para a diretoria
- Bloquear BitTorrent e VPNs em toda a rede
- Monitorar o uso de bandwidth sem bloquear
- Relatórios mensais de uso por departamento

### Escola / Universidade
- Bloquear conteúdo adulto, gambling e malware via blacklists
- Bloquear jogos e streaming na rede dos alunos
- Permitir YouTube para a rede dos professores
- Agendamento: acesso livre fora do horário de aulas
- Bloquear ferramentas de IA durante provas

### Hotel / Espaço público
- Rede de visitantes: bloquear torrents e VPNs
- Rede administrativa: acesso completo
- Monitorar uso de banda por aplicação
- Anti-bypass DNS para garantir que as regras não são contornadas

### ISP / Provedor (com pfSense)
- Oferecer controlo parental como serviço premium
- Relatórios de uso por cliente
- Gestão centralizada de 50+ firewalls

### Clínica / Hospital
- Bloquear redes sociais em terminais de atendimento
- Permitir acesso a sistemas de saúde
- Excepções para equipamentos médicos conectados

---

## Compatibilidade

| Item | Suporte |
|------|---------|
| **pfSense CE** | 2.7.x e 2.8.x |
| **FreeBSD** | 14 e 15 |
| **Interfaces** | Até 8 interfaces simultâneas |
| **Políticas** | Ilimitadas |
| **Excepções** | Até 16 |
| **Blacklist rules** | Até 8 regras simultâneas |
| **Grupos** | Ilimitados |
| **Aplicações detectáveis** | 350+ (nDPI) |
| **Idiomas** | Português e Inglês |

---

## Instalação

A instalação é simples e rápida:

1. Aceder ao pfSense por SSH
2. Executar o comando de instalação (1 linha)
3. Aceder a **Services > Layer 7** na interface web do pfSense
4. Seleccionar as interfaces de rede a monitorizar
5. Criar políticas ou aplicar perfis rápidos
6. Pronto!

Tempo médio de instalação: **menos de 5 minutos**.

---

## Distribuição

O Layer7 é distribuído como pacote `.pkg` nativo do FreeBSD, instalável directamente no pfSense sem necessidade de compilação ou dependências externas.

Actualizações são feitas pela própria interface gráfica do produto.

---

## Sobre a Systemup

A **Systemup Solução em Tecnologia** (www.systemup.inf.br) é especializada em soluções de infraestrutura e segurança de rede.

O Layer7 para pfSense CE é desenvolvido por **Pablo Michelin**, com foco em oferecer funcionalidades de controlo de aplicações de nível enterprise para o ecossistema open-source pfSense.

---

## Contacto

| | |
|--|--|
| **Website** | [www.systemup.inf.br](https://www.systemup.inf.br) |
| **Produto** | Layer7 para pfSense CE |
| **Versão actual** | 1.4.0 |

---

*Layer7 para pfSense CE — Controlo inteligente de aplicações para o seu firewall.*

*Layer7 para pfSense CE NÃO é afiliado com Netgate ou o projecto pfSense. pfSense é uma marca registada da Electric Sheep Fencing LLC d/b/a Netgate.*
