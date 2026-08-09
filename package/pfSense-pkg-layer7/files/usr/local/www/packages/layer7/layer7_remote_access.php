<?php
##|+PRIV
##|*IDENT=page-services-layer7-remote-access
##|*NAME=Services: Layer 7 (remote access)
##|*DESCR=Redirect to Policies / Quick profiles (Remote access group).
##|*MATCH=layer7_remote_access.php*
##|-PRIV

/*
 * BG-107: o editor de checkboxes desta pagina duplicava os cartoes
 * individuais em Politicas / Perfis rapidos. O caminho canonico e a
 * grelha de perfis (pacote «Acesso Remoto (todos)» + um toggle por
 * software). Mantemos o ficheiro e o privilege para bookmarks antigos.
 */
require_once("guiconfig.inc");

header("Location: /packages/layer7/layer7_policies.php#l7-ra");
exit;
