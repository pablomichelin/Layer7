# layer7-tlsproxy — PoC-3 SNI policy + TLS lab (OpenSSL).
OUT ?= layer7-tlsproxy
CC ?= cc
CFLAGS ?= -O2 -Wall -Wextra -Werror
CPPFLAGS += -DLAYER7_TLSPROXY_VERSION=\"0.0.3-poc3\"
SRCS = main.c ipc.c tls_lab.c
LDLIBS ?= -lssl -lcrypto

.PHONY: all clean test test-tls-lab test-poc3

all: $(OUT)

$(OUT): $(SRCS) ipc.h tls_lab.h
	$(CC) $(CPPFLAGS) $(CFLAGS) -o $(OUT) $(SRCS) $(LDLIBS)

clean:
	rm -f $(OUT)

test: $(OUT)
	./$(OUT) --version
	./$(OUT) --health | grep -q '"intercept": false'
	./$(OUT) --health | grep -q '"mitm_effective_claim": false'
	./$(OUT) --health | grep -q '"sni_policy": true'
	@rc=0; ./$(OUT) --lab-allow-bind >/dev/null 2>&1 || rc=$$?; test $$rc -eq 3
	@rc=0; LAYER7_TLSPROXY_LAB=1 ./$(OUT) --lab-allow-bind >/dev/null 2>&1 || rc=$$?; test $$rc -eq 4
	@rc=0; ./$(OUT) --ipc-serve >/dev/null 2>&1 || rc=$$?; test $$rc -eq 3
	@rc=0; LAYER7_TLSPROXY_LAB=1 ./$(OUT) --ipc-serve --sock /var/run/layer7/mitm.sock >/dev/null 2>&1 || rc=$$?; test $$rc -eq 3
	@SOCK=./.poc-test.$$$$.sock; \
	  LAYER7_TLSPROXY_LAB=1 ./$(OUT) --ipc-serve --sock $$SOCK --oneshot & \
	  SPID=$$!; \
	  i=0; \
	  while [ ! -S $$SOCK ] && [ $$i -lt 50 ]; do sleep 0.05; i=$$((i+1)); done; \
	  if [ ! -S $$SOCK ]; then echo "serve failed"; wait $$SPID; exit 1; fi; \
	  LAYER7_TLSPROXY_LAB=1 ./$(OUT) --ipc-ping --sock $$SOCK; \
	  RC=$$?; wait $$SPID 2>/dev/null || true; rm -f $$SOCK; test $$RC -eq 0
	@rc=0; LAYER7_TLSPROXY_LAB=1 ./$(OUT) --lab-tls-listen 0.0.0.0:8443 --cert /x --key /y >/dev/null 2>&1 || rc=$$?; test $$rc -eq 3
	@echo "PoC gate tests PASS"

test-tls-lab: $(OUT)
	@test -f lab-certs/server.crt -a -f lab-certs/server.key
	@LAYER7_TLSPROXY_LAB=1 ./$(OUT) --lab-tls-listen 127.0.0.1:8443 \
	  --cert lab-certs/server.crt --key lab-certs/server.key --oneshot & \
	  SPID=$$!; sleep 0.3; \
	  curl -fsSk https://127.0.0.1:8443/ | grep -q '"mitm_effective":false'; \
	  RC=$$?; wait $$SPID 2>/dev/null || true; test $$RC -eq 0
	@echo "PoC-2 TLS oneshot PASS"

# S3 block page + S4 bypass via SNI (curl --resolve)
test-poc3: $(OUT)
	@test -f lab-certs/server.crt -a -f lab-certs/server.key
	@LAYER7_TLSPROXY_LAB=1 ./$(OUT) --lab-tls-listen 127.0.0.1:8443 \
	  --cert lab-certs/server.crt --key lab-certs/server.key \
	  --block-sni blocked.test --bypass-sni bank.example >/tmp/poc3-srv.log 2>&1 & \
	  SPID=$$!; sleep 0.4; \
	  BODY=$$(curl -sSk --resolve blocked.test:8443:127.0.0.1 https://blocked.test:8443/); \
	  echo "$$BODY" | grep -q 'Acesso bloqueado'; R1=$$?; \
	  BODY=$$(curl -sSk --resolve bank.example:8443:127.0.0.1 https://bank.example:8443/); \
	  echo "$$BODY" | grep -q '"verdict":"bypass"'; R2=$$?; \
	  BODY=$$(curl -sSk --resolve other.test:8443:127.0.0.1 https://other.test:8443/); \
	  echo "$$BODY" | grep -q '"verdict":"allow"'; R3=$$?; \
	  kill $$SPID 2>/dev/null || true; wait $$SPID 2>/dev/null || true; \
	  test $$R1 -eq 0 -a $$R2 -eq 0 -a $$R3 -eq 0
	@echo "PoC-3 S3/S4 lab PASS"
