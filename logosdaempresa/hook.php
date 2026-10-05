<?php

/**
 * O plugin não altera arquivos do GLPI: logos enviados ficam em files/_plugins/logosdaempresa/logos
 * e são aplicados por CSS. Sem configuração, nada é carregado.
 */
function plugin_logosdaempresa_install(): bool {
   global $DB;

   // Pasta dos logos personalizados (dados do plugin, fora do core)
   $pasta = GLPI_PLUGIN_DOC_DIR . DIRECTORY_SEPARATOR . 'logosdaempresa' . DIRECTORY_SEPARATOR . 'logos';
   if (!is_dir($pasta)) {
      @mkdir($pasta, 0755, true);
   }

   // Pasta dos vídeos de fundo da tela de login (e das partes durante o envio)
   $videos = GLPI_PLUGIN_DOC_DIR . DIRECTORY_SEPARATOR . 'logosdaempresa' . DIRECTORY_SEPARATOR . 'videos' . DIRECTORY_SEPARATOR . 'envio';
   if (!is_dir($videos)) {
      @mkdir($videos, 0755, true);
   }

   // Criar tabela de configurações
   $tabela = 'glpi_plugin_logosdaempresa_configs';
   if (!$DB->tableExists($tabela)) {
      $DB->doQuery("
         CREATE TABLE `$tabela` (
            `id`       int unsigned NOT NULL AUTO_INCREMENT,
            `name`     varchar(255) NOT NULL,
            `value`    text,
            `date_mod` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `name` (`name`)
         ) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
      ");
   }

   return true;
}

/**
 * Limpa a cor do tema e o layout personalizado no banco
 */
function plugin_logosdaempresa_limpar_configs(): void {
   global $DB;

   $tabela = 'glpi_plugin_logosdaempresa_configs';
   if ($DB->tableExists($tabela)) {
      $DB->update($tabela, ['value' => ''], ['name' => 'cor_tema']);
      $DB->update($tabela, ['value' => '0'], ['name' => 'layout_lateral_ativo']);
      $DB->update($tabela, ['value' => '0'], ['name' => 'fundo_login_ativo']);
   }
}

function plugin_logosdaempresa_uninstall(): bool {
   // A tabela de configurações, os logos enviados e a imagem lateral são mantidos:
   // reinstalar recupera tudo. Nenhum arquivo do GLPI foi alterado, então não há o que restaurar.
   return true;
}

/**
 * Chamada pelo GLPI (11 e 12) ao desativar o plugin: o CSS deixa de ser carregado
 * (logos voltam aos do GLPI) e cor e layout voltam ao padrão.
 */
function plugin_logosdaempresa_deactivate(): bool {
   plugin_logosdaempresa_limpar_configs();
   return true;
}
