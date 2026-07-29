/*
 * Parser mínimo para o objeto JSON "layer7" (campos no nível imediato
 * antes de "policies"). Adequado ao formato de samples/config/layer7-minimal.json.
 */
#ifndef LAYER7_CONFIG_PARSE_H
#define LAYER7_CONFIG_PARSE_H

#include <stddef.h>

#define L7_MAX_INTERFACES 8
#define L7_IFACE_NAME_LEN 32

struct layer7_parsed {
	int has_layer7;   /* 1 se encontrou chave "layer7" */
	int has_enabled;
	int enabled;      /* só válido se has_enabled */
	int has_mode;
	char mode[48];    /* ex.: monitor, enforce */
	int has_log_level;
	char log_level[24]; /* error, warn, info, debug */
	int has_syslog_remote;
	int syslog_remote;
	int has_syslog_remote_host;
	char syslog_remote_host[256];
	int has_syslog_remote_port;
	int syslog_remote_port; /* 1–65535; default 514 se remoto ativo */
	int has_debug_minutes;
	int debug_minutes; /* 0=cancela; 1–720 boost LOG_DEBUG até expirar */
	int has_log_file_max_mb;
	int log_file_max_mb; /* 1–100 MiB por ficheiro activo */
	int has_log_file_keep;
	int log_file_keep; /* 1–10 copias rotacionadas */
	int has_event_log_enabled;
	int event_log_enabled; /* eventos detalhados; bloqueios continuam auditados */
	int n_event_interfaces;
	char event_interfaces[L7_MAX_INTERFACES][L7_IFACE_NAME_LEN];
	int has_sni_inspection;
	int sni_inspection; /* Caminho A / A3: usar SNI (TLS) extraido pelo nDPI
	                     * como host para matching de politicas. Default off. */
	int has_enforcement_model;
	char enforcement_model[32]; /* Caminho B / E0: "legacy_global" (default)
	                             * ou "scoped_hybrid". So parse em E0; runtime
	                             * escopado activo a partir de E2/E3. */
	int n_interfaces;
	char interfaces[L7_MAX_INTERFACES][L7_IFACE_NAME_LEN];
	int has_protos_file;
	char protos_file[256];
};

/* json: conteúdo UTF-8; len: tamanho ou strlen se null-terminated com len=0 */
int layer7_parse_json(const char *json, size_t len, struct layer7_parsed *out);

#endif
