<?php

// Carregado pelo GLPI 11/12 (inc/includes.php e obsoleto)

Session::checkLoginUser();

if (!Session::haveRight('config', UPDATE)) {
   throw new \Glpi\Exception\Http\AccessDeniedHttpException();
}

PluginLogosdaempresaConfig::downloadLogosOriginais();