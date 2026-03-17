# Syslog remoto (lab)

## Objetivo

Receber logs do pfSense (e futuramente do `layer7d`) em um host na LAN de teste para inspeção sem poluir a GUI.

## No pfSense CE

1. **Status → System Logs → Settings** (ou **Services → Syslog** conforme versão).
2. Habilitar envio remoto.
3. **IP do coletor:** ex. `10.20.30.10` (rede da topologia em `lab-topology.md`).
4. Porta típica: **514** UDP (ou TCP se configurado no servidor).
5. Salvar; gerar tráfego e confirmar chegada no servidor.

## Coletor simples (Linux na VM `10.20.30.10`)

```bash
# Ubuntu/Debian: instalar rsyslog se necessário
sudo apt install -y rsyslog

# Em /etc/rsyslog.conf ou snippet em /etc/rsyslog.d/pfsense.conf:
# module(load="imudp")
# input(type="imudp" port="514")

sudo systemctl restart rsyslog
sudo tcpdump -n -i any udp port 514
# ou tail -f /var/log/syslog
```

Ajuste conforme sua distro; objetivo é **validar entrega** antes de exigir formato JSON do produto.

## Documentar

- IP do coletor usado.
- Se usou UDP ou TCP.
- Timezone alinhado entre pfSense e coletor (facilita correlação).
