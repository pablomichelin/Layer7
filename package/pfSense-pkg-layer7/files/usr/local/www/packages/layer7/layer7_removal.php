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
		/* Sem nowdoc (evita typo << vs <<< e parse errors no PHP do pfSense). */
		$sh = implode("\n", array(
			"#!/bin/sh",
			"set -eu",
			"PATH=/sbin:/bin:/usr/sbin:/usr/bin:/usr/local/sbin:/usr/local/bin",
			"export PATH",
			"LOG=/tmp/layer7_pkg_rm.log",
			"echo \"\$(date -u +%Y-%m-%dT%H:%M:%SZ) job: parar layer7d\" >>\"\$LOG\"",
			"/usr/sbin/service layer7d onestop >>\"\$LOG\" 2>&1 || true",
			"if [ -x /usr/local/libexec/layer7-pfctl ]; then",
			"	/usr/local/libexec/layer7-pfctl flush-all >>\"\$LOG\" 2>&1 || true",
			"fi",
			"sleep 2",
			"if /usr/sbin/pkg info -e pfSense-pkg-layer7 >>\"\$LOG\" 2>&1; then",
			"	echo \"\$(date -u +%Y-%m-%dT%H:%M:%SZ) job: pkg delete\" >>\"\$LOG\"",
			"	/usr/sbin/pkg delete -y pfSense-pkg-layer7 >>\"\$LOG\" 2>&1 || echo \"pkg delete rc=\$?\" >>\"\$LOG\"",
			"else",
			"	echo \"\$(date -u +%Y-%m-%dT%H:%M:%SZ) job: pacote ja ausente\" >>\"\$LOG\"",
			"fi",
			"rm -f /tmp/layer7_pkg_rm.sh /tmp/layer7_pkg_rm_started",
			"echo \"\$(date -u +%Y-%m-%dT%H:%M:%SZ) job: fim\" >>\"\$LOG\"",
		)) . "\n";
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
?>
<?php layer7_render_tabs("removal"); ?>

<?php layer7_render_messages(); ?>

<div id="l7-removal-root">

<div class="panel panel-default" id="l7-removal-warning">
	<div class="panel-heading">
		<h2 class="panel-title"><?= l7_t("Remocao completa do Layer7"); ?></h2>
	</div>
	<div class="panel-body">
		<div class="alert alert-danger" role="alert">
			<strong><?= l7_t("Atencao"); ?>:</strong>
			<?= l7_t("Esta operacao remove o pacote pfSense-pkg-layer7, o daemon, a GUI Layer7, blacklists locais, cron e limpa as tabelas PF layer7_* . Equivalente a uma desinstalacao completa."); ?>
		</div>
		<p class="help-block"><?= l7_t("Alternativa: System > Package Manager > Installed Packages > Remove (o hook do pacote tambem limpa residuos). Esta pagina permite o mesmo com opcoes de preservacao e arranque em segundo plano."); ?></p>
	</div>
</div>

<?php if (!$pkg_installed) { ?>
<div class="panel panel-default" id="l7-removal-state">
	<div class="panel-heading">
		<h2 class="panel-title"><?= l7_t("Estado"); ?></h2>
	</div>
	<div class="panel-body">
		<div class="alert alert-info" role="status"><?= l7_t("O pacote Layer7 nao esta instalado neste sistema (ou a remocao ja terminou)."); ?></div>
	</div>
</div>
<?php } elseif ($job_running) { ?>
<div class="panel panel-default" id="l7-removal-state">
	<div class="panel-heading">
		<h2 class="panel-title"><?= l7_t("Estado"); ?></h2>
	</div>
	<div class="panel-body">
		<div class="alert alert-warning" role="status">
			<?= l7_t("Um pedido de remocao foi iniciado recentemente. Aguarde e verifique o Package Manager. Log:"); ?>
			<code><?= htmlspecialchars($log_rm); ?></code>
		</div>
	</div>
</div>
<?php } else { ?>
<?php
require_once("classes/Form.class.php");
$form = new Form(false);
$sec_opts = new Form_Section(l7_t("Opcoes"));
$keep_lic = new Form_Checkbox(
	"keep_license",
	l7_t("Manter apenas /usr/local/etc/layer7.lic (remove layer7.json)"),
	"",
	false,
	"1"
);
$keep_cfg = new Form_Checkbox(
	"keep_config",
	l7_t("Manter layer7.json, layer7.lic, profiles-custom.json, CA MITM e secrets Identity (remove so o cache de blacklists)"),
	"",
	false,
	"1"
);
$keep_cfg->setHelp(l7_t("Se marcar ambos, prevalece \"manter configuracao\" (ambos os ficheiros)."));
$sec_opts->addInput($keep_lic);
$sec_opts->addInput($keep_cfg);
$form->add($sec_opts);

$sec_confirm = new Form_Section(l7_t("Confirmar"));
$confirm_in = new Form_Input(
	"layer7_remove_confirm",
	l7_t("Confirmacao"),
	"text",
	""
);
$confirm_in->setAttribute("placeholder", "REMOVER");
$confirm_in->setAttribute("autocomplete", "off");
$confirm_in->setHelp(l7_t("Escreva REMOVER em maiusculas para confirmar."));
$sec_confirm->addInput($confirm_in);
$remove_btn_html = '<button type="submit" name="layer7_pkg_remove_do" value="1" class="btn btn-danger">' .
	'<i class="fa fa-trash icon-embed-btn"></i> ' .
	htmlspecialchars(l7_t("Remover pacote agora")) . '</button>';
$sec_confirm->addInput(new Form_StaticText("", $remove_btn_html));
$form->add($sec_confirm);
print($form);
?>
<?php } ?>

<div class="panel panel-default" id="l7-removal-after">
	<div class="panel-heading">
		<h2 class="panel-title"><?= l7_t("Apos remover"); ?></h2>
	</div>
	<div class="panel-body">
		<ul>
			<li><?= l7_t("Recarregue o painel ou aceda a System > Package Manager para confirmar."); ?></li>
			<li><?= l7_t("Se usou overrides anti-DoH no Unbound, remova-os manualmente em Services > DNS Resolver se ainda existirem."); ?></li>
			<li><?= l7_t("Log da ultima remocao via GUI:"); ?> <code><?= htmlspecialchars($log_rm); ?></code></li>
		</ul>
	</div>
</div>

</div>
<p class="text-center text-muted">
	Layer7 para pfSense CE &mdash;
	<a href="https://www.systemup.inf.br" target="_blank">Systemup</a>
	Solu&ccedil;&atilde;o em Tecnologia
</p>
<?php require_once("foot.inc"); ?>
