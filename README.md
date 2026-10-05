# Logos da Empresa para GLPI

> Autor: **GLPI Salvador** · Licença: **GPLv3** · Compatível com GLPI **11.0.0 a 12.x**

Personaliza a **identidade visual** do GLPI pela tela: logos, cor do tema e o visual da tela de login. O plugin **não altera arquivos do GLPI**: tudo é aplicado por CSS. **Sem configuração, nada muda.**

## O que o plugin faz

### Logos
- Envie os logos da empresa nos tamanhos e variações que o GLPI usa: logo completo e símbolo do menu recolhido, em branco, preto e cinza, e o logo grande da tela de login.
- **Prévia** do menu lateral antes de aplicar.
- Os arquivos ficam em `files/_plugins/logosdaempresa/logos` e são aplicados por CSS. Os originais do GLPI continuam intactos.

### Cor do tema
- Escolha a **cor base**, pelo seletor ou em HEX, e o plugin gera a paleta para os temas do GLPI.

### Tela de login
- **Logo próprio** na tela de login.
- **Layout lateral:** uma imagem de um lado e o formulário do outro.
- **Fundo com imagem ou vídeo** (MP4 de até 100 MB):
  - tela inteira ou dentro de uma caixa, com tamanho e cantos arredondados ajustáveis;
  - posição do formulário, estilo do formulário e escurecimento do fundo;
  - **prévia ao vivo** antes de salvar;
  - o vídeo é enviado em partes, com barra de progresso, para caber no limite de upload do PHP;
  - o vídeo é entregue por faixas, então começa a tocar antes de terminar o download e repete sem travar.
- **Texto do rodapé** personalizado.

As imagens, o vídeo e o CSS do tema são liberados para quem ainda não fez login, só o necessário para a tela de login funcionar.

## Configuração

Fica em *Configurar → Plugins → Logos da Empresa*, pelo ícone de engrenagem. Cada recurso pode ser ativado ou desativado separadamente, e desativar volta ao visual padrão do GLPI.

---

## Download e instalação

1. Baixe o arquivo `logosdaempresa-X.Y.Z.zip` da **[última versão](../../releases/latest)**. Use o arquivo anexado à release, não o "Source code".
2. Descompacte dentro da pasta `plugins/` do GLPI. O resultado deve ser `plugins/logosdaempresa/setup.php`.
3. Ajuste o dono dos arquivos para o usuário do servidor web, por exemplo:
   ```bash
   chown -R www-data:www-data /var/www/glpi/plugins/logosdaempresa
   ```
4. No GLPI, vá em **Configurar → Plugins** e clique em **Instalar** e depois em **Ativar**. Pela linha de comando:
   ```bash
   php bin/console plugin:install logosdaempresa -u <usuário administrador>
   php bin/console plugin:activate logosdaempresa
   ```

A instalação cria as tabelas, as configurações padrão e as ações automáticas do plugin, e funciona num GLPI sem nada configurado antes.

### Atualização

Substitua a pasta `plugins/logosdaempresa` pela versão nova e rode **Instalar** de novo, ou `php bin/console plugin:install logosdaempresa -f`. Depois, ative o plugin. As tabelas e colunas novas são criadas sem perder os dados.

### Desinstalação

A desinstalação **não apaga as tabelas do plugin**: reinstalar recupera os dados.

## Versões

O histórico, com o que mudou em cada versão e o arquivo para download, está em **[Releases](../../releases)**. Cada versão entrou por um **[pull request](../../pulls?q=is%3Apr)**.

## Licença

Distribuído sob a **GNU General Public License v3.0**. Veja o arquivo [LICENSE](LICENSE).