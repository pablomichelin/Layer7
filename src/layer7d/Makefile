# Build standalone do layer7d (espelha flags do port pfSense-pkg-layer7).
# Uso: cd src/layer7d && make && ./layer7d -t -c ../../samples/config/layer7-minimal.json
#
# Variáveis:
#   OUT       — nome do binário (default: layer7d)
#   VSTR_DIR  — diretório que contém version.str para -I (default: .)
#
# Smoke: make OUT=layer7d-smoke VSTR_DIR=/tmp/l7vers

LAYER7_COMMON = ../common
SRCS = main.c config_parse.c policy.c enforce.c
OUT ?= layer7d
VSTR_DIR ?= .

.PHONY: all clean check

all: $(OUT)

$(OUT): $(SRCS)
	@test -f "$(VSTR_DIR)/version.str" || { \
		echo "layer7d Makefile: falta $(VSTR_DIR)/version.str"; exit 1; }
	$(CC) $(CFLAGS) -Wall -Wextra -O2 -I$(VSTR_DIR) -I. -I$(LAYER7_COMMON) \
		-o $(OUT) $(SRCS)

clean:
	rm -f layer7d layer7d-smoke

check: layer7d
	./layer7d -V >/dev/null
	./layer7d -t -c ../../samples/config/layer7-minimal.json | grep -q layer7d_version
