<?php

// Carregado pelo GLPI 11/12 (inc/includes.php e obsoleto)

Session::checkLoginUser();

if (!Session::haveRight('config', UPDATE)) {
   throw new \Glpi\Exception\Http\AccessDeniedHttpException();
}

// Arquivo acima do post_max_size do PHP: o POST chega vazio
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
   Session::addMessageAfterRedirect(
      sprintf(__('O arquivo é maior que o permitido pelo servidor (%s).', 'logosdaempresa'), ini_get('post_max_size')),
      false,
      ERROR
   );
}

if (isset($_POST['save_action'])) {
   switch ($_POST['save_action']) {

      case 'upload_logo':
         if (
            isset($_POST['arquivo_logo'])
            && isset($_FILES['logo_file'])
            && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK
         ) {
            $arquivo = $_POST['arquivo_logo'];

            $permitidos = [];
            foreach (PluginLogosdaempresaConfig::LOGOS_MAP as $grupo) {
               foreach ($grupo['arquivos'] as $arq) {
                  $permitidos[] = $arq;
               }
            }

            if (in_array($arquivo, $permitidos)) {
               PluginLogosdaempresaConfig::processarUpload($arquivo, $_FILES['logo_file']);
            } else {
               Session::addMessageAfterRedirect(
                  __('Arquivo de logo não permitido.', 'logosdaempresa'),
                  false,
                  ERROR
               );
            }
         } else {
            Session::addMessageAfterRedirect(
               __('Nenhum arquivo enviado ou erro no upload.', 'logosdaempresa'),
               false,
               ERROR
            );
         }
         break;

      case 'upload_grupo':
         // Uma imagem para todas as variantes (branco, preto e cinza) do grupo
         $grupoId = (string) ($_POST['grupo_id'] ?? '');
         if (
            isset(PluginLogosdaempresaConfig::LOGOS_MAP[$grupoId])
            && isset($_FILES['logo_file'])
            && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK
         ) {
            PluginLogosdaempresaConfig::processarUpload(
               PluginLogosdaempresaConfig::LOGOS_MAP[$grupoId]['arquivos'][0],
               $_FILES['logo_file'],
               true
            );
         } else {
            Session::addMessageAfterRedirect(
               __('Nenhum arquivo enviado ou erro no upload.', 'logosdaempresa'),
               false,
               ERROR
            );
         }
         break;

      case 'restaurar_logo':
         if (isset($_POST['arquivo_logo'])) {
            $arquivo    = $_POST['arquivo_logo'];
            $permitidos = [];
            foreach (PluginLogosdaempresaConfig::LOGOS_MAP as $grupo) {
               foreach ($grupo['arquivos'] as $arq) {
                  $permitidos[] = $arq;
               }
            }

            if (in_array($arquivo, $permitidos)) {
               PluginLogosdaempresaConfig::restaurarLogo($arquivo);
            } else {
               Session::addMessageAfterRedirect(
                  __('Arquivo de logo não permitido.', 'logosdaempresa'),
                  false,
                  ERROR
               );
            }
         }
         break;

      case 'restaurar_grupo':
         if (isset($_POST['grupo_id'])) {
            PluginLogosdaempresaConfig::restaurarGrupo($_POST['grupo_id']);
         }
         break;

      case 'salvar_cor_tema':
         if (isset($_POST['cor_tema'])) {
            $cor = trim($_POST['cor_tema']);
            if (PluginLogosdaempresaConfig::setCorTema($cor)) {
               Session::addMessageAfterRedirect(
                  __('Cor do tema aplicada com sucesso.', 'logosdaempresa'),
                  true,
                  INFO
               );
            }
         }
         break;

      case 'remover_cor_tema':
         if (PluginLogosdaempresaConfig::setCorTema('')) {
            Session::addMessageAfterRedirect(
               __('Cor personalizada removida. Tema padrão do GLPI restaurado.', 'logosdaempresa'),
               true,
               INFO
            );
         }
         break;

      case 'toggle_layout_lateral':
         $ativoAtual = PluginLogosdaempresaConfig::layoutLateralAtivo();
         PluginLogosdaempresaConfig::setLayoutLateral(!$ativoAtual);
         // Layout lateral e imagem de fundo são arranjos diferentes da tela: só um por vez
         if (!$ativoAtual && PluginLogosdaempresaConfig::fundoLoginAtivo()) {
            PluginLogosdaempresaConfig::setFundoLogin(false);
            Session::addMessageAfterRedirect(__('A imagem de fundo da tela de login foi desativada.', 'logosdaempresa'), true, INFO);
         }
         Session::addMessageAfterRedirect(
            $ativoAtual
               ? __('Layout lateral desativado.', 'logosdaempresa')
               : __('Layout lateral ativado.', 'logosdaempresa'),
            true,
            INFO
         );
         break;

      case 'upload_imagem_lateral':
         if (
            isset($_FILES['imagem_lateral'])
            && $_FILES['imagem_lateral']['error'] === UPLOAD_ERR_OK
         ) {
            PluginLogosdaempresaConfig::processarUploadImagemLateral($_FILES['imagem_lateral']);
         } else {
            Session::addMessageAfterRedirect(
               __('Nenhum arquivo enviado ou erro no upload.', 'logosdaempresa'),
               false,
               ERROR
            );
         }
         break;

      case 'upload_fundo_login':
         $erroUpload = $_FILES['fundo_login']['error'] ?? UPLOAD_ERR_NO_FILE;
         if ($erroUpload === UPLOAD_ERR_OK) {
            if (PluginLogosdaempresaConfig::processarUploadFundoLogin($_FILES['fundo_login'])) {
               // Primeira imagem enviada: já passa a valer na tela de login
               if (!PluginLogosdaempresaConfig::fundoLoginAtivo()) {
                  PluginLogosdaempresaConfig::setFundoLogin(true);
                  if (PluginLogosdaempresaConfig::layoutLateralAtivo()) {
                     PluginLogosdaempresaConfig::setLayoutLateral(false);
                     Session::addMessageAfterRedirect(__('O layout lateral foi desativado para usar a imagem de fundo.', 'logosdaempresa'), true, INFO);
                  }
               }
            }
         } elseif (in_array($erroUpload, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            Session::addMessageAfterRedirect(
               sprintf(__('A imagem é maior que o permitido pelo servidor (%s). Reduza a imagem e envie de novo.', 'logosdaempresa'), ini_get('upload_max_filesize')),
               false,
               ERROR
            );
         } else {
            Session::addMessageAfterRedirect(__('Nenhum arquivo enviado ou erro no upload.', 'logosdaempresa'), false, ERROR);
         }
         break;

      case 'remover_fundo_login':
         PluginLogosdaempresaConfig::removerFundoLogin();
         break;

      case 'toggle_fundo_login':
         $ativarFundo = !PluginLogosdaempresaConfig::fundoLoginAtivo();
         if ($ativarFundo && PluginLogosdaempresaConfig::caminhoFundoLogin() === null) {
            Session::addMessageAfterRedirect(__('Envie uma imagem de fundo antes de ativar.', 'logosdaempresa'), false, WARNING);
            break;
         }
         PluginLogosdaempresaConfig::setFundoLogin($ativarFundo);
         if ($ativarFundo && PluginLogosdaempresaConfig::layoutLateralAtivo()) {
            PluginLogosdaempresaConfig::setLayoutLateral(false);
            Session::addMessageAfterRedirect(__('O layout lateral foi desativado para usar a imagem de fundo.', 'logosdaempresa'), true, INFO);
         }
         Session::addMessageAfterRedirect(
            $ativarFundo ? __('Imagem de fundo ativada na tela de login.', 'logosdaempresa') : __('Imagem de fundo desativada.', 'logosdaempresa'),
            true,
            INFO
         );
         break;

      case 'salvar_opcoes_fundo':
         PluginLogosdaempresaConfig::salvarOpcoesFundoLogin($_POST);
         break;

      case 'remover_imagem_lateral':
         PluginLogosdaempresaConfig::removerImagemLateral();
         break;
      case 'salvar_texto_rodape':
         $texto = isset($_POST['texto_rodape_login']) ? trim($_POST['texto_rodape_login']) : '';
         PluginLogosdaempresaConfig::setConfig('texto_rodape_login', $texto);
         Session::addMessageAfterRedirect(
            __('Texto de rodapé atualizado.', 'logosdaempresa'),
            true,
            INFO
         );
         break;

      case 'remover_texto_rodape':
         PluginLogosdaempresaConfig::setConfig('texto_rodape_login', '');
         Session::addMessageAfterRedirect(
            __('Texto de rodapé removido. Será usado o padrão do GLPI.', 'logosdaempresa'),
            true,
            INFO
         );
         break;

   }
}

// Página intermediária que limpa cache das imagens e redireciona
global $CFG_GLPI;
$urlConfig = $CFG_GLPI['root_doc'] . '/plugins/logosdaempresa/front/config.php';
$urlLogos  = $CFG_GLPI['root_doc'] . '/pics/logos';

$logos = [
   'logo-GLPI-100-white.png',
   'logo-GLPI-100-black.png',
   'logo-GLPI-100-grey.png',
   'logo-G-100-white.png',
   'logo-G-100-black.png',
   'logo-G-100-grey.png',
   'logo-GLPI-250-white.png',
   'logo-GLPI-250-black.png',
   'logo-GLPI-250-grey.png',
];

echo '<!DOCTYPE html><html><head><meta charset="utf-8">';
echo '<title>Atualizando logos...</title>';
echo '<style>';
echo 'body{display:flex;align-items:center;justify-content:center;height:100vh;margin:0;';
echo 'font-family:sans-serif;background:#f8f9fa;color:#666;font-size:13px}';
echo '</style>';
echo '</head><body>';
echo '<div>Aplicando alterações...</div>';
echo '<script>';
echo 'async function limparCacheERedirecionar(){';
echo '  var logos=' . json_encode($logos) . ';';
echo '  var base="' . $urlLogos . '";';
echo '  var promises=[];';
echo '  for(var i=0;i<logos.length;i++){';
echo '    var url=base+"/"+logos[i];';
echo '    promises.push(fetch(url,{cache:"reload",mode:"no-cors"}).catch(function(){}));';
echo '  }';
echo '  await Promise.all(promises);';
echo '  if("caches" in window){';
echo '    var keys=await caches.keys();';
echo '    for(var j=0;j<keys.length;j++){';
echo '      var cache=await caches.open(keys[j]);';
echo '      for(var k=0;k<logos.length;k++){';
echo '        await cache.delete(base+"/"+logos[k]).catch(function(){});';
echo '      }';
echo '    }';
echo '  }';
echo '  window.location.replace("' . $urlConfig . '");';
echo '}';
echo 'limparCacheERedirecionar();';
echo '</script>';
echo '</body></html>';
exit;