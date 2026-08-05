<?php
##|+PRIV
##|*IDENT=page-services-layer7-identity
##|*NAME=Services: Layer 7 (Identity)
##|*DESCR=Identity / User-ID add-on (entitlement gated).
##|*MATCH=layer7_identity.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("/usr/local/pkg/layer7.inc");

$ent = layer7_entitlements();
$unlocked = !empty($ent["has_identity"]);

$pgtitle = array(l7_t("Services"), l7_t("Layer 7"), l7_t("Identity"));
$pglinks = array("", "/packages/layer7/layer7_status.php", "@self");
include("head.inc");
layer7_render_styles();
?>
<div class="panel panel-default layer7-page">
	<div class="panel-heading">
		<h2 class="panel-title"><?= htmlspecialchars(l7_t("Layer 7 - Identity (User-ID)")); ?></h2>
		<?php layer7_render_tabs("identity"); ?>
	</div>
	<div class="panel-body">
		<div class="layer7-content">
<?php if (!$unlocked): ?>
			<div class="alert alert-info" role="alert">
				<strong><?= htmlspecialchars(l7_t("Add-on nao incluido nesta licenca")); ?></strong>
				<p style="margin: 10px 0 0;">
					<?= htmlspecialchars(l7_t(
					    "Identity (politicas por utilizador/grupo AD, mapa user↔IP) " .
					    "requer o entitlement \"identity\" na licenca (SKU Y). " .
					    "A licenca actual nao inclui este add-on. " .
					    "Contacte a Systemup para upgrade. O produto base continua a funcionar normalmente."
					)); ?>
				</p>
				<p style="margin: 10px 0 0; color: #666; font-size: 12px;">
					features=<?= htmlspecialchars($ent["raw"] !== "" ? $ent["raw"] : "(base / legado)"); ?>
					· ADR-0025 / ADR-0027 · captive portal do pfSense permanece fora de escopo
				</p>
			</div>
			<p class="layer7-lead">
				<?= htmlspecialchars(l7_t(
				    "Quando o entitlement estiver activo, esta pagina configurara fontes " .
				    "(RADIUS accounting, agente no Domain Controller, LDAP) e o diagnostico do mapa no daemon."
				)); ?>
			</p>
<?php else: ?>
			<div class="alert alert-success" role="alert">
				<?= htmlspecialchars(l7_t("Entitlement identity activo.")); ?>
				<?= htmlspecialchars(l7_t("Configuracao Identity chega nas ondas IM3–IM6 — por agora so o gate comercial esta aberto.")); ?>
			</div>
			<p class="layer7-lead">
				features=<?= htmlspecialchars($ent["raw"]); ?>
			</p>
<?php endif; ?>
		</div>
	</div>
</div>
<?php
layer7_render_footer();
include("foot.inc");
