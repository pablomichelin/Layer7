<?php
##|+PRIV
##|*IDENT=page-services-layer7-mitm
##|*NAME=Services: Layer 7 (MITM)
##|*DESCR=MITM TLS inspection add-on (entitlement gated; CA scaffolding; runtime OFF).
##|*MATCH=layer7_mitm.php*
##|-PRIV
/*
 * MITM / inspecao TLS (IM2 / 20.10b).
 * Intencao mitm.enabled vs mitm_effective (runtime+intercept_ready+CA+entitlement).
 * Chave CA fora do JSON. Squid rejeitado. OFF ≡ ADR-0017.
 */

require_once("guiconfig.inc");
require_once("/usr/local/pkg/layer7.inc");

/* Nao usar $ent: head.inc faz foreach ($ifentries as $ent => ...) e sobrescreve. */
$l7_ent = layer7_entitlements();
$unlocked = !empty($l7_ent["has_mitm"]);
$l7_feat_raw = isset($l7_ent["raw"]) ? (string)$l7_ent["raw"] : "";

$savemsg = "";
$input_errors = array();
$data = layer7_load_or_default();
/* P3: expirar janela no load (sobrevive sem depender só do save). */
if (function_exists("layer7_mitm_expire_if_needed")) {
	$ex = layer7_mitm_expire_if_needed($data);
	if (!empty($ex["changed"])) {
		$data = $ex["data"];
		layer7_save_json($data);
		layer7_mitm_sync_helper($data, $unlocked);
		layer7_filter_configure_safe();
		$savemsg = l7_t("Janela MITM expirada — auto-disable fail-closed (mitm.enabled OFF).");
	}
}
$mitm = layer7_mitm_from_config($data);
$runtime_ok = layer7_mitm_runtime_available();
$effective = layer7_mitm_effective($mitm, $unlocked);
$ca_ok = !empty($mitm["ca"]["present"]);
$toggle_ok = $unlocked && $ca_ok;
$win_status = function_exists("layer7_mitm_window_status")
    ? layer7_mitm_window_status($mitm) : array();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
	if (!$unlocked) {
		$input_errors[] = l7_t("Add-on mitm nao incluido nesta licenca.");
	} else {
		if (isset($_POST["mitm_break_glass"])) {
			$data = layer7_mitm_failsafe_rollback($data, "break-glass operador");
			layer7_save_json($data);
			layer7_filter_configure_safe();
			$savemsg = l7_t("Break-glass: MITM OFF, control-plane limpo.");
			$mitm = layer7_mitm_from_config($data);
		} elseif (isset($_POST["mitm_save_bypass"])) {
			$prev_mitm = $mitm;
			$mitm["bypass"]["sni"] = isset($_POST["bypass_sni"]) ? (string)$_POST["bypass_sni"] : "";
			$mitm["bypass"]["cidr"] = isset($_POST["bypass_cidr"]) ? (string)$_POST["bypass_cidr"] : "";
			$mitm["intercept"]["source_cidr"] = isset($_POST["intercept_source_cidr"])
			    ? (string)$_POST["intercept_source_cidr"] : "";
			$mitm["intercept"]["dest_cidr"] = isset($_POST["intercept_dest_cidr"])
			    ? (string)$_POST["intercept_dest_cidr"] : "";
			$mitm["intercept"]["block_sni"] = isset($_POST["intercept_block_sni"])
			    ? (string)$_POST["intercept_block_sni"] : "";
			$mitm["enabled"] = !empty($_POST["mitm_enabled"]);
			$mitm["quic_mode"] = isset($_POST["quic_mode"]) ? (string)$_POST["quic_mode"] : "bypass";
			$mitm["ca"]["cn"] = isset($_POST["ca_cn"]) ? (string)$_POST["ca_cn"] : $mitm["ca"]["cn"];
			$mitm["window"]["max_minutes"] = isset($_POST["mitm_max_window"])
			    ? (int)$_POST["mitm_max_window"]
			    : (int)($mitm["window"]["max_minutes"] ?? 15);
			$errs = layer7_mitm_validate($mitm);
			if (!empty($errs)) {
				$input_errors = array_merge($input_errors, $errs);
			}
			$mitm = layer7_mitm_prepare_window_on_save($prev_mitm, $mitm);
			$data = layer7_mitm_apply_to_config($data, $mitm);
			if (empty($input_errors) && layer7_save_json($data)) {
				$want_helper = layer7_mitm_should_start_helper($mitm, true);
				$sync_ok = layer7_mitm_sync_helper($data, true);
				if ($want_helper && !$sync_ok) {
					/* Fail-safe: teardown + intenção OFF + filter sem rdr. */
					$data = layer7_mitm_failsafe_rollback($data,
					    "sync falhou apos save");
					layer7_save_json($data);
					layer7_filter_configure_safe();
					$input_errors[] = l7_t(
					    "Falha no control-plane MITM (tlsproxy timeout/erro). " .
					    "Rollback fail-safe: helper/gate/flag/tabelas limpos e mitm.enabled=OFF."
					);
				} else {
					layer7_filter_configure_safe();
					$eff = layer7_mitm_effective($mitm, true);
					$savemsg = $eff
					    ? l7_t("Configuracao MITM gravada (mitm_effective ON).") .
						" " . l7_t("Rdr selectivo activo (source_cidr E dest_cidr obrigatorios).")
					    : l7_t(
						"Configuracao MITM gravada. Intencao guardada; mitm_effective OFF " .
						"(gates incompletos — exige CA, entitlement, runtime, source+dest IPv4 e janela valida)."
					    );
				}
				$mitm = layer7_mitm_from_config($data);
			} elseif (empty($input_errors)) {
				$input_errors[] = l7_t("Falha ao gravar layer7.json.");
			}
		} elseif (isset($_POST["mitm_ca_generate"])) {
			$cn = isset($_POST["ca_cn"]) ? (string)$_POST["ca_cn"] : "Layer7 MITM CA";
			$r = layer7_mitm_ca_generate($cn, 3650);
			if (empty($r["ok"])) {
				$input_errors[] = $r["msg"];
			} else {
				$mitm["ca"]["cn"] = $cn;
				$data = layer7_mitm_apply_to_config($data, $mitm);
				layer7_save_json($data);
				$data = layer7_load_or_default();
				$mitm_after = layer7_mitm_from_config($data);
				$want_helper = layer7_mitm_should_start_helper($mitm_after, true);
				$sync_ok = layer7_mitm_sync_helper($data, true);
				if ($want_helper && !$sync_ok) {
					$data = layer7_mitm_failsafe_rollback($data,
					    "sync falhou apos CA generate");
					layer7_save_json($data);
					layer7_filter_configure_safe();
					$input_errors[] = l7_t(
					    "CA gerada, mas falha no control-plane MITM (tlsproxy). " .
					    "Rollback fail-safe: estado limpo (OFF)."
					);
				} else {
					layer7_filter_configure_safe();
					$savemsg = l7_t("CA gerada. Exporte o certificado e distribua via GPO — a chave nunca sai do appliance.");
				}
				$mitm = layer7_mitm_from_config($data);
			}
		} elseif (isset($_POST["mitm_ca_import"])) {
			$cert_pem = isset($_POST["ca_cert_pem"]) ? (string)$_POST["ca_cert_pem"] : "";
			$key_pem = isset($_POST["ca_key_pem"]) ? (string)$_POST["ca_key_pem"] : "";
			$r = layer7_mitm_ca_import($cert_pem, $key_pem);
			if (empty($r["ok"])) {
				$input_errors[] = $r["msg"];
			} else {
				$data = layer7_mitm_apply_to_config($data, $mitm);
				layer7_save_json($data);
				$data = layer7_load_or_default();
				$mitm_after = layer7_mitm_from_config($data);
				$want_helper = layer7_mitm_should_start_helper($mitm_after, true);
				$sync_ok = layer7_mitm_sync_helper($data, true);
				if ($want_helper && !$sync_ok) {
					$data = layer7_mitm_failsafe_rollback($data,
					    "sync falhou apos CA import");
					layer7_save_json($data);
					layer7_filter_configure_safe();
					$input_errors[] = l7_t(
					    "CA importada, mas falha no control-plane MITM (tlsproxy). " .
					    "Rollback fail-safe: estado limpo (OFF)."
					);
				} else {
					layer7_filter_configure_safe();
					$savemsg = l7_t("CA importada.");
				}
				$mitm = layer7_mitm_from_config($data);
			}
		} elseif (isset($_POST["mitm_ca_delete"])) {
			if (!layer7_mitm_ca_delete()) {
				$input_errors[] = l7_t("Falha ao remover ficheiros da CA.");
			} else {
				$data = layer7_mitm_apply_to_config($data, $mitm);
				layer7_save_json($data);
				$data = layer7_load_or_default();
				/* 1.9.41: derrubar helper/gate/rdr quando CA some (effective OFF). */
				layer7_mitm_sync_helper($data, true);
				layer7_filter_configure_safe();
				$savemsg = l7_t("CA removida do appliance. Helper MITM parado se estava activo.");
				$mitm = layer7_mitm_from_config($data);
			}
		} elseif (isset($_POST["mitm_ca_export"])) {
			$cert = layer7_mitm_ca_cert_path();
			if (!is_readable($cert)) {
				$input_errors[] = l7_t("Nenhuma CA para exportar.");
			} else {
				$pem = file_get_contents($cert);
				header("Content-Type: application/x-pem-file");
				header("Content-Disposition: attachment; filename=\"layer7-mitm-ca.crt\"");
				header("Content-Length: " . strlen($pem));
				echo $pem;
				exit;
			}
		}
	}
}

