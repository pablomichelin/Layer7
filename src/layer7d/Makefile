# Build standalone do layer7d (espelha flags do port pfSense-pkg-layer7).
# Uso: cd src/layer7d && make && ./layer7d -t -c ../../samples/config/layer7-minimal.json
#
# Variáveis:
#   OUT       — nome do binário (default: layer7d)
#   VSTR_DIR  — diretório que contém version.str para -I (default: .)
#   NDPI      — 0 para compilar sem nDPI (smoke tests CI), 1 com nDPI (default: auto)
#
# Smoke: make OUT=layer7d-smoke VSTR_DIR=/tmp/l7vers NDPI=0

LAYER7_COMMON = ../common
SRCS_BASE = main.c config_parse.c policy.c enforce.c
OUT ?= layer7d
VSTR_DIR ?= .

# Auto-detect nDPI: se o header existe, ativa
NDPI_DETECT != test -f /usr/local/include/ndpi/ndpi_api.h && echo 1 || echo 0
NDPI ?= $(NDPI_DETECT)

.if $(NDPI) == 1
SRCS = $(SRCS_BASE) capture.c
NDPI_CFLAGS = -I/usr/local/include/ndpi -DHAVE_NDPI=1
NDPI_LDFLAGS = /usr/local/lib/libndpi.a -lpcap -lm -lpthread
.else
SRCS = $(SRCS_BASE)
NDPI_CFLAGS = -DHAVE_NDPI=0
NDPI_LDFLAGS =
.endif

.PHONY: all clean check

all: $(OUT)

$(OUT): $(SRCS)
	@test -f "$(VSTR_DIR)/version.str" || { \
		echo "layer7d Makefile: falta $(VSTR_DIR)/version.str"; exit 1; }
	@if [ "$(NDPI)" = "1" ] && [ ! -f /usr/local/lib/libndpi.a ]; then \
		echo "layer7d Makefile: falta /usr/local/lib/libndpi.a para gerar binário autocontido"; exit 1; \
	fi
	$(CC) $(CFLAGS) -Wall -Wextra -O2 -I$(VSTR_DIR) -I. -I$(LAYER7_COMMON) \
		$(NDPI_CFLAGS) -o $(OUT) $(SRCS) $(NDPI_LDFLAGS)

clean:
	rm -f layer7d layer7d-smoke

check: layer7d
	./layer7d -V >/dev/null
	./layer7d -t -c ../../samples/config/layer7-minimal.json | grep -q layer7d_version
