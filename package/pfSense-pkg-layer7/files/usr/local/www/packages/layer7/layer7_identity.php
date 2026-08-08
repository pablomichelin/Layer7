<?php
##|+PRIV
##|*IDENT=page-services-layer7-identity
##|*NAME=Services: Layer 7 (Identity)
##|*DESCR=Identity / User-ID add-on (entitlement gated).
##|*MATCH=layer7_identity.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("/usr/local/pkg/layer7.inc");

/* Nao usar $ent: head.inc faz foreach ($ifentries as $ent => ...) e sobrescreve. */
$l7_ent = layer7_entitlements();
$unlocked = !empty($l7_ent["has_identity"]);
$l7_feat_raw = isset($l7_ent["raw"]) ? (string)$l7_ent["raw"] : "";

$input_errors = array();
$savemsg = "";
$ldap_test = null;

$data = layer7_load_or_default();
$identity = layer7_identity_from_config($data);
$pwd_set = layer7_identity_bind_password_is_set();
$radius_secret_set = layer7_identity_radius_secret_is_set();
$dc_secret_set = layer7_identity_dc_secret_is_set();
$dc_token_once = "";

if ($unlocked && isset($_POST["test_ldap"])) {
	/* Usa a config ja gravada + secret em disco (GI5.4: sem password no POST). */
	$ldap_test = layer7_identity_ldap_test();
	layer7_identity_ldap_test_state_save($ldap_test);
	if (!empty($ldap_test["ok"])) {
		$savemsg = l7_t("Teste LDAP: ligacao OK.") .
		    " (" . (int)$ldap_test["ms"] . " ms)";
	} else {
		$input_errors[] = l7_t("Teste LDAP falhou:") . " " .
		    (string)($ldap_test["message"] ?? "");
	}
} elseif ($unlocked && (isset($_POST["save_identity"]) ||
    isset($_POST["dc_generate_token"]))) {
	$nas_raw = trim((string)($_POST["radius_nas_acl"] ?? ""));
	$dc_acl_raw = trim((string)($_POST["dc_acl"] ?? ""));
	$identity = array(
		"enabled" => isset($_POST["identity_enabled"]),
		"ldap" => array(
			"enabled" => isset($_POST["ldap_enabled"]),
			"server" => trim((string)($_POST["ldap_server"] ?? "")),
			"port" => (int)($_POST["ldap_port"] ?? 636),
			"use_tls" => isset($_POST["ldap_use_tls"]),
			"bind_dn" => trim((string)($_POST["ldap_bind_dn"] ?? "")),
			"base_dn" => trim((string)($_POST["ldap_base_dn"] ?? "")),
			"user_filter" => trim((string)($_POST["ldap_user_filter"] ?? "")),
			"group_filter" => trim((string)($_POST["ldap_group_filter"] ?? "")),
			"group_depth" => (int)($_POST["ldap_group_depth"] ?? 5),
			"max_members" => (int)($_POST["ldap_max_members"] ?? 4096)
		),
		"radius" => array(
			"enabled" => isset($_POST["radius_enabled"]),
			"listen_port" => (int)($_POST["radius_listen_port"] ?? 1813),
			"bind_address" => trim((string)($_POST["radius_bind_address"] ?? "0.0.0.0")),
			"nas_acl" => $nas_raw
		),
		"dc_agent" => array(
			"enabled" => isset($_POST["dc_enabled"]),
			"listen_port" => (int)($_POST["dc_listen_port"] ?? 8743),
			"bind_address" => trim((string)($_POST["dc_bind_address"] ?? "127.0.0.1")),
			"skew_sec" => (int)($_POST["dc_skew_sec"] ?? 300),
			"dc_acl" => $dc_acl_raw
		)
	);
	$identity = layer7_identity_normalize($identity);
	$input_errors = layer7_identity_validate($identity);

	$new_pwd = (string)($_POST["ldap_bind_password"] ?? "");
	$clear_pwd = isset($_POST["ldap_clear_password"]);
	if ($clear_pwd) {
		if (!layer7_identity_bind_password_clear()) {
			$input_errors[] = l7_t("Nao foi possivel limpar a palavra-passe de bind.");
		} else {
			$pwd_set = false;
		}
	} elseif ($new_pwd !== "") {
		if (!layer7_identity_bind_password_save($new_pwd)) {
			$input_errors[] = l7_t("Nao foi possivel gravar a palavra-passe de bind.");
		} else {
			$pwd_set = true;
		}
	}

	if (!empty($identity["ldap"]["enabled"]) && !$pwd_set && empty($input_errors)) {
		$input_errors[] = l7_t(
		    "Palavra-passe de bind e obrigatoria quando o directorio LDAP esta activo."
		);
	}

	$new_rad_secret = (string)($_POST["radius_secret"] ?? "");
	$clear_rad = isset($_POST["radius_clear_secret"]);
	if ($clear_rad) {
		if (!layer7_identity_radius_secret_clear()) {
			$input_errors[] = l7_t("Nao foi possivel limpar o shared secret RADIUS.");
		} else {
			$radius_secret_set = false;
		}
	} elseif ($new_rad_secret !== "") {
		if (!layer7_identity_radius_secret_save($new_rad_secret)) {
			$input_errors[] = l7_t("Nao foi possivel gravar o shared secret RADIUS.");
		} else {
			$radius_secret_set = true;
		}
	}

	if (!empty($identity["radius"]["enabled"]) && !$radius_secret_set &&
	    empty($input_errors)) {
		$input_errors[] = l7_t(
		    "Shared secret RADIUS e obrigatorio quando o receiver esta activo."
		);
	}

	if (isset($_POST["dc_generate_token"])) {
		$tok = layer7_identity_dc_secret_generate();
		if ($tok === false) {
			$input_errors[] = l7_t("Nao foi possivel gerar o token do agente DC.");
		} else {
			$dc_secret_set = true;
			$dc_token_once = $tok;
		}
	} elseif (isset($_POST["dc_clear_secret"])) {
		if (!layer7_identity_dc_secret_clear()) {
			$input_errors[] = l7_t("Nao foi possivel limpar o token do agente DC.");
		} else {
			$dc_secret_set = false;
		}
	} else {
		$new_dc = (string)($_POST["dc_secret"] ?? "");
		if ($new_dc !== "") {
			if (!layer7_identity_dc_secret_save($new_dc)) {
				$input_errors[] = l7_t("Nao foi possivel gravar o token do agente DC.");
			} else {
				$dc_secret_set = true;
			}
		}
	}

	if (!empty($identity["dc_agent"]["enabled"]) && !$dc_secret_set &&
	    empty($input_errors)) {
		$input_errors[] = l7_t(
		    "Token do agente DC e obrigatorio quando o receiver esta activo."
		);
	}
	if (!empty($identity["dc_agent"]["enabled"]) && empty($input_errors)) {
		if (!layer7_identity_dc_ensure_tls_cert()) {
			$input_errors[] = l7_t(
			    "Nao foi possivel criar/validar o certificado TLS do receiver DC."
			);
		}
	}

	if (empty($input_errors)) {
		$data = layer7_identity_apply_to_config($data, $identity);
		if (!layer7_save_json($data)) {
			$input_errors[] = l7_t("Nao foi possivel gravar a configuracao Identity.");
		} else {
			layer7_signal_reload();
			$savemsg = l7_t(
			    "Configuracao Identity guardada. " .
			    "O daemon aplica LDAP, RADIUS e agente DC no reload."
			);
			$identity = layer7_identity_from_config($data);
			$pwd_set = layer7_identity_bind_password_is_set();
			$radius_secret_set = layer7_identity_radius_secret_is_set();
			$dc_secret_set = layer7_identity_dc_secret_is_set();
		}
	}
}

