<?php

/**
 * Plugin Logos da Empresa para GLPI 11 e 12
 * Gerenciamento de logos personalizados, cor do tema e layout da tela de login
 */

use Glpi\Http\Firewall;

define('PLUGIN_LOGOSDAEMPRESA_VERSION', '1.4.1');

function plugin_init_logosdaempresa(): void {
   global $PLUGIN_HOOKS;

   // Chave literal: a constante Hooks::CSRF_COMPLIANT existe no GLPI 11 e foi removida no 12
   $PLUGIN_HOOKS['csrf_compliant']['logosdaempresa'] = true;

   // Nada é carregado por padrão: logos, cor, layout e rodapé só aparecem depois de configurados

   // Acesso apenas pelo ícone de configuração no marketplace
   if (Session::haveRight('config', UPDATE)) {
      $PLUGIN_HOOKS['config_page']['logosdaempresa'] = 'front/config.php';
   }

   // Injetar CSS do tema em todas as páginas (logadas)
   $PLUGIN_HOOKS['add_css']['logosdaempresa'] = 'front/tema.css.php';

   // Injetar CSS/JS na tela de login via hook display_login
   $PLUGIN_HOOKS['display_login']['logosdaempresa'] = 'plugin_logosdaempresa_display_login';

   // Liberar para usuários não autenticados: CSS do tema e imagens enviadas (logo e imagem lateral do login)
   Firewall::addPluginStrategyForLegacyScripts(
      'logosdaempresa',
      '#^/front/tema\.css\.php$#',
      Firewall::STRATEGY_NO_CHECK
   );
   Firewall::addPluginStrategyForLegacyScripts(
      'logosdaempresa',
      '#^/front/imagem\.php$#',
      Firewall::STRATEGY_NO_CHECK
   );

   Plugin::registerClass('PluginLogosdaempresaConfig');
   Plugin::registerClass('PluginLogosdaempresaMenu');
}

/**
 * Injeta CSS e estilos de logo/tema/layout na tela de login.
 * Estrutura da tela (GLPI 11 e 12): main.page-anonymous > div > .container-tight >
 *   [.text-center (logo), (main no 12 >) .main-content-card > (.card-header, .card-body > form), .text-muted (rodapé)]
 */