$mitm = layer7_mitm_from_config(layer7_load_or_default());
$runtime_ok = layer7_mitm_runtime_available();
$effective = layer7_mitm_effective($mitm, $unlocked);
$ca_ok = !empty($mitm["ca"]["present"]);
$toggle_ok = $unlocked && $ca_ok;
$win_status = function_exists("layer7_mitm_window_status")
    ? layer7_mitm_window_status($mitm) : array();
$sup_status = function_exists("layer7_mitm_window_supervisor_status")
    ? layer7_mitm_window_supervisor_status() : array();

$pgtitle = array(l7_t("Services"), l7_t("Layer 7"), l7_t("MITM"));
$pglinks = array("", "/packages/layer7/layer7_status.php", "@self");
include("head.inc");
layer7_render_styles();

if (!empty($input_errors)) {
	print_input_errors($input_errors);
}
if ($savemsg !== "") {
	print_info_box($savemsg, "success");
}
?>
<div class="panel panel-default layer7-page">
	<div class="panel-heading">
		<h2 class="panel-title"><?= htmlspecialchars(l7_t("Layer 7 - MITM / Inspecao TLS")); ?></h2>
	</div>
	<div class="panel-body">
		<?php layer7_render_tabs("mitm"); ?>
		<div class="layer7-content">
			<p class="layer7-lead">
				<?= htmlspecialchars(l7_t(
				    "1.9.47 P3: janela com max_window + deadline_unix; auto-disable fail-closed ao expirar " .
				    "(incluindo apos reload). mitm.enabled e intencao; mitm_effective exige " .
				    "entitlement+CA+runtime+source+dest+janela valida. Nunca from any. Squid rejeitado."
				)); ?>
			</p>

			<div class="layer7-admin-block">
				<div class="layer7-admin-block__header">
					<?= htmlspecialchars(l7_t("Estado do add-on")); ?>
				</div>
				<div class="layer7-admin-block__body">
<?php if (!$unlocked): ?>
					<div class="alert alert-info" role="alert" style="margin-bottom:0;">
						<strong><?= htmlspecialchars(l7_t("Add-on nao incluido nesta licenca")); ?></strong>
						<p style="margin: 10px 0 0;">
							<?= htmlspecialchars(l7_t(
							    "A inspecao TLS (MITM) com CA no dominio requer o entitlement \"mitm\". " .
							    "A licenca actual nao inclui este add-on. " .
							    "Sem MITM, o bloqueio HTTPS continua conforme ADR-0017 (DNS sinkhole / pagina HTTP). " .
							    "Contacte a Systemup para upgrade."
							)); ?>
						</p>
						<p style="margin: 10px 0 0; color: #666; font-size: 12px;">
							features=<?= htmlspecialchars($l7_feat_raw !== "" ? $l7_feat_raw : "(base / legado)"); ?>
							· ADR-0025 / ADR-0026 · default OFF
						</p>
					</div>
<?php else: ?>
					<div class="alert alert-success" role="alert" style="margin-bottom:12px;">
						<?= htmlspecialchars(l7_t("Entitlement mitm activo.")); ?>
						<?= htmlspecialchars($runtime_ok
						    ? l7_t("Runtime presente (20.10b). mitm_effective so com todos os gates.")
						    : l7_t("Runtime layer7-tlsproxy ausente — inspecao OFF.")); ?>
					</div>
					<table class="table table-condensed" style="max-width:720px; margin-bottom:12px;">
						<tr><th>mitm.enabled (intencao)</th><td><code><?= !empty($mitm["enabled"]) ? "true" : "false"; ?></code></td></tr>
						<tr><th>mitm_effective</th><td><code><?= $effective ? "true" : "false"; ?></code></td></tr>
						<tr><th>runtime</th><td><code><?= $runtime_ok ? "yes" : "no"; ?></code></td></tr>
						<tr><th>intercept_ready</th><td><code><?= layer7_mitm_intercept_ready() ? "true" : "false"; ?></code></td></tr>
						<tr><th>source_cidr</th><td><code><?= htmlspecialchars(implode(", ", $win_status["source_cidr"] ?? array())); ?></code></td></tr>
						<tr><th>dest_cidr</th><td><code><?= htmlspecialchars(implode(", ", $win_status["dest_cidr"] ?? array())); ?></code></td></tr>
						<tr><th>block_sni</th><td><code><?= htmlspecialchars(implode(", ", $win_status["block_sni"] ?? array())); ?></code></td></tr>
						<tr><th>quic_mode</th><td><code><?= htmlspecialchars($win_status["quic_mode"] ?? ($mitm["quic_mode"] ?? "bypass")); ?></code></td></tr>
						<tr><th>max_window (min)</th><td><code><?= (int)($win_status["max_minutes"] ?? 15); ?></code></td></tr>
						<tr><th>deadline_unix (UTC)</th><td><code><?php
							$dl = (int)($win_status["deadline_unix"] ?? 0);
							echo $dl > 0 ? htmlspecialchars(gmdate("Y-m-d\TH:i:s\Z", $dl) . " ({$dl})") : "0";
						?></code></td></tr>
						<tr><th>tempo restante</th><td><code><?php
							$rs = (int)($win_status["remaining_sec"] ?? 0);
							if ($rs <= 0) {
								echo !empty($win_status["expired"]) ? "expirado / OFF" : "0s";
							} else {
								echo htmlspecialchars(sprintf("%dm %ds", intdiv($rs, 60), $rs % 60));
							}
						?></code></td></tr>
						<tr><th><?= htmlspecialchars(l7_t("Supervisor on-box (P4.1)")); ?></th><td><code><?php
							if (!empty($sup_status["armed"])) {
								$ls = (int)($sup_status["last_unix"] ?? 0);
								echo htmlspecialchars(l7_t("armado") . ($ls > 0
								    ? " " . gmdate("Y-m-d\\TH:i:s\\Z", $ls)
								    : ""));
							} else {
								echo htmlspecialchars(l7_t("nao armado / stale"));
							}
						?></code></td></tr>
						<tr><th>features</th><td><code><?= htmlspecialchars($l7_feat_raw); ?></code></td></tr>
					</table>
