/*
 * Parse de entitlements `features` (ADR-0025 P1–P6, T1).
 */

#include "features.h"

#include <ctype.h>
#include <string.h>

static void
feat_tolower_inplace(char *s)
{
	unsigned char c;

	if (s == NULL)
		return;
	for (; *s != '\0'; s++) {
		c = (unsigned char)*s;
		*s = (char)tolower(c);
	}
}

static char *
feat_trim(char *s)
{
	char *end;

	if (s == NULL)
		return s;
	while (*s != '\0' && isspace((unsigned char)*s))
		s++;
	if (*s == '\0')
		return s;
	end = s + strlen(s) - 1;
	while (end > s && isspace((unsigned char)*end)) {
		*end = '\0';
		end--;
	}
	return s;
}

static void
feat_build_normalized(struct l7_features *out)
{
	size_t n = 0;

	out->normalized[0] = '\0';
	if (out->flags & L7_FEAT_BASE) {
		memcpy(out->normalized, "base", 4);
		n = 4;
	}
	if (out->flags & L7_FEAT_IDENTITY) {
		if (n > 0 && n < sizeof(out->normalized) - 1)
			out->normalized[n++] = ',';
		if (n + 8 < sizeof(out->normalized)) {
			memcpy(out->normalized + n, "identity", 8);
			n += 8;
		}
	}
	if (out->flags & L7_FEAT_MITM) {
		if (n > 0 && n < sizeof(out->normalized) - 1)
			out->normalized[n++] = ',';
		if (n + 4 < sizeof(out->normalized)) {
			memcpy(out->normalized + n, "mitm", 4);
			n += 4;
		}
	}
	out->normalized[n] = '\0';
}

int
layer7_features_parse(const char *raw, struct l7_features *out)
{
	char buf[L7_FEATURES_MAX + 1];
	char *save = NULL;
	char *tok;
	size_t len;
	int truncated = 0;

	if (out == NULL)
		return 0;

	memset(out, 0, sizeof(*out));
	out->flags = L7_FEAT_BASE; /* fail-open no base (P4) */

	if (raw == NULL) {
		feat_build_normalized(out);
		return 0;
	}

	len = strlen(raw);
	if (len > L7_FEATURES_MAX) {
		truncated = 1;
		len = L7_FEATURES_MAX;
	}
	out->truncated = truncated;
	memcpy(out->raw, raw, len);
	out->raw[len] = '\0';

	memcpy(buf, out->raw, len + 1);

	/* CSV vazio / só espaços → base (T1 / P4) */
	{
		char *probe = feat_trim(buf);

		if (probe[0] == '\0') {
			feat_build_normalized(out);
			return truncated;
		}
	}

	/* Re-copiar: feat_trim pode alterar trailing spaces; usar out->raw */
	memcpy(buf, out->raw, len + 1);

	for (tok = strtok_r(buf, ",", &save); tok != NULL;
	    tok = strtok_r(NULL, ",", &save)) {
		char *t = feat_trim(tok);

		if (t[0] == '\0')
			continue;
		feat_tolower_inplace(t);

		if (strcmp(t, "full") == 0) {
			/* T1: legado full → base apenas (não concede add-on) */
			continue;
		}
		if (strcmp(t, "base") == 0) {
			out->flags |= L7_FEAT_BASE;
			continue;
		}
		if (strcmp(t, "identity") == 0) {
			out->flags |= L7_FEAT_IDENTITY;
			continue;
		}
		if (strcmp(t, "mitm") == 0) {
			out->flags |= L7_FEAT_MITM;
			continue;
		}
		/* P3: token desconhecido — ignorar */
	}

	/*
	 * Base está sempre presente. Identity/MITM só se tokens explícitos.
	 * "full" sozinho (ou com desconhecidos) deixa só BASE — T1.
	 */
	out->flags |= L7_FEAT_BASE;
	feat_build_normalized(out);
	return truncated;
}

int
layer7_features_has(const struct l7_features *f, unsigned flag)
{
	if (f == NULL || flag == 0)
		return 0;
	return (f->flags & flag) != 0 ? 1 : 0;
}