$ldap = $identity["ldap"];
$radius = isset($identity["radius"]) && is_array($identity["radius"]) ?
    $identity["radius"] : layer7_identity_defaults()["radius"];
$dc = isset($identity["dc_agent"]) && is_array($identity["dc_agent"]) ?
    $identity["dc_agent"] : layer7_identity_defaults()["dc_agent"];
$nas_acl_text = is_array($radius["nas_acl"] ?? null) ?
    implode(", ", $radius["nas_acl"]) : "";
$dc_acl_text = is_array($dc["dc_acl"] ?? null) ?
    implode(", ", $dc["dc_acl"]) : "";
if ($ldap_test === null) {
	$ldap_test = layer7_identity_ldap_test_state_load();
}
$pgtitle = array(l7_t("Services"), l7_t("Layer 7"), l7_t("Identity"));
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
		<h2 class="panel-title"><?= htmlspecialchars(l7_t("Layer 7 - Identity (mapa de utilizadores)")); ?></h2>
	</div>
	<div class="panel-body">
		<?php layer7_render_tabs("identity"); ?>
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
					features=<?= htmlspecialchars($l7_feat_raw !== "" ? $l7_feat_raw : "(base / legado)"); ?>
					· ADR-0025 / ADR-0027 · captive portal do pfSense permanece fora de escopo
				</p>
			</div>
			<p class="layer7-lead">
				<?= htmlspecialchars(l7_t(
				    "Quando o entitlement estiver activo, esta pagina configura o directorio LDAP " .
				    "e, em passos seguintes, as fontes de sessao (RADIUS, agente no Domain Controller)."
				)); ?>
			</p>
