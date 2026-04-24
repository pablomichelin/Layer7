# Quick start — validação lab completa

Fluxo encadeado para fechar o gate **pacote + serviço** em [`validacao-lab.md`](../04-package/validacao-lab.md).
O artefacto de instalação no ramo actual é o **`.pkg`** gerado pelo port (ver
[`CORTEX.md`](../../CORTEX.md) e [`MANUAL-INSTALL`](../10-license-server/MANUAL-INSTALL.md));
nao confundir com o historico de `.txz` em notas antigas.

## Pré-requisitos

- [ ] **Builder FreeBSD** conforme [`builder-freebsd.md`](builder-freebsd.md) (VM com `git`, `pkg`, toolchain)
- [ ] **pfSense CE** conforme [`lab-topology.md`](lab-topology.md) (WAN+LAN, cliente)
- [ ] **Snapshot** antes de instalar o pacote ([`snapshots-e-gate.md`](snapshots-e-gate.md))
- [ ] Caminho para copiar o **`.pkg`** do builder para o pfSense (SCP, pasta partilhada, etc.)

## Sequência

### 1. No builder (FreeBSD)

```sh
cd /caminho/para/Layer7
sh scripts/package/check-port-files.sh
sh scripts/package/smoke-layer7d.sh
cd package/pfSense-pkg-layer7
make clean 2>/dev/null || true
make package
# Anotar caminho do .pkg (ex.: package/pfSense-pkg-layer7/work/pkg/pfSense-pkg-layer7-<versao>.pkg)
```

### 2. Transferir o `.pkg` para pfSense

SCP, datastore, ou pasta partilhada — conforme o seu ambiente.

### 3. No pfSense (SSH ou consola)

```sh
cd /root   # ou diretório do .pkg
pkg add ./pfSense-pkg-layer7-*.pkg
cp /usr/local/etc/layer7.json.sample /usr/local/etc/layer7.json
service layer7d onestart
service layer7d status
ps auxww | grep layer7d
```

### 4. Verificar

- Logs: `clog /var/log/system.log | tail -n 80` — deve conter `daemon_start`
- GUI: `https://<IP_PFSENSE>/packages/layer7/layer7_status.php`
- Parar: `service layer7d onestop`

### 5. Documentar

Preencher as caixas em [`validacao-lab.md`](../04-package/validacao-lab.md) com os outputs reais. Sem evidência, o gate não fecha.

## Opcional

- **§6b** — testar `pfctl -t layer7_block -T add/show/delete`
- **§6c** — testar `layer7d -e` / `-n` no appliance (config enforce: `samples/config/layer7-enforce-smoke.json` no clone; copiar para pfSense ou colar conteúdo)

## Troubleshooting

Ver tabela em [`validacao-lab.md`](../04-package/validacao-lab.md) §3 (build) e critérios de reprovação §9.
