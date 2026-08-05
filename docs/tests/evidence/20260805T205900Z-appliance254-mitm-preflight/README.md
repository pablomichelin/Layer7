# Evidência — preflight MITM spike no appliance 192.168.100.254

- **Quando:** 2026-08-05 ~17:59 -03
- **Host:** systemupfw.system.up (pfSense Plus 26.03.1)
- **Pacote:** pfSense-pkg-layer7 **1.9.13**
- **Layer7:** enabled=false, mode=monitor (passivo)
- **Licença:** sem `/usr/local/etc/layer7.lic`
- **IM1 no binário:** NÃO (código 20.3–20.6 ainda só no git; sem release nova)
- **squid:** não instalado; disponível no repo (`pfSense-pkg-squid`, `squid-7.4`)
- **nginx:** instalado (1.28.0)
- **CPU baseline:** idle ~97% (vmstat); load ~0.9; 48 CPUs; ~16 GB RAM
- **Acção MITM:** nenhuma instalação/alteração de proxy nesta sessão (produção)

Veredicto spike 20.7: **ainda PENDENTE** — PoC exige instalar candidato (squid) em janela controlada.