<?php else: ?>
			<div class="alert alert-success" role="alert" style="margin-bottom: 16px;">
				<?= htmlspecialchars(l7_t("Entitlement identity activo.")); ?>
				<?= htmlspecialchars(l7_t(
				    "Isto e User-ID de rede (mapa utilizador↔IP), nao um agente em cada PC."
				)); ?>
			</div>

			<p class="help-block" style="margin-top:0;">
				<?= htmlspecialchars(l7_t(
				    "Quando usar: ligue o directorio LDAP do Active Directory (ou LDAP " .
				    "compativel) para expandir grupos. A sessao (quem esta em que IP) chega " .
				    "via RADIUS accounting ou agente no Domain Controller — " .
				    "nao use captive portal do Layer7."
				)); ?>
			</p>
			<p class="help-block text-muted">
				<?= htmlspecialchars(l7_t(
				    "Sem inspeccao TLS (MITM): bloqueio HTTPS continua alinhado a pagina HTTP/DNS. " .
				    "Se o LDAP falhar depois de activo, politicas por grupo deixam de aplicar " .
				    "(fail-mode seguro) — a LAN nao e fechada."
				)); ?>
			</p>
			<p class="help-block text-muted">
				<?= htmlspecialchars(l7_t(
				    "Limite honesto (topologia): o IP reportado pelo AD/RADIUS pode diferir do IP " .
				    "visto no firewall (NAT, Wi-Fi partilhado). Se dois utilizadores aparecerem no " .
				    "mesmo IP ao mesmo tempo, o mapa marca multi-user e politicas ad_* nao aplicam " .
				    "nesse IP (fallback seguro) — evento identity_ip_conflict no log."
				)); ?>
			</p>

			<form method="post" action="layer7_identity.php" class="form-horizontal">

				<h3 style="margin-top: 8px;"><?= htmlspecialchars(l7_t("Modulo Identity")); ?></h3>
				<div class="form-group">
					<label class="col-sm-3 control-label"><?= htmlspecialchars(l7_t("Activar Identity")); ?></label>
					<div class="col-sm-9">
						<label class="checkbox-inline">
							<input type="checkbox" name="identity_enabled" value="1"
								<?= !empty($identity["enabled"]) ? 'checked="checked"' : ""; ?> />
							<?= htmlspecialchars(l7_t("Intencao de usar o add-on neste appliance (default OFF)")); ?>
						</label>
						<p class="help-block">
							<?= htmlspecialchars(l7_t(
							    "O daemon so inicializa o mapa com entitlement. Este toggle prepara a config; " .
							    "fontes de sessao e politicas por grupo entram em passos seguintes."
							)); ?>
						</p>
					</div>
				</div>

				<hr />
				<h3><?= htmlspecialchars(l7_t("Directorio LDAP / LDAPS")); ?></h3>
				<div class="form-group">
					<label class="col-sm-3 control-label"><?= htmlspecialchars(l7_t("Usar directorio")); ?></label>
					<div class="col-sm-9">
						<label class="checkbox-inline">
							<input type="checkbox" name="ldap_enabled" value="1"
								<?= !empty($ldap["enabled"]) ? 'checked="checked"' : ""; ?> />
							<?= htmlspecialchars(l7_t("Consultar LDAP para grupos (default OFF)")); ?>
						</label>
						<p class="help-block">
							<?= htmlspecialchars(l7_t("Prefira LDAPS (TLS). Conta de servico com privilegio minimo.")); ?>
						</p>
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= htmlspecialchars(l7_t("Servidor")); ?></label>
					<div class="col-sm-9">
						<input type="text" name="ldap_server" class="form-control" style="max-width: 420px;"
							maxlength="253"
							value="<?= htmlspecialchars($ldap["server"]); ?>"
							placeholder="dc01.empresa.local" />
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= htmlspecialchars(l7_t("Porto")); ?></label>
					<div class="col-sm-9">
						<input type="number" name="ldap_port" class="form-control" style="max-width: 120px;"
							min="1" max="65535" value="<?= (int)$ldap["port"]; ?>" />
						<p class="help-block"><?= htmlspecialchars(l7_t("636 = LDAPS tipico; 389 = LDAP (menos seguro).")); ?></p>
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= htmlspecialchars(l7_t("TLS / LDAPS")); ?></label>
					<div class="col-sm-9">
						<label class="checkbox-inline">
							<input type="checkbox" name="ldap_use_tls" value="1"
								<?= !empty($ldap["use_tls"]) ? 'checked="checked"' : ""; ?> />
							<?= htmlspecialchars(l7_t("Usar ligacao cifrada (recomendado)")); ?>
						</label>
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= htmlspecialchars(l7_t("Bind DN")); ?></label>
					<div class="col-sm-9">
						<input type="text" name="ldap_bind_dn" class="form-control" style="max-width: 520px;"
							maxlength="512"
							value="<?= htmlspecialchars($ldap["bind_dn"]); ?>"
							placeholder="CN=layer7,OU=Service,DC=empresa,DC=local" />
						<p class="help-block"><?= htmlspecialchars(l7_t("Conta de servico que le o directorio.")); ?></p>
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= htmlspecialchars(l7_t("Palavra-passe de bind")); ?></label>
					<div class="col-sm-9">
						<input type="password" name="ldap_bind_password" class="form-control" style="max-width: 320px;"
							maxlength="256" value="" autocomplete="new-password"
							placeholder="<?= $pwd_set
							    ? htmlspecialchars(l7_t("(definida — deixe vazio para manter)"))
							    : ""; ?>" />
						<label class="checkbox-inline" style="margin-left: 12px;">
							<input type="checkbox" name="ldap_clear_password" value="1" />
							<?= htmlspecialchars(l7_t("Limpar palavra-passe guardada")); ?>
						</label>
						<p class="help-block">
							<?= htmlspecialchars(l7_t(
							    "Guardada num ficheiro privado no appliance (nao no JSON de config). " .
							    "Nunca aparece em logs."
							)); ?>
							<?php if ($pwd_set): ?>
								— <span class="text-success"><?= htmlspecialchars(l7_t("palavra-passe definida")); ?></span>
							<?php else: ?>
								— <span class="text-muted"><?= htmlspecialchars(l7_t("ainda nao definida")); ?></span>
							<?php endif; ?>
						</p>
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= htmlspecialchars(l7_t("Base DN")); ?></label>
					<div class="col-sm-9">
						<input type="text" name="ldap_base_dn" class="form-control" style="max-width: 520px;"
							maxlength="512"
							value="<?= htmlspecialchars($ldap["base_dn"]); ?>"
							placeholder="DC=empresa,DC=local" />
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= htmlspecialchars(l7_t("Filtro de utilizadores")); ?></label>
					<div class="col-sm-9">
						<input type="text" name="ldap_user_filter" class="form-control" style="max-width: 520px;"
							maxlength="512"
							value="<?= htmlspecialchars($ldap["user_filter"]); ?>" />
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= htmlspecialchars(l7_t("Filtro de grupos")); ?></label>
					<div class="col-sm-9">
						<input type="text" name="ldap_group_filter" class="form-control" style="max-width: 520px;"
							maxlength="512"
							value="<?= htmlspecialchars($ldap["group_filter"]); ?>" />
					</div>
				</div>

				<hr />
				<h3><?= htmlspecialchars(l7_t("RADIUS accounting (fonte de sessao)")); ?></h3>
				<p class="help-block">
					<?= htmlspecialchars(l7_t(
					    "O Layer7 escuta Accounting-Request (User-Name + Framed-IP) " .
					    "do seu controlador Wi-Fi / NAS. Configure o NAS para enviar " .
					    "accounting para este appliance (porto tipico 1813). " .
					    "Default OFF. Secret e ACL NAS obrigatorios quando activo."
					)); ?>
				</p>
				<div class="form-group">
					<label class="col-sm-3 control-label"><?= htmlspecialchars(l7_t("Receiver RADIUS")); ?></label>
					<div class="col-sm-9">
						<label class="checkbox-inline">
							<input type="checkbox" name="radius_enabled" value="1"
								<?= !empty($radius["enabled"]) ? 'checked="checked"' : ""; ?> />
							<?= htmlspecialchars(l7_t("Aceitar accounting e popular o mapa user↔IP (default OFF)")); ?>
						</label>
					</div>
				</div>
				<div class="form-group">
					<label class="col-sm-3 control-label"><?= htmlspecialchars(l7_t("Porto de escuta")); ?></label>
					<div class="col-sm-9">
						<input type="number" name="radius_listen_port" class="form-control" style="max-width: 120px;"
							min="1" max="65535" value="<?= (int)$radius["listen_port"]; ?>" />
						<p class="help-block"><?= htmlspecialchars(l7_t("1813 = accounting tipico (RFC 2866).")); ?></p>
					</div>
				</div>
				<div class="form-group">
					<label class="col-sm-3 control-label"><?= htmlspecialchars(l7_t("Endereco de bind")); ?></label>
					<div class="col-sm-9">
						<input type="text" name="radius_bind_address" class="form-control" style="max-width: 220px;"
							maxlength="64"
							value="<?= htmlspecialchars($radius["bind_address"]); ?>"
							placeholder="0.0.0.0" />
						<p class="help-block">
							<?= htmlspecialchars(l7_t(
							    "Prefira o IP da LAN. Nao exponha este porto na WAN. " .
							    "A ACL NAS rejeita peers nao listados."
							)); ?>
						</p>
					</div>
				</div>
				<div class="form-group">
					<label class="col-sm-3 control-label"><?= htmlspecialchars(l7_t("ACL NAS (IPs)")); ?></label>
					<div class="col-sm-9">
						<input type="text" name="radius_nas_acl" class="form-control" style="max-width: 520px;"
							maxlength="512"
							value="<?= htmlspecialchars($nas_acl_text); ?>"
							placeholder="10.0.0.1, 10.0.0.2" />
						<p class="help-block">
							<?= htmlspecialchars(l7_t(
							    "IPs dos NAS autorizados (separados por virgula). " .
							    "Lista vazia = receiver nao arranca (seguro)."
							)); ?>
						</p>
					</div>
				</div>
				<div class="form-group">
					<label class="col-sm-3 control-label"><?= htmlspecialchars(l7_t("Shared secret")); ?></label>
					<div class="col-sm-9">
						<input type="password" name="radius_secret" class="form-control" style="max-width: 320px;"
							maxlength="256" value="" autocomplete="new-password"
							placeholder="<?= $radius_secret_set
							    ? htmlspecialchars(l7_t("(definido — deixe vazio para manter)"))
							    : ""; ?>" />
						<label class="checkbox-inline" style="margin-left: 12px;">
							<input type="checkbox" name="radius_clear_secret" value="1" />
							<?= htmlspecialchars(l7_t("Limpar secret guardado")); ?>
						</label>
						<p class="help-block">
							<?= htmlspecialchars(l7_t(
							    "Ficheiro privado no appliance (nao no JSON). Nunca aparece em logs."
							)); ?>
							<?php if ($radius_secret_set): ?>
								— <span class="text-success"><?= htmlspecialchars(l7_t("secret definido")); ?></span>
							<?php else: ?>
								— <span class="text-muted"><?= htmlspecialchars(l7_t("ainda nao definido")); ?></span>
							<?php endif; ?>
						</p>
					</div>
				</div>

				<hr />
				<h3><?= htmlspecialchars(l7_t("Agente DC (fonte de sessao)")); ?></h3>
				<p class="help-block">
					<?= htmlspecialchars(l7_t(
					    "Receiver HTTPS no appliance (porto 8743) para o agente no Domain Controller. " .
					    "Autenticacao: token + HMAC-SHA256. Bind so em IP LAN (nunca 0.0.0.0). " .
					    "Default OFF. Agente Windows (Event Log 4624/4634): pasta identity-dc-agent no repositorio Layer7. " .
					    "Isto e User-ID de rede — o IP reportado pelo AD pode diferir do IP visto no firewall."
					)); ?>
				</p>