<?php if ($effective || !empty($mitm["enabled"])): ?>
					<form method="post" style="margin-bottom:0;">
						<button type="submit" name="mitm_break_glass" value="1" class="btn btn-danger btn-sm"
							onclick="return confirm(<?= htmlspecialchars(json_encode(l7_t("Break-glass: desactivar MITM agora e limpar control-plane?")), ENT_QUOTES); ?>);">
							<i class="fa fa-exclamation-triangle"></i> <?= htmlspecialchars(l7_t("Break-glass (OFF imediato)")); ?>
						</button>
					</form>
<?php endif; ?>
<?php endif; ?>
				</div>
			</div>

<?php if ($unlocked): ?>
			<div class="layer7-admin-block">
				<div class="layer7-admin-block__header"><?= htmlspecialchars(l7_t("Autoridade de certificacao (CA)")); ?></div>
				<div class="layer7-admin-block__body">
					<p class="help-block">
						<?= htmlspecialchars(l7_t(
						    "A chave privada fica em /usr/local/etc/layer7/mitm/ (0600) e nunca no git nem em layer7.json. " .
						    "Exporte so o certificado (.crt) para GPO / trust store dos clientes."
						)); ?>
					</p>
<?php if (!empty($mitm["ca"]["present"])) { ?>
					<table class="table table-condensed" style="max-width:720px;">
						<tr><th><?= htmlspecialchars(l7_t("Subject")); ?></th><td><code><?= htmlspecialchars($mitm["ca"]["subject"]); ?></code></td></tr>
						<tr><th><?= htmlspecialchars(l7_t("Fingerprint SHA-256")); ?></th><td><code><?= htmlspecialchars($mitm["ca"]["fingerprint_sha256"]); ?></code></td></tr>
						<tr><th><?= htmlspecialchars(l7_t("Valido ate")); ?></th><td><?= htmlspecialchars($mitm["ca"]["not_after"]); ?></td></tr>
					</table>
					<form method="post" class="form-inline" style="margin-bottom:14px;">
						<button type="submit" name="mitm_ca_export" value="1" class="btn btn-default btn-sm">
							<i class="fa fa-download"></i> <?= htmlspecialchars(l7_t("Exportar certificado (.crt)")); ?>
						</button>
						<button type="submit" name="mitm_ca_delete" value="1" class="btn btn-danger btn-sm"
							onclick="return confirm(<?= htmlspecialchars(json_encode(l7_t("Remover a CA deste appliance? Os clientes deixam de confiar neste certificado.")), ENT_QUOTES); ?>);">
							<i class="fa fa-trash"></i> <?= htmlspecialchars(l7_t("Remover CA")); ?>
						</button>
					</form>
<?php } else { ?>
					<p class="text-muted"><?= htmlspecialchars(l7_t("Nenhuma CA instalada.")); ?></p>
<?php } ?>

					<form method="post" class="form-horizontal">
						<div class="form-group">
							<label class="col-sm-3 control-label"><?= htmlspecialchars(l7_t("CN da CA")); ?></label>
							<div class="col-sm-6">
								<input type="text" name="ca_cn" class="form-control" maxlength="64"
									value="<?= htmlspecialchars($mitm["ca"]["cn"] ?? "Layer7 MITM CA"); ?>" />
							</div>
							<div class="col-sm-3">
								<button type="submit" name="mitm_ca_generate" value="1" class="btn btn-primary">
									<?= htmlspecialchars(l7_t("Gerar CA")); ?>
								</button>
							</div>
						</div>
					</form>

					<details style="margin-top:12px;">
						<summary><?= htmlspecialchars(l7_t("Importar CA (PEM)")); ?></summary>
						<form method="post" class="form-horizontal" style="margin-top:10px;">
							<div class="form-group">
								<label class="col-sm-3 control-label"><?= htmlspecialchars(l7_t("Certificado PEM")); ?></label>
								<div class="col-sm-9">
									<textarea name="ca_cert_pem" class="form-control" rows="6" placeholder="-----BEGIN CERTIFICATE-----"></textarea>
								</div>
							</div>
							<div class="form-group">
								<label class="col-sm-3 control-label"><?= htmlspecialchars(l7_t("Chave privada PEM")); ?></label>
								<div class="col-sm-9">
									<textarea name="ca_key_pem" class="form-control" rows="6" placeholder="-----BEGIN PRIVATE KEY-----"></textarea>
									<p class="help-block"><?= htmlspecialchars(l7_t("A chave e gravada so no disco local (0600).")); ?></p>
								</div>
							</div>
							<div class="form-group">
								<div class="col-sm-offset-3 col-sm-9">
									<button type="submit" name="mitm_ca_import" value="1" class="btn btn-default">
										<?= htmlspecialchars(l7_t("Importar")); ?>
									</button>
								</div>
							</div>
						</form>
					</details>
				</div>
			</div>

			<div class="layer7-admin-block">
				<div class="layer7-admin-block__header"><?= htmlspecialchars(l7_t("Inspecao e bypass")); ?></div>
				<div class="layer7-admin-block__body">
					<form method="post" class="form-horizontal">
						<input type="hidden" name="ca_cn" value="<?= htmlspecialchars($mitm["ca"]["cn"] ?? "Layer7 MITM CA"); ?>" />
						<div class="form-group">
							<label class="col-sm-3 control-label"><?= htmlspecialchars(l7_t("mitm.enabled")); ?></label>
							<div class="col-sm-9">
								<label class="checkbox-inline">
									<input type="checkbox" name="mitm_enabled" value="1"
										<?= !empty($mitm["enabled"]) ? 'checked="checked"' : ""; ?>
										<?= $toggle_ok ? "" : 'disabled="disabled"'; ?> />
									<?= htmlspecialchars(l7_t("Intencao: activar inspecao TLS quando o runtime existir")); ?>
								</label>
