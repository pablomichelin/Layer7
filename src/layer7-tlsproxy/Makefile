# layer7-tlsproxy — PoC-4. Make recipes stay foreground (no hang on &).
OUT ?= layer7-tlsproxy
CC ?= cc
CFLAGS ?= -O2 -Wall -Wextra -Werror
CPPFLAGS += -DLAYER7_TLSPROXY_VERSION=\"0.0.4-poc4\"
SRCS = main.c ipc.c tls_lab.c
LDLIBS ?= -lssl -lcrypto
STUB ?= ./poc-upstream-stub.py

.PHONY: all clean test test-poc3 test-poc4

all: $(OUT)

$(OUT): $(SRCS) ipc.h tls_lab.h
	$(CC) $(CPPFLAGS) $(CFLAGS) -o $(OUT) $(SRCS) $(LDLIBS)

clean:
	rm -f $(OUT)

test: $(OUT)
	./$(OUT) --version
	./$(OUT) --health | grep -q '"intercept": false'
	./$(OUT) --health | grep -q '"mitm_effective_claim": false'
	./$(OUT) --health | grep -q '"upstream_capable": true'
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
	@echo "PoC gate tests PASS"

# Entire TLS suite under one timeout — make sees a single foreground process.
test-poc3: $(OUT)
	@test -f lab-certs/server.crt
	timeout 25 sh ./run-poc-tests.sh poc3

test-poc4: $(OUT)
	@test -f lab-certs/server.crt
	@test -f $(STUB)
	STUB=$(STUB) timeout 25 sh ./run-poc-tests.sh poc4