<?php if ($dc_token_once !== ""): ?>
				<div class="alert alert-warning">
					<?= htmlspecialchars(l7_t("Novo token (copie agora — nao sera mostrado outra vez):")); ?>
					<code style="user-select: all;"><?= htmlspecialchars($dc_token_once); ?></code>
				</div>
<?php endif; ?>
				<div class="form-group">
					<label class="col-sm-3 control-label"><?= htmlspecialchars(l7_t("Receiver agente DC")); ?></label>
					<div class="col-sm-9">
						<label class="checkbox-inline">
							<input type="checkbox" name="dc_enabled" value="1"
								<?= !empty($dc["enabled"]) ? 'checked="checked"' : ""; ?> />
							<?= htmlspecialchars(l7_t("Aceitar push de logon/logoff (default OFF)")); ?>
						</label>
					</div>
				</div>
				<div class="form-group">
					<label class="col-sm-3 control-label"><?= htmlspecialchars(l7_t("Porto")); ?></label>
					<div class="col-sm-9">
						<input type="number" name="dc_listen_port" class="form-control" style="max-width: 120px;"
							min="1" max="65535" value="<?= (int)$dc["listen_port"]; ?>" />
					</div>
				</div>
				<div class="form-group">
					<label class="col-sm-3 control-label"><?= htmlspecialchars(l7_t("Bind LAN")); ?></label>
					<div class="col-sm-9">
						<input type="text" name="dc_bind_address" class="form-control" style="max-width: 220px;"
							maxlength="64"
							value="<?= htmlspecialchars($dc["bind_address"]); ?>"
							placeholder="192.168.1.1" />
					</div>
				</div>
				<div class="form-group">
					<label class="col-sm-3 control-label"><?= htmlspecialchars(l7_t("ACL Domain Controllers")); ?></label>
					<div class="col-sm-9">
						<input type="text" name="dc_acl" class="form-control" style="max-width: 520px;"
							maxlength="512"
							value="<?= htmlspecialchars($dc_acl_text); ?>"
							placeholder="10.0.0.10, 10.0.0.11" />
					</div>
				</div>
				<div class="form-group">
					<label class="col-sm-3 control-label"><?= htmlspecialchars(l7_t("Skew (segundos)")); ?></label>
					<div class="col-sm-9">
						<input type="number" name="dc_skew_sec" class="form-control" style="max-width: 120px;"
							min="60" max="900" value="<?= (int)$dc["skew_sec"]; ?>" />
					</div>
				</div>
				<div class="form-group">
					<label class="col-sm-3 control-label"><?= htmlspecialchars(l7_t("Token agente")); ?></label>
					<div class="col-sm-9">
						<input type="password" name="dc_secret" class="form-control" style="max-width: 320px;"
							maxlength="256" value="" autocomplete="new-password"
							placeholder="<?= $dc_secret_set
							    ? htmlspecialchars(l7_t("(definido — deixe vazio para manter)"))
							    : ""; ?>" />
						<label class="checkbox-inline" style="margin-left: 12px;">
							<input type="checkbox" name="dc_clear_secret" value="1" />
							<?= htmlspecialchars(l7_t("Limpar token")); ?>
						</label>
						<button type="submit" name="dc_generate_token" value="1" class="btn btn-default btn-sm"
							style="margin-left: 12px;"
							onclick="return confirm(<?= json_encode(l7_t('Gerar novo token? O anterior deixa de funcionar.')); ?>);">
							<?= htmlspecialchars(l7_t("Gerar token")); ?>
						</button>
						<p class="help-block">
							<?php if ($dc_secret_set): ?>
								<span class="text-success"><?= htmlspecialchars(l7_t("token definido")); ?></span>
							<?php else: ?>
								<span class="text-muted"><?= htmlspecialchars(l7_t("ainda nao definido")); ?></span>
							<?php endif; ?>
						</p>
					</div>
				</div>

				<hr />
				<h3><?= htmlspecialchars(l7_t("Limites de escala")); ?></h3>
				<p class="help-block">
					<?= htmlspecialchars(l7_t(
					    "Limites para expansao de grupos aninhados (defaults ADR-0027). " .
					    "Estouro = log previsivel, sem fechar a LAN."
					)); ?>
				</p>
				<div class="form-group">
					<label class="col-sm-3 control-label"><?= htmlspecialchars(l7_t("Profundidade de grupos")); ?></label>
					<div class="col-sm-9">
						<input type="number" name="ldap_group_depth" class="form-control" style="max-width: 120px;"
							min="1" max="10" value="<?= (int)$ldap["group_depth"]; ?>" />
						<p class="help-block"><?= htmlspecialchars(l7_t("Default 5 (grupos aninhados).")); ?></p>
					</div>
				</div>
				<div class="form-group">
					<label class="col-sm-3 control-label"><?= htmlspecialchars(l7_t("Max. membros por grupo")); ?></label>
					<div class="col-sm-9">
						<input type="number" name="ldap_max_members" class="form-control" style="max-width: 120px;"
							min="1" max="16384" value="<?= (int)$ldap["max_members"]; ?>" />
						<p class="help-block"><?= htmlspecialchars(l7_t("Default 4096.")); ?></p>
					</div>
				</div>

				<div class="form-group" style="margin-top: 20px;">
					<div class="col-sm-9 col-sm-offset-3">
						<button type="submit" name="save_identity" value="1" class="btn btn-primary">
							<?= htmlspecialchars(l7_t("Guardar")); ?>
						</button>
						<button type="submit" name="test_ldap" value="1" class="btn btn-default"
							style="margin-left: 8px;"
							<?= empty($ldap["enabled"]) ? 'disabled="disabled"' : ""; ?>>
							<?= htmlspecialchars(l7_t("Testar ligacao LDAP")); ?>
						</button>
						<p class="help-block" style="margin-top: 10px;">
							<?= htmlspecialchars(l7_t(
							    "O teste usa a configuracao e a palavra-passe ja gravadas " .
							    "(nao envia a password no pedido). Guarde antes de testar " .
							    "alteracoes. Resultado e logs sem secrets (GI5.4)."
							)); ?>
						</p>
					</div>
				</div>
			</form>
