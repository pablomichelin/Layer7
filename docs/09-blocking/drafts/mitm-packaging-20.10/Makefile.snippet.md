# Makefile snippet — DRAFT (não mergeado)

Flag proposta no `package/pfSense-pkg-layer7/Makefile` **após** GO produto:

```make
# MITM helper — default OFF até GO produto (prep-20.10).
# Sem GO: manter ausente; não definir yes em builds públicos.
WITH_LAYER7_TLSPROXY?=	no

LAYER7_TLSPROXY_DIR=	${.CURDIR}/../../src/layer7-tlsproxy

.if ${WITH_LAYER7_TLSPROXY:tl} == yes
.if !exists(${LAYER7_TLSPROXY_DIR}/main.c)
.error "WITH_LAYER7_TLSPROXY=yes exige fontes em ${LAYER7_TLSPROXY_DIR}"
.endif
# do-build: compilar layer7-tlsproxy (produto; sem LAYER7_TLSPROXY_LAB gate de PoC)
# do-install: INSTALL_PROGRAM + rc.d; NÃO sysrc enable
.endif
```

## Regras

1. Default **`no`** — builds públicos actuais (`1.9.38`) não incluem o binário.
2. `pkg-install` **não** corre `sysrc layer7_tlsproxy_enable=YES`.
3. Mesmo com `WITH_LAYER7_TLSPROXY=yes`, rcvar permanece `NO` até operador + entitlement + GO.
4. `mitm_runtime_available` no `layer7d` só muda para `true` no mesmo bloco 20.10 (não neste draft).
