<?php

class PluginLogosdaempresaConfig extends CommonDBTM {

   // $rightname nao e redeclarada: e tipada (string) no GLPI 12 e sem tipo no GLPI 11.
   // Os direitos sao verificados pelos metodos can*().

   /**
    * GLPI 11 exige token CSRF nos formularios; no 12 a protecao e por cabecalho e o token foi removido
    */
   static function tokenCsrf(): string {
      return version_compare(GLPI_VERSION, '12.0.0-dev', '<') ? Session::getNewCSRFToken() : '';
   }

   /**
    * Variaveis da cor da empresa para todas as paletas do GLPI (claras e escuras).
    * :root[data-glpi-theme] tem a mesma especificidade das paletas e vem depois, entao prevalece.
    */
   static function cssCorTema(string $corHex): string {
      $css = ":root, :root[data-glpi-theme] {\n";
      foreach (self::gerarVariaveisCss($corHex) as $prop => $valor) {
         $css .= "   {$prop}: {$valor};\n";
      }
      return $css . "}\n";
   }
   static $table = 'glpi_plugin_logosdaempresa_configs';

   const LOGOS_MAP = [
      'sidebar_expandida' => [
         'titulo'  => 'Sidebar expandida (canto superior esquerdo)',
         'largura' => 100,
         'altura'  => 55,
         'arquivos' => [
            'logo-GLPI-100-white.png',
            'logo-GLPI-100-black.png',
            'logo-GLPI-100-grey.png',
         ],
      ],
      'sidebar_colapsada' => [
         'titulo'  => 'Sidebar colapsada (menu recolhido)',
         'largura' => 40,
         'altura'  => 40,
         'arquivos' => [
            'logo-G-100-white.png',
            'logo-G-100-black.png',
            'logo-G-100-grey.png',
         ],
      ],
      'tela_login' => [
         'titulo'  => 'Tela de login (acima do formulário)',
         'largura' => 250,
         'altura'  => 138,
         'unificado' => true,
         'arquivos' => [
            'logo-GLPI-250-black.png',
            'logo-GLPI-250-white.png',
            'logo-GLPI-250-grey.png',
         ],
      ],
   ];

   const VARIANTES_COR = [
      'white' => 'Branco (tema escuro)',
      'black' => 'Preto (tema claro / login)',
      'grey'  => 'Cinza (contraste)',
   ];

   // =========================================================================
   // Métodos de configuração (banco de dados)
   // =========================================================================

   static function getConfig(string $name, $default = null) {
      global $DB;

      $tabela = 'glpi_plugin_logosdaempresa_configs';
      if (!$DB->tableExists($tabela)) {
         return $default;
      }

      $iterator = $DB->request([
         'SELECT' => ['value'],
         'FROM'   => $tabela,
         'WHERE'  => ['name' => $name],
         'LIMIT'  => 1,
      ]);

      if (count($iterator)) {
         foreach ($iterator as $row) {
            return $row['value'];
         }
      }

      return $default;
   }

   static function setConfig(string $name, $value): bool {
      global $DB;

      $tabela = 'glpi_plugin_logosdaempresa_configs';

      $iterator = $DB->request([
         'SELECT' => ['id'],
         'FROM'   => $tabela,
         'WHERE'  => ['name' => $name],
         'LIMIT'  => 1,
      ]);

      if (count($iterator)) {
         return $DB->update($tabela, ['value' => $value], ['name' => $name]);
      } else {
         return $DB->insert($tabela, ['name' => $name, 'value' => $value]);
      }
   }

   // =========================================================================
   // Métodos de cor do tema
   // =========================================================================

   static function getCorTema(): ?string {
      return self::getConfig('cor_tema', null);
   }

   static function setCorTema(?string $cor): bool {
      if ($cor === null || $cor === '') {
         return self::setConfig('cor_tema', '');
      }
      if (!preg_match('/^#[0-9a-fA-F]{6}$/', $cor)) {
         Session::addMessageAfterRedirect(
            __('Formato de cor inválido. Use o formato hexadecimal (#RRGGBB).', 'logosdaempresa'),
            false,
            ERROR
         );
         return false;
      }
      return self::setConfig('cor_tema', $cor);
   }

   static function corTemaAtiva(): bool {
      $cor = self::getCorTema();
      return !empty($cor) && preg_match('/^#[0-9a-fA-F]{6}$/', $cor);
   }

   // =========================================================================
   // Métodos de layout da tela de login
   // =========================================================================

   static function layoutLateralAtivo(): bool {
      return self::getConfig('layout_lateral_ativo', '0') === '1';
   }

   static function setLayoutLateral(bool $ativo): bool {
      return self::setConfig('layout_lateral_ativo', $ativo ? '1' : '0');
   }

   static function processarUploadImagemLateral(array $dadosArquivo): bool {
      $mimePermitidos = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];
      $mimeEnviado    = mime_content_type($dadosArquivo['tmp_name']);

      if (!in_array($mimeEnviado, $mimePermitidos)) {
         Session::addMessageAfterRedirect(
            __('Formato não suportado. Use PNG, JPG, GIF ou WebP.', 'logosdaempresa'),
            false,
            ERROR
         );
         return false;
      }

      $pluginDocDir = GLPI_PLUGIN_DOC_DIR . DIRECTORY_SEPARATOR . 'logosdaempresa';
      if (!is_dir($pluginDocDir)) {
         @mkdir($pluginDocDir, 0755, true);
      }

      // Remover imagem anterior
      $imagemAnterior = self::getConfig('layout_imagem_lateral', '');
      if (!empty($imagemAnterior) && file_exists($pluginDocDir . '/' . $imagemAnterior)) {
         @unlink($pluginDocDir . '/' . $imagemAnterior);
      }

      $extensao    = pathinfo($dadosArquivo['name'], PATHINFO_EXTENSION) ?: 'png';
      $nomeArquivo = 'lateral_' . uniqid() . '.' . $extensao;
      $destino     = $pluginDocDir . '/' . $nomeArquivo;

      if (!move_uploaded_file($dadosArquivo['tmp_name'], $destino)) {
         Session::addMessageAfterRedirect(
            __('Erro ao salvar a imagem lateral.', 'logosdaempresa'),
            false,
            ERROR
         );
         return false;
      }

      self::setConfig('layout_imagem_lateral', $nomeArquivo);

