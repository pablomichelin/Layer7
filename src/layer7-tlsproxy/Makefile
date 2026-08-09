# layer7-tlsproxy — PoC-0 idle (no OpenSSL required).
# Uso: make && ./layer7-tlsproxy --health
#
# NÃO empacotar no pfSense-pkg-layer7 até GO de empacotamento.
# NÃO correr --lab-allow-bind em produção.

OUT ?= layer7-tlsproxy
CC ?= cc
CFLAGS ?= -O2 -Wall -Wextra -Werror
CPPFLAGS += -DLAYER7_TLSPROXY_VERSION=\"0.0.0-poc0\"

.PHONY: all clean test

all: $(OUT)

$(OUT): main.c
	$(CC) $(CPPFLAGS) $(CFLAGS) -o $(OUT) main.c

clean:
	rm -f $(OUT)

test: $(OUT)
	./$(OUT) --version
	./$(OUT) --health | grep -q '"intercept": false'
	./$(OUT) --lab-allow-bind >/dev/null 2>&1; test $$? -eq 3
	LAYER7_TLSPROXY_LAB=1 ./$(OUT) --lab-allow-bind >/dev/null 2>&1; test $$? -eq 4
	@echo "PoC-0 idle tests PASS"
