# Recomendação de destino HTTPS (não configurado)

**Data:** `2026-08-09`  
**Contexto:** pós-smoke passivo `1.9.42` na `.254`; proprietário sem destino reservado.

## Critérios

1. IPv4 **/32** único (não rede larga)  
2. HTTPS **:443** estável e sob controlo do operador  
3. Fora do appliance / GUI / `.234` / `.235`  
4. Com `source_cidr=192.168.100.24/32`, só a VM `.24` seria elegível — mesmo assim o destino não deve ser CDN partilhado  
5. SNI conhecido para `block_sni` no teste

## Análise de candidatos públicos (só leitura)

| Candidato | Observação | Veredicto |
|-----------|------------|-----------|
| `example.com` → `104.20.23.154` / `172.66.147.243` | Cloudflare anycast; IP partilhado com muitos sítios | **NO-GO** como `dest_cidr` |
| `neverssl.com` → `34.223.124.45` | HTTPS não fiável (empty reply); não dedicado | **NO-GO** |
| `1.1.1.1` / CDN genérico | Anycast crítico | **NO-GO** |
| Servidores LAN de produção | Risco operacional | **NO-GO** |

## Recomendação (preferida)

**Provisionar um host de teste dedicado** com um único IPv4 `/32`, por exemplo:

- VM/container lab na LAN (IP novo, **não** `.234`/`.235`/`.254`) com nginx/caddy a servir HTTPS :443 e hostname tipo `mitm-test.lab.local` / FQDN interno; **ou**
- VPS barato com IP dedicado usado **só** para este gate.

No próximo GO de activação (fora deste bloco):

| Campo | Valor sugerido |
|-------|----------------|
| `intercept.source_cidr` | `192.168.100.24/32` |
| `intercept.dest_cidr` | `<IP_DEDICADO>/32` |
| `intercept.block_sni` | hostname HTTPS desse host |
| CA | só na VM `.24` |

## Riscos se usarem destino público partilhado

- `dest_cidr` num IP anycast pode redireccionar **outros** hostnames no mesmo IP (ainda que a origem seja só `.24`)  
- Instabilidade de IP (CDN muda A records)  
- Sem controlo do certificado/upstream para validar block page

**Não configurado neste bloco.** Aguardar escolha do proprietário + GO para activação escopada.