      Session::addMessageAfterRedirect(
         __('Imagem lateral atualizada com sucesso.', 'logosdaempresa'),
         true,
         INFO
      );
      return true;
   }

   static function removerImagemLateral(): bool {
      $pluginDocDir   = GLPI_PLUGIN_DOC_DIR . DIRECTORY_SEPARATOR . 'logosdaempresa';
      $imagemAnterior = self::getConfig('layout_imagem_lateral', '');

      if (!empty($imagemAnterior) && file_exists($pluginDocDir . '/' . $imagemAnterior)) {
         @unlink($pluginDocDir . '/' . $imagemAnterior);
      }

      self::setConfig('layout_imagem_lateral', '');

      Session::addMessageAfterRedirect(
         __('Imagem lateral removida.', 'logosdaempresa'),
         true,
         INFO
      );
      return true;
   }

   // =========================================================================
   // Imagem de fundo da tela de login (formulário ao lado)
   // =========================================================================

   const FUNDO_POSICOES = ['direita' => 'Direita', 'esquerda' => 'Esquerda', 'centro' => 'Centro'];
   const FUNDO_ESTILOS  = ['painel' => 'Painel lateral de altura inteira', 'cartao' => 'Cartão flutuante'];
   const FUNDO_MODOS    = ['tela' => 'Tela inteira (formulário por cima da imagem)', 'area' => 'Área separada ao lado do formulário'];

   static function fundoLoginAtivo(): bool {
      return self::getConfig('fundo_login_ativo', '0') === '1';
   }

   static function setFundoLogin(bool $ativo): bool {
      return self::setConfig('fundo_login_ativo', $ativo ? '1' : '0');
   }

   /** Caminho da imagem de fundo enviada (null se não houver) */
   static function caminhoFundoLogin(): ?string {
      $arquivo = (string) self::getConfig('fundo_login_imagem', '');
      if ($arquivo === '') {
         return null;
      }
      $caminho = GLPI_PLUGIN_DOC_DIR . DIRECTORY_SEPARATOR . 'logosdaempresa' . DIRECTORY_SEPARATOR . $arquivo;
      return is_file($caminho) ? $caminho : null;
   }

   static function getUrlFundoLogin(): string {
      global $CFG_GLPI;
      $caminho = self::caminhoFundoLogin();
      if ($caminho === null) {
         return '';
      }
      return $CFG_GLPI['root_doc'] . '/plugins/logosdaempresa/front/imagem.php?arquivo='
         . urlencode(basename($caminho)) . '&v=' . filemtime($caminho);
   }

   /** Opções do fundo com valores válidos (posição, estilo e escurecimento 0-70%) */
   static function opcoesFundoLogin(): array {
      $posicao = (string) self::getConfig('fundo_login_posicao', 'direita');
      $estilo  = (string) self::getConfig('fundo_login_estilo', 'painel');
      return [
         'posicao'    => isset(self::FUNDO_POSICOES[$posicao]) ? $posicao : 'direita',
         'estilo'     => isset(self::FUNDO_ESTILOS[$estilo]) ? $estilo : 'painel',
         'escurecer'  => max(0, min(70, (int) self::getConfig('fundo_login_escurecer', '20'))),
         'modo'       => self::getConfig('fundo_login_modo', 'tela') === 'area' ? 'area' : 'tela',
         'largura'    => max(30, min(75, (int) self::getConfig('fundo_login_area_largura', '55'))),
         'moldura'    => self::getConfig('fundo_login_area_moldura', '0') === '1',
      ];
   }

   static function salvarOpcoesFundoLogin(array $dados): bool {
      $posicao   = (string) ($dados['fundo_login_posicao'] ?? 'direita');
      $estilo    = (string) ($dados['fundo_login_estilo'] ?? 'painel');
      $escurecer = max(0, min(70, (int) ($dados['fundo_login_escurecer'] ?? 20)));
      self::setConfig('fundo_login_posicao', isset(self::FUNDO_POSICOES[$posicao]) ? $posicao : 'direita');
      self::setConfig('fundo_login_estilo', isset(self::FUNDO_ESTILOS[$estilo]) ? $estilo : 'painel');
      self::setConfig('fundo_login_escurecer', (string) $escurecer);
      self::setConfig('fundo_login_modo', ($dados['fundo_login_modo'] ?? 'tela') === 'area' ? 'area' : 'tela');
      self::setConfig('fundo_login_area_largura', (string) max(30, min(75, (int) ($dados['fundo_login_area_largura'] ?? 55))));
      self::setConfig('fundo_login_area_moldura', !empty($dados['fundo_login_area_moldura']) ? '1' : '0');
      Session::addMessageAfterRedirect(__('Opções da imagem de fundo salvas.', 'logosdaempresa'), true, INFO);
      return true;
   }

   /**
    * Guarda a imagem de fundo. Imagens acima de 2560 x 1600 são reduzidas (JPEG 85%, ou PNG se tiver transparência)
    * para a tela de login continuar leve.
    */
   static function processarUploadFundoLogin(array $dadosArquivo): bool {
      $mimePermitidos = ['image/png', 'image/jpeg', 'image/webp'];
      $mimeEnviado    = mime_content_type($dadosArquivo['tmp_name']);
      if (!in_array($mimeEnviado, $mimePermitidos, true)) {
         Session::addMessageAfterRedirect(__('Formato não suportado. Use JPG, PNG ou WebP.', 'logosdaempresa'), false, ERROR);
         return false;
      }

      $pasta = GLPI_PLUGIN_DOC_DIR . DIRECTORY_SEPARATOR . 'logosdaempresa';
      if (!is_dir($pasta)) {
         @mkdir($pasta, 0755, true);
      }
      if (!is_dir($pasta) || !is_writable($pasta)) {
         Session::addMessageAfterRedirect(__('Sem permissão de escrita na pasta de dados do plugin.', 'logosdaempresa') . ' ' . $pasta, false, ERROR);
         return false;
      }

      $imagem = match ($mimeEnviado) {
         'image/png'  => @imagecreatefrompng($dadosArquivo['tmp_name']),
         'image/jpeg' => @imagecreatefromjpeg($dadosArquivo['tmp_name']),
         'image/webp' => @imagecreatefromwebp($dadosArquivo['tmp_name']),
      };
      if (!$imagem) {
         Session::addMessageAfterRedirect(__('Erro ao processar a imagem enviada.', 'logosdaempresa'), false, ERROR);
         return false;
      }

      $w = imagesx($imagem);
      $h = imagesy($imagem);
      $escala = min(1, 2560 / $w, 1600 / $h);
      $mensagem = __('Imagem de fundo atualizada.', 'logosdaempresa');
      if ($escala < 1) {
         $nw = (int) round($w * $escala);
         $nh = (int) round($h * $escala);
         $menor = imagecreatetruecolor($nw, $nh);
         imagealphablending($menor, false);
         imagesavealpha($menor, true);
         imagecopyresampled($menor, $imagem, 0, 0, 0, 0, $nw, $nh, $w, $h);
         imagedestroy($imagem);
         $imagem = $menor;
         $mensagem .= ' ' . sprintf(__('Reduzida de %1$d x %2$d para %3$d x %4$d px para a tela carregar rápido.', 'logosdaempresa'), $w, $h, $nw, $nh);
      }

      $comTransparencia = $mimeEnviado === 'image/png' && self::temTransparencia($imagem);
      $nome    = 'fundo_' . uniqid() . ($comTransparencia ? '.png' : '.jpg');
      $destino = $pasta . DIRECTORY_SEPARATOR . $nome;
      $gravou  = $comTransparencia ? imagepng($imagem, $destino, 9) : imagejpeg($imagem, $destino, 85);
      imagedestroy($imagem);

      if (!$gravou) {
         Session::addMessageAfterRedirect(__('Erro ao salvar a imagem de fundo.', 'logosdaempresa'), false, ERROR);
         return false;
      }

      $anterior = self::caminhoFundoLogin();
      if ($anterior !== null) {
         @unlink($anterior);
      }
      self::setConfig('fundo_login_imagem', $nome);
      Session::addMessageAfterRedirect($mensagem, true, INFO);
      return true;
   }

   /**
    * Formulário dentro do painel: o cartão do GLPI se mistura ao painel e os campos usam a largura toda
    * (o GLPI usa col-md-5, pensado para o cartão largo de 60rem, e reserva uma coluna para o hook display_login)
    */
   static function cssFormularioNoPainel(string $painel): string {
      $css  = "{$painel} .main-content-card {\n   border: 0 !important;\n   box-shadow: none !important;\n   background: transparent !important;\n}\n";
      $css .= "{$painel} .main-content-card > .card-body {\n   padding: 8px 0 !important;\n}\n";
      $css .= "{$painel} .main-content-card form > .row {\n   margin: 0 !important;\n}\n";
      $css .= "{$painel} .main-content-card form > .row > div {\n";
      $css .= "   flex: 0 0 100% !important;\n   max-width: 100% !important;\n   width: 100% !important;\n   padding-left: 0 !important;\n   padding-right: 0 !important;\n";
      $css .= "}\n";
      $css .= "{$painel} .main-content-card form .card-header h2 {\n   white-space: normal;\n}\n";
      return $css;
   }

   /**
    * Modo "área separada": a imagem ocupa só uma faixa da tela (largura configurável, com moldura opcional)
    * e o formulário fica na outra parte, sobre o fundo normal do GLPI.
    */
   static function cssFundoLoginArea(string $url, array $o, string $escuro, string $pagina, string $painel): string {
      // A imagem fica do lado oposto ao formulário (centro: formulário à direita)
      $ladoImagem = $o['posicao'] === 'esquerda' ? 'right' : 'left';
      $largura    = $o['largura'];
      $margem     = $o['moldura'] ? 16 : 0;
      $raio       = $o['moldura'] ? 12 : 0;

      $css  = "body.welcome-anonymous::before {\n";
      $css .= "   content: '';\n   position: fixed;\n   top: {$margem}px;\n   bottom: {$margem}px;\n   {$ladoImagem}: {$margem}px;\n";
      $css .= "   width: calc({$largura}% - " . ($margem * 2) . "px);\n";
      $css .= "   background: linear-gradient(rgba(0, 0, 0, {$escuro}), rgba(0, 0, 0, {$escuro})), #222 url(\"{$url}\") center center / cover no-repeat;\n";
      $css .= "   border-radius: {$raio}px;\n   pointer-events: none;\n   z-index: 0;\n";
      $css .= "}\n";
      $css .= "{$pagina} {\n   position: relative;\n   z-index: 1;\n   min-height: 100vh;\n}\n";
      $css .= "{$pagina} > div {\n";
      $css .= "   min-height: 100vh;\n   margin-top: 0 !important;\n   padding: 0 !important;\n";
      $css .= "   margin-{$ladoImagem}: {$largura}% !important;\n";
      $css .= "   flex-direction: row !important;\n   align-items: center !important;\n   justify-content: center !important;\n";
      $css .= "}\n";

      $css .= "{$painel} {\n   margin: 0 auto !important;\n   width: 440px;\n   max-width: calc(100% - 32px) !important;\n";
      if ($o['estilo'] === 'cartao') {
         $css .= "   padding: 24px 28px !important;\n   border-radius: 10px;\n";
         $css .= "   background: rgba(255, 255, 255, 0.97);\n   box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);\n";
      } else {
         $css .= "   padding: 24px 8px !important;\n";
      }
      $css .= "}\n";
      if ($o['estilo'] === 'cartao') {
         $css .= ":root[data-glpi-theme-dark=\"1\"] {$painel} {\n   background: rgba(24, 27, 34, 0.94);\n}\n";
      }

      $css .= self::cssFormularioNoPainel($painel);

      // Telas estreitas: só o formulário
      $css .= "@media only screen and (max-width: 900px) {\n";
      $css .= "   body.welcome-anonymous::before { display: none; }\n";
      $css .= "   {$pagina} > div { margin-left: 0 !important; margin-right: 0 !important; }\n";
      $css .= "}\n";

      return $css;
   }
   /** Amostra a imagem procurando pixels transparentes (evita varrer imagens grandes pixel a pixel) */
   static function temTransparencia(\GdImage $imagem): bool {
      $w = imagesx($imagem);
      $h = imagesy($imagem);
      $passoX = max(1, (int) ($w / 60));
      $passoY = max(1, (int) ($h / 60));
      for ($y = 0; $y < $h; $y += $passoY) {
         for ($x = 0; $x < $w; $x += $passoX) {
            if (((imagecolorat($imagem, $x, $y) >> 24) & 0x7F) > 0) {
               return true;
            }
         }
      }
      return false;
   }

   static function removerFundoLogin(): bool {
      $caminho = self::caminhoFundoLogin();
      if ($caminho !== null) {
         @unlink($caminho);
      }
      self::setConfig('fundo_login_imagem', '');
      self::setFundoLogin(false);
      Session::addMessageAfterRedirect(__('Imagem de fundo removida.', 'logosdaempresa'), true, INFO);
      return true;
   }

   /**
    * CSS da tela de login com imagem de fundo e o formulário ao lado (GLPI 11 e 12:
    * main.page-anonymous > div.flex-fill > .container-tight [logo, cartão, rodapé])
    */
   static function cssFundoLogin(): string {
      $url = self::getUrlFundoLogin();
      if ($url === '') {
         return '';
      }
      $o = self::opcoesFundoLogin();
      $alinhar = ['direita' => 'flex-end', 'esquerda' => 'flex-start', 'centro' => 'center'][$o['posicao']];
      $escuro  = number_format($o['escurecer'] / 100, 2, '.', '');
      $pagina  = 'body.welcome-anonymous .page-anonymous';
      $painel  = $pagina . ' .container-tight';

      if ($o['modo'] === 'area') {
         return self::cssFundoLoginArea($url, $o, $escuro, $pagina, $painel);
      }

      $css  = "body.welcome-anonymous {\n";
      $css .= "   background: #222 url(\"{$url}\") center center / cover no-repeat fixed !important;\n";
      $css .= "   min-height: 100vh;\n";
      $css .= "}\n";
      $css .= "body.welcome-anonymous::before {\n";
      $css .= "   content: '';\n   position: fixed;\n   inset: 0;\n   background: rgba(0, 0, 0, {$escuro});\n   pointer-events: none;\n   z-index: 0;\n";
      $css .= "}\n";
      $css .= "{$pagina} {\n   background: transparent !important;\n   position: relative;\n   z-index: 1;\n   min-height: 100vh;\n}\n";
      $css .= "{$pagina} > div {\n";
      $css .= "   min-height: 100vh;\n   margin-top: 0 !important;\n   padding: 0 !important;\n";
      $css .= "   flex-direction: row !important;\n   align-items: center !important;\n   justify-content: {$alinhar} !important;\n";
      $css .= "}\n";

      if ($o['estilo'] === 'painel') {
         $css .= "{$painel} {\n";
         $css .= "   margin: 0 !important;\n   width: 520px;\n   max-width: 100% !important;\n   min-height: 100vh;\n";
         $css .= "   display: flex;\n   flex-direction: column;\n   justify-content: center;\n";
         $css .= "   padding: 32px 48px !important;\n";
         $css .= "   background: rgba(255, 255, 255, 0.95);\n   box-shadow: 0 0 32px rgba(0, 0, 0, 0.25);\n";
         $css .= "}\n";
      } else {
         $margem = $o['posicao'] === 'centro' ? 'auto' : '0 6vw';
         $css .= "{$painel} {\n";
         $css .= "   margin: {$margem} !important;\n   width: 480px;\n   max-width: calc(100% - 32px) !important;\n";
         $css .= "   padding: 24px 28px !important;\n   border-radius: 10px;\n";
         $css .= "   background: rgba(255, 255, 255, 0.95);\n   box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);\n";
         $css .= "}\n";
      }

      $css .= self::cssFormularioNoPainel($painel);

      // Tema escuro do GLPI
      $css .= ":root[data-glpi-theme-dark=\"1\"] {$painel} {\n   background: rgba(24, 27, 34, 0.94);\n}\n";

      // Celular: painel na largura toda
      $css .= "@media only screen and (max-width: 768px) {\n";
      $css .= "   {$pagina} > div { justify-content: center !important; }\n";
      $css .= "   {$painel} { width: 100% !important; margin: 0 !important; border-radius: 0; min-height: 100vh; }\n";
      $css .= "}\n";

      return $css;
   }
   static function hexParaHsl(string $hex): array {
      $hex = ltrim($hex, '#');
      $r = hexdec(substr($hex, 0, 2)) / 255;
      $g = hexdec(substr($hex, 2, 2)) / 255;
      $b = hexdec(substr($hex, 4, 2)) / 255;

      $max = max($r, $g, $b);
      $min = min($r, $g, $b);
      $l   = ($max + $min) / 2;

      if ($max === $min) {
         $h = $s = 0;
      } else {
         $d = $max - $min;
         $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);

         switch ($max) {
            case $r:
               $h = (($g - $b) / $d + ($g < $b ? 6 : 0)) / 6;
               break;
            case $g:
               $h = (($b - $r) / $d + 2) / 6;
               break;
            case $b:
               $h = (($r - $g) / $d + 4) / 6;
               break;
         }
      }

      return [
         'h' => round($h * 360),
         's' => round($s * 100),
         'l' => round($l * 100),
      ];
   }

   static function hexParaRgb(string $hex): array {
      $hex = ltrim($hex, '#');
      return [
         'r' => hexdec(substr($hex, 0, 2)),
         'g' => hexdec(substr($hex, 2, 2)),
         'b' => hexdec(substr($hex, 4, 2)),
      ];
   }

   static function hslParaHex(int $h, int $s, int $l): string {
      $s /= 100;
      $l /= 100;

      $c = (1 - abs(2 * $l - 1)) * $s;
      $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
      $m = $l - $c / 2;

      if ($h < 60)       { $r = $c; $g = $x; $b = 0; }
      elseif ($h < 120)  { $r = $x; $g = $c; $b = 0; }
      elseif ($h < 180)  { $r = 0;  $g = $c; $b = $x; }
      elseif ($h < 240)  { $r = 0;  $g = $x; $b = $c; }
      elseif ($h < 300)  { $r = $x; $g = 0;  $b = $c; }
      else               { $r = $c; $g = 0;  $b = $x; }

      $r = round(($r + $m) * 255);
      $g = round(($g + $m) * 255);
      $b = round(($b + $m) * 255);

      return sprintf('#%02x%02x%02x', $r, $g, $b);
   }

   static function gerarVariaveisCss(string $corHex): array {
      $hsl = self::hexParaHsl($corHex);
      $rgb = self::hexParaRgb($corHex);
      $h   = $hsl['h'];
      $s   = $hsl['s'];
      $l   = $hsl['l'];

      $hComplementar = ($h + 40) % 360;

      $corTexto = ($l < 55) ? '#ffffff' : '#1a1a2e';

      $corComplementarHex = self::hslParaHex($hComplementar, min($s + 10, 100), 65);
      $rgbComplementar    = self::hexParaRgb($corComplementarHex);

      $corLinkHex = self::hslParaHex($h, min($s, 80), max($l - 15, 20));
      $rgbLink    = self::hexParaRgb($corLinkHex);

      return [
         '--tblr-primary-rgb'                  => $rgbComplementar['r'] . ', ' . $rgbComplementar['g'] . ', ' . $rgbComplementar['b'],
         '--tblr-link-color-rgb'               => $rgbLink['r'] . ', ' . $rgbLink['g'] . ', ' . $rgbLink['b'],
         '--glpi-mainmenu-bg'                  => $corHex,
         '--glpi-mainmenu-fg'                  => $corTexto,
         '--glpi-helpdesk-header'              => 'hsl(' . $h . 'deg, ' . max($s - 20, 10) . '%, 85%)',
         '--glpi-palette-color-1'              => self::hslParaHex($h, min($s + 5, 100), min($l + 10, 60)),
         '--glpi-palette-color-2'              => self::hslParaHex($h, $s, max($l - 25, 10)),
         '--glpi-palette-color-3'              => self::hslParaHex($h, $s, max($l - 10, 15)),
         '--glpi-palette-color-4'              => $corComplementarHex,
         '--glpi-illustrations-gradient-1'     => 'hsl(' . $h . 'deg, ' . max($s - 20, 10) . '%, 92%)',
         '--glpi-illustrations-gradient-2'     => 'hsl(' . $h . 'deg, ' . max($s - 20, 10) . '%, 65%)',
         '--glpi-illustrations-gradient-3'     => 'hsl(' . $h . 'deg, ' . max($s - 20, 10) . '%, 38%)',
      ];
   }

   static function gerarCssTema(): string {
      $css = "/* Plugin Logos da Empresa - Tema personalizado */\n";

      // =====================================================================
      // Parte 1: Override dos logos com cache bust
      // =====================================================================
      // Só os logos enviados na configuração; sem envio, vale o logo do próprio GLPI
      $mapaLogosVariaveis = [
         'logo-GLPI-100-white.png' => '--glpi-logo-light',
         'logo-G-100-white.png'    => '--glpi-logo-light-reduced',
         'logo-GLPI-100-black.png' => '--glpi-logo-dark',
         'logo-G-100-black.png'    => '--glpi-logo-dark-reduced',
         'logo-GLPI-250-black.png' => '--glpi-logo-dark-login',
         'logo-GLPI-250-white.png' => '--glpi-logo-light-login',
      ];

      $overridesLogos = [];
      foreach ($mapaLogosVariaveis as $arquivo => $variavel) {
         if (self::logoFoiAlterado($arquivo)) {
            $overridesLogos[$variavel] = 'url("' . self::getUrlPersonalizado($arquivo) . '")';
         }
      }

      if (!empty($overridesLogos)) {
         $css .= ":root, :root[data-glpi-theme] {\n";
         foreach ($overridesLogos as $variavel => $valor) {
            $css .= "   {$variavel}: {$valor};\n";
         }
         $css .= "}\n";
         // O GLPI desenha o logo expandido sem ajuste (imagem grande sai cortada): encaixa no espaço
         $css .= ".glpi-logo {\n   background-size: contain !important;\n   background-position: center !important;\n   background-repeat: no-repeat !important;\n}\n";
      }
      // =====================================================================
      // Parte 2: Cor do tema
      // =====================================================================
      if (self::corTemaAtiva()) {
         $css .= self::cssCorTema(self::getCorTema());
      }

      return $css;
   }

   // =========================================================================
   // Métodos de logos
   // =========================================================================

   static function getTypeName($nb = 0): string {
      return 'Logos da Empresa';
   }

   static function canView(): bool {
      return Session::haveRight('config', UPDATE);
   }

   static function canCreate(): bool {
      return Session::haveRight('config', UPDATE);
   }

   /** Logos do próprio GLPI (somente leitura: o plugin nunca altera arquivos do core) */
   static function getDiretorioLogosGlpi(): string {
      return GLPI_ROOT . '/public/pics/logos';
   }

   /** Logos enviados na tela de configuração (pasta de dados do plugin, fora do core) */
   static function getDiretorioPersonalizados(): string {
      return GLPI_PLUGIN_DOC_DIR . DIRECTORY_SEPARATOR . 'logosdaempresa' . DIRECTORY_SEPARATOR . 'logos';
   }

   /** Nomes de logo aceitos (os mesmos arquivos que o GLPI usa) */
   static function arquivosPermitidos(): array {
      $lista = [];
      foreach (self::LOGOS_MAP as $grupo) {
         foreach ($grupo['arquivos'] as $arq) {
            $lista[] = $arq;
         }
      }
      return $lista;
   }

   static function caminhoPersonalizado(string $arquivo): ?string {
      if (!in_array($arquivo, self::arquivosPermitidos(), true)) {
         return null;
      }
      $caminho = self::getDiretorioPersonalizados() . DIRECTORY_SEPARATOR . $arquivo;
      return is_file($caminho) ? $caminho : null;
   }

   /** URL do logo personalizado (servido por front/imagem.php, liberado também na tela de login) */
   static function getUrlPersonalizado(string $arquivo): string {
      global $CFG_GLPI;
      $caminho = self::caminhoPersonalizado($arquivo);
      return $CFG_GLPI['root_doc'] . '/plugins/logosdaempresa/front/imagem.php?logo=' . urlencode($arquivo)
         . '&v=' . ($caminho !== null ? filemtime($caminho) : 0);
   }

   /** Logo do próprio GLPI */
   static function getUrlOriginal(string $arquivo): string {
      global $CFG_GLPI;
      $caminho = self::getDiretorioLogosGlpi() . '/' . $arquivo;
      return $CFG_GLPI['root_doc'] . '/pics/logos/' . $arquivo . '?v=' . (is_file($caminho) ? filemtime($caminho) : 0);
   }

   /** Logo em uso: o personalizado, se houver; senão o do GLPI */
   static function getUrlLogo(string $arquivo): string {
      return self::caminhoPersonalizado($arquivo) !== null ? self::getUrlPersonalizado($arquivo) : self::getUrlOriginal($arquivo);
   }

   static function logoFoiAlterado(string $arquivo): bool {
      return self::caminhoPersonalizado($arquivo) !== null;
   }

   /** Garante a pasta dos logos personalizados; false se o servidor não permitir */
   static function prepararPastaPersonalizados(): bool {
      $pasta = self::getDiretorioPersonalizados();
      if (!is_dir($pasta)) {
         @mkdir($pasta, 0755, true);
      }
      return is_dir($pasta) && is_writable($pasta);
   }
   /**
    * Verifica se algum arquivo de um grupo unificado foi alterado
    */
   static function grupoUnificadoFoiAlterado(string $grupoId): bool {
      if (!isset(self::LOGOS_MAP[$grupoId])) {
         return false;
      }
      foreach (self::LOGOS_MAP[$grupoId]['arquivos'] as $arquivo) {
         if (self::logoFoiAlterado($arquivo)) {
            return true;
         }
      }
      return false;
   }

   /**
    * Ajusta a imagem ao espaço do logo sem deformar: se passar do espaço (em 2x, para telas de alta
    * resolução) é reduzida proporcionalmente; depois é centralizada numa tela transparente com a
    * proporção do espaço, para caber inteira sem corte nem esticamento. Imagens pequenas não são ampliadas.
    *
    * @return array{0: \GdImage, 1: bool} imagem final e se houve redução
    */
   static function ajustarAoEspaco(\GdImage $origem, int $larguraEspaco, int $alturaEspaco): array {
      $w = imagesx($origem);
      $h = imagesy($origem);
      $maxW = $larguraEspaco * 2;
      $maxH = $alturaEspaco * 2;

      $escala   = min(1, $maxW / $w, $maxH / $h);
      $reduzida = $escala < 1;
      $nw = max(1, (int) round($w * $escala));
      $nh = max(1, (int) round($h * $escala));

      // Tela com a proporção do espaço (sem ultrapassar o tamanho da imagem ajustada)
      $proporcao = $larguraEspaco / $alturaEspaco;
      if ($nw / $nh > $proporcao) {
         $cw = $nw;
         $ch = max($nh, (int) round($nw / $proporcao));
      } else {
         $ch = $nh;
         $cw = max($nw, (int) round($nh * $proporcao));
      }

      $tela = imagecreatetruecolor($cw, $ch);
      imagealphablending($tela, false);
      imagesavealpha($tela, true);
      imagefill($tela, 0, 0, imagecolorallocatealpha($tela, 0, 0, 0, 127));
      imagealphablending($tela, true);
      imagecopyresampled($tela, $origem, (int) (($cw - $nw) / 2), (int) (($ch - $nh) / 2), 0, 0, $nw, $nh, $w, $h);
      imagealphablending($tela, false);
      imagesavealpha($tela, true);

      return [$tela, $reduzida];
   }

   /**
    * @param bool $todasVariantes envia a mesma imagem para branco, preto e cinza do grupo
    */
   static function processarUpload(string $arquivo, array $dadosArquivo, bool $todasVariantes = false): bool {
      $grupoEncontrado = null;
      $grupoId         = null;
      foreach (self::LOGOS_MAP as $gId => $grupo) {
         if (in_array($arquivo, $grupo['arquivos'])) {
            $grupoEncontrado = $grupo;
            $grupoId         = $gId;
            break;
         }
      }

      if ($grupoEncontrado === null) {
         Session::addMessageAfterRedirect(
            __('Arquivo de logo não reconhecido.', 'logosdaempresa'),
            false,
            ERROR
         );
         return false;
      }

      $mimePermitidos = ['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'image/svg+xml'];
      $mimeEnviado    = mime_content_type($dadosArquivo['tmp_name']);

      if (!in_array($mimeEnviado, $mimePermitidos)) {
         Session::addMessageAfterRedirect(
            __('Formato não suportado. Use PNG, JPG, GIF, WebP ou SVG.', 'logosdaempresa'),
            false,
            ERROR
         );
         return false;
      }

      // Determinar lista de arquivos destino
      $arquivosDestino = [$arquivo];
      if ((!empty($grupoEncontrado['unificado']) && $grupoEncontrado['unificado'] === true) || $todasVariantes) {
         $arquivosDestino = $grupoEncontrado['arquivos'];
      }

      // Os logos enviados ficam na pasta de dados do plugin; os arquivos do GLPI não são alterados
      if (!self::prepararPastaPersonalizados()) {
         Session::addMessageAfterRedirect(
            __('Sem permissão de escrita na pasta de dados do plugin.', 'logosdaempresa') . ' ' . self::getDiretorioPersonalizados(),
            false,
            ERROR
         );
         return false;
      }
      $diretorioLogos = self::getDiretorioPersonalizados();

      // SVG: copiar direto sem processamento
      if ($mimeEnviado === 'image/svg+xml') {
         $primeiroDestino = $diretorioLogos . '/' . $arquivosDestino[0];
         if (!move_uploaded_file($dadosArquivo['tmp_name'], $primeiroDestino)) {
            Session::addMessageAfterRedirect(
               __('Erro ao salvar o arquivo SVG.', 'logosdaempresa'),
               false,
               ERROR
            );
            return false;
         }
         // Copiar para os demais arquivos (grupo unificado ou envio para todas as variantes)
         for ($i = 1; $i < count($arquivosDestino); $i++) {
            @copy($primeiroDestino, $diretorioLogos . '/' . $arquivosDestino[$i]);
         }
         Session::addMessageAfterRedirect(
            __('Logo atualizado com sucesso (SVG, ajustado ao espaço automaticamente).', 'logosdaempresa'),
            true,
            INFO
         );
         return true;
      }

      // Imagem raster: converter para PNG mantendo transparência
      $imagemOrigem = null;
      switch ($mimeEnviado) {
         case 'image/png':
            $imagemOrigem = imagecreatefrompng($dadosArquivo['tmp_name']);
            break;
         case 'image/jpeg':
            $imagemOrigem = imagecreatefromjpeg($dadosArquivo['tmp_name']);
            break;
         case 'image/gif':
            $imagemOrigem = imagecreatefromgif($dadosArquivo['tmp_name']);
            break;
         case 'image/webp':
            $imagemOrigem = imagecreatefromwebp($dadosArquivo['tmp_name']);
            break;
      }

      if (!$imagemOrigem) {
         Session::addMessageAfterRedirect(
            __('Erro ao processar a imagem enviada.', 'logosdaempresa'),
            false,
            ERROR
         );
         return false;
      }

      imagealphablending($imagemOrigem, false);
      imagesavealpha($imagemOrigem, true);

      // Ajusta ao espaço disponível (largura x altura do grupo)
      $larguraOriginal = imagesx($imagemOrigem);
      $alturaOriginal  = imagesy($imagemOrigem);
      [$imagemFinal, $reduzida] = self::ajustarAoEspaco($imagemOrigem, (int) $grupoEncontrado['largura'], (int) $grupoEncontrado['altura']);
      imagedestroy($imagemOrigem);
      $imagemOrigem = $imagemFinal;

      // Salvar no primeiro arquivo
      $primeiroDestino = $diretorioLogos . '/' . $arquivosDestino[0];
      $resultado = imagepng($imagemOrigem, $primeiroDestino, 9);

      if (!$resultado) {
         imagedestroy($imagemOrigem);
         Session::addMessageAfterRedirect(
            __('Erro ao salvar o logo.', 'logosdaempresa'),
            false,
            ERROR
         );
         return false;
      }

      // Copiar para os demais arquivos do grupo unificado
      for ($i = 1; $i < count($arquivosDestino); $i++) {
         @copy($primeiroDestino, $diretorioLogos . '/' . $arquivosDestino[$i]);
      }

      imagedestroy($imagemOrigem);

      $mensagem = count($arquivosDestino) > 1 && empty($grupoEncontrado['unificado'])
         ? __('Logo aplicado às variantes branco, preto e cinza.', 'logosdaempresa')
         : __('Logo atualizado com sucesso.', 'logosdaempresa');
      if ($reduzida) {
         $mensagem .= ' ' . sprintf(
            __('A imagem (%1$d x %2$d px) era maior que o espaço e foi ajustada para caber em %3$d x %4$d px.', 'logosdaempresa'),
            $larguraOriginal, $alturaOriginal, $grupoEncontrado['largura'], $grupoEncontrado['altura']
         );
      }
      Session::addMessageAfterRedirect($mensagem, true, INFO);
      return true;
   }

   /** Volta ao logo do GLPI removendo o personalizado */
   static function restaurarLogo(string $arquivo): bool {
      $personalizado = self::caminhoPersonalizado($arquivo);

      if ($personalizado === null) {
         Session::addMessageAfterRedirect(
            __('Este logo já é o do GLPI.', 'logosdaempresa'),
            false,
            INFO
         );
         return true;
      }

      if (!@unlink($personalizado)) {
         Session::addMessageAfterRedirect(
            __('Erro ao restaurar o logo original.', 'logosdaempresa'),
            false,
            ERROR
         );
         return false;
      }

      Session::addMessageAfterRedirect(
         __('Logo original restaurado com sucesso.', 'logosdaempresa'),
         true,
         INFO
      );
      return true;
   }

   static function restaurarGrupo(string $grupo): bool {
      if (!isset(self::LOGOS_MAP[$grupo])) {
         Session::addMessageAfterRedirect(
            __('Grupo de logos não reconhecido.', 'logosdaempresa'),
            false,
            ERROR
         );
         return false;
      }

      $erros = 0;
      foreach (self::LOGOS_MAP[$grupo]['arquivos'] as $arquivo) {
         $personalizado = self::caminhoPersonalizado($arquivo);
         if ($personalizado !== null && !@unlink($personalizado)) {
            $erros++;
         }
      }

      if ($erros > 0) {
         Session::addMessageAfterRedirect(
            __('Alguns logos não puderam ser restaurados.', 'logosdaempresa'),
            false,
            WARNING
         );
         return false;
      }

      Session::addMessageAfterRedirect(
         __('Logos originais restaurados com sucesso.', 'logosdaempresa'),
         true,
         INFO
      );
      return true;
   }

   static function downloadLogosOriginais(): void {
      $glpiLogosDir = self::getDiretorioLogosGlpi();
      $tmpFile      = tempnam(sys_get_temp_dir(), 'logos_') . '.zip';

      $zip = new ZipArchive();
      if ($zip->open($tmpFile, ZipArchive::CREATE) !== true) {
         Session::addMessageAfterRedirect(
            __('Erro ao criar arquivo ZIP.', 'logosdaempresa'),
            false,
            ERROR
         );
         Html::back();
         return;
      }

      $logos = [];
      foreach (self::LOGOS_MAP as $grupo) {
         foreach ($grupo['arquivos'] as $arq) {
            $logos[] = $arq;
         }
      }

      // Logos em uso: o personalizado, se houver; senão o do GLPI
      foreach ($logos as $logo) {
         $caminho = self::caminhoPersonalizado($logo) ?? ($glpiLogosDir . '/' . $logo);
         if (file_exists($caminho)) {
            $zip->addFile($caminho, 'logos-glpi/' . $logo);
         }
      }

      $zip->close();

      header('Content-Type: application/zip');
      header('Content-Disposition: attachment; filename="logos-em-uso.zip"');
      header('Content-Length: ' . filesize($tmpFile));
      header('Cache-Control: no-cache, no-store, must-revalidate');

      readfile($tmpFile);
      unlink($tmpFile);
      exit;
   }

   // =========================================================================
   // Renderização da página de configurações
   // =========================================================================

   /** Aviso apenas quando o servidor não permite criar a pasta dos logos personalizados */
   function renderizarAvisoPasta(): void {
      if (self::prepararPastaPersonalizados()) {
         return;
      }
      $pasta = self::getDiretorioPersonalizados();

      echo '<div class="logosdaempresa-aviso">';
      echo '<div class="logosdaempresa-aviso-icone"><i class="ti ti-alert-triangle"></i></div>';
      echo '<div class="logosdaempresa-aviso-conteudo">';
      echo '<div class="logosdaempresa-aviso-titulo">Pasta dos logos personalizados sem permissão de escrita</div>';
      echo '<div class="logosdaempresa-aviso-texto">Sem ela não é possível enviar logos. Execute no servidor:</div>';
      echo '<div class="logosdaempresa-aviso-codigo"><code>';
      echo 'mkdir -p ' . htmlspecialchars($pasta) . '<br><br>';
      echo 'chown -R www-data:www-data ' . htmlspecialchars(dirname($pasta)) . '/';
      echo '</code></div>';
      echo '</div>';
      echo '</div>';
   }
   function renderizarSecaoCor(): void {
      global $CFG_GLPI;

      $corAtual = self::getCorTema() ?: '';
      $ativa    = self::corTemaAtiva();

      echo '<div class="logosdaempresa-grupo">';

      echo '<div class="logosdaempresa-grupo-titulo">';
      echo '<i class="ti ti-palette"></i> ';
      echo 'Cor do tema principal';
      if ($ativa) {
         echo '<span class="logosdaempresa-tamanho" style="background:' . htmlspecialchars($corAtual) . ';color:#fff;padding:2px 10px;border-radius:3px">';
         echo htmlspecialchars($corAtual);
         echo '</span>';
      }
      echo '</div>';

      echo '<div style="padding:14px">';

      echo '<form method="post" action="' . $CFG_GLPI['root_doc'] . '/plugins/logosdaempresa/front/config.form.php">';
      echo '<input type="hidden" name="_glpi_csrf_token" value="' . self::tokenCsrf() . '">';
      echo '<input type="hidden" name="save_action" value="salvar_cor_tema">';

      echo '<div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">';

      echo '<div style="display:flex;align-items:center;gap:8px">';
      echo '<label style="font-size:12px;font-weight:600;color:#555">Cor base:</label>';
      echo '<input type="color" name="cor_tema" value="' . htmlspecialchars($corAtual ?: '#2c3e6b') . '" ';
      echo 'id="logosdaempresa-cor-picker" ';
      echo 'style="width:40px;height:32px;border:1px solid #ddd;border-radius:4px;cursor:pointer;padding:2px">';
      echo '</div>';

      echo '<div style="display:flex;align-items:center;gap:6px">';
      echo '<label style="font-size:11px;color:#888">HEX:</label>';
      echo '<input type="text" id="logosdaempresa-cor-hex" value="' . htmlspecialchars($corAtual ?: '#2c3e6b') . '" ';
      echo 'maxlength="7" ';
      echo 'style="width:80px;padding:5px 8px;font-size:12px;font-family:monospace;border:1px solid #ddd;border-radius:4px;text-align:center">';
      echo '</div>';

      echo '<div id="logosdaempresa-cor-preview" style="display:flex;align-items:center;gap:2px;padding:4px 12px;border-radius:4px;font-size:11px;font-weight:600;';
      if ($ativa) {
         echo 'background:' . htmlspecialchars($corAtual) . ';color:#fff';
      } else {
         echo 'background:#2c3e6b;color:#fff';
      }
      echo '"><i class="ti ti-menu-2"></i> Preview sidebar</div>';

      echo '<button type="submit" class="logosdaempresa-btn-upload" style="padding:6px 14px">';
      echo '<i class="ti ti-check"></i> Aplicar cor';
      echo '</button>';

      echo '</form>';

      if ($ativa) {
         echo '<form method="post" action="' . $CFG_GLPI['root_doc'] . '/plugins/logosdaempresa/front/config.form.php" style="margin:0">';
         echo '<input type="hidden" name="_glpi_csrf_token" value="' . self::tokenCsrf() . '">';
         echo '<input type="hidden" name="save_action" value="remover_cor_tema">';
         echo '<button type="submit" class="logosdaempresa-btn-restaurar">';
         echo '<i class="ti ti-x"></i> Remover cor personalizada';
         echo '</button>';
         echo '</form>';
      }

      echo '</div>';

      echo '<div style="margin-top:12px;padding-top:12px;border-top:1px solid rgba(0,0,0,0.06)">';
      echo '<div style="font-size:11px;color:#888;margin-bottom:8px">Cores sugeridas:</div>';
      echo '<div style="display:flex;gap:6px;flex-wrap:wrap">';

      $coresSugeridas = [
         '#2c3e6b' => 'Azul GLPI',
         '#008C50' => 'Verde',
         '#7c3aed' => 'Roxo',
         '#dc2626' => 'Vermelho',
         '#ea580c' => 'Laranja',
         '#0891b2' => 'Ciano',
         '#4f46e5' => 'Índigo',
         '#059669' => 'Esmeralda',
         '#1e293b' => 'Escuro',
         '#7c2d12' => 'Marrom',
         '#be185d' => 'Rosa',
         '#0d9488' => 'Teal',
      ];

      foreach ($coresSugeridas as $hex => $nome) {
         echo '<div class="logosdaempresa-cor-sugerida" data-cor="' . $hex . '" ';
         echo 'title="' . htmlspecialchars($nome) . ' (' . $hex . ')" ';
         echo 'style="width:28px;height:28px;border-radius:4px;cursor:pointer;background:' . $hex . ';';
         echo 'border:2px solid ' . ($corAtual === $hex ? '#333' : 'transparent') . ';';
         echo 'transition:border-color 0.2s,transform 0.15s">';
         echo '</div>';
      }

      echo '</div>';
      echo '</div>';

      if ($ativa) {
         echo '<div style="margin-top:12px;padding:8px 12px;background:rgba(0,123,255,0.04);border:1px solid rgba(0,123,255,0.1);border-radius:4px;font-size:11px;color:#555">';
         echo '<i class="ti ti-info-circle"></i> ';
         echo 'A cor é aplicada em todos os temas do GLPI. As variações (paleta, gradientes, links) são calculadas automaticamente.';
         echo '</div>';
      }

      echo '</div>';
      echo '</div>';

      echo '<script>';
      echo '(function(){';
      echo '  var picker = document.getElementById("logosdaempresa-cor-picker");';
      echo '  var hexInput = document.getElementById("logosdaempresa-cor-hex");';
      echo '  var preview = document.getElementById("logosdaempresa-cor-preview");';
      echo '  var sugeridas = document.querySelectorAll(".logosdaempresa-cor-sugerida");';

      echo '  function atualizarPreview(cor){';
      echo '    preview.style.background = cor;';
      echo '    var r=parseInt(cor.substr(1,2),16),g=parseInt(cor.substr(3,2),16),b=parseInt(cor.substr(5,2),16);';
      echo '    preview.style.color = (r*0.299+g*0.587+b*0.114) < 140 ? "#fff" : "#1a1a2e";';
      echo '  }';

      echo '  function atualizarTudo(cor){';
      echo '    picker.value = cor;';
      echo '    hexInput.value = cor;';
      echo '    atualizarPreview(cor);';
      echo '    sugeridas.forEach(function(el){';
      echo '      el.style.borderColor = el.getAttribute("data-cor")===cor ? "#333" : "transparent";';
      echo '    });';
      echo '  }';

      echo '  picker.addEventListener("input", function(){ atualizarTudo(this.value); });';

      echo '  hexInput.addEventListener("input", function(){';
      echo '    var v = this.value;';
      echo '    if(/^#[0-9a-fA-F]{6}$/.test(v)){ atualizarTudo(v); }';
      echo '  });';

      echo '  sugeridas.forEach(function(el){';
      echo '    el.addEventListener("click", function(){';
      echo '      atualizarTudo(this.getAttribute("data-cor"));';
      echo '    });';
      echo '  });';

      echo '})();';
      echo '</script>';
   }

   function renderizarSecaoLayout(): void {
      global $CFG_GLPI;

      $ativo       = self::layoutLateralAtivo();
      $imgLateral  = self::getConfig('layout_imagem_lateral', '');
      $temImagem   = false;
      $urlImagem   = '';

      if (!empty($imgLateral)) {
         $pluginDocDir = GLPI_PLUGIN_DOC_DIR . DIRECTORY_SEPARATOR . 'logosdaempresa';
         if (file_exists($pluginDocDir . '/' . $imgLateral)) {
            $temImagem = true;
            $urlImagem = $CFG_GLPI['root_doc'] . '/plugins/logosdaempresa/front/imagem.php?arquivo=' . urlencode($imgLateral) . '&v=' . time();
         }
      }

      echo '<div class="logosdaempresa-grupo">';

      echo '<div class="logosdaempresa-grupo-titulo">';
      echo '<i class="ti ti-layout-sidebar-right"></i> ';
      echo 'Layout da tela de login';
      if ($ativo) {
         echo '<span class="logosdaempresa-tamanho" style="background:rgba(37,99,235,0.1);color:#2563eb;padding:2px 10px;border-radius:3px;font-size:10px">ATIVO</span>';
      }
      echo '</div>';

      echo '<div style="padding:14px">';

      // Toggle ativar/desativar
      echo '<form method="post" action="' . $CFG_GLPI['root_doc'] . '/plugins/logosdaempresa/front/config.form.php">';
      echo '<input type="hidden" name="_glpi_csrf_token" value="' . self::tokenCsrf() . '">';
      echo '<input type="hidden" name="save_action" value="toggle_layout_lateral">';

      echo '<div style="display:flex;align-items:center;gap:12px;margin-bottom:14px">';
      echo '<label style="font-size:12px;font-weight:600;color:#555">Layout lateral com imagem:</label>';

      if ($ativo) {
         echo '<button type="submit" class="logosdaempresa-btn-restaurar" style="padding:5px 14px">';
         echo '<i class="ti ti-x"></i> Desativar';
         echo '</button>';
      } else {
         echo '<button type="submit" class="logosdaempresa-btn-upload" style="padding:5px 14px">';
         echo '<i class="ti ti-check"></i> Ativar';
         echo '</button>';
      }

      echo '</div>';
      echo '</form>';

      // Info explicativa
      echo '<div style="font-size:11px;color:#888;margin-bottom:14px;padding:8px 12px;background:rgba(0,0,0,0.02);border:1px solid rgba(0,0,0,0.06);border-radius:4px">';
      echo '<i class="ti ti-info-circle"></i> ';
      echo 'Quando ativo, a tela de login exibe um box com imagem lateral à esquerda e o formulário de login à direita. ';
      echo 'Tamanho recomendado da imagem: <b>1250 x 770 px</b>. No mobile, apenas o formulário é exibido.';
      echo '</div>';

      // Upload de imagem lateral
      echo '<div style="display:flex;gap:14px;align-items:flex-start;flex-wrap:wrap">';

      // Preview
      if ($temImagem) {
         echo '<div style="flex-shrink:0">';
         echo '<span class="logosdaempresa-preview-titulo">Imagem lateral atual</span>';
         echo '<div class="logosdaempresa-preview logosdaempresa-preview-black" style="min-width:220px;min-height:130px">';
         echo '<img src="' . $urlImagem . '" alt="Imagem lateral" style="max-height:120px">';
         echo '</div>';
         echo '</div>';
      }

      // Ações
      echo '<div style="display:flex;flex-direction:column;gap:8px;justify-content:center;padding-top:16px">';

      // Upload
      echo '<form method="post" enctype="multipart/form-data" ';
      echo 'action="' . $CFG_GLPI['root_doc'] . '/plugins/logosdaempresa/front/config.form.php">';
      echo '<input type="hidden" name="_glpi_csrf_token" value="' . self::tokenCsrf() . '">';
      echo '<input type="hidden" name="save_action" value="upload_imagem_lateral">';
      echo '<label class="logosdaempresa-btn-upload">';
      echo '<i class="ti ti-upload"></i> Enviar imagem lateral';
      echo '<input type="file" name="imagem_lateral" accept="image/png,image/jpeg,image/gif,image/webp" ';
      echo 'onchange="this.closest(\'form\').submit()" style="display:none">';
      echo '</label>';
      echo '</form>';

      // Remover imagem
      if ($temImagem) {
         echo '<form method="post" action="' . $CFG_GLPI['root_doc'] . '/plugins/logosdaempresa/front/config.form.php">';
         echo '<input type="hidden" name="_glpi_csrf_token" value="' . self::tokenCsrf() . '">';
         echo '<input type="hidden" name="save_action" value="remover_imagem_lateral">';
         echo '<button type="submit" class="logosdaempresa-btn-restaurar">';
         echo '<i class="ti ti-trash"></i> Remover imagem';
         echo '</button>';
         echo '</form>';
      }

      echo '</div>';
      echo '</div>';

      echo '</div>';
      echo '</div>';
   }

   function renderizarSecaoFundo(): void {
      global $CFG_GLPI;

      $acao    = $CFG_GLPI['root_doc'] . '/plugins/logosdaempresa/front/config.form.php';
      $csrf    = '<input type="hidden" name="_glpi_csrf_token" value="' . self::tokenCsrf() . '">';
      $ativo   = self::fundoLoginAtivo();
      $url     = self::getUrlFundoLogin();
      $opcoes  = self::opcoesFundoLogin();

      echo '<div class="logosdaempresa-grupo">';

      echo '<div class="logosdaempresa-grupo-titulo">';
      echo '<i class="ti ti-photo"></i> Imagem de fundo da tela de login';
      if ($ativo && $url !== '') {
         echo '<span class="logosdaempresa-tamanho logosdaempresa-selo-ativo">ATIVO</span>';
      }
      echo '</div>';

      echo '<div class="logosdaempresa-fundo">';

      // Prévia: imagem (tela inteira ou área separada) com o painel na posição escolhida
      $escuroPreview = 'rgba(0,0,0,' . number_format($opcoes['escurecer'] / 100, 2, '.', '') . ')';
      $imagemPreview = $url !== '' ? 'background-image:url(\'' . htmlspecialchars($url) . '\');' : '';
      $posicaoPreview = $opcoes['modo'] === 'area' && $opcoes['posicao'] === 'centro' ? 'direita' : $opcoes['posicao'];
      echo '<div class="logosdaempresa-fundo-preview logosdaempresa-fundo-' . $posicaoPreview
         . ' logosdaempresa-fundo-estilo-' . $opcoes['estilo'] . ' logosdaempresa-fundo-modo-' . $opcoes['modo'] . '"'
         . ($opcoes['modo'] === 'tela' && $imagemPreview !== '' ? ' style="' . $imagemPreview . '"' : '') . '>';
      if ($opcoes['modo'] === 'area') {
         $ladoArea = $posicaoPreview === 'esquerda' ? 'right' : 'left';
         $margemArea = $opcoes['moldura'] ? 6 : 0;
         echo '<span class="logosdaempresa-fundo-area" style="' . $imagemPreview . $ladoArea . ':' . $margemArea . 'px;top:' . $margemArea . 'px;bottom:' . $margemArea . 'px;'
            . 'width:calc(' . $opcoes['largura'] . '% - ' . ($margemArea * 2) . 'px);border-radius:' . ($opcoes['moldura'] ? 6 : 0) . 'px">';
         echo '<span class="logosdaempresa-fundo-escuro" style="background:' . $escuroPreview . ';border-radius:inherit"></span>';
         echo '</span>';
      } else {
         echo '<span class="logosdaempresa-fundo-escuro" style="background:' . $escuroPreview . '"></span>';
      }
      if ($url === '') {
         echo '<span class="logosdaempresa-fundo-vazio"><i class="ti ti-photo-off"></i> Nenhuma imagem enviada</span>';
      }
      echo '<span class="logosdaempresa-fundo-painel"><i class="ti ti-lock"></i><b></b><b></b><i class="logosdaempresa-fundo-botao"></i></span>';
      echo '</div>';

      echo '<div class="logosdaempresa-fundo-controles">';

      // Enviar / remover imagem
      echo '<div class="logosdaempresa-acoes">';
      echo '<form method="post" enctype="multipart/form-data" action="' . $acao . '" style="margin:0">' . $csrf;
      echo '<input type="hidden" name="save_action" value="upload_fundo_login">';
      echo '<label class="logosdaempresa-btn-upload"><i class="ti ti-upload"></i> ' . ($url !== '' ? 'Trocar imagem de fundo' : 'Enviar imagem de fundo');
      echo '<input type="file" name="fundo_login" accept="image/jpeg,image/png,image/webp" onchange="this.closest(\'form\').submit()" style="display:none">';
      echo '</label>';
      echo '</form>';
      if ($url !== '') {
         echo '<form method="post" action="' . $acao . '" style="margin:0">' . $csrf;
         echo '<input type="hidden" name="save_action" value="remover_fundo_login">';
         echo '<button type="submit" class="logosdaempresa-btn-restaurar"><i class="ti ti-trash"></i> Remover imagem</button>';
         echo '</form>';

         echo '<form method="post" action="' . $acao . '" style="margin:0">' . $csrf;
         echo '<input type="hidden" name="save_action" value="toggle_fundo_login">';
         echo $ativo
            ? '<button type="submit" class="logosdaempresa-btn-restaurar"><i class="ti ti-eye-off"></i> Desativar na tela de login</button>'
            : '<button type="submit" class="logosdaempresa-btn-upload"><i class="ti ti-check"></i> Ativar na tela de login</button>';
         echo '</form>';
      }
      echo '</div>';

      // Opções
      echo '<form method="post" action="' . $acao . '" class="logosdaempresa-fundo-opcoes">' . $csrf;
      echo '<input type="hidden" name="save_action" value="salvar_opcoes_fundo">';

      echo '<label>Exibição da imagem<select name="fundo_login_modo" class="form-select form-select-sm" '
         . 'onchange="this.form.querySelector(\'.logosdaempresa-fundo-so-area\').hidden = this.value !== \'area\'">';
      foreach (self::FUNDO_MODOS as $valor => $rotulo) {
         echo '<option value="' . $valor . '"' . ($opcoes['modo'] === $valor ? ' selected' : '') . '>' . $rotulo . '</option>';
      }
      echo '</select></label>';

      echo '<label>Formulário de login<select name="fundo_login_posicao" class="form-select form-select-sm">';
      foreach (self::FUNDO_POSICOES as $valor => $rotulo) {
         echo '<option value="' . $valor . '"' . ($opcoes['posicao'] === $valor ? ' selected' : '') . '>' . $rotulo . '</option>';
      }
      echo '</select></label>';

      echo '<label>Estilo<select name="fundo_login_estilo" class="form-select form-select-sm">';
      foreach (self::FUNDO_ESTILOS as $valor => $rotulo) {
         echo '<option value="' . $valor . '"' . ($opcoes['estilo'] === $valor ? ' selected' : '') . '>' . $rotulo . '</option>';
      }
      echo '</select></label>';

      echo '<label>Escurecer o fundo: <span class="logosdaempresa-fundo-valor">' . $opcoes['escurecer'] . '%</span>';
      echo '<input type="range" name="fundo_login_escurecer" min="0" max="70" step="5" value="' . $opcoes['escurecer'] . '" class="form-range" '
         . 'oninput="this.previousElementSibling.textContent=this.value+\'%\'">';
      echo '</label>';

      // Só no modo área separada
      echo '<div class="logosdaempresa-fundo-so-area"' . ($opcoes['modo'] === 'area' ? '' : ' hidden') . '>';
      echo '<label>Largura da área da imagem: <span class="logosdaempresa-fundo-valor">' . $opcoes['largura'] . '%</span>';
      echo '<input type="range" name="fundo_login_area_largura" min="30" max="75" step="5" value="' . $opcoes['largura'] . '" class="form-range" '
         . 'oninput="this.previousElementSibling.textContent=this.value+\'%\'">';
      echo '</label>';
      echo '<label class="logosdaempresa-fundo-check form-check form-switch">';
      echo '<input type="checkbox" class="form-check-input" name="fundo_login_area_moldura" value="1"' . ($opcoes['moldura'] ? ' checked' : '') . '>';
      echo '<span class="form-check-label">Moldura (espaçamento e cantos arredondados)</span>';
      echo '</label>';
      echo '</div>';

      echo '<button type="submit" class="logosdaempresa-btn-upload"><i class="ti ti-device-floppy"></i> Salvar opções</button>';
      echo '</form>';

      echo '</div>'; // controles
      echo '</div>'; // fundo

      echo '<div class="logosdaempresa-fundo-info"><i class="ti ti-info-circle"></i> ';
      echo 'Tela inteira: a imagem cobre a tela e o formulário fica por cima, no lado escolhido. Área separada: a imagem ocupa só a faixa definida e o formulário fica ao lado, sobre o fundo normal do GLPI (em telas estreitas, só o formulário). ';
      echo 'Painel de login com 520 px (cartão: 480 px). Recomendado: 1920 x 1080 px, JPG, até 2 MB (limite do servidor). Imagens maiores que 2560 x 1600 são reduzidas. ';
      echo 'Ativar o fundo desativa o "Layout lateral com imagem" (e vice-versa). No celular o formulário ocupa a largura toda.';
      echo '</div>';

      echo '</div>';
   }
   function renderizarSecaoRodape(): void {
      global $CFG_GLPI;

      $textoAtual = self::getConfig('texto_rodape_login', '');

      echo '<div class="logosdaempresa-grupo">';

      echo '<div class="logosdaempresa-grupo-titulo">';
      echo '<i class="ti ti-file-text"></i> ';
      echo 'Texto de rodapé da tela de login';
      echo '</div>';

      echo '<div style="padding:14px">';

      echo '<form method="post" action="' . $CFG_GLPI['root_doc'] . '/plugins/logosdaempresa/front/config.form.php">';
      echo '<input type="hidden" name="_glpi_csrf_token" value="' . self::tokenCsrf() . '">';
      echo '<input type="hidden" name="save_action" value="salvar_texto_rodape">';

      echo '<div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">';

      echo '<div style="flex:1;min-width:250px">';
      echo '<label style="font-size:12px;font-weight:600;color:#555;display:block;margin-bottom:4px">Texto personalizado:</label>';
      echo '<input type="text" name="texto_rodape_login" value="' . htmlspecialchars($textoAtual) . '" ';
      echo 'placeholder="Ex: Todos os Direitos Reservados 2026 Empresa" ';
      echo 'style="width:100%;padding:6px 10px;font-size:12px;border:1px solid #ddd;border-radius:4px">';
      echo '</div>';

      echo '<button type="submit" class="logosdaempresa-btn-upload" style="padding:6px 14px;margin-top:18px">';
      echo '<i class="ti ti-check"></i> Salvar';
      echo '</button>';

      echo '</form>';

      if (!empty($textoAtual)) {
         echo '<form method="post" action="' . $CFG_GLPI['root_doc'] . '/plugins/logosdaempresa/front/config.form.php" style="margin:0;margin-top:18px">';
         echo '<input type="hidden" name="_glpi_csrf_token" value="' . self::tokenCsrf() . '">';
         echo '<input type="hidden" name="save_action" value="remover_texto_rodape">';
         echo '<button type="submit" class="logosdaempresa-btn-restaurar">';
         echo '<i class="ti ti-x"></i> Remover texto personalizado (usar padrão do GLPI)';
         echo '</button>';
         echo '</form>';
      }

      echo '</div>';

      echo '<div style="padding:0 14px 14px;font-size:11px;color:#888">';
      echo '<i class="ti ti-info-circle"></i> ';
      echo 'Deixe vazio para manter o texto padrão do GLPI. O texto aparece na parte inferior da tela de login.';
      echo '</div>';

      echo '</div>';
   }

   function showConfigPage(): void {
      global $CFG_GLPI;

      $cssUrl = $CFG_GLPI['root_doc'] . '/plugins/logosdaempresa/css/logosdaempresa.css';
      echo '<link rel="stylesheet" href="' . $cssUrl . '">';

      echo '<div class="logosdaempresa-container">';

      $this->renderizarAvisoPasta();

      // Seção de cor do tema
      $this->renderizarSecaoCor();

      // Seção de layout da tela de login
      $this->renderizarSecaoLayout();

      // Seção de imagem de fundo da tela de login
      $this->renderizarSecaoFundo();

      // Seção de texto de rodapé
      $this->renderizarSecaoRodape();

      echo '<div class="logosdaempresa-download-section">';
      echo '<form method="post" action="' . $CFG_GLPI['root_doc'] . '/plugins/logosdaempresa/front/download.php">';
      echo '<input type="hidden" name="_glpi_csrf_token" value="' . self::tokenCsrf() . '">';
      echo '<button type="submit" class="logosdaempresa-btn-download">';
      echo '<i class="ti ti-download"></i> ';
      echo 'Baixar logos em uso (ZIP)';
      echo '</button>';
      echo '</form>';
      echo '</div>';

      echo '<div class="logosdaempresa-info">';
      echo '<i class="ti ti-info-circle"></i> ';
      echo 'Envie imagens em qualquer tamanho. O GLPI redimensiona e centraliza automaticamente via CSS. ';
      echo 'Para melhor qualidade, use imagens com fundo transparente (PNG).';
      echo '</div>';


      foreach (self::LOGOS_MAP as $grupoId => $grupo) {
         $isUnificado = !empty($grupo['unificado']) && $grupo['unificado'] === true;

         echo '<div class="logosdaempresa-grupo">';

         echo '<div class="logosdaempresa-grupo-titulo">';
         echo '<i class="ti ti-photo"></i> ';
         echo htmlspecialchars($grupo['titulo']);
         echo '<span class="logosdaempresa-tamanho">' . $grupo['largura'] . ' x ' . $grupo['altura'] . ' px</span>';
         echo '</div>';

         if ($isUnificado) {
            // Grupo unificado: mostrar apenas um upload com preview do arquivo principal
            $arquivoPrincipal = $grupo['arquivos'][0]; // logo-GLPI-250-black.png
            $urlAtual         = self::getUrlLogo($arquivoPrincipal);
            $urlGlpi        = self::getUrlOriginal($arquivoPrincipal);
            $foiAlterado      = self::grupoUnificadoFoiAlterado($grupoId);
            $temGlpi        = true; // o logo do GLPI sempre existe

            echo '<div style="padding:14px;text-align:center">';

            // Preview
            echo '<div style="display:flex;gap:12px;justify-content:center;align-items:flex-start;margin-bottom:10px">';

            echo '<div class="logosdaempresa-preview-box">';
            echo '<span class="logosdaempresa-preview-titulo">Em uso</span>';
            echo '<div class="logosdaempresa-preview logosdaempresa-preview-black" style="min-width:180px">';
            echo '<img src="' . $urlAtual . '" alt="' . htmlspecialchars($arquivoPrincipal) . '">';
            echo '</div>';
            echo '</div>';

            if ($temGlpi) {
               echo '<div class="logosdaempresa-preview-box">';
               echo '<span class="logosdaempresa-preview-titulo">Padrão do GLPI</span>';
               echo '<div class="logosdaempresa-preview logosdaempresa-preview-black" style="min-width:180px">';
               echo '<img src="' . $urlGlpi . '" alt="GLPI ' . htmlspecialchars($arquivoPrincipal) . '">';
               echo '</div>';
               echo '</div>';
            }

            echo '</div>';

            if ($foiAlterado) {
               echo '<div class="logosdaempresa-alterado" style="justify-content:center">';
               echo '<i class="ti ti-alert-circle"></i> Personalizado';
               echo '</div>';
            }

            // Informativo
            echo '<div style="font-size:11px;color:#888;margin-bottom:8px">';
            echo '<i class="ti ti-info-circle"></i> A mesma imagem será aplicada em todas as variantes de cor da tela de login.';
            echo '</div>';

            // Ações
            echo '<div class="logosdaempresa-acoes" style="justify-content:center">';

            echo '<form method="post" enctype="multipart/form-data" ';
            echo 'action="' . $CFG_GLPI['root_doc'] . '/plugins/logosdaempresa/front/config.form.php">';
            echo '<input type="hidden" name="_glpi_csrf_token" value="' . self::tokenCsrf() . '">';
            echo '<input type="hidden" name="save_action" value="upload_logo">';
            echo '<input type="hidden" name="arquivo_logo" value="' . htmlspecialchars($arquivoPrincipal) . '">';
            echo '<label class="logosdaempresa-btn-upload">';
            echo '<i class="ti ti-upload"></i> Enviar imagem';
            echo '<input type="file" name="logo_file" accept="image/*" ';
            echo 'onchange="this.closest(\'form\').submit()" style="display:none">';
            echo '</label>';
            echo '</form>';

            if ($temGlpi && $foiAlterado) {
               echo '<form method="post" action="' . $CFG_GLPI['root_doc'] . '/plugins/logosdaempresa/front/config.form.php">';
               echo '<input type="hidden" name="_glpi_csrf_token" value="' . self::tokenCsrf() . '">';
               echo '<input type="hidden" name="save_action" value="restaurar_grupo">';
               echo '<input type="hidden" name="grupo_id" value="' . htmlspecialchars($grupoId) . '">';
               echo '<button type="submit" class="logosdaempresa-btn-restaurar">';
               echo '<i class="ti ti-refresh"></i> Voltar ao logo do GLPI';
               echo '</button>';
               echo '</form>';
            }

            echo '</div>';
            echo '</div>';

         } else {
            // Uma imagem para as 3 variantes; depois cada variante pode receber a sua
            echo '<div class="logosdaempresa-todas-variantes">';
            echo '<form method="post" enctype="multipart/form-data" style="margin:0" ';
            echo 'action="' . $CFG_GLPI['root_doc'] . '/plugins/logosdaempresa/front/config.form.php">';
            echo '<input type="hidden" name="_glpi_csrf_token" value="' . self::tokenCsrf() . '">';
            echo '<input type="hidden" name="save_action" value="upload_grupo">';
            echo '<input type="hidden" name="grupo_id" value="' . htmlspecialchars($grupoId) . '">';
            echo '<label class="logosdaempresa-btn-upload">';
            echo '<i class="ti ti-upload"></i> Enviar para as 3 variantes';
            echo '<input type="file" name="logo_file" accept="image/*" onchange="this.closest(\'form\').submit()" style="display:none">';
            echo '</label>';
            echo '</form>';
            echo '<span class="logosdaempresa-todas-variantes-dica"><i class="ti ti-info-circle"></i> ';
            echo 'A mesma imagem vai para branco, preto e cinza. Depois, se quiser, envie uma imagem própria em cada variante abaixo. ';
            echo 'Imagens maiores que o espaço são ajustadas automaticamente.</span>';
            echo '</div>';

            // Grupo normal: mostrar cada variante separada
            echo '<div class="logosdaempresa-variantes">';

            foreach ($grupo['arquivos'] as $arquivo) {
               $cor = 'white';
               if (strpos($arquivo, '-black.') !== false) {
                  $cor = 'black';
               } elseif (strpos($arquivo, '-grey.') !== false) {
                  $cor = 'grey';
               }

               $urlAtual    = self::getUrlLogo($arquivo);
               $urlGlpi   = self::getUrlOriginal($arquivo);
               $corLabel    = self::VARIANTES_COR[$cor] ?? $cor;
               $foiAlterado = self::logoFoiAlterado($arquivo);
               $temGlpi   = true; // o logo do GLPI sempre existe

               echo '<div class="logosdaempresa-variante">';

               echo '<div class="logosdaempresa-variante-label">' . htmlspecialchars($corLabel) . '</div>';

               echo '<div class="logosdaempresa-previews">';

               echo '<div class="logosdaempresa-preview-box">';
               echo '<span class="logosdaempresa-preview-titulo">Em uso</span>';
               echo '<div class="logosdaempresa-preview logosdaempresa-preview-' . $cor . '">';
               echo '<img src="' . $urlAtual . '" alt="' . htmlspecialchars($arquivo) . '">';
               echo '</div>';
               echo '</div>';

               if ($temGlpi) {
                  echo '<div class="logosdaempresa-preview-box">';
                  echo '<span class="logosdaempresa-preview-titulo">Padrão do GLPI</span>';
                  echo '<div class="logosdaempresa-preview logosdaempresa-preview-' . $cor . '">';
                  echo '<img src="' . $urlGlpi . '" alt="GLPI ' . htmlspecialchars($arquivo) . '">';
                  echo '</div>';
                  echo '</div>';
               }

               echo '</div>';

               if ($foiAlterado) {
                  echo '<div class="logosdaempresa-alterado">';
                  echo '<i class="ti ti-alert-circle"></i> Personalizado';
                  echo '</div>';
               }

               echo '<form method="post" enctype="multipart/form-data" ';
               echo 'action="' . $CFG_GLPI['root_doc'] . '/plugins/logosdaempresa/front/config.form.php">';
               echo '<input type="hidden" name="_glpi_csrf_token" value="' . self::tokenCsrf() . '">';
               echo '<input type="hidden" name="save_action" value="upload_logo">';
               echo '<input type="hidden" name="arquivo_logo" value="' . htmlspecialchars($arquivo) . '">';

               echo '<div class="logosdaempresa-acoes">';
               echo '<label class="logosdaempresa-btn-upload">';
               echo '<i class="ti ti-upload"></i> Enviar imagem';
               echo '<input type="file" name="logo_file" accept="image/*" ';
               echo 'onchange="this.closest(\'form\').submit()" style="display:none">';
               echo '</label>';
               echo '</form>';

               if ($foiAlterado) {
                  echo '<form method="post" action="' . $CFG_GLPI['root_doc'] . '/plugins/logosdaempresa/front/config.form.php">';
                  echo '<input type="hidden" name="_glpi_csrf_token" value="' . self::tokenCsrf() . '">';
                  echo '<input type="hidden" name="save_action" value="restaurar_logo">';
                  echo '<input type="hidden" name="arquivo_logo" value="' . htmlspecialchars($arquivo) . '">';
                  echo '<button type="submit" class="logosdaempresa-btn-restaurar">';
                  echo '<i class="ti ti-refresh"></i> Voltar ao do GLPI';
                  echo '</button>';
                  echo '</form>';
               }

               echo '</div>';

               echo '</div>';
            }

            echo '</div>';

            if (self::grupoUnificadoFoiAlterado($grupoId)) {
               echo '<form method="post" action="' . $CFG_GLPI['root_doc'] . '/plugins/logosdaempresa/front/config.form.php">';
               echo '<input type="hidden" name="_glpi_csrf_token" value="' . self::tokenCsrf() . '">';
               echo '<input type="hidden" name="save_action" value="restaurar_grupo">';
               echo '<input type="hidden" name="grupo_id" value="' . htmlspecialchars($grupoId) . '">';
               echo '<button type="submit" class="logosdaempresa-btn-restaurar-grupo">';
               echo '<i class="ti ti-refresh-alert"></i> Voltar todas as variantes ao logo do GLPI';
               echo '</button>';
               echo '</form>';
            }
         }

         echo '</div>';
      }

      echo '</div>';
   }
}