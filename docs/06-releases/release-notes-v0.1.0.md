# Release Notes — v0.1.0

**Data:** 2026-03-23
**Tag:** `v0.1.0`
**Artefato:** `pfSense-pkg-layer7-0.1.0.pkg`
**Checksum:** `pfSense-pkg-layer7-0.1.0.pkg.sha256`

**Nota (alinhamento documental):** a instalação a partir de GitHub Release segue
hoje o script **`install.sh`** e o **`.pkg`** (ver
[`MANUAL-INSTALL`](../10-license-server/MANUAL-INSTALL.md) e
[`scripts/release/README.md`](../../scripts/release/README.md)). O comando abaixo
foi actualizado a partir de referencias antigas a `install-lab.sh`.

---

## Resumo

Primeira release pública do Layer7 para pfSense CE. Inclui daemon com classificação de tráfego em tempo real via nDPI, policy engine com suporte a block/allow/monitor/tag por aplicação e categoria, enforcement via PF tables, GUI integrada no WebGUI do pfSense, e observabilidade via syslog (local e remoto).

---

## Funcionalidades

- **Daemon `layer7d`** — binário C standalone, sem dependências de runtime além de libndpi e libpcap
- **Classificação nDPI** — ~350 protocolos reconhecidos por deep packet inspection (BitTorrent, YouTube, Netflix, Telegram, etc.)
- **Custom protocols file** — regras adicionais por host/porta/IP sem recompilar (`/usr/local/etc/layer7-protos.txt`)
- **Policy engine** — até 24 políticas e 16 exceções, com prioridade e match por `ndpi_app` e `ndpi_category`
- **Exceções por IP/CIDR** — whitelist de hosts ou subnets que ignoram políticas
- **Enforcement PF** — modo `enforce` adiciona IPs às tabelas PF (`layer7_block`, `layer7_tagged`) via `pfctl`
- **Modo monitor** — classifica e loga sem bloquear (safe para produção inicial)
- **GUI pfSense** — 6 páginas integradas: Settings, Policies, Exceptions, Diagnostics, Events, Status
- **Syslog local + remoto** — envio UDP para coletor externo (configurável via GUI)
- **SIGUSR1 stats** — estatísticas de runtime (pacotes, fluxos classificados, policy matches)
- **Debug boost** — elevação temporária do log_level via `debug_minutes` na GUI
- **Resolução automática de interfaces** — GUI converte nomes pfSense (lan, opt1) para device real (em0, igb1)

## Limitações conhecidas

- Apenas IPv4 (IPv6 planejado para V2)
- Sem reassembly TCP (classifica pelos primeiros ~48 pacotes de cada fluxo)
- Tabela de fluxos com tamanho fixo (64K entradas)
- Atualização de protocolos nDPI requer recompilação da libndpi no builder
- Depende de `IGNORE_OSVERSION=yes` para instalação no pfSense CE
- Distribuição via GitHub Releases (não integrado ao Package Manager oficial do pfSense)

---

## Instalação

### Pré-requisitos no pfSense

Criar tabela PF antes de usar modo enforce:

```sh
pfctl -t layer7_block -T add 127.0.0.255
pfctl -t layer7_block -T delete 127.0.0.255
```

Adicionar regra de block no firewall:

```
block drop quick on egress from <layer7_block>
```

### Primeira instalação (Diagnostics > Command Prompt)

```sh
fetch -o /tmp/install.sh https://github.com/OWNER/REPO/releases/download/v0.1.0/install.sh && sh /tmp/install.sh
```

### Pós-instalação

```sh
cp /usr/local/etc/layer7.json.sample /usr/local/etc/layer7.json
service layer7d onestart
service layer7d status
```

Verificar classificação:
```sh
kill -USR1 $(pgrep layer7d)
grep layer7d /var/log/system.log | tail -5
```

---

## Upgrade de versão anterior (alpha)

```sh
pkg delete -y pfSense-pkg-layer7
# Depois executar o comando de instalação acima
```

---

## Rollback

```sh
service layer7d onestop
pkg delete -y pfSense-pkg-layer7
```

Para reinstalar versão anterior, usar o `install.sh` (ou o `.pkg` com `pkg add
-f`) do release desejado.
Ver [`docs/05-runbooks/rollback.md`](../05-runbooks/rollback.md).

---

## Compatibilidade

- pfSense CE 2.7.x / 2.8.x (FreeBSD 14/15)
- Builder FreeBSD 15.0-RELEASE
- nDPI 5.x

---

## Atualização do nDPI

Para atualizar os protocolos reconhecidos:

1. **Regras por host/porta/IP** — editar `/usr/local/etc/layer7-protos.txt` e enviar SIGHUP ao daemon
2. **Core nDPI** — no builder: `scripts/release/update-ndpi.sh` (pull, build, deploy)

Ver [`docs/core/ndpi-update-strategy.md`](../core/ndpi-update-strategy.md).
