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
			$dur_mode = isset($_POST["mitm_duration_mode"])
			    ? (string)$_POST["mitm_duration_mode"] : "timed";
			if ($dur_mode === "until_off") {
				$mitm["window"]["max_minutes"] = 0;
			} else {
				$mitm["window"]["max_minutes"] = isset($_POST["mitm_max_window"])
				    ? (int)$_POST["mitm_max_window"]
				    : (int)($mitm["window"]["max_minutes"] ?? 15);
			}
			$errs = layer7_mitm_validate($mitm);
			if (!empty($errs)) {
				$input_errors = array_merge($input_errors, $errs);
			}
			$mitm = layer7_mitm_prepare_window_on_save($prev_mitm, $mitm);
			$data = layer7_mitm_apply_to_config($data, $mitm);
			$tr = layer7_addon_disarm_unentitled($data);
			$data = $tr["data"];
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
					    "Nao foi possivel activar a inspeccao TLS. " .
					    "Por seguranca, a inspeccao ficou desligada. Verifique o certificado e tente de novo."
					);
				} else {
					layer7_filter_configure_safe();
					$eff = layer7_mitm_effective($mitm, true);
					$savemsg = $eff
					    ? l7_t("Configuracao gravada. A inspeccao TLS esta activa para os origens e destinos definidos.")
					    : l7_t(
						"Configuracao gravada. A inspeccao permanece desligada ate existirem " .
						"certificado, origens e destinos validos (e o add-on na licenca)."
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

if (!empty($input_errors)) {
	print_input_errors($input_errors);
}
if ($savemsg !== "") {
	print_info_box($savemsg, "success");
}
?>
<?php layer7_render_tabs("mitm"); ?>

<div id="l7-mitm-root">

<div class="panel panel-default" id="l7-mitm-header">
	<div class="panel-heading">
		<h2 class="panel-title"><?= htmlspecialchars(l7_t("Layer 7 - MITM / Inspecao TLS")); ?></h2>
	</div>
	<div class="panel-body">
		<p class="help-block">
			<?= htmlspecialchars(l7_t(
			    "Inspecao TLS (MITM) e um add-on opcional: o firewall pode abrir HTTPS " .
			    "para aplicar bloqueios com pagina segura. Esta desligada por defeito e so " .
			    "funciona com o add-on na licenca, um certificado de inspeccao instalado e " .
			    "origens e destinos definidos. Sem este add-on, o bloqueio HTTPS continua " .
			    "pela pagina HTTP e pelo DNS."
			)); ?>
		</p>
	</div>
</div>

<?php if (!$unlocked): ?>
<div class="panel panel-default" id="l7-mitm-locked">
	<div class="panel-heading">
		<h2 class="panel-title"><?= htmlspecialchars(l7_t("Estado do add-on")); ?></h2>
	</div>
	<div class="panel-body">
		<div class="alert alert-info" role="alert">
			<strong><?= htmlspecialchars(l7_t("Add-on nao incluido nesta licenca")); ?></strong>
			<p class="help-block">
				<?= htmlspecialchars(l7_t(
				    "A inspecao TLS (MITM) nao faz parte desta licenca. " .
				    "O produto base continua a bloquear sites pela pagina HTTP e pelo DNS. " .
				    "Para activar a inspeccao HTTPS, contacte a Systemup."
				)); ?>
			</p>
		</div>
	</div>
</div>
<?php else: ?>

<div class="panel panel-default" id="l7-mitm-status">
	<div class="panel-heading">
		<h2 class="panel-title"><?= htmlspecialchars(l7_t("Estado do add-on")); ?></h2>
	</div>
	<div class="panel-body">
		<div class="alert <?= $effective ? "alert-success" : "alert-info"; ?>" role="alert">
			<strong><?= htmlspecialchars($effective
			    ? l7_t("Inspeccao TLS ligada")
			    : l7_t("Inspeccao TLS desligada")); ?></strong>
			<p class="help-block">
				<?= htmlspecialchars($runtime_ok
				    ? l7_t("Incluida nesta licenca. Liga com certificado, origens e destinos definidos — como uma politica, nao um formulario em papel.")
				    : l7_t("O motor de inspeccao nao esta disponivel neste appliance — inspecao desligada.")); ?>
			</p>
		</div>
		<table class="table table-condensed">
			<tr><th><?= htmlspecialchars(l7_t("Pedido do operador")); ?></th><td><?= !empty($mitm["enabled"]) ? htmlspecialchars(l7_t("Ligar")) : htmlspecialchars(l7_t("Desligar")); ?></td></tr>
			<tr><th><?= htmlspecialchars(l7_t("Estado real")); ?></th><td><?= $effective ? htmlspecialchars(l7_t("Activa")) : htmlspecialchars(l7_t("Inactiva")); ?></td></tr>
			<tr><th><?= htmlspecialchars(l7_t("Origens")); ?></th><td><?= htmlspecialchars(implode(", ", $win_status["source_cidr"] ?? array()) ?: "—"); ?></td></tr>
			<tr><th><?= htmlspecialchars(l7_t("Destinos")); ?></th><td><?= htmlspecialchars(implode(", ", $win_status["dest_cidr"] ?? array()) ?: "—"); ?></td></tr>
			<tr><th><?= htmlspecialchars(l7_t("Sites com pagina HTTPS")); ?></th><td><?= htmlspecialchars(implode(", ", $win_status["block_sni"] ?? array()) ?: "—"); ?></td></tr>
			<tr><th><?= htmlspecialchars(l7_t("QUIC / HTTP3")); ?></th><td><?= htmlspecialchars($win_status["quic_mode"] ?? ($mitm["quic_mode"] ?? "bypass")); ?></td></tr>
			<tr><th><?= htmlspecialchars(l7_t("Duracao")); ?></th><td><?php
				if (!empty($win_status["until_off"])) {
					echo htmlspecialchars(l7_t("Ate desligar"));
				} else {
					echo htmlspecialchars((int)($win_status["max_minutes"] ?? 15) . " " . l7_t("minutos"));
				}
			?></td></tr>
			<tr><th><?= htmlspecialchars(l7_t("tempo restante")); ?></th><td><?php
				if (!empty($win_status["until_off"])) {
					echo htmlspecialchars(l7_t("Sem limite de tempo"));
				} else {
					$rs = (int)($win_status["remaining_sec"] ?? 0);
					if ($rs <= 0) {
						echo !empty($win_status["expired"])
						    ? htmlspecialchars(l7_t("Expirada / desligada"))
						    : "—";
					} else {
						echo htmlspecialchars(sprintf("%dm %ds", intdiv($rs, 60), $rs % 60));
					}
				}
			?></td></tr>
			<tr><th><?= htmlspecialchars(l7_t("Supervisor automatico")); ?></th><td><?php
				if (!empty($sup_status["armed"])) {
					echo htmlspecialchars(l7_t("A vigiar"));
				} else {
					echo htmlspecialchars(l7_t("Ainda nao reportou"));
				}
			?></td></tr>
		</table>
<?php if ($effective || !empty($mitm["enabled"])): ?>
		<form method="post" id="l7-mitm-break-glass-form">
			<button type="submit" name="mitm_break_glass" value="1" class="btn btn-danger btn-sm"
				onclick="return confirm(<?= json_encode(l7_t("Desligar a inspeccao TLS agora e limpar o redireccionamento?")); ?>);">
				<i class="fa fa-exclamation-triangle"></i> <?= htmlspecialchars(l7_t("Desligar agora")); ?>
			</button>
		</form>
<?php endif; ?>
	</div>
</div>

<div class="panel panel-default" id="l7-mitm-ca">
	<div class="panel-heading">
		<h2 class="panel-title"><?= htmlspecialchars(l7_t("Autoridade de certificacao (CA)")); ?></h2>
	</div>
	<div class="panel-body">
		<p class="help-block">
			<?= htmlspecialchars(l7_t(
			    "A chave privada fica so neste firewall e nunca e exportada. " .
			    "Exporte so o certificado (.crt) e distribua-o aos PCs (GPO ou instalacao manual)."
			)); ?>
		</p>
<?php if (!empty($mitm["ca"]["present"])) { ?>
		<table class="table table-condensed">
			<tr><th><?= htmlspecialchars(l7_t("Subject")); ?></th><td><code><?= htmlspecialchars($mitm["ca"]["subject"]); ?></code></td></tr>
			<tr><th><?= htmlspecialchars(l7_t("Fingerprint SHA-256")); ?></th><td><code><?= htmlspecialchars($mitm["ca"]["fingerprint_sha256"]); ?></code></td></tr>
			<tr><th><?= htmlspecialchars(l7_t("Valido ate")); ?></th><td><?= htmlspecialchars($mitm["ca"]["not_after"]); ?></td></tr>
		</table>
		<form method="post" id="l7-mitm-ca-actions-form">
			<button type="submit" name="mitm_ca_export" value="1" class="btn btn-default btn-sm">
				<i class="fa fa-download"></i> <?= htmlspecialchars(l7_t("Exportar certificado (.crt)")); ?>
			</button>
			<button type="submit" name="mitm_ca_delete" value="1" class="btn btn-danger btn-sm"
				onclick="return confirm(<?= json_encode(l7_t("Remover a CA deste appliance? Os clientes deixam de confiar neste certificado.")); ?>);">
				<i class="fa fa-trash"></i> <?= htmlspecialchars(l7_t("Remover CA")); ?>
			</button>
		</form>
<?php } else { ?>
		<p class="text-muted"><?= htmlspecialchars(l7_t("Nenhuma CA instalada.")); ?></p>
<?php } ?>

		<form method="post" class="form-horizontal" id="l7-mitm-ca-generate-form">
			<div class="form-group">
				<label class="col-sm-3 control-label" for="l7m-ca-cn"><?= htmlspecialchars(l7_t("CN da CA")); ?></label>
				<div class="col-sm-6">
					<input type="text" name="ca_cn" id="l7m-ca-cn" class="form-control" maxlength="64"
						value="<?= htmlspecialchars($mitm["ca"]["cn"] ?? "Layer7 MITM CA"); ?>" />
				</div>
				<div class="col-sm-3">
					<button type="submit" name="mitm_ca_generate" value="1" class="btn btn-primary">
						<?= htmlspecialchars(l7_t("Gerar CA")); ?>
					</button>
				</div>
			</div>
		</form>

		<details id="l7-mitm-ca-import">
			<summary><?= htmlspecialchars(l7_t("Importar CA (PEM)")); ?></summary>
			<form method="post" class="form-horizontal" id="l7-mitm-ca-import-form">
				<div class="form-group">
					<label class="col-sm-3 control-label" for="l7m-ca-cert-pem"><?= htmlspecialchars(l7_t("Certificado PEM")); ?></label>
					<div class="col-sm-9">
						<textarea name="ca_cert_pem" id="l7m-ca-cert-pem" class="form-control" rows="6" placeholder="-----BEGIN CERTIFICATE-----"></textarea>
					</div>
				</div>
				<div class="form-group">
					<label class="col-sm-3 control-label" for="l7m-ca-key-pem"><?= htmlspecialchars(l7_t("Chave privada PEM")); ?></label>
					<div class="col-sm-9">
						<textarea name="ca_key_pem" id="l7m-ca-key-pem" class="form-control" rows="6" placeholder="-----BEGIN PRIVATE KEY-----"></textarea>
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

<div class="panel panel-default" id="l7-mitm-inspection">
	<div class="panel-heading">
		<h2 class="panel-title"><?= htmlspecialchars(l7_t("Inspecao e bypass")); ?></h2>
	</div>
	<div class="panel-body">
		<form method="post" class="form-horizontal" id="l7-mitm-form">
			<input type="hidden" name="ca_cn" value="<?= htmlspecialchars($mitm["ca"]["cn"] ?? "Layer7 MITM CA"); ?>" />
			<div class="form-group">
				<label class="col-sm-3 control-label" for="l7m-mitm-enabled"><?= htmlspecialchars(l7_t("Activar inspeccao TLS")); ?></label>
				<div class="col-sm-9">
					<div class="checkbox">
						<label>
							<input type="checkbox" name="mitm_enabled" id="l7m-mitm-enabled" value="1"
								<?= !empty($mitm["enabled"]) ? 'checked="checked"' : ""; ?>
								<?= $toggle_ok ? "" : 'disabled="disabled"'; ?> />
							<?= htmlspecialchars(l7_t("Ligar a inspeccao para as origens e destinos abaixo")); ?>
						</label>
					</div>
<?php if (!$ca_ok): ?>
					<p class="help-block">
						<?= htmlspecialchars(l7_t("Instale uma CA antes de activar a intencao.")); ?>
					</p>
<?php elseif (!$runtime_ok): ?>
					<p class="help-block">
						<?= htmlspecialchars(l7_t(
						    "Pode gravar agora. Sem o motor de inspeccao instalado, a inspeccao permanece desligada. " .
						    "Uma actualizacao do pacote nunca liga a inspeccao por defeito."
						)); ?>
					</p>
<?php elseif (!$effective): ?>
					<p class="help-block">
						<?= htmlspecialchars(l7_t(
						    "O motor de inspeccao esta pronto. A inspeccao so liga com certificado, " .
						    "origens e destinos IPv4 definidos (nao use «qualquer destino»)."
						)); ?>
					</p>
<?php endif; ?>
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-3 control-label"><?= htmlspecialchars(l7_t("Duracao")); ?></label>
				<div class="col-sm-9">
<?php
$until_off_ui = ((int)($mitm["window"]["max_minutes"] ?? 15) === 0);
$win_ui = $until_off_ui ? 15 : (int)($mitm["window"]["max_minutes"] ?? 15);
?>
					<div class="radio">
						<label>
							<input type="radio" name="mitm_duration_mode" value="until_off"
								<?= $until_off_ui ? 'checked="checked"' : ""; ?> />
							<?= htmlspecialchars(l7_t("Manter ligada ate eu desligar")); ?>
						</label>
					</div>
					<div class="radio">
						<label>
							<input type="radio" name="mitm_duration_mode" value="timed"
								<?= $until_off_ui ? "" : 'checked="checked"'; ?> />
							<?= htmlspecialchars(l7_t("Desligar automaticamente apos")); ?>
						</label>
					</div>
					<input type="number" name="mitm_max_window" class="form-control" min="1" max="240"
						value="<?= $win_ui; ?>" />
					<p class="help-block"><?= htmlspecialchars(l7_t(
					    "O modo temporizado desliga sozinho (1 a 240 minutos). " .
					    "Pode sempre desligar agora. Uma actualizacao do pacote nunca liga a inspeccao sozinha."
					)); ?></p>
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-3 control-label" for="l7m-intercept-source"><?= htmlspecialchars(l7_t("Origens (obrigatorio)")); ?></label>
				<div class="col-sm-9">
					<textarea name="intercept_source_cidr" id="l7m-intercept-source" class="form-control" rows="3"
						placeholder="192.168.100.24/32"><?= htmlspecialchars(implode("\n", $mitm["intercept"]["source_cidr"] ?? array())); ?></textarea>
					<p class="help-block"><?= htmlspecialchars(l7_t(
					    "Quem e inspeccionado: IP ou rede IPv4 (ex.: 192.168.100.24/32). " .
					    "Vazio = ninguem. Obrigatorio em conjunto com Destinos. " .
					    "Nao use «qualquer origem» nem a Internet inteira."
					)); ?></p>
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-3 control-label" for="l7m-intercept-dest"><?= htmlspecialchars(l7_t("Destinos (obrigatorio)")); ?></label>
				<div class="col-sm-9">
					<textarea name="intercept_dest_cidr" id="l7m-intercept-dest" class="form-control" rows="3"
						placeholder="203.0.113.10&#10;198.51.100.0/24"><?= htmlspecialchars(implode("\n", $mitm["intercept"]["dest_cidr"] ?? array())); ?></textarea>
					<p class="help-block"><?= htmlspecialchars(l7_t(
					    "Para onde o HTTPS e inspeccionado (IP ou rede IPv4). " .
					    "Vazio = ninguem. Nao use a Internet inteira. " .
					    "Os enderecos deste firewall ficam de fora."
					)); ?></p>
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-3 control-label" for="l7m-intercept-block-sni"><?= htmlspecialchars(l7_t("Sites a bloquear (HTTPS)")); ?></label>
				<div class="col-sm-9">
					<textarea name="intercept_block_sni" id="l7m-intercept-block-sni" class="form-control" rows="3"
						placeholder="blocked.example"><?= htmlspecialchars(implode("\n", $mitm["intercept"]["block_sni"] ?? array())); ?></textarea>
					<p class="help-block"><?= htmlspecialchars(l7_t(
					    "Dominios que recebem a pagina de bloqueio em HTTPS. Nao e excepcao."
					)); ?></p>
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-3 control-label" for="l7m-quic-mode"><?= htmlspecialchars(l7_t("QUIC / HTTP3")); ?></label>
				<div class="col-sm-6">
					<select name="quic_mode" id="l7m-quic-mode" class="form-control">
<?php
$qmodes = array(
	"bypass" => l7_t("Nao forcar (o motor ainda pode bloquear QUIC no mesmo escopo)"),
	"block" => l7_t("Bloquear QUIC nestas origens e destinos (recomendado)"),
	"downgrade" => l7_t("Preferir TCP (igual a bloquear QUIC nesta versao)")
);
$qcur = $mitm["quic_mode"] ?? "bypass";
foreach ($qmodes as $qv => $ql) {
	$sel = ($qcur === $qv) ? ' selected="selected"' : "";
	echo '<option value="' . htmlspecialchars($qv) . '"' . $sel . '>' . htmlspecialchars($ql) . "</option>\n";
}
?>
					</select>
					<p class="help-block"><?= htmlspecialchars(l7_t("O bloqueio QUIC aplica-se so as origens e destinos desta pagina, para o browser cair em HTTPS normal. Desliga-se com a inspeccao.")); ?></p>
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-3 control-label" for="l7m-bypass-sni"><?= htmlspecialchars(l7_t("Excepcoes (dominios)")); ?></label>
				<div class="col-sm-9">
					<textarea name="bypass_sni" id="l7m-bypass-sni" class="form-control" rows="4"
						placeholder="banco.exemplo.pt&#10;login.microsoftonline.com"><?= htmlspecialchars(implode("\n", $mitm["bypass"]["sni"] ?? array())); ?></textarea>
					<p class="help-block"><?= htmlspecialchars(l7_t("Um host por linha. Hosts invalidos sao rejeitados. IPs usam o campo CIDR.")); ?></p>
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-3 control-label" for="l7m-bypass-cidr"><?= htmlspecialchars(l7_t("Excepcoes (IP ou rede)")); ?></label>
				<div class="col-sm-9">
					<textarea name="bypass_cidr" id="l7m-bypass-cidr" class="form-control" rows="3"
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

</div>
<p class="text-center text-muted">
	Layer7 para pfSense CE &mdash;
	<a href="https://www.systemup.inf.br" target="_blank">Systemup</a>
	Solu&ccedil;&atilde;o em Tecnologia
</p>
<?php require_once("foot.inc");
