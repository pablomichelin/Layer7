#!/usr/bin/env bash
set -euo pipefail

usage() {
  cat <<'EOF'
Uso:
  init-f3-validation-campaign.sh [opcoes]

Opcoes:
  --run-id <id>         Identificador da campanha. Default: UTC timestamp.
  --output-root <dir>   Directorio raiz das evidencias.
  --help                Mostra esta ajuda.
EOF
}

SCRIPT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
REPO_ROOT="$(CDPATH= cd -- "$SCRIPT_DIR/../.." && pwd)"
SCENARIO_TEMPLATE="$REPO_ROOT/docs/tests/templates/f3-scenario-evidence.md"
REPORT_TEMPLATE="$REPO_ROOT/docs/tests/templates/f3-validation-campaign-report.md"
F310_RUNBOOK="$REPO_ROOT/docs/01-architecture/f3-runbook-proxima-campanha-real.md"

RUN_ID="$(date -u +%Y%m%dT%H%M%SZ)"
OUTPUT_ROOT="${TMPDIR:-/tmp}/layer7-f3-evidence"
SCENARIOS=(S01 S02 S03 S04 S05 S06 S07 S08 S09 S10 S11 S12 S13)

while [[ $# -gt 0 ]]; do
  case "$1" in
    --run-id)
      RUN_ID="${2:-}"
      shift 2
      ;;
    --output-root)
      OUTPUT_ROOT="${2:-}"
      shift 2
      ;;
    --help|-h)
      usage
      exit 0
      ;;
    *)
      echo "[init-f3-validation-campaign] argumento desconhecido: $1" >&2
      usage >&2
      exit 1
      ;;
  esac
done

CAMPAIGN_DIR="${OUTPUT_ROOT%/}/${RUN_ID}"
mkdir -p "$CAMPAIGN_DIR"

cat > "$CAMPAIGN_DIR/00-campaign-manifest.txt" <<EOF
run_id=$RUN_ID
generated_at_utc=$(date -u +%Y-%m-%dT%H:%M:%SZ)
output_root=$OUTPUT_ROOT
repo_root=$REPO_ROOT
report_template=$REPORT_TEMPLATE
scenario_template=$SCENARIO_TEMPLATE
preflight_runbook=$F310_RUNBOOK
scenarios=$(printf '%s ' "${SCENARIOS[@]}" | sed 's/ $//')
EOF

cat > "$CAMPAIGN_DIR/10-preflight-deploy.txt" <<EOF
run_id=$RUN_ID
generated_at_utc=$(date -u +%Y-%m-%dT%H:%M:%SZ)
artefacto=preflight_deploy
estado=preencher_antes_de_S01
minimo_esperado=repo_commit_ou_ref, host_observado, origin_observado, prova_de_que_o_deploy_nao_e_desconhecido
EOF

cat > "$CAMPAIGN_DIR/20-preflight-schema.txt" <<EOF
run_id=$RUN_ID
generated_at_utc=$(date -u +%Y-%m-%dT%H:%M:%SZ)
artefacto=preflight_schema
estado=preencher_antes_de_S01
minimo_esperado=licenses, activations_log, admin_audit_log, admin_sessions, admin_login_guards
EOF

cat > "$CAMPAIGN_DIR/30-preflight-admin.txt" <<EOF
run_id=$RUN_ID
generated_at_utc=$(date -u +%Y-%m-%dT%H:%M:%SZ)
artefacto=preflight_admin
estado=preencher_antes_de_S01
minimo_esperado=resultado_do_login, escopo_autorizado, limites_de_uso_da_credencial
EOF

cat > "$CAMPAIGN_DIR/40-preflight-appliance.txt" <<EOF
run_id=$RUN_ID
generated_at_utc=$(date -u +%Y-%m-%dT%H:%M:%SZ)
artefacto=preflight_appliance
estado=preencher_antes_de_S01
minimo_esperado=hostname, date_utc, layer7d_status, kern.hostuuid, fingerprint, estado_do_.lic, stats_json_inicial
EOF

cat > "$CAMPAIGN_DIR/50-preflight-inventory.md" <<EOF
# Preflight Inventory

run_id: $RUN_ID
generated_at_utc: $(date -u +%Y-%m-%dT%H:%M:%SZ)
estado: preencher_antes_de_S01

| Alias | license_id | license_key | Cenario(s) | Appliance alvo | Estado esperado |
|-------|------------|-------------|------------|----------------|-----------------|
| LIC-A |            |             | S01, S02   |                | activa sem bind |
| LIC-B |            |             | S03, S04   |                | activa bindada |
| LIC-C |            |             | S05, S06   |                | activa bindada |
| LIC-D |            |             | S07, S08   |                | expirada / grace |
| LIC-E |            |             | S09, S11   |                | activa -> revogada |
| LIC-F |            |             | S12, S13   |                | controlo de grace / drift |
EOF

if [[ -f "$REPORT_TEMPLATE" && ! -f "$CAMPAIGN_DIR/f3-validation-campaign-report.md" ]]; then
  cp "$REPORT_TEMPLATE" "$CAMPAIGN_DIR/f3-validation-campaign-report.md"
fi

for scenario_code in "${SCENARIOS[@]}"; do
  scenario_dir="$CAMPAIGN_DIR/$scenario_code"
  mkdir -p "$scenario_dir"

  cat > "$scenario_dir/00-manifest.txt" <<EOF
run_id=$RUN_ID
scenario_code=$scenario_code
generated_at_utc=$(date -u +%Y-%m-%dT%H:%M:%SZ)
EOF

  if [[ -f "$SCENARIO_TEMPLATE" && ! -f "$scenario_dir/01-operator-notes.md" ]]; then
    cp "$SCENARIO_TEMPLATE" "$scenario_dir/01-operator-notes.md"
  fi
done

echo "[init-f3-validation-campaign] campanha preparada em: $CAMPAIGN_DIR"
echo "[init-f3-validation-campaign] relatorio final: $CAMPAIGN_DIR/f3-validation-campaign-report.md"
echo "[init-f3-validation-campaign] preflight criado:"
echo "  - $CAMPAIGN_DIR/10-preflight-deploy.txt"
echo "  - $CAMPAIGN_DIR/20-preflight-schema.txt"
echo "  - $CAMPAIGN_DIR/30-preflight-admin.txt"
echo "  - $CAMPAIGN_DIR/40-preflight-appliance.txt"
echo "  - $CAMPAIGN_DIR/50-preflight-inventory.md"
echo "[init-f3-validation-campaign] cenarios preparados: ${SCENARIOS[*]}"
