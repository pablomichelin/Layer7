/*
 * Layer7 license features CSV parse — ADR-0025 (P1–P6, transição T1).
 * Módulo puro (sem OpenSSL/FreeBSD) para testes unitários portáteis.
 *
 * Nome l7_features.h (não features.h): em Linux, -I src/layer7d + features.h
 * tapa <features.h> da glibc e parte o CI smoke-layer7d
 * (`__GLIBC_USE (…)` → missing binary operator).
 */
#ifndef LAYER7_FEATURES_H
#define LAYER7_FEATURES_H

#include <stddef.h>

#define L7_FEATURES_MAX     63  /* bytes úteis; buffer destino = 64 com NUL */
#define L7_FEAT_BASE        (1u << 0)
#define L7_FEAT_IDENTITY    (1u << 1)
#define L7_FEAT_MITM        (1u << 2)

struct l7_features {
	unsigned flags;              /* L7_FEAT_* */
	int      truncated;          /* 1 se input > L7_FEATURES_MAX */
	char     normalized[64];     /* CSV canónico: base[,identity][,mitm] */
	char     raw[64];            /* cópia truncada do input (após P1) */
};

/*
 * Parse CSV de features. Sempre preenche out (nunca falha de forma que
 * deixe o produto base sem L7_FEAT_BASE). Tokens desconhecidos ignorados.
 * Transição T1: "full" / vazio / só desconhecidos → base apenas.
 * Retorna 1 se truncou, 0 caso contrário.
 */
int layer7_features_parse(const char *raw, struct l7_features *out);

/* 1 se o bit está activo. */
int layer7_features_has(const struct l7_features *f, unsigned flag);

/*
 * Interseção ADR-0025: check-in só pode retirar add-ons (nunca acrescentar).
 * Resultado inclui sempre L7_FEAT_BASE.
 */
unsigned layer7_features_intersect(unsigned lic_flags, unsigned checkin_flags);

int layer7_features_allows_identity(unsigned flags);
int layer7_features_allows_mitm(unsigned flags);

/*
 * Runtime Identity: token no .lic NÃO liga o módulo.
 * Só entitled ∧ toggle do operador (identity.enabled).
 */
int layer7_features_identity_want(unsigned flags, int operator_enabled);

#endif /* LAYER7_FEATURES_H */
