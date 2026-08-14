#!/bin/bash
# Fecha P4: veredicto + docs SSOT + commit/push. Chamado após rollback.
set -u
EV="${EV:-/Users/pablomichelin/Documents/Layer 7/docs/tests/evidence/20260813T170000Z-p4-retry-254}"
REPO="/Users/pablomichelin/Documents/Layer 7"
OUTCOME="${1:-FAIL}"   # PASS | FAIL | ABORT
REASON="${2:-unspecified}"
UTC=$(date -u +%Y-%m-%dT%H:%M:%SZ)
LOCK="$EV/.finalize.lock"
if [ -f "$LOCK" ]; then
  echo "FINALIZE_ALREADY_DONE $(cat "$LOCK")"
  exit 0
fi
echo "$UTC $OUTCOME" >"$LOCK"

RB_OK=0
grep -q 'rollback_clean=1' "$EV/90-rollback-summary.txt" 2>/dev/null && RB_OK=1
[ -f "$EV/90-rollback-ok.txt" ] && RB_OK=1

MITM_OFF=0
if grep -qE 'mitm_effective=false' "$EV/93-post-state.txt" 2>/dev/null \
  && grep -qE 'LISTEN8443=0' "$EV/93-post-state.txt" 2>/dev/null; then
  MITM_OFF=1
fi

# Veredicto honesto
VERDICT="$OUTCOME"
if [ "$OUTCOME" = "PASS" ] && { [ "$RB_OK" != "1" ] || [ "$MITM_OFF" != "1" ]; }; then
  VERDICT="FAIL"
  REASON="$REASON; rollback_or_post_state incomplete"
fi
if [ "$OUTCOME" = "ABORT" ]; then
  VERDICT="FAIL"
fi

HEALTH_N=$(ls "$EV"/07-health-[0-9][0-9].txt 2>/dev/null | wc -l | tr -d ' ')
ABORT_N=$(ls "$EV"/11-ABORT*.txt 2>/dev/null | wc -l | tr -d ' ')

cat >"$EV/11-VERDICT.txt" <<EOF
P4_SOAK_VERDICT=$VERDICT
closed_utc=$UTC
outcome_raw=$OUTCOME
reason=$REASON
health_samples=$HEALTH_N
abort_files=$ABORT_N
rollback_clean=$RB_OK
mitm_off_verified=$MITM_OFF
scope=src=192.168.100.24/32 dst=198.18.0.10/32 SNI=mitm-lab.test max_window=240
external_dest=none
phaseC_internal=PASS (resume)
notes=Skip example.com ≠ abort; soak lab-only; P5 externo ainda proibido
EOF

cat >"$EV/11-STATUS.txt" <<EOF
status=CLOSED_$VERDICT
closed_utc=$UTC
rollback_now=DONE
mitm_should_be=OFF
EOF

cat >"$EV/README.md" <<EOF
# Evidência P4 soak — \`20260813T170000Z-p4-retry-254\`

