/*
 * PoC-2/3/4/5 lab TLS — isolated VM (.54) only.
 * Requires LAYER7_TLSPROXY_LAB=1. Default bind 127.0.0.1.
 * Never claims mitm_effective=true. No payload to disk.
 */
#ifndef LAYER7_TLSPROXY_TLS_H
#define LAYER7_TLSPROXY_TLS_H

void l7_tls_set_allow_any(int v);
void l7_tls_set_upstream(const char *host, int port);
void l7_tls_set_transparent(int v);

int l7_tls_policy_add_bypass(const char *sni);
int l7_tls_policy_add_block(const char *sni);

int l7_tls_lab_listen(const char *bind_host, int bind_port,
    const char *cert_path, const char *key_path, int oneshot);

#endif
