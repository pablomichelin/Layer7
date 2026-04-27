<?php
##|+PRIV
##|*IDENT=page-services-layer7-removal
##|*NAME=Services: Layer 7 (removal)
##|*DESCR=Remove the Layer 7 package completely from this firewall.
##|*MATCH=layer7_removal.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("/usr/local/pkg/layer7.inc");

global $input_errors, $savemsg;

$pkg_name = "pfSense-pkg-layer7";
$log_rm = "/tmp/layer7_pkg_rm.log";
$flag_started = "/tmp/layer7_pkg_rm_started";
$flag_keep_lic = "/var/run/layer7-uninstall-keep-license";
$flag_keep_cfg = "/var/run/layer7-uninstall-keep-config";

if ($_POST["layer7_pkg_remove_do"] ?? false) {
	$chk = trim((string)($_POST["layer7_remove_confirm"] ?? ""));
	if ($chk !== "REMOVER") {
		$input_errors[] = l7_t("Digite REMOVER na caixa de confirmacao.");
	} elseif (!file_exists("/usr/local/pkg/layer7.xml")) {
		$input_errors[] = l7_t("O pacote Layer7 nao parece estar instalado.");
	} else {
		@unlink($flag_keep_lic);
		@unlink($flag_keep_cfg);
		if (!empty($_POST["keep_config"])) {
			@touch($flag_keep_cfg);
		} elseif (!empty($_POST["keep_license"])) {
			@touch($flag_keep_lic);
		}
		@file_put_contents($log_rm, gmdate('c') . " GUI: pedido de remocao do pacote\n", FILE_APPEND);
		@touch($flag_started);
		$sh = <<'EOSH'
#!/bin/sh
set -eu
PATH=/sbin:/bin:/usr/sbin:/usr/bin:/usr/local/sbin:/usr/local/bin
export PATH
LOG=/tmp/layer7_pkg_rm.log
echo "$(date -u +%Y-%m-%dT%H:%M:%SZ) job: parar layer7d" >>"$LOG"
/usr/sbin/service layer7d onestop >>"$LOG" 2>&1 || true
if [ -x /usr/local/libexec/layer7-pfctl ]; then
	/usr/local/libexec/layer7-pfctl flush-all >>"$LOG" 2>&1 || true
fi
sleep 2
if /usr/sbin/pkg info -e pfSense-pkg-layer7 >>"$LOG" 2>&1; then
	echo "$(date -u +%Y-%m-%dT%H:%M:%SZ) job: pkg delete" >>"$LOG"
	/usr/sbin/pkg delete -y pfSense-pkg-layer7 >>"$LOG" 2>&1 || echo "pkg delete rc=$?" >>"$LOG"
else
	echo "$(date -u +%Y-%m-%dT%H:%M:%SZ) job: pacote ja ausente" >>"$LOG"
fi
rm -f /tmp/layer7_pkg_rm.sh /tmp/layer7_pkg_rm_started
echo "$(date -u +%Y-%m-%dT%H:%M:%SZ) job: fim" >>"$LOG"
EOSH;
		$script_path = "/tmp/layer7_pkg_rm.sh";
		@file_put_contents($script_path, $sh);
		@chmod($script_path, 0700);
		@pclose(@popen("/usr/bin/nohup /bin/sh " . escapeshellarg($script_path) .
		    " >/dev/null 2>&1 &", "r"));
		$savemsg = l7_t("Remocao iniciada. Aguarde ~30–60 s. O menu Layer7 deixara de existir. Verifique System > Package Manager ou o ficheiro de log. Recarregue esta pagina depois; se pedir login, e normal.");
	}
}

$pkg_installed = file_exists("/usr/local/pkg/layer7.xml");
$job_running = file_exists($flag_started);

$pgtitle = array(l7_t("Services"), l7_t("Layer 7"), l7_t("Remocao do pacote"));
include("head.inc");
layer7_render_styles();
?>
<div class="panel panel-default layer7-page">
	<div class="panel-heading">
		<h2 class="panel-title"><?= l7_t("Remocao completa do Layer7"); ?></h2>
	</div>
	<div class="panel-body">
		<?php layer7_render_tabs("removal"); ?>
		<div class="layer7-content">
		<?php layer7_render_messages(); ?>

		<div class="alert alert-danger">
			<strong><?= l7_t("Atencao"); ?>:</strong>
			<?= l7_t("Esta operacao remove o pacote pfSense-pkg-layer7, o daemon, a GUI Layer7, blacklists locais, cron e limpa as tabelas PF layer7_* . Equivalente a uma desinstalacao completa."); ?>
		</div>

		<p class="layer7-lead"><?= l7_t("Alternativa: System > Package Manager > Installed Packages > Remove (o hook do pacote tambem limpa residuos). Esta pagina permite o mesmo com opcoes de preservacao e arranque em segundo plano."); ?></p>

		<?php if (!$pkg_installed) { ?>
		<div class="alert alert-info"><?= l7_t("O pacote Layer7 nao esta instalado neste sistema (ou a remocao ja terminou)."); ?></div>
		<?php } elseif ($job_running) { ?>
		<div class="alert alert-warning">
			<?= l7_t("Um pedido de remocao foi iniciado recentemente. Aguarde e verifique o Package Manager. Log:"); ?>
			<code><?= htmlspecialchars($log_rm); ?></code>
		</div>
		<?php } else { ?>

		<form method="post" class="form-horizontal">
			<div class="form-group">
				<label class="col-sm-3 control-label"><?= l7_t("Preservar ficheiros"); ?></label>
				<div class="col-sm-9">
					<div class="checkbox">
						<label>
							<input type="checkbox" name="keep_license" value="1" />
							<?= l7_t("Manter apenas /usr/local/etc/layer7.lic (remove layer7.json)"); ?>
						</label>
					</div>
					<div class="checkbox">
						<label>
							<input type="checkbox" name="keep_config" value="1" />
							<?= l7_t("Manter layer7.json e layer7.lic (remove cache blacklists em /usr/local/etc/layer7/)"); ?>
						</label>
					</div>
					<p class="help-block"><?= l7_t("Se marcar ambos, prevalece \"manter configuracao\" (ambos os ficheiros)."); ?></p>
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-3 control-label"><?= l7_t("Confirmacao"); ?></label>
				<div class="col-sm-9">
					<input type="text" name="layer7_remove_confirm" class="form-control" placeholder="REMOVER" autocomplete="off" />
					<p class="help-block"><?= l7_t("Escreva REMOVER em maiusculas para confirmar."); ?></p>
				</div>
			</div>
			<div class="form-group">
				<div class="col-sm-offset-3 col-sm-9">
					<button type="submit" name="layer7_pkg_remove_do" value="1" class="btn btn-danger">
						<i class="fa fa-trash icon-embed-btn"></i>
						<?= l7_t("Remover pacote agora"); ?>
					</button>
				</div>
			</div>
		</form>
		<?php } ?>

		<div class="layer7-section">
			<h3 class="layer7-section-title"><?= l7_t("Apos remover"); ?></h3>
			<ul>
				<li><?= l7_t("Recarregue o painel ou aceda a System > Package Manager para confirmar."); ?></li>
				<li><?= l7_t("Se usou overrides anti-DoH no Unbound, remova-os manualmente em Services > DNS Resolver se ainda existirem."); ?></li>
				<li><?= l7_t("Log da ultima remocao via GUI:"); ?> <code><?= htmlspecialchars($log_rm); ?></code></li>
			</ul>
		</div>

		<?php layer7_render_footer(); ?>
		</div>
	</div>
</div>
<?php include("foot.inc"); ?>