**Estado: CLOSED — $VERDICT** (\`$UTC\`).

## Resultado
- Veredicto: **$VERDICT**
- Motivo: $REASON
- Health samples: $HEALTH_N
- Rollback limpo: $RB_OK
- MITM OFF verificado: $MITM_OFF

## Escopo GO (respeitado)
- Upgrade \`.254\` \`1.9.46\` → \`1.9.47\`
- MITM scoped: src \`192.168.100.24/32\` → dst \`198.18.0.10/32\` (\`.54\`), SNI \`mitm-lab.test\`
- CA efémera, \`max_window=240\`, \`quic_mode=block\`, sem payload TLS
- Sem \`.234\`/\`.235\`, sem destinos externos, sem \`from any\`

## Nota Skip
Aprovação nativa **Skip** = só recusa de \`example.com\` como negativo; **não** abortou o P4.

## Artefactos
- Health: \`07-health-*.txt\`, \`07-soak-loop.log\`
- Rollback: \`90-rollback-*.txt\`, \`91-rollback-24.txt\`, \`92-rollback-54.txt\`, \`93-post-state.txt\`
- Veredicto: \`11-VERDICT.txt\`
EOF

# Actualizar SSOT mínimo (estado P4 fechado; P5 continua proibido)
python3 - <<'PY'
from pathlib import Path
repo = Path("/Users/pablomichelin/Documents/Layer 7")
ev = repo / "docs/tests/evidence/20260813T170000Z-p4-retry-254"
verdict = "FAIL"
for line in (ev / "11-VERDICT.txt").read_text().splitlines():
    if line.startswith("P4_SOAK_VERDICT="):
        verdict = line.split("=", 1)[1].strip()
        break
reason = ""
for line in (ev / "11-VERDICT.txt").read_text().splitlines():
    if line.startswith("reason="):
        reason = line.split("=", 1)[1].strip()
        break
tag = f"**P4 CLOSED {verdict}**"
repls = [
    (repo / "CORTEX.md", [
        ("**P4 soak IN_PROGRESS**", f"**P4 CLOSED {verdict}**"),
        ("P4 soak IN_PROGRESS", f"P4 CLOSED {verdict}"),
        ("**SOAK_IN_PROGRESS** (Phase C interna PASS; Skip≠abort)", f"**CLOSED {verdict}** ({reason[:80]})"),
        ("SOAK_IN_PROGRESS", f"CLOSED {verdict}"),
        ("concluir soak P4 + rollback limpo", "P4 fechado; P5 só com ficha"),
        ("Appliance `.254`: **`1.9.47`** — MITM ON scoped lab (P4); sem piloto externo",
         "Appliance `.254`: **`1.9.47`** MONITOR / MITM OFF (pós-P4); sem piloto externo"),
    ]),
    (repo / "docs/00-overview/START-HERE-identity-mitm.md", [
        ("**P4 soak IN_PROGRESS**", f"**P4 CLOSED {verdict}**"),
        ("P4 soak IN_PROGRESS", f"P4 CLOSED {verdict}"),
        ("**SOAK_IN_PROGRESS**", f"**CLOSED {verdict}**"),
        ("SOAK_IN_PROGRESS", f"CLOSED {verdict}"),
        ("Concluir soak P4 → rollback limpo; depois **P5 só com ficha**",
         f"P4 CLOSED {verdict}; **P5 só com ficha**; **proibido** piloto externo/permanente"),
        ("concluir soak P4 + rollback limpo; P5 só com ficha; sem piloto externo/permanente",
         f"P4 CLOSED {verdict}; P5 só com ficha; sem piloto externo/permanente"),
        ("**`1.9.47`** — **P4 soak IN_PROGRESS** (MITM ON scoped lab); permanente **NO-GO**",
         f"**`1.9.47`** MONITOR/MITM OFF (pós-P4 {verdict}); permanente **NO-GO**"),
        ("rev. do plano | **`2026-08-09av`**", "rev. do plano | **`2026-08-09aw`**"),
        ("**`2026-08-09av`**", "**`2026-08-09aw`**"),
    ]),
]
# checklist
chk = repo / "docs/02-roadmap/checklist-mestre.md"
if chk.exists():
    t = chk.read_text()
    old = "- [ ] P4 soak lab — **IN_PROGRESS** `234042Z` (Phase C interna PASS; Skip≠abort; janela ~4h; rollback só no fecho)"
    new = f"- [{'x' if verdict=='PASS' else ' '}] P4 soak lab — **CLOSED {verdict}** `234042Z` ({reason[:60]})"
    if old in t:
        chk.write_text(t.replace(old, new))
for path, pairs in repls:
    if not path.exists():
        continue
    t = path.read_text()
    for a, b in pairs:
        t = t.replace(a, b)
    path.write_text(t)
print("docs_patched verdict=", verdict)
PY

cd "$REPO" || exit 1
git add \
  CORTEX.md \
  docs/00-overview/START-HERE-identity-mitm.md \
  docs/02-roadmap/checklist-mestre.md \
  docs/02-roadmap/backlog.md \
  docs/02-roadmap/plano-identity-mitm-addon.md \
  docs/09-blocking/mapa-prontidao-mitm-piloto-2026-08-09.md \
  docs/09-blocking/runbook-piloto-mitm-generico.md \
  docs/tests/evidence/20260813T170000Z-p4-retry-254/ 2>/dev/null || true

# backlog/mapa/plano best-effort textual
python3 - <<'PY'
from pathlib import Path
repo = Path("/Users/pablomichelin/Documents/Layer 7")
verdict = "FAIL"
for line in (repo/"docs/tests/evidence/20260813T170000Z-p4-retry-254/11-VERDICT.txt").read_text().splitlines():
    if line.startswith("P4_SOAK_VERDICT="):
        verdict = line.split("=",1)[1].strip(); break
for rel in [
    "docs/02-roadmap/backlog.md",
    "docs/02-roadmap/plano-identity-mitm-addon.md",
    "docs/09-blocking/mapa-prontidao-mitm-piloto-2026-08-09.md",
    "docs/09-blocking/runbook-piloto-mitm-generico.md",
]:
    p = repo/rel
    if not p.exists(): continue
    t = p.read_text()
    t2 = t.replace("P4 soak IN_PROGRESS", f"P4 CLOSED {verdict}")
    t2 = t2.replace("**P4 soak IN_PROGRESS**", f"**P4 CLOSED {verdict}**")
    t2 = t2.replace("SOAK_IN_PROGRESS", f"CLOSED {verdict}")
    t2 = t2.replace("2026-08-09av", "2026-08-09aw")
    if t2 != t:
        p.write_text(t2)
        print("patched", rel)
PY

git add -A docs/tests/evidence/20260813T170000Z-p4-retry-254/ \
  CORTEX.md \
  docs/00-overview/START-HERE-identity-mitm.md \
  docs/02-roadmap/checklist-mestre.md \
  docs/02-roadmap/backlog.md \
  docs/02-roadmap/plano-identity-mitm-addon.md \
  docs/09-blocking/mapa-prontidao-mitm-piloto-2026-08-09.md \
  docs/09-blocking/runbook-piloto-mitm-generico.md

echo "COMMIT_SKIP_BY_POLICY — finalize nao commita/push sozinho (P4.1 retry)"
# git commit/push desligados neste retry: o agente/operador fecha o bloco.
git status -sb | head -20
echo "FINALIZE_DONE $UTC $VERDICT" | tee "$EV/11-FINALIZE-DONE.txt"
