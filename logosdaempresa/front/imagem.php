<?php

/**
 * Serve as imagens enviadas na configuração (acesso público liberado no setup.php, para a tela de login):
 *  - ?logo=logo-GLPI-100-black.png  -> logo personalizado (somente os nomes de logo do GLPI)
 *  - ?arquivo=lateral_xxx.png       -> imagem lateral configurada
 * Toolbox::sendFile foi removido no GLPI 12; getFileAsResponse existe no 11 e no 12.
 */

$caminho = null;
$nome    = '';

if (class_exists('PluginLogosdaempresaConfig')) {
   if (!empty($_GET['logo'])) {
      $nome    = (string) $_GET['logo'];
      $caminho = PluginLogosdaempresaConfig::caminhoPersonalizado($nome);
   } elseif (!empty($_GET['arquivo'])) {
      $nome = preg_replace('/[^\w\-.]+/', '', (string) $_GET['arquivo']);
      $arquivoLateral = PluginLogosdaempresaConfig::getConfig('layout_imagem_lateral', '');
      $candidato = GLPI_PLUGIN_DOC_DIR . DIRECTORY_SEPARATOR . 'logosdaempresa' . DIRECTORY_SEPARATOR . $nome;
      if ($nome !== '' && $nome === $arquivoLateral && is_file($candidato)) {
         $caminho = $candidato;
      }
   }
}

if ($caminho !== null) {
   $mime = mime_content_type($caminho) ?: null;
   Toolbox::getFileAsResponse($caminho, $nome, $mime, true)->send();
   exit;
}

http_response_code(404);
exit;
