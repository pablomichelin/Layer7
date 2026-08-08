# Topologia de laboratório (referência)

## Malha de produção Systemup (homologação)

| Host | Papel | Notas |
|------|--------|------|
| `192.168.100.254` | pfSense (Plus) Layer7 | Backup Veeam; SSH `root` |
| `192.168.100.234` | Ubuntu cliente A (`server`) | SSH `root` a partir do Mac/lab |
| `192.168.100.235` | Ubuntu cliente B (`zpro-aimirim`) | SSH `root` a partir do Mac/lab |
| `192.168.100.12` | Builder FreeBSD | Build `.pkg` |

**Importante:** o appliance **não** tem chave SSH para `.234`/`.235` — orquestrar
curls a partir do Mac (ver `tests/lab/run-im9-20.33-homolog-orchestrator.sh`).

Evidência two-client recente: `docs/tests/evidence/20260808T174100Z-im9-20.33-homolog-1.9.29/`.

---

## Objetivo

Isolar tráfego de teste, permitir captura/classificação no pfSense e validar políticas sem afetar produção.

## Diagrama lógico (texto)

```text
                    [ Internet opcional ]
                            |
                     +-------------+
                     |  pfSense CE |  (VM lab)
                     |   (layer7)  |
                     +------+------+
                            |
              +-------------+-------------+
              |                           |
        [ WAN / uplink ]            [ LAN teste ]
        (NAT ou rota)               10.20.30.0/24 (exemplo)
                                            |
                    +-----------------------+-----------------------+
                    |                       |                       |
              [ Host cliente ]        [ Servidor syslog ]    [ opcional: gerador ]
              Linux/Windows           10.20.30.10            iperf/curl/browser
```

## Interfaces sugeridas (VM pfSense)

| Interface | Papel | Exemplo |
|-----------|--------|---------|
| WAN | Uplink (NAT para internet ou rede corporativa isolada) | vtnet0 |
| LAN | Rede de teste Layer7 | vtnet1 — `10.20.30.1/24` |

## Host cliente

- **1 VM ou físico** na mesma LAN de teste (ex.: `10.20.30.100`).
- Navegador, `curl`, `iperf3` cliente, atualizações opcionais — para gerar fluxos classificáveis na PoC (HTTP/S, DNS, etc.).

## SSH no pfSense (menu vs shell)

- Com utilizador **admin** (e em muitas consolas pfSense), a sessão SSH abre o **menu de texto** do pfSense; **não** cai de imediato num shell. Para trabalhar com comandos, escolhe normalmente a opção **8 (Shell)**.
- Comandos remotos *numa linha* (`ssh … 'comando'`) a partir de scripts ou agente podem **falhar** ou colidir com o menu. Para automação, costuma ser preferível: **SSHD com acesso** que entregue *shell* directo (ex.: `root` com login SSH permitido em *System* > *Advanced* se a tua politica o permitir) ou sessão interactiva; validar a política de segurança do teu lab antes.
- Nunca colocar credenciais no repositório; usar `lab-inventory` local ou segredo fora de Git.
- Diagnóstico rápido (copiar o script para o pfSense e correr com `sh`): [`../../scripts/diagnose-layer7-appliance.sh`](../../scripts/diagnose-layer7-appliance.sh) — versão pacote/binário, `--license-status`, `mode` / `enforcement_model`, regras PF, tabelas `layer7_*`, logs recentes.
- O diagnóstico é passivo por defeito. `L7_DIAG_REFRESH_STATS=1` envia
  `SIGUSR1` e fica reservado ao lab/janela aprovada.
- Smoke enforcement escopado (Caminho B / gate two-client): [`../../tests/lab/smoke-enforcement-scoped.sh`](../../tests/lab/smoke-enforcement-scoped.sh) — modo estático no workspace; modo appliance no pfSense (ver [`../04-package/validacao-lab.md`](../04-package/validacao-lab.md) sec. 12).

## Regras de firewall (lab)

- Começar permissivo na LAN de teste (apenas lab).
- Documentar qualquer regra manual aplicada para repetir após snapshot restore.

## Endereçamento (template)

| Ativo | IP exemplo |
|-------|------------|
| pfSense LAN | 10.20.30.1/24 |
| Cliente | 10.20.30.100/24 |
| Coletor syslog | 10.20.30.10/24 |

Ajuste faixas conforme sua rede; mantenha **anotado** neste doc ou em `docs/08-lab/lab-inventory.md` (criar ao provisionar a partir de [`lab-inventory.template.md`](lab-inventory.template.md)).

## Trilha após montar a topologia

Fluxo condensado: [`quick-start-lab.md`](quick-start-lab.md).

1. **Builder FreeBSD:** [`builder-freebsd.md`](builder-freebsd.md) → `sh scripts/package/check-port-files.sh`, `sh scripts/package/smoke-layer7d.sh` e `make package` no port.
2. **Gate pacote + serviço no pfSense:** [`../04-package/validacao-lab.md`](../04-package/validacao-lab.md) (início: *Gates oficiais F4* e roteiros **10a** / **10b** / **11** quando aplicável; §6b PF opcional, §6c **`layer7d -e`**).
3. **Snapshots:** [`snapshots-e-gate.md`](snapshots-e-gate.md) antes de instalar o `.pkg`.
4. **PoC nDPI (tráfego real):** [`../poc/README.md`](../poc/README.md).