<?php if (is_array($ldap_test)): ?>
			<div class="panel panel-<?= !empty($ldap_test["ok"]) ? "success" : "warning"; ?>"
				style="margin-top: 16px;">
				<div class="panel-heading">
					<h3 class="panel-title" style="font-size: 14px;">
						<?= htmlspecialchars(l7_t("Ultimo teste LDAP")); ?>
					</h3>
				</div>
				<div class="panel-body" style="font-size: 13px;">
					<p style="margin: 0 0 6px;">
						<strong><?= htmlspecialchars(l7_t("Resultado")); ?>:</strong>
						<?= !empty($ldap_test["ok"])
						    ? htmlspecialchars(l7_t("OK"))
						    : htmlspecialchars(l7_t("Falha")); ?>
						· <?= htmlspecialchars((string)($ldap_test["message"] ?? "")); ?>
					</p>
					<p style="margin: 0; color: #666;">
						<?= htmlspecialchars(l7_t("Fase")); ?>:
						<?= htmlspecialchars((string)($ldap_test["phase"] ?? "-")); ?>
						· <?= htmlspecialchars((string)($ldap_test["server"] ?? "")); ?>
						:<?= (int)($ldap_test["port"] ?? 0); ?>
						· TLS=<?= !empty($ldap_test["tls"]) ? "yes" : "no"; ?>
						· Base DN=<?= !empty($ldap_test["base_ok"]) ? "ok" : "-"; ?>
						· <?= (int)($ldap_test["ms"] ?? 0); ?> ms
<?php if (!empty($ldap_test["tested_at"])): ?>
						· <?= htmlspecialchars((string)$ldap_test["tested_at"]); ?>
<?php endif; ?>
					</p>
				</div>
			</div>
<?php endif; ?>
			<p class="text-muted" style="margin-top: 24px; font-size: 12px;">
				features=<?= htmlspecialchars($l7_feat_raw); ?>
			</p>
<?php endif; ?>
		</div>
	</div>
</div>
<?php
layer7_render_footer();
include("foot.inc");