<?php if (!$ca_ok): ?>
								<p class="help-block" style="margin-top:6px;">
									<?= htmlspecialchars(l7_t("Instale uma CA antes de activar a intencao.")); ?>
								</p>
<?php elseif (!$runtime_ok): ?>
								<p class="help-block" style="margin-top:6px;">
									<?= htmlspecialchars(l7_t(
									    "Pode gravar a intencao agora. Sem runtime, mitm_effective permanece false. " .
									    "Upgrades nunca ligam MITM por defeito."
									)); ?>
								</p>
<?php elseif (!$effective): ?>
								<p class="help-block" style="margin-top:6px;">
									<?= htmlspecialchars(l7_t(
									    "Runtime e intercept_ready presentes. mitm_effective exige intencao+CA+" .
									    "source_cidr E dest_cidr IPv4 explicitos (vazio/invalido/any = fail-closed)."
									)); ?>
								</p>
<?php endif; ?>
							</div>
						</div>
						<div class="form-group">
							<label class="col-sm-3 control-label"><?= htmlspecialchars(l7_t("max_window (minutos)")); ?></label>
							<div class="col-sm-3">
								<input type="number" name="mitm_max_window" class="form-control" min="1" max="240"
									value="<?= (int)($mitm["window"]["max_minutes"] ?? 15); ?>" />
								<p class="help-block"><?= htmlspecialchars(l7_t(
								    "P3: ao activar, deadline_unix = agora + max_window. " .
								    "Ao expirar, mitm_effective=false e mitm.enabled=OFF (fail-closed). " .
								    "1–240 min; default 15. Sem payload TLS em disco."
								)); ?></p>
							</div>
						</div>
						<div class="form-group">
							<label class="col-sm-3 control-label"><?= htmlspecialchars(l7_t("Origens rdr (obrigatorio)")); ?></label>
							<div class="col-sm-9">
								<textarea name="intercept_source_cidr" class="form-control" rows="3"
									placeholder="192.168.100.24/32"><?= htmlspecialchars(implode("\n", $mitm["intercept"]["source_cidr"] ?? array())); ?></textarea>
								<p class="help-block"><?= htmlspecialchars(l7_t(
								    "Quem pode ser interceptado: CIDR/IP IPv4 de origem (ex.: um PC de teste 192.168.100.24/32). " .
								    "Vazio = zero rdr — os outros clientes da LAN nao sao afectados. " .
								    "Obrigatorio em conjunto com Destinos. Proibido: any, 0.0.0.0/0, IPv6, prefixo </8 " .
								    "(sem from any / expansao silenciosa)."
								)); ?></p>
							</div>
						</div>
						<div class="form-group">
							<label class="col-sm-3 control-label"><?= htmlspecialchars(l7_t("Destinos rdr (obrigatorio)")); ?></label>
							<div class="col-sm-9">
								<textarea name="intercept_dest_cidr" class="form-control" rows="3"
									placeholder="203.0.113.10&#10;198.51.100.0/24"><?= htmlspecialchars(implode("\n", $mitm["intercept"]["dest_cidr"] ?? array())); ?></textarea>
								<p class="help-block"><?= htmlspecialchars(l7_t(
								    "Para onde o trafego e redireccionado (destino IPv4) → 127.0.0.1:8443. " .
								    "Vazio = zero rdr. Use um destino de teste dedicado — nao a Internet inteira. " .
								    "Sem rdr inet6 / ::1 / to any. IPs do proprio appliance / GUI sao excluidos."
								)); ?></p>
							</div>
						</div>
						<div class="form-group">
							<label class="col-sm-3 control-label"><?= htmlspecialchars(l7_t("Block SNI (pagina HTTPS)")); ?></label>
							<div class="col-sm-9">
								<textarea name="intercept_block_sni" class="form-control" rows="3"
									placeholder="blocked.example"><?= htmlspecialchars(implode("\n", $mitm["intercept"]["block_sni"] ?? array())); ?></textarea>
								<p class="help-block"><?= htmlspecialchars(l7_t(
								    "SNI que recebem a pagina HTML de bloqueio via helper. Nao e bypass."
								)); ?></p>
							</div>
						</div>
						<div class="form-group">
							<label class="col-sm-3 control-label"><?= htmlspecialchars(l7_t("QUIC / HTTP3")); ?></label>
							<div class="col-sm-6">
								<select name="quic_mode" class="form-control">
