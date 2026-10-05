<?php

/**
 * Entrega o vídeo de fundo da tela de login (acesso público e sem sessão, liberado no setup.php).
 * Só serve o vídeo configurado. Atende pedidos por faixa (Range), que os navegadores usam para
 * começar a tocar antes do download terminar e para o loop.
 */

while (ob_get_level() > 0) {
   ob_end_clean();
}

$caminho = class_exists('PluginLogosdaempresaConfig') ? PluginLogosdaempresaConfig::caminhoVideoFundo() : null;
if ($caminho === null) {
   http_response_code(404);
   exit;
}

// Nada de sessão presa enquanto o vídeo é transmitido
if (session_status() === PHP_SESSION_ACTIVE) {
   session_write_close();
}
@set_time_limit(0);

$tamanho = (int) filesize($caminho);
$inicio  = 0;
$fim     = $tamanho - 1;
$etag    = '"' . md5($caminho . '|' . filemtime($caminho) . '|' . $tamanho) . '"';

header('Content-Type: video/mp4');
header('Accept-Ranges: bytes');
header('Cache-Control: public, max-age=604800');
header('ETag: ' . $etag);
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($caminho)) . ' GMT');
header('X-Content-Type-Options: nosniff');

if (trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag && empty($_SERVER['HTTP_RANGE'])) {
   http_response_code(304);
   exit;
}

if (preg_match('/^bytes=(\d*)-(\d*)$/', trim((string) ($_SERVER['HTTP_RANGE'] ?? '')), $m) && ($m[1] !== '' || $m[2] !== '')) {
   if ($m[1] === '') {
      // Últimos N bytes
      $inicio = max(0, $tamanho - (int) $m[2]);
   } else {
      $inicio = (int) $m[1];
      if ($m[2] !== '') {
         $fim = min((int) $m[2], $tamanho - 1);
      }
   }
   if ($inicio > $fim || $inicio >= $tamanho) {
      http_response_code(416);
      header('Content-Range: bytes */' . $tamanho);
      exit;
   }
   http_response_code(206);
   header('Content-Range: bytes ' . $inicio . '-' . $fim . '/' . $tamanho);
}

header('Content-Length: ' . ($fim - $inicio + 1));

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') {
   exit;
}

$arquivo = fopen($caminho, 'rb');
if ($arquivo === false) {
   exit;
}
fseek($arquivo, $inicio);
$restante = $fim - $inicio + 1;
while ($restante > 0 && !feof($arquivo) && !connection_aborted()) {
   $bloco = fread($arquivo, (int) min(262144, $restante));
   if ($bloco === false || $bloco === '') {
      break;
   }
   echo $bloco;
   flush();
   $restante -= strlen($bloco);
}
fclose($arquivo);
exit;
