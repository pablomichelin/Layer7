/*
 * 30.16 / BG-122 / A-02 — decisão de enforce distribuída (sem ofuscação).
 *
 * Dois gates independentes devem concordar. Discordância ⇒ monitor (N2).
 * Root ainda pode patchar o binário (R-A); o objectivo é eliminar o NOP
 * trivial do if único em refresh_enforce_cfg().
 */

#include "license.h"

#include <stddef.h>

int
layer7_license_gate_a(const struct l7_license_info *li)
{
	if (!li)
		return 0;
	return li->valid ? 1 : 0;
}

int
layer7_license_gate_b(const struct l7_license_info *li)
{
	if (!li)
		return 0;
	/* Exige material de licença parseado — ficheiro ausente não passa. */
	if (li->expiry[0] == '\0')
		return 0;
	if (li->clock_suspect)
		return 0;
	if (li->expired)
		return li->grace ? 1 : 0;
	return 1;
}

int
layer7_license_allows_enforce(const struct l7_license_info *li)
{
	int a = layer7_license_gate_a(li);
	int b = layer7_license_gate_b(li);

	if (a != b)
		return 0;
	return a;
}
