<?php

/**
 * CSS do tema (logos e cor), incluído em todas as páginas pelo hook add_css.
 * No GLPI 11 e 12 o próprio GLPI carrega este script (acesso público liberado no setup.php).
 */

header('Content-Type: text/css; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

if (class_exists('PluginLogosdaempresaConfig')) {
   echo PluginLogosdaempresaConfig::gerarCssTema();
}