<?php
$qmodes = array(
	"bypass" => l7_t("Bypass (legado S5 — runtime ainda bloqueia UDP/443 no escopo MITM)"),
	"block" => l7_t("Bloquear QUIC no escopo MITM (recomendado)"),
	"downgrade" => l7_t("Preferir downgrade para TCP (igual a block no runtime actual)")
);
$qcur = $mitm["quic_mode"] ?? "bypass";
foreach ($qmodes as $qv => $ql) {
	$sel = ($qcur === $qv) ? ' selected="selected"' : "";
	echo '<option value="' . htmlspecialchars($qv) . '"' . $sel . '>' . htmlspecialchars($ql) . "</option>\n";
}
?>
								</select>
								<p class="help-block"><?= htmlspecialchars(l7_t("Com MITM efectivo o produto emite block UDP/443 apenas de layer7_mitm_src para layer7_mitm_dst (forca fallback TCP). Sem regra global/IPv6. Remove-se no rollback.")); ?></p>
							</div>
						</div>
						<div class="form-group">
							<label class="col-sm-3 control-label"><?= htmlspecialchars(l7_t("Bypass SNI")); ?></label>
							<div class="col-sm-9">
								<textarea name="bypass_sni" class="form-control" rows="4"
									placeholder="banco.exemplo.pt&#10;login.microsoftonline.com"><?= htmlspecialchars(implode("\n", $mitm["bypass"]["sni"] ?? array())); ?></textarea>
								<p class="help-block"><?= htmlspecialchars(l7_t("Um host por linha. Hosts invalidos sao rejeitados. IPs usam o campo CIDR.")); ?></p>
							</div>
						</div>
						<div class="form-group">
							<label class="col-sm-3 control-label"><?= htmlspecialchars(l7_t("Bypass CIDR/IP")); ?></label>
							<div class="col-sm-9">
								<textarea name="bypass_cidr" class="form-control" rows="3"
									placeholder="10.0.0.0/8&#10;192.168.1.50"><?php
									$show_cidr = array();
									foreach (($mitm["bypass"]["cidr"] ?? array()) as $c) {
										if ($c === "127.0.0.1/32" || $c === "::1/128") {
											continue; /* protegidos — mostrados abaixo */
										}
										$show_cidr[] = $c;
									}
									echo htmlspecialchars(implode("\n", $show_cidr));
								?></textarea>
								<p class="help-block">
									<?= htmlspecialchars(l7_t("Sempre em bypass (protegido): 127.0.0.1/32 e ::1/128.")); ?>
								</p>
							</div>
						</div>
						<div class="form-group">
							<div class="col-sm-offset-3 col-sm-9">
								<button type="submit" name="mitm_save_bypass" value="1" class="btn btn-success">
									<?= htmlspecialchars(l7_t("Gravar")); ?>
								</button>
							</div>
						</div>
					</form>
				</div>
			</div>
<?php endif; ?>

			<div class="layer7-admin-block">
				<div class="layer7-admin-block__header"><?= htmlspecialchars(l7_t("Proximos passos")); ?></div>
				<div class="layer7-admin-block__body">
					<ol style="margin:0; padding-left:18px;">
						<li><?= htmlspecialchars(l7_t("20.8–20.10a — CA / intencao / runtime OFF — PASS")); ?></li>
						<li><?= htmlspecialchars(l7_t("20.10b — Listen selectivo + PF rdr + pagina HTTPS (este bloco)")); ?></li>
						<li><?= htmlspecialchars(l7_t("20.11 — Lab GI2/GI3 (nao avancado neste bloco)")); ?></li>
					</ol>
					<p class="help-block" style="margin:10px 0 0;">
						<?= htmlspecialchars(l7_t("Contrato IPC: docs/01-architecture/contrato-ipc-layer7-tlsproxy-20.9.md — Squid rejeitado.")); ?>
					</p>
				</div>
			</div>
		</div>
	</div>
</div>
<?php
layer7_render_footer();
include("foot.inc");
