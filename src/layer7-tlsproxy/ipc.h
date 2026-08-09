/*
 * PoC-1 IPC — unix stream, length-prefixed JSON (≤4 KiB).
 * Lab-only. Never claims mitm_effective=true.
 */
#ifndef LAYER7_TLSPROXY_IPC_H
#define LAYER7_TLSPROXY_IPC_H

#include <stddef.h>

#define L7_IPC_MAX_BODY 4096
#define L7_IPC_DEFAULT_SOCK "/tmp/layer7-tlsproxy-poc.sock"

int l7_ipc_path_allowed(const char *path);
int l7_ipc_lab_ok(void);

/* Serve until SIGINT/SIGTERM or one-shot if oneshot!=0. Returns 0 on clean stop. */
int l7_ipc_serve(const char *sock_path, int oneshot);

/* Client: send PING, expect ok:true and mitm_effective:false. */
int l7_ipc_ping(const char *sock_path);

#endif
