<?php
/**
 * Render da view real com fixture de busca (stdout = HTML).
 * Usado por test_categories_search.js / jsdom. Não é pfSense.
 */
require_once __DIR__ . "/bootstrap.php";

$ndpi = l7hc_ndpi(array(
	"Social" => array("Facebook", "TikTok"),
	"Voip" => array("WhatsApp"),
	"Streaming" => array("Netflix"),
), array("Facebook", "TikTok", "WhatsApp", "Netflix"));

echo l7hc_render($ndpi);
