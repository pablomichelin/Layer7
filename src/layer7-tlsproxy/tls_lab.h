/*
 * TLS listen — lab (LAYER7_TLSPROXY_LAB=1) ou produto (LAYER7_TLSPROXY_PRODUCT=1).
 * Produto: bind só loopback. Nunca afirma mitm_effective=true. Sem payload em disco.
 */
#ifndef LAYER7_TLSPROXY_TLS_H
#define LAYER7_TLSPROXY_TLS_H

void l7_tls_set_allow_any(int v);
void l7_tls_set_upstream(const char *host, int port);
void l7_tls_set_transparent(int v);
void l7_tls_set_product(int v);
int l7_tls_product_ok(void);

int l7_tls_policy_add_bypass(const char *sni);
int l7_tls_policy_add_block(const char *sni);

int l7_tls_lab_listen(const char *bind_host, int bind_port,
    const char *cert_path, const char *key_path, int oneshot);

#endif
