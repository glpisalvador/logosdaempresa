<?php

class PluginLogosdaempresaMenu extends CommonGLPI {

   // $rightname nao e redeclarada: e tipada (string) no GLPI 12 e sem tipo no GLPI 11

   static function getTypeName($nb = 0): string {
      return 'Logos da Empresa';
   }

   static function getMenuName(): string {
      return 'Logos da Empresa';
   }

   static function canView(): bool {
      return Session::haveRight('config', UPDATE);
   }

   static function canCreate(): bool {
      return Session::haveRight('config', UPDATE);
   }

   static function getMenuContent(): array {
      return [
         'title'   => self::getMenuName(),
         'page'    => '/plugins/logosdaempresa/front/config.php',
         'icon'    => 'ti ti-photo',
         'options' => [
            'config' => [
               'title' => 'Logos da Empresa',
               'page'  => '/plugins/logosdaempresa/front/config.php',
               'icon'  => 'ti ti-photo',
               'links' => [
                  'search' => '/plugins/logosdaempresa/front/config.php',
               ],
            ],
         ],
      ];
   }
}