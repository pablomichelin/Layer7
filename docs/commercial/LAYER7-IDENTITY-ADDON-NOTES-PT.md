# Layer7 — Notes comerciais: Add-on Identity (SKU Y)

**Systemup Solução em Tecnologia** · [www.systemup.inf.br](https://www.systemup.inf.br)  
**Estado:** `2026-08-08` — passo **20.32** (IM9) · pacote lab/`latest` **`1.9.29`**  
**Produção enforce pin:** **`1.9.8`** (promoção independente deste add-on)

Documento para vendas / MSP / onboarding. Não substitui ADRs nem o MANUAL.

---

## Oferta

| SKU | `features` | Mensagem comercial |
|-----|------------|--------------------|
| **Standard (X)** | `base` | Controlo de aplicações Layer7 (produto base) |
| **Identity (Y)** | `base,identity` | **Âncora PME** — políticas por utilizador/grupo AD via User-ID de rede |
| **Y+ MITM** | `base,identity,mitm` | **Futuro** — token pode existir; inspeção TLS **não entregue** (DEFER) |

Licença legada `full` = **apenas base** (T1). Identity exige **reemissão** explícita.

---

## O que dizer ao cliente PME

1. Políticas Layer7 por **utilizador ou grupo AD**, sem depender só de IP estático.
2. Fontes: **RADIUS accounting** e/ou **agente leve no Domain Controller** + LDAP para grupos.
3. Mesmo pacote pfSense; add-on **opt-in** (não liga sozinho no upgrade).
4. Exactidão = **User-ID de rede** (IP visto no firewall), não agente em cada PC.

## O que **não** prometer

- Agente endpoint tipo GlobalProtect nesta release (ADIAR — ADR-0029).
- Terminal Server / VDI com vários users no mesmo IP para `ad_*`.
- MITM / descriptografia TLS “já disponível”.
- Captive portal Layer7.
- Paridade NGFW TLS com Palo Alto / FortiGate.

---

## Qualificação rápida (GO comercial)

| Pergunta | Se sim → | Se não → |
|----------|----------|----------|
| Tem AD + RADIUS ou pode instalar agente no DC? | Candidato Y | Avaliar só X ou adiar Y |
| Precisa exactidão por posto (PC)? | Explicar limite rede; reopen endpoint só com GO | Y adequado |
| Precisa MITM HTTPS? | Roadmap diferido; não fechar como Y+ entregue | Y sem MITM |
| Upgrade a partir de 1.9.8? | Pacote novo + reemissão `base,identity` | — |

---

## Referências

| Documento | Uso |
|-----------|-----|
| [`../00-overview/posicionamento-pme-identity-first.md`](../00-overview/posicionamento-pme-identity-first.md) | Nicho e barra UX |
| [`../10-license-server/MANUAL-USO-LICENCAS.md`](../10-license-server/MANUAL-USO-LICENCAS.md) §14 | Emissão SKU |
| [`../10-license-server/MANUAL-INSTALL.md`](../10-license-server/MANUAL-INSTALL.md) | Pacote + secção Identity |
| [`LAYER7-EVALUATION-PACK-PT.md`](LAYER7-EVALUATION-PACK-PT.md) | Pack avaliação (base) |
| ADR-0025 / 0027 / 0029 | Contratos técnicos |

English summary: [`LAYER7-IDENTITY-ADDON-NOTES-EN.md`](LAYER7-IDENTITY-ADDON-NOTES-EN.md).
