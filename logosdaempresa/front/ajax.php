<?php

/**
 * Endpoint AJAX (JSON) do plugin: envio do vídeo de fundo da tela de login em partes.
 *
 * O PHP deste servidor aceita arquivos pequenos por requisição (upload_max_filesize), então o
 * navegador envia o MP4 de até 100 MB em pedaços, que são juntados aqui:
 *   video_iniciar  (nome, tamanho)       -> id do envio
 *   video_parte    (id, inicio, parte)   -> recebe um pedaço na posição informada
 *   video_concluir (id)                  -> confere o MP4 e passa a usá-lo como fundo
 *   video_cancelar (id)                  -> descarta o envio
 * As partes ficam em files/_plugins/logosdaempresa/videos/envio até a conclusão.
 */

while (ob_get_level() > 0) {
   ob_end_clean();
}

register_shutdown_function(function () {
   $erro = error_get_last();
   if ($erro !== null && in_array($erro['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
      while (ob_get_level() > 0) {
         ob_end_clean();
      }
      header('Content-Type: application/json; charset=utf-8');
      echo json_encode(['success' => false, 'mensagem' => 'Erro interno ao processar o envio.']);
   }
});

// Carregado pelo GLPI 11/12 (inc/includes.php e obsoleto)

while (ob_get_level() > 0) {
   ob_end_clean();
}

function logosdaempresa_json(array $dados): never {
   while (ob_get_level() > 0) {
      ob_end_clean();
   }
   header('Content-Type: application/json; charset=utf-8');
   $dados['new_token'] = PluginLogosdaempresaConfig::tokenCsrf();
   echo json_encode($dados, JSON_UNESCAPED_UNICODE);
   exit;
}

Session::checkLoginUser();
if (!Session::haveRight('config', UPDATE)) {
   logosdaempresa_json(['success' => false, 'mensagem' => 'Sem permissão para alterar a configuração.']);
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
   logosdaempresa_json(['success' => false, 'mensagem' => 'Método não permitido.']);
}

@set_time_limit(120);

$pastaEnvio = PluginLogosdaempresaConfig::pastaVideos() . DIRECTORY_SEPARATOR . 'envio';
if (!is_dir($pastaEnvio)) {
   @mkdir($pastaEnvio, 0755, true);
}
if (!is_dir($pastaEnvio) || !is_writable($pastaEnvio)) {
   logosdaempresa_json(['success' => false, 'mensagem' => 'Sem permissão de escrita na pasta ' . $pastaEnvio . '.']);
}

/** Dados de um envio em andamento (somente do próprio usuário) */
function logosdaempresa_envio(string $pasta): array {
   $id = (string) ($_POST['id'] ?? '');
   if (!preg_match('/^[a-f0-9]{24}$/', $id)) {
      logosdaempresa_json(['success' => false, 'mensagem' => 'Envio inválido.']);
   }
   $meta = json_decode((string) @file_get_contents($pasta . DIRECTORY_SEPARATOR . $id . '.json'), true);
   if (!is_array($meta) || (int) ($meta['users_id'] ?? 0) !== (int) Session::getLoginUserID()) {
      logosdaempresa_json(['success' => false, 'mensagem' => 'Envio não encontrado. Tente enviar o vídeo de novo.']);
   }
   $meta['id']    = $id;
   $meta['parte'] = $pasta . DIRECTORY_SEPARATOR . $id . '.part';
   $meta['json']  = $pasta . DIRECTORY_SEPARATOR . $id . '.json';
   return $meta;
}

function logosdaempresa_descartar(array $meta): void {
   @unlink($meta['parte']);
   @unlink($meta['json']);
}

switch ((string) ($_POST['action'] ?? '')) {

   case 'video_iniciar':
      $nome    = (string) ($_POST['nome'] ?? '');
      $tamanho = (int) ($_POST['tamanho'] ?? 0);
      if (strtolower(pathinfo($nome, PATHINFO_EXTENSION)) !== 'mp4') {
         logosdaempresa_json(['success' => false, 'mensagem' => 'Envie um arquivo MP4.']);
      }
      if ($tamanho <= 0 || $tamanho > PluginLogosdaempresaConfig::VIDEO_MAX_BYTES) {
         logosdaempresa_json(['success' => false, 'mensagem' => 'O vídeo deve ter até 100 MB.']);
      }
      $livre = @disk_free_space($pastaEnvio);
      if ($livre !== false && $livre < $tamanho * 2 + 50 * 1024 * 1024) {
         logosdaempresa_json(['success' => false, 'mensagem' => 'Espaço em disco insuficiente no servidor para este vídeo.']);
      }

      // Envios abandonados há mais de 6 horas
      foreach (glob($pastaEnvio . DIRECTORY_SEPARATOR . '*.{json,part}', GLOB_BRACE) ?: [] as $antigo) {
         if (filemtime($antigo) < time() - 6 * 3600) {
            @unlink($antigo);
         }
      }

      $id = bin2hex(random_bytes(12));
      file_put_contents($pastaEnvio . DIRECTORY_SEPARATOR . $id . '.json', json_encode([
         'users_id' => (int) Session::getLoginUserID(),
         'nome'     => mb_substr($nome, 0, 200),
         'tamanho'  => $tamanho,
         'inicio'   => time(),
      ]));
      file_put_contents($pastaEnvio . DIRECTORY_SEPARATOR . $id . '.part', '');
      logosdaempresa_json([
         'success' => true,
         'id'      => $id,
         'parte'   => PluginLogosdaempresaConfig::tamanhoParteUpload(),
      ]);

   case 'video_parte':
      $meta   = logosdaempresa_envio($pastaEnvio);
      $inicio = (int) ($_POST['inicio'] ?? -1);
      $erro   = $_FILES['parte']['error'] ?? UPLOAD_ERR_NO_FILE;
      if ($erro !== UPLOAD_ERR_OK) {
         logosdaempresa_json(['success' => false, 'mensagem' => 'Falha ao receber uma parte do vídeo (código ' . (int) $erro . ').']);
      }
      clearstatcache(true, $meta['parte']);
      $atual = (int) @filesize($meta['parte']);
      // Parte repetida (reenvio após falha de rede): já foi gravada
      if ($inicio >= 0 && $inicio < $atual) {
         logosdaempresa_json(['success' => true, 'recebido' => $atual]);
      }
      if ($inicio !== $atual) {
         logosdaempresa_json(['success' => false, 'mensagem' => 'Parte fora de ordem. Tente enviar o vídeo de novo.', 'recebido' => $atual]);
      }
      $tamParte = (int) filesize($_FILES['parte']['tmp_name']);
      if ($atual + $tamParte > (int) $meta['tamanho']) {
         logosdaempresa_json(['success' => false, 'mensagem' => 'O vídeo ficou maior que o informado.']);
      }
      $origem  = fopen($_FILES['parte']['tmp_name'], 'rb');
      $destino = fopen($meta['parte'], 'ab');
      if (!$origem || !$destino) {
         logosdaempresa_json(['success' => false, 'mensagem' => 'Não foi possível gravar o vídeo no servidor.']);
      }
      stream_copy_to_stream($origem, $destino);
      fclose($origem);
      fclose($destino);
      clearstatcache(true, $meta['parte']);
      logosdaempresa_json(['success' => true, 'recebido' => (int) filesize($meta['parte'])]);

   case 'video_concluir':
      $meta = logosdaempresa_envio($pastaEnvio);
      clearstatcache(true, $meta['parte']);
      if ((int) @filesize($meta['parte']) !== (int) $meta['tamanho']) {
         logosdaempresa_descartar($meta);
         logosdaempresa_json(['success' => false, 'mensagem' => 'O vídeo chegou incompleto. Tente enviar de novo.']);
      }

      // MP4 de verdade: caixa "ftyp" no início e tipo de vídeo reconhecido
      $cabecalho = (string) @file_get_contents($meta['parte'], false, null, 0, 12);
      $mime      = (string) @mime_content_type($meta['parte']);
      if (substr($cabecalho, 4, 4) !== 'ftyp' || !in_array($mime, ['video/mp4', 'video/x-m4v', 'video/quicktime', 'application/octet-stream'], true)) {
         logosdaempresa_descartar($meta);
         logosdaempresa_json(['success' => false, 'mensagem' => 'O arquivo não é um vídeo MP4 válido.']);
      }

      $nome  = 'fundo_' . bin2hex(random_bytes(6)) . '.mp4';
      $final = PluginLogosdaempresaConfig::pastaVideos() . DIRECTORY_SEPARATOR . $nome;
      if (!@rename($meta['parte'], $final)) {
         logosdaempresa_descartar($meta);
         logosdaempresa_json(['success' => false, 'mensagem' => 'Não foi possível salvar o vídeo.']);
      }
      @chmod($final, 0644);
      @unlink($meta['json']);

      $anterior = PluginLogosdaempresaConfig::caminhoVideoFundo();
      PluginLogosdaempresaConfig::setConfig('fundo_login_video', $nome);
      PluginLogosdaempresaConfig::setConfig('fundo_login_tipo', 'video');
      if ($anterior !== null && $anterior !== $final) {
         @unlink($anterior);
      }

      // Primeiro fundo enviado: já passa a valer na tela de login
      $mensagem = 'Vídeo de fundo enviado.';
      if (!PluginLogosdaempresaConfig::fundoLoginAtivo()) {
         PluginLogosdaempresaConfig::setFundoLogin(true);
         $mensagem .= ' O fundo foi ativado na tela de login.';
         if (PluginLogosdaempresaConfig::layoutLateralAtivo()) {
            PluginLogosdaempresaConfig::setLayoutLateral(false);
            $mensagem .= ' O layout lateral foi desativado.';
         }
      }
      Session::addMessageAfterRedirect($mensagem, true, INFO);
      logosdaempresa_json(['success' => true, 'mensagem' => $mensagem]);

   case 'video_cancelar':
      logosdaempresa_descartar(logosdaempresa_envio($pastaEnvio));
      logosdaempresa_json(['success' => true]);

   default:
      logosdaempresa_json(['success' => false, 'mensagem' => 'Ação desconhecida.']);
}
