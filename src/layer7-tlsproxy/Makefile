# layer7-tlsproxy — PoC-1 IPC (no OpenSSL required).
# Uso: make && make test
#
# NÃO empacotar no pfSense-pkg-layer7 até GO de empacotamento.
# NÃO correr IPC/bind em produção (.254/.234/.235).

OUT ?= layer7-tlsproxy
CC ?= cc
CFLAGS ?= -O2 -Wall -Wextra -Werror
CPPFLAGS += -DLAYER7_TLSPROXY_VERSION=\"0.0.1-poc1\"
SRCS = main.c ipc.c

.PHONY: all clean test

all: $(OUT)

$(OUT): $(SRCS) ipc.h
	$(CC) $(CPPFLAGS) $(CFLAGS) -o $(OUT) $(SRCS)

clean:
	rm -f $(OUT)

test: $(OUT)
	./$(OUT) --version
	./$(OUT) --health | grep -q '"intercept": false'
	./$(OUT) --health | grep -q '"mitm_effective_claim": false'
	./$(OUT) --health | grep -q '"ipc_capable": true'
	./$(OUT) --lab-allow-bind >/dev/null 2>&1; test $$? -eq 3
	LAYER7_TLSPROXY_LAB=1 ./$(OUT) --lab-allow-bind >/dev/null 2>&1; test $$? -eq 4
	./$(OUT) --ipc-serve >/dev/null 2>&1; test $$? -eq 3
	./$(OUT) --ipc-ping >/dev/null 2>&1; test $$? -eq 3
	LAYER7_TLSPROXY_LAB=1 ./$(OUT) --ipc-serve --sock /var/run/layer7/mitm.sock >/dev/null 2>&1; test $$? -eq 3
	@SOCK=./.poc-test.$$$$.sock; \
	  LAYER7_TLSPROXY_LAB=1 ./$(OUT) --ipc-serve --sock $$SOCK --oneshot & \
	  SPID=$$!; \
	  i=0; \
	  while [ ! -S $$SOCK ] && [ $$i -lt 50 ]; do sleep 0.05; i=$$((i+1)); done; \
	  if [ ! -S $$SOCK ]; then echo "serve failed to create $$SOCK"; wait $$SPID; exit 1; fi; \
	  LAYER7_TLSPROXY_LAB=1 ./$(OUT) --ipc-ping --sock $$SOCK; \
	  RC=$$?; \
	  wait $$SPID 2>/dev/null || true; \
	  rm -f $$SOCK; \
	  test $$RC -eq 0
	@echo "PoC-1 IPC tests PASS"
