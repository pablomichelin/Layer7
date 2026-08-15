# layer7-tlsproxy — 20.10b product + PoC lab. Make recipes stay foreground.
OUT ?= layer7-tlsproxy
CC ?= cc
CFLAGS ?= -O2 -Wall -Wextra -Werror
CPPFLAGS += -DLAYER7_TLSPROXY_VERSION=\"0.1.3\"
SRCS = main.c ipc.c tls_lab.c
# Sobrescrevível pelo runner local quando o OpenSSL não vive no loader path
# padrão (por exemplo, Homebrew em macOS ARM). FreeBSD mantém o default.
OPENSSL_CFLAGS ?=
OPENSSL_LDFLAGS ?=
CPPFLAGS += $(OPENSSL_CFLAGS)
LDLIBS ?= $(OPENSSL_LDFLAGS) -lssl -lcrypto
STUB ?= ./poc-upstream-stub.py

.PHONY: all clean test test-regress test-poc3 test-poc4

all: $(OUT)

$(OUT): $(SRCS) ipc.h tls_lab.h
	$(CC) $(CPPFLAGS) $(CFLAGS) -o $(OUT) $(SRCS) $(LDLIBS)

clean:
	rm -f $(OUT) .l7-regress-bin

# Regressões próximas ao código (D1 leaf + política sem bypass).
test-regress:
	sh ./test-regress.sh

test: $(OUT)
	./$(OUT) --version
	./$(OUT) --health | grep -q '"intercept": false'
	./$(OUT) --health | grep -q '"intercept_ready": true'
	./$(OUT) --health | grep -q '"mitm_effective_claim": false'
	./$(OUT) --health | grep -q '"tls_product_capable": true'
	./$(OUT) --health | grep -q '"upstream_capable": true'
	./$(OUT) --health | grep -q '"transparent_capable": true'
	@rc=0; ./$(OUT) --lab-allow-bind >/dev/null 2>&1 || rc=$$?; test $$rc -eq 3
	@rc=0; LAYER7_TLSPROXY_LAB=1 ./$(OUT) --lab-allow-bind >/dev/null 2>&1 || rc=$$?; test $$rc -eq 4
	@rc=0; ./$(OUT) --ipc-serve >/dev/null 2>&1 || rc=$$?; test $$rc -eq 3
	@rc=0; LAYER7_TLSPROXY_LAB=1 ./$(OUT) --ipc-serve --sock /var/run/layer7/mitm.sock >/dev/null 2>&1 || rc=$$?; test $$rc -eq 3
	@SOCK=./.poc-test.$$$$.sock; \
	  LAYER7_TLSPROXY_LAB=1 ./$(OUT) --ipc-serve --sock $$SOCK --oneshot >/dev/null 2>&1 & \
	  SPID=$$!; \
	  i=0; while [ ! -S $$SOCK ] && [ $$i -lt 50 ]; do sleep 0.05; i=$$((i+1)); done; \
	  LAYER7_TLSPROXY_LAB=1 ./$(OUT) --ipc-ping --sock $$SOCK; RC=$$?; \
	  kill -9 $$SPID 2>/dev/null || true; wait $$SPID 2>/dev/null || true; rm -f $$SOCK; test $$RC -eq 0
	@rc=0; LAYER7_TLSPROXY_LAB=1 ./$(OUT) --lab-tls-listen 0.0.0.0:8443 --cert /x --key /y >/dev/null 2>&1 || rc=$$?; test $$rc -eq 3
	@rc=0; ./$(OUT) --lab-tls-listen 127.0.0.1:8443 --cert /x --key /y >/dev/null 2>&1 || rc=$$?; test $$rc -eq 3
	@rc=0; LAYER7_TLSPROXY_PRODUCT=1 ./$(OUT) --product-listen 0.0.0.0:8443 --cert /x --key /y >/dev/null 2>&1 || rc=$$?; test $$rc -eq 3
	@echo "PoC/product gate tests PASS"

# Entire TLS suite under one timeout — make sees a single foreground process.
test-poc3: $(OUT)
	@test -f lab-certs/server.crt
	timeout 25 sh ./run-poc-tests.sh poc3

test-poc4: $(OUT)
	@test -f lab-certs/server.crt
	@test -f $(STUB)
	STUB=$(STUB) timeout 25 sh ./run-poc-tests.sh poc4