function plugin_logosdaempresa_display_login(): void {
   global $CFG_GLPI;

   $css = '';
   $js  = '';

   // =====================================================================
   // Parte 1: Override do logo da tela de login
   // =====================================================================
   // Só quando um logo de login foi enviado na configuração; sem envio, vale o logo do GLPI
   if (class_exists('PluginLogosdaempresaConfig')) {
      $logosLogin = [
         'logo-GLPI-250-black.png' => 'body.welcome-anonymous .glpi-logo',
         'logo-GLPI-250-white.png' => ':root[data-glpi-theme-dark="1"] body.welcome-anonymous .glpi-logo',
      ];
      foreach ($logosLogin as $arquivo => $seletor) {
         if (PluginLogosdaempresaConfig::logoFoiAlterado($arquivo)) {
            $css .= "{$seletor} {\n";
            $css .= '   content: url("' . PluginLogosdaempresaConfig::getUrlPersonalizado($arquivo) . "\") !important;\n";
            $css .= "   object-fit: contain !important;\n";
            $css .= "}\n";
         }
      }
   }
   // =====================================================================
   // Parte 2: Cor do tema na tela de login
   // =====================================================================
   if (class_exists('PluginLogosdaempresaConfig') && PluginLogosdaempresaConfig::corTemaAtiva()) {
      $css .= PluginLogosdaempresaConfig::cssCorTema(PluginLogosdaempresaConfig::getCorTema());
   }

   // =====================================================================
   // Parte 3: Layout lateral da tela de login
   // =====================================================================
   // =====================================================================
   // Parte 2b: Imagem de fundo na tela inteira, formulário ao lado
   // =====================================================================
   $fundoAtivo = class_exists('PluginLogosdaempresaConfig')
      && PluginLogosdaempresaConfig::fundoLoginAtivo()
      && PluginLogosdaempresaConfig::caminhoFundoLogin() !== null;
   if ($fundoAtivo) {
      $css .= PluginLogosdaempresaConfig::cssFundoLogin();
   }

   if (!$fundoAtivo && class_exists('PluginLogosdaempresaConfig') && PluginLogosdaempresaConfig::layoutLateralAtivo()) {
      $imgLateral    = PluginLogosdaempresaConfig::getConfig('layout_imagem_lateral', '');
      $imgLateralUrl = '';

      if (!empty($imgLateral)) {
         $pluginDocDir = GLPI_PLUGIN_DOC_DIR . DIRECTORY_SEPARATOR . 'logosdaempresa';
         if (file_exists($pluginDocDir . '/' . $imgLateral)) {
            $imgLateralUrl = $CFG_GLPI['root_doc'] . '/plugins/logosdaempresa/front/imagem.php?arquivo=' . urlencode($imgLateral)
               . '&v=' . filemtime($pluginDocDir . '/' . $imgLateral);
         }
      }

      $container = 'body.welcome-anonymous .page-anonymous .container-tight';
      $cartao    = 'body.welcome-anonymous .page-anonymous .main-content-card';

      // --- CSS Desktop ---
      $css .= "@media only screen and (min-width: 1025px) {\n";

      // Logo principal no topo: esconder (vai para dentro do formulário)
      $css .= "   {$container} > .text-center:first-child { display: none !important; }\n";

      // Área útil ocupando a tela
      $css .= "   body.welcome-anonymous .page-anonymous { min-height: 100vh; }\n";
      $css .= "   body.welcome-anonymous .page-anonymous > div {\n";
      $css .= "      min-height: 100vh;\n";
      $css .= "      padding-top: 0 !important;\n";
      $css .= "      padding-bottom: 0 !important;\n";
      $css .= "      margin-top: 0 !important;\n";
      $css .= "      display: flex;\n";
      $css .= "      align-items: center;\n";
      $css .= "      justify-content: center;\n";
      $css .= "   }\n";

      // Container do cartão
      $css .= "   {$container} {\n";
      $css .= "      max-width: unset !important;\n";
      $css .= "      width: 90vw !important;\n";
      $css .= "      padding-top: 0 !important;\n";
      $css .= "      padding-bottom: 0 !important;\n";
      $css .= "   }\n";

      // Cartão principal
      $css .= "   {$cartao} {\n";
      $css .= "      height: 80vh;\n";
      $css .= "      border: none !important;\n";
      $css .= "      border-radius: 8px !important;\n";
      $css .= "      overflow: hidden;\n";
      $css .= "      box-shadow: 0 4px 24px rgba(0,0,0,0.08);\n";
      $css .= "   }\n";

      // Cabeçalho ("Faça login"): esconder
      $css .= "   {$cartao} > .card-header { display: none !important; }\n";

      // Corpo: imagem à esquerda, formulário à direita
      $css .= "   {$cartao} > .card-body {\n";
      $css .= "      padding: 0 !important;\n";
      $css .= "      display: flex !important;\n";
      $css .= "      flex-direction: row !important;\n";
      $css .= "      overflow-y: hidden;\n";
      $css .= "      height: 100%;\n";
      $css .= "   }\n";

      // Imagem lateral (div inserida via JS)
      $css .= "   #logosdaempresa-img-lateral {\n";
      $css .= "      flex: 1;\n";
      $css .= "      min-height: 100%;\n";
      $css .= "      border-radius: 8px 0 0 8px;\n";
      if (!empty($imgLateralUrl)) {
         $css .= "      background-image: url(\"{$imgLateralUrl}\");\n";
         $css .= "      background-size: cover;\n";
         $css .= "      background-position: center;\n";
         $css .= "      background-repeat: no-repeat;\n";
      } else {
         $css .= "      background: rgba(0,0,0,0.05);\n";
      }
      $css .= "   }\n";

      // Formulário: coluna direita
      $css .= "   {$cartao} > .card-body > form {\n";
      $css .= "      overflow-y: auto;\n";
      $css .= "      flex: 0 0 400px;\n";
      $css .= "      height: 100%;\n";
      $css .= "      width: unset !important;\n";
      $css .= "      padding: 40px 50px !important;\n";
      $css .= "      box-shadow: -2px 0 12px rgba(0,0,0,0.06);\n";
      $css .= "      display: flex;\n";
      $css .= "      flex-direction: column;\n";
      $css .= "      justify-content: center;\n";
      $css .= "      align-items: stretch;\n";
      $css .= "   }\n";

      // Logo dentro do formulário
      $css .= "   {$cartao} > .card-body > form .glpi-logo {\n";
      $css .= "      width: 180px !important;\n";
      $css .= "      height: 100px !important;\n";
      $css .= "      margin: 0 auto 20px auto !important;\n";
      $css .= "      display: block !important;\n";
      $css .= "   }\n";

      $css .= "   {$cartao} > .card-body > form h2 { display: none !important; }\n";

      // Campos do formulário na largura toda
      $css .= "   {$cartao} > .card-body > form .row { margin: 0 !important; }\n";
      $css .= "   {$cartao} > .card-body > form .row > div {\n";
      $css .= "      width: 100% !important;\n";
      $css .= "      flex: 0 0 100% !important;\n";
      $css .= "      max-width: 100% !important;\n";
      $css .= "      padding: 0 !important;\n";
      $css .= "   }\n";

      $css .= "}\n";

      // --- CSS Mobile ---
      $css .= "@media only screen and (max-width: 1024px) {\n";
      $css .= "   #logosdaempresa-img-lateral { display: none !important; }\n";
      $css .= "}\n";

      // --- CSS telas baixas ---
      $css .= "@media only screen and (max-height: 600px) {\n";
      $css .= "   {$cartao} > .card-body > form .glpi-logo { display: none !important; }\n";
      $css .= "}\n";

      // --- JavaScript: imagem lateral e logo dentro do formulário (após montar a página) ---
      $js .= "document.addEventListener('DOMContentLoaded', function(){\n";
      $js .= "   var cardBody = document.querySelector('{$cartao} > .card-body');\n";
      $js .= "   if(!cardBody || document.getElementById('logosdaempresa-img-lateral')){ return; }\n";
      $js .= "   var divImg = document.createElement('div');\n";
      $js .= "   divImg.id = 'logosdaempresa-img-lateral';\n";
      $js .= "   cardBody.insertBefore(divImg, cardBody.firstChild);\n";
      $js .= "   var logoFora = document.querySelector('{$container} > .text-center:first-child .glpi-logo');\n";
      $js .= "   var formEl = cardBody.querySelector('form');\n";
      $js .= "   if(logoFora && formEl){\n";
      $js .= "      var logoContainer = document.createElement('div');\n";
      $js .= "      logoContainer.style.textAlign = 'center';\n";
      $js .= "      logoContainer.style.marginBottom = '20px';\n";
      $js .= "      logoContainer.appendChild(logoFora);\n";
      $js .= "      formEl.insertBefore(logoContainer, formEl.firstChild);\n";
      $js .= "   }\n";
      $js .= "});\n";
   }

   // =====================================================================
   // Parte 4: Rodapé personalizado
   // =====================================================================
   if (class_exists('PluginLogosdaempresaConfig')) {
      $textoRodapeConfig = PluginLogosdaempresaConfig::getConfig('texto_rodape_login', '');
      $layoutAtivo = PluginLogosdaempresaConfig::layoutLateralAtivo();

      if ($layoutAtivo || !empty($textoRodapeConfig)) {
         $js .= "document.addEventListener('DOMContentLoaded', function(){\n";
         $js .= "   var rodapeOriginal = document.querySelector('body.welcome-anonymous .container-tight > .text-muted');\n";
         $js .= "   if(rodapeOriginal){ rodapeOriginal.style.display = 'none'; }\n";

         if (!empty($textoRodapeConfig)) {
            $js .= "   var textoRodape = " . json_encode($textoRodapeConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ";\n";
         } else {
            $js .= "   var textoRodape = rodapeOriginal ? rodapeOriginal.textContent.trim() : '';\n";
         }

         $js .= "   if(textoRodape && !document.getElementById('logosdaempresa-rodape')){\n";
         $js .= "      var novoRodape = document.createElement('div');\n";
         $js .= "      novoRodape.id = 'logosdaempresa-rodape';\n";
         $js .= "      novoRodape.textContent = textoRodape;\n";
         $js .= "      novoRodape.style.cssText = 'width:100%;font-size:13px;font-weight:600;color:#888;text-align:center;padding:8px 0 0 0;';\n";
         $js .= "      var cardContainer = document.querySelector('body.welcome-anonymous .container-tight');\n";
         $js .= "      if(cardContainer){ cardContainer.appendChild(novoRodape); } else { document.body.appendChild(novoRodape); }\n";
         $js .= "   }\n";
         $js .= "});\n";
      }
   }

   // =====================================================================
   // Injetar na página
   // =====================================================================
   if (!empty($css)) {
      echo '<style>' . $css . '</style>';
   }

   if (!empty($js)) {
      echo '<script>' . $js . '</script>';
   }
}

function plugin_version_logosdaempresa(): array {
   return [
      'name'           => 'Logos da Empresa',
      'version'        => PLUGIN_LOGOSDAEMPRESA_VERSION,
      'author'         => 'GLPI Salvador',
      'license'        => 'GPLv3',
      'homepage'       => '',
      'requirements'   => [
         'glpi' => [
            'min' => '11.0.0',
            'max' => '12.99.99',
         ],
         'php'  => [
            'min' => '8.2',
         ],
      ],
   ];
}

function plugin_logosdaempresa_check_prerequisites(): bool {
   if (!extension_loaded('gd')) {
      echo "Extensão GD do PHP é necessária para redimensionar imagens.";
      return false;
   }
   if (!class_exists('ZipArchive')) {
      echo "Extensão ZipArchive do PHP é necessária para download dos logos.";
      return false;
   }
   return true;
}

function plugin_logosdaempresa_check_config(): bool {
   return true;
}
