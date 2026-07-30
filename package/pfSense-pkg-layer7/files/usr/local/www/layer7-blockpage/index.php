<?php
/**
 * Pagina de bloqueio Layer7 — utilizador final (sem autenticacao).
 * Servido em 127.0.0.1:8099; acessivel via NAT rdr no IP portal :80.
 */

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

$cfg_path = '/usr/local/etc/layer7.json';
$defaults = array(
	'title' => 'Acesso bloqueado',
	'message' => 'Este site ou servico foi bloqueado pela politica de rede do seu administrador.',
	'contact' => '',
	'show_host' => true,
	'show_policy' => false,
);

$bp = $defaults;
if (is_readable($cfg_path)) {
	$raw = @file_get_contents($cfg_path);
	$j = @json_decode($raw, true);
	if (is_array($j) && isset($j['layer7']['block_page']) &&
	    is_array($j['layer7']['block_page'])) {
		$src = $j['layer7']['block_page'];
		if (!empty($src['title'])) {
			$bp['title'] = (string)$src['title'];
		}
		if (!empty($src['message'])) {
			$bp['message'] = (string)$src['message'];
		}
		if (isset($src['contact'])) {
			$bp['contact'] = trim((string)$src['contact']);
		}
		$bp['show_host'] = !isset($src['show_host']) || !empty($src['show_host']);
		$bp['show_policy'] = !empty($src['show_policy']);
	}
}

$host = '';
if (!empty($_SERVER['HTTP_HOST'])) {
	$host = strtolower(trim((string)$_SERVER['HTTP_HOST']));
	$host = preg_replace('/:\d+$/', '', $host);
}
if ($host === '' && !empty($_SERVER['SERVER_NAME'])) {
	$host = strtolower(trim((string)$_SERVER['SERVER_NAME']));
}

$policy_name = '';
if ($bp['show_policy'] && $host !== '' && is_readable('/usr/local/pkg/layer7.inc')) {
	require_once '/usr/local/pkg/layer7.inc';
	if (function_exists('layer7_blockpage_policy_for_host')) {
		$policy_name = layer7_blockpage_policy_for_host($host);
	}
}

function l7bp_h($s)
{
	return htmlspecialchars((string)$s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

?><!DOCTYPE html>
<html lang="pt">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex,nofollow">
	<title><?= l7bp_h($bp['title']) ?></title>
	<style>
		:root {
			--bg: #0f172a;
			--card: #1e293b;
			--accent: #3b82f6;
			--text: #f1f5f9;
			--muted: #94a3b8;
			--border: #334155;
		}
		* { box-sizing: border-box; margin: 0; padding: 0; }
		body {
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
			background: linear-gradient(145deg, var(--bg) 0%, #1a1f35 100%);
			color: var(--text);
			min-height: 100vh;
			display: flex;
			align-items: center;
			justify-content: center;
			padding: 24px;
		}
		.card {
			background: var(--card);
			border: 1px solid var(--border);
			border-radius: 12px;
			max-width: 520px;
			width: 100%;
			padding: 32px 28px;
			box-shadow: 0 20px 50px rgba(0,0,0,.35);
		}
		.badge {
			display: inline-block;
			background: rgba(59,130,246,.15);
			color: var(--accent);
			font-size: 11px;
			font-weight: 600;
			letter-spacing: .06em;
			text-transform: uppercase;
			padding: 4px 10px;
			border-radius: 999px;
			margin-bottom: 16px;
		}
		h1 {
			font-size: 1.5rem;
			font-weight: 700;
			margin-bottom: 12px;
			line-height: 1.3;
		}
		p.msg {
			color: var(--muted);
			line-height: 1.6;
			margin-bottom: 20px;
			white-space: pre-wrap;
		}
		.detail {
			background: rgba(15,23,42,.6);
			border: 1px solid var(--border);
			border-radius: 8px;
			padding: 14px 16px;
			margin-bottom: 12px;
			font-size: 14px;
		}
		.detail dt {
			color: var(--muted);
			font-size: 11px;
			text-transform: uppercase;
			letter-spacing: .05em;
			margin-bottom: 4px;
		}
		.detail dd {
			word-break: break-all;
			font-family: ui-monospace, monospace;
			font-size: 13px;
		}
		.footer {
			margin-top: 24px;
			padding-top: 16px;
			border-top: 1px solid var(--border);
			font-size: 12px;
			color: var(--muted);
			text-align: center;
		}
		.footer strong { color: #64748b; }
	</style>
</head>
<body>
	<div class="card" role="alert">
		<div class="badge">Layer7 · Systemup</div>
		<h1><?= l7bp_h($bp['title']) ?></h1>
		<p class="msg"><?= l7bp_h($bp['message']) ?></p>
		<?php if ($bp['show_host'] && $host !== '') { ?>
		<dl class="detail">
			<dt>Endereco bloqueado</dt>
			<dd><?= l7bp_h($host) ?></dd>
		</dl>
		<?php } ?>
		<?php if ($bp['show_policy'] && $policy_name !== '') { ?>
		<dl class="detail">
			<dt>Politica</dt>
			<dd><?= l7bp_h($policy_name) ?></dd>
		</dl>
		<?php } ?>
		<?php if ($bp['contact'] !== '') { ?>
		<dl class="detail">
			<dt>Contacto</dt>
			<dd><?= l7bp_h($bp['contact']) ?></dd>
		</dl>
		<?php } ?>
		<div class="footer">
			<strong>Layer7</strong> — filtragem de rede gerida pelo administrador
		</div>
	</div>
</body>
</html>
