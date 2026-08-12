# Build standalone do layer7d (espelha flags do port pfSense-pkg-layer7).
# Uso: cd src/layer7d && make && ./layer7d -t -c ../../samples/config/layer7-minimal.json
#
# Variáveis:
#   OUT       — nome do binário (default: layer7d)
#   VSTR_DIR  — diretório que contém version.str para -I (default: .)
#   NDPI      — 0 para compilar sem nDPI (smoke tests CI), 1 com nDPI (default: auto)
#   L7_DEV_BUILD — se 1, adiciona -DL7_DEV_BUILD (lab only; ausente no port)
#
# Smoke: make OUT=layer7d-smoke VSTR_DIR=/tmp/l7vers NDPI=0
# Lab com bypass dev (NÃO usar em package): make L7_DEV_BUILD=1

LAYER7_COMMON = ../common
SRCS_BASE = main.c config_parse.c policy.c enforce.c license.c \
	license_enforce_gate.c features.c \
	blacklist.c bl_config.c allowlist.c log_store.c identity_map.c \
	identity_ldap.c identity_radius.c identity_dc.c
OUT ?= layer7d
VSTR_DIR ?= .
# identity_map + ldap + radius + dc (20.12 / 20.17 / 20.19 / 20.20) — pthread; OpenLDAP se detectado
PTHREAD_LDFLAGS = -lpthread
LDAP_DETECT != test -f /usr/local/include/ldap.h && echo 1 || echo 0
.if $(LDAP_DETECT) == 1
LDAP_CFLAGS = -DHAVE_OPENLDAP=1 -I/usr/local/include
LDAP_LDFLAGS = -L/usr/local/lib -lldap -llber
.else
LDAP_CFLAGS = -DHAVE_OPENLDAP=0
LDAP_LDFLAGS =
.endif

# Auto-detect nDPI: se o header existe, ativa
NDPI_DETECT != test -f /usr/local/include/ndpi/ndpi_api.h && echo 1 || echo 0
NDPI ?= $(NDPI_DETECT)

.if $(NDPI) == 1
SRCS = $(SRCS_BASE) capture.c
NDPI_CFLAGS = -I/usr/local/include/ndpi -DHAVE_NDPI=1
NDPI_LDFLAGS = /usr/local/lib/libndpi.a -lpcap -lm
.else
SRCS = $(SRCS_BASE)
NDPI_CFLAGS = -DHAVE_NDPI=0
NDPI_LDFLAGS =
.endif

.PHONY: all clean check

# Default: produção (sem bypass). Só lab: make L7_DEV_BUILD=1
L7_DEV_BUILD ?= 0
.if $(L7_DEV_BUILD) == 1
DEV_CFLAGS = -DL7_DEV_BUILD
.else
DEV_CFLAGS =
.endif

all: $(OUT)

$(OUT): $(SRCS)
	@test -f "$(VSTR_DIR)/version.str" || { \
		echo "layer7d Makefile: falta $(VSTR_DIR)/version.str"; exit 1; }
	@if [ "$(NDPI)" = "1" ] && [ ! -f /usr/local/lib/libndpi.a ]; then \
		echo "layer7d Makefile: falta /usr/local/lib/libndpi.a para gerar binário autocontido"; exit 1; \
	fi
	$(CC) $(CFLAGS) -Wall -Wextra -O2 -I$(VSTR_DIR) -I. -I$(LAYER7_COMMON) \
		$(NDPI_CFLAGS) $(LDAP_CFLAGS) $(DEV_CFLAGS) -o $(OUT) $(SRCS) $(NDPI_LDFLAGS) \
		$(PTHREAD_LDFLAGS) $(LDAP_LDFLAGS) -lssl -lcrypto

clean:
	rm -f layer7d layer7d-smoke

check: layer7d
	./layer7d -V >/dev/null
	./layer7d -t -c ../../samples/config/layer7-minimal.json | grep -q layer7d_version
