/*
 * Layer7 pfSense — tipos compartilhados (Bloco 4)
 * Cabeçalho estável para daemon/classifier/policy; sem deps nDPI aqui.
 */
#ifndef LAYER7_TYPES_H
#define LAYER7_TYPES_H

#include <stdint.h>

enum layer7_mode {
	LAYER7_MODE_MONITOR = 0,
	LAYER7_MODE_ENFORCE = 1,
};

enum layer7_log_level {
	LAYER7_LOG_ERROR = 0,
	LAYER7_LOG_WARN = 1,
	LAYER7_LOG_INFO = 2,
	LAYER7_LOG_DEBUG = 3,
};

enum layer7_action {
	LAYER7_ACTION_ALLOW = 0,
	LAYER7_ACTION_BLOCK = 1,
	LAYER7_ACTION_MONITOR = 2,
	LAYER7_ACTION_TAG = 3,
};

enum layer7_event_type {
	LAYER7_EV_FLOW_CLASSIFIED = 1,
	LAYER7_EV_DAEMON_START = 2,
	LAYER7_EV_DAEMON_STOP = 3,
	LAYER7_EV_CONFIG_RELOAD = 4,
	LAYER7_EV_CONFIG_RELOAD_ERR = 5,
	LAYER7_EV_POLICY_MATCH = 6,
	LAYER7_EV_ENFORCE_BLOCK = 7,
	LAYER7_EV_ENFORCE_TAG = 8,
};

enum layer7_class_confidence {
	LAYER7_CONF_DETECTED = 0,
	LAYER7_CONF_GUESSED = 1,
	LAYER7_CONF_UNKNOWN = 2,
};

#endif /* LAYER7_TYPES_H */
