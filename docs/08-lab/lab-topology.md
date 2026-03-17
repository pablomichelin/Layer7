# Topologia de laboratório (referência)

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

## Regras de firewall (lab)

- Começar permissivo na LAN de teste (apenas lab).
- Documentar qualquer regra manual aplicada para repetir após snapshot restore.

## Endereçamento (template)

| Ativo | IP exemplo |
|-------|------------|
| pfSense LAN | 10.20.30.1/24 |
| Cliente | 10.20.30.100/24 |
| Coletor syslog | 10.20.30.10/24 |

Ajuste faixas conforme sua rede; mantenha **anotado** neste doc ou em `docs/08-lab/lab-inventory.md` (criar ao provisionar).
