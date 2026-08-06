# Evidência — preflight MITM spike no appliance 192.168.100.254

- **Quando:** 2026-08-05 ~17:59 -03
- **Host:** systemupfw.system.up (pfSense Plus 26.03.1)
- **Pacote:** pfSense-pkg-layer7 **1.9.13**
- **Layer7:** enabled=false, mode=monitor (passivo)
- **Licença:** sem `/usr/local/etc/layer7.lic`
- **IM1 no binário:** NÃO (código 20.3–20.6 ainda só no git; sem release nova nesta data)
- **squid:** não instalado; disponível no repo pkg — **depois rejeitado como caminho de produto**
- **nginx:** instalado (1.28.0)
- **CPU baseline:** idle ~97% (vmstat); load ~0.9; 48 CPUs; ~16 GB RAM
- **Acção MITM:** nenhuma instalação/alteração de proxy nesta sessão (produção)

## Veredicto do spike (actualizado)

| Campo | Valor |
|-------|-------|
| Preflight | **PASS** (só-leitura; evidência desta pasta) |
| Spike 20.7 / 20.7a | **DEFER formal** (`2026-08-06`) — **não** instalar Squid |
| SSOT do veredicto | [`../../../09-blocking/spike-mitm-20.7.md`](../../../09-blocking/spike-mitm-20.7.md) |
| ADR | [`../../../03-adr/ADR-0026-mitm-tls-inspection-opt-in.md`](../../../03-adr/ADR-0026-mitm-tls-inspection-opt-in.md) — implementação diferida |
| Próximo da trilha | IM3 / 20.12 (mapa daemon) — ver START-HERE |

**Não usar** esta evidência para justificar PoC Squid. O preflight ficou histórico; a decisão de produto é DEFER + Identity-first PME.
