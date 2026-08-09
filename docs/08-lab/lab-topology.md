# Topologia de laboratório (referência)

## Política de validação (obrigatória)

| Regra | Valor |
|-------|--------|
| Preferência | **Testes reais** na malha Systemup — **não** substituir por smoke só local/simulado |
| Orquestração | **Mac → clientes Ubuntu** (SSH chave) + **Mac → pfSense** (comandos) |
| Proibido | Assumir PASS de MITM/Identity/enforcement só com unit tests ou curls no Mac |
| Produção | Hosts abaixo são **funcionais em produção**; mutações com snapshot/rollback e janela clara |

## Malha de produção Systemup (homologação / testes reais)

| Host | Papel | Acesso SSH |
|------|--------|------------|
| `192.168.100.254` | pfSense Plus + Layer7 (`systemupfw`) | Ver secção **SSH pfSense** |
| `192.168.100.234` | Ubuntu cliente A (`server`) | `root@192.168.100.234` (chave do Mac) |
| `192.168.100.235` | Ubuntu cliente B (`zpro-aimirim`) | `root@192.168.100.235` (chave do Mac) |
| `192.168.100.12` | Builder FreeBSD | `root` (ver `builder-freebsd.md`) |

## Lab descartável MITM / tlsproxy (PoC)

| Host | Papel | Acesso SSH |
|------|--------|------------|
| `192.168.100.54` | Ubuntu 24.04 — **lab isolado** para PoC `layer7-tlsproxy` | `root@192.168.100.54` (chave do Mac) |

**Regra:** em `.54` o agente **pode** instalar deps, gerar CA de lab, bind TLS e medir S*.  
**Proibido** repetir esses passos em `.254`/`.234`/`.235` sem GO produto explícito.

Artefacto PoC no `.54`: `/opt/layer7-poc/` (binário + `lab-certs/` locais; chaves **fora** do git).

**Importante:** o appliance **não** tem chave SSH para `.234`/`.235`.  
Curls/orquestração: **sempre a partir do Mac** para os clientes (ver
`tests/lab/run-im9-20.33-homolog-orchestrator.sh`).

Evidência two-client recente Identity:  
`docs/tests/evidence/20260808T174100Z-im9-20.33-homolog-1.9.29/`.  
Evidência S8 real MITM OFF:  
`docs/tests/evidence/20260809T022400Z-s8-real-two-client-1.9.38/`.

---

## SSH no pfSense — menu vs shell (**ler sempre**)

| Utilizador | Comportamento | Como obter shell |
|------------|---------------|------------------|
| **`admin`** (e muitas sessões interactivas) | Abre o **menu de texto** do pfSense | Escolher **`8) Shell`** |
| **`root`** (quando SSH root permitido) | Pode entregar **shell directo** | Adequado a `ssh … 'comando'` / agentes |

Regras para agentes e scripts:

1. Se a sessão mostrar o menu pfSense (`0) Logout` … `8) Shell`), **entrar em 8** antes de qualquer comando.
2. Não assumir que `ssh admin@192.168.100.254 'layer7d -V'` funciona — o menu engole o comando.
3. Automação preferida neste lab: `root@192.168.100.254` com shell directo (política actual).
4. **Nunca** gravar passwords no git; inventário local em `lab-inventory.md` (gitignored) a partir do template.

```text
# Interactivo (admin / menu)
ssh admin@192.168.100.254
# → teclar 8  Enter  → shell

# Automação (root shell directo — lab Systemup)
ssh root@192.168.100.254 'layer7d -V'
```

---

## Objetivo

Validar Layer7 com **tráfego real** dos clientes `.234`/`.235` através do
pfSense `.254`, com captura/classificação/enforcement observáveis — sem
inventar resultados a partir de stubs.

## Diagrama (malha real)

```text
  Mac (orquestrador)
    |-- SSH root@254 ---------> pfSense Layer7 192.168.100.254   [PRODUÇÃO]
    |-- SSH root@234 ---------> Ubuntu A "server"          192.168.100.234 [PRODUÇÃO]
    |-- SSH root@235 ---------> Ubuntu B "zpro-aimirim"    192.168.100.235 [PRODUÇÃO]
    |-- SSH root@54  ---------> Ubuntu lab MITM/PoC        192.168.100.54  [DESCARTÁVEL]
    |-- SSH root@12  ---------> Builder FreeBSD            192.168.100.12

  .234 / .235  --LAN-->  .254  --WAN-->  Internet
  .54          --LAN-->  (PoC tlsproxy local; sem intercept prod)
```

## Diagnóstico / smokes

- Diagnóstico passivo appliance: [`../../scripts/diagnose-layer7-appliance.sh`](../../scripts/diagnose-layer7-appliance.sh)
- Two-client / homolog: `tests/lab/run-im9-20.33-homolog-orchestrator.sh`
- Enforcement scoped: [`../../tests/lab/smoke-enforcement-scoped.sh`](../../tests/lab/smoke-enforcement-scoped.sh)
- MITM pré-runtime S1–S8: [`../09-blocking/runbook-s1-s8-mitm-pre-runtime.md`](../09-blocking/runbook-s1-s8-mitm-pre-runtime.md)

## Regras de firewall (lab)

- Mutações PF/políticas: preferir **scoped** a um cliente (ex. só `.234`) e
  reverter no mesmo bloco.
- Documentar qualquer regra manual para repetir após snapshot restore.

## Endereçamento (template genérico — secundário)

O diagrama `10.20.30.0/24` abaixo é **exemplo de lab isolado**. A malha
**canónica Systemup** para gates reais é a tabela do topo (`.254`/`.234`/`.235`).

| Ativo | IP exemplo (isolado) |
|-------|----------------------|
| pfSense LAN | 10.20.30.1/24 |
| Cliente | 10.20.30.100/24 |

## Trilha após montar a topologia

Fluxo condensado: [`quick-start-lab.md`](quick-start-lab.md).

1. **Builder FreeBSD:** [`builder-freebsd.md`](builder-freebsd.md)
2. **Gate pacote + serviço no pfSense:** [`../04-package/validacao-lab.md`](../04-package/validacao-lab.md)
3. **Snapshots:** [`snapshots-e-gate.md`](snapshots-e-gate.md) / [`p1-snapshot-gate-b1.md`](p1-snapshot-gate-b1.md)
4. **PoC nDPI (tráfego real):** [`../poc/README.md`](../poc/README.md)
