/*
 * PoC-2 lab TLS listen — isolated VM only (.54).
 * Requires LAYER7_TLSPROXY_LAB=1. Default bind 127.0.0.1.
 * Never claims mitm_effective=true. No PF redirect.
 */
#ifndef LAYER7_TLSPROXY_TLS_H
#define LAYER7_TLSPROXY_TLS_H

void l7_tls_set_allow_any(int v);

int l7_tls_lab_listen(const char *bind_host, int bind_port,
    const char *cert_path, const char *key_path, int oneshot);

#endif
