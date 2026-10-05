/**
 * Logos da Empresa - fundo da tela de login
 *  - prévia ao vivo: reflete tipo, posição, exibição, estilo, escurecimento e caixa antes de salvar
 *  - envio do vídeo MP4 (até 100 MB) em partes, com barra de progresso, para caber no limite de upload do PHP
 */
(function () {
   'use strict';

   var previa = document.getElementById('logosdaempresa-previa');
   var form   = document.getElementById('logosdaempresa-fundo-opcoes');
   if (!previa || !form) {
      return;
   }

   var midia    = document.getElementById('logosdaempresa-previa-midia');
   var login    = document.getElementById('logosdaempresa-previa-login');
   var escuro   = document.getElementById('logosdaempresa-previa-escuro');
   var vazio    = document.getElementById('logosdaempresa-previa-vazio');
   var video    = document.getElementById('logosdaempresa-previa-video');
   var salvar   = document.getElementById('logosdaempresa-fundo-salvar');
   var pendente = document.getElementById('logosdaempresa-fp-pendente');
   var soCaixa  = document.getElementById('logosdaempresa-fp-caixa');

   // ---------------------------------------------------------------------
   // Prévia ao vivo. Cada peça recebe posição e tamanho calculados (em % da
   // prévia) e limitados à área: nada fica de fora ao trocar modo ou estilo.
   // ---------------------------------------------------------------------
   function valor(nome) {
      var el = form.elements[nome];
      if (!el) { return ''; }
      if (el.type === 'checkbox') { return el.checked ? '1' : '0'; }
      return el.value;
   }

   function estadoAtual() {
      return ['fundo_login_tipo', 'fundo_login_posicao', 'fundo_login_modo', 'fundo_login_estilo',
         'fundo_login_escurecer', 'fundo_login_caixa_largura', 'fundo_login_caixa_altura', 'fundo_login_caixa_cantos']
         .map(valor).join('|');
   }

   var estadoSalvo = estadoAtual();

   function limitar(n, min, max) {
      return Math.max(min, Math.min(max, n));
   }

   function posicionar(el, r) {
      el.style.left   = r.x + '%';
      el.style.top    = r.y + '%';
      el.style.width  = r.w + '%';
      el.style.height = r.h + '%';
   }

   function atualizarPrevia() {
      var tipo    = valor('fundo_login_tipo') === 'video' ? 'video' : 'imagem';
      var posicao = valor('fundo_login_posicao') || 'direita';
      var caixa   = valor('fundo_login_modo') === 'caixa';
      var painel  = valor('fundo_login_estilo') !== 'cartao';
      var imagem  = previa.getAttribute('data-imagem') || '';
      var urlVid  = previa.getAttribute('data-video') || '';
      var usarVideo = tipo === 'video' && urlVid !== '' && !!video;

      // Área do fundo: tela inteira ou caixa centralizada
      var fundo = { x: 0, y: 0, w: 100, h: 100 };
      if (caixa) {
         fundo.w = limitar(parseInt(valor('fundo_login_caixa_largura'), 10) || 80, 50, 95);
         fundo.h = limitar(parseInt(valor('fundo_login_caixa_altura'), 10) || 75, 50, 90);
         fundo.x = (100 - fundo.w) / 2;
         fundo.y = (100 - fundo.h) / 2;
      }
      posicionar(midia, fundo);
      midia.classList.toggle('logosdaempresa-fp-midia-caixa', caixa);
      midia.style.borderRadius = caixa && valor('fundo_login_caixa_cantos') === '1' ? '6px' : '0';
      // A imagem aparece também com vídeo: é a capa enquanto ele carrega
      midia.style.backgroundImage = imagem !== '' ? 'url("' + imagem.replace(/"/g, '\\"') + '")' : '';

      // Formulário dentro da área do fundo
      var f = {};
      f.w = fundo.w * (painel ? (caixa ? 0.36 : 0.32) : (caixa ? 0.32 : 0.28));
      f.h = painel ? fundo.h : fundo.h * 0.74;
      var margem = painel ? 0 : fundo.w * 0.05;
      if (posicao === 'esquerda') {
         f.x = fundo.x + margem;
      } else if (posicao === 'centro') {
         f.x = fundo.x + (fundo.w - f.w) / 2;
      } else {
         f.x = fundo.x + fundo.w - f.w - margem;
      }
      f.y = fundo.y + (fundo.h - f.h) / 2;
      posicionar(login, f);
      login.classList.toggle('logosdaempresa-fp-login-cartao', !painel);

      escuro.style.background = 'rgba(0,0,0,' + ((parseInt(valor('fundo_login_escurecer'), 10) || 0) / 100) + ')';

      if (video) {
         video.hidden = !usarVideo;
         if (usarVideo) {
            var p = video.play();
            if (p && p.catch) { p.catch(function () { }); }
         } else {
            video.pause();
         }
      }

      var faltando = '';
      if (tipo === 'video' && urlVid === '') { faltando = 'Nenhum vídeo enviado'; }
      if (tipo === 'imagem' && imagem === '') { faltando = 'Nenhuma imagem enviada'; }
      vazio.hidden = faltando === '';
      vazio.querySelector('span').textContent = faltando;

      if (soCaixa) { soCaixa.hidden = !caixa; }

      var mudou = estadoAtual() !== estadoSalvo;
      pendente.hidden = !mudou;
      salvar.classList.toggle('logosdaempresa-pendente', mudou);
   }

   form.addEventListener('input', function (ev) {
      var alvo = ev.target;
      if (alvo && alvo.type === 'range') {
         var rotulo = alvo.parentNode.querySelector('.logosdaempresa-fp-valor');
         if (rotulo) { rotulo.textContent = alvo.value + (rotulo.getAttribute('data-sufixo') || ''); }
      }
      atualizarPrevia();
   });
   form.addEventListener('change', atualizarPrevia);
   atualizarPrevia();
   // ---------------------------------------------------------------------
   // Envio do vídeo em partes
   // ---------------------------------------------------------------------
   var entrada = document.getElementById('logosdaempresa-video-arquivo');
   if (!entrada) {
      return;
   }

   var urlAjax  = entrada.getAttribute('data-ajax');
   var maximo   = parseInt(entrada.getAttribute('data-maximo'), 10) || 104857600;
   var token    = entrada.getAttribute('data-token') || '';
   var botao    = document.getElementById('logosdaempresa-video-botao');
   var caixa    = document.getElementById('logosdaempresa-video-progresso');
   var status   = document.getElementById('logosdaempresa-video-status');
   var pct      = document.getElementById('logosdaempresa-video-pct');
   var barra    = document.getElementById('logosdaempresa-video-barra');
   var enviando = false;

   function mb(bytes) {
      return (bytes / 1048576).toFixed(1).replace('.', ',') + ' MB';
   }

   function mostrar(texto, porcento, erro) {
      caixa.hidden = false;
      caixa.classList.toggle('logosdaempresa-video-erro', !!erro);
      status.textContent = texto;
      if (typeof porcento === 'number') {
         pct.textContent = porcento + '%';
         barra.style.width = porcento + '%';
      } else {
         pct.textContent = '';
      }
   }

   function falhar(texto) {
      enviando = false;
      botao.classList.remove('logosdaempresa-desabilitado');
      mostrar(texto, null, true);
      if (typeof window.glpi_toast_error === 'function') {
         window.glpi_toast_error(texto);
      }
   }

   /* Resposta pode vir com avisos do PHP antes do JSON: tenta o JSON do final */
   function lerJson(texto) {
      try { return JSON.parse(texto); } catch (e) { }
      var m = texto.match(/\{[\s\S]*\}\s*$/);
      if (m) { try { return JSON.parse(m[0]); } catch (e2) { } }
      return null;
   }

   function postar(campos, parte) {
      var dados = new FormData();
      Object.keys(campos).forEach(function (k) { dados.append(k, campos[k]); });
      dados.append('_glpi_csrf_token', token);
      if (parte) { dados.append('parte', parte, 'parte.bin'); }
      return fetch(urlAjax, {
         method: 'POST',
         body: dados,
         credentials: 'same-origin',
         headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-Glpi-Csrf-Token': token }
      })
         .then(function (r) { return r.text(); })
         .then(function (texto) {
            var d = lerJson(texto);
            if (!d) { throw new Error('Resposta inválida do servidor.'); }
            if (d.new_token) { token = d.new_token; }
            return d;
         });
   }

   /* Uma parte, com até 3 tentativas (rede instável) */
   function enviarParte(id, arquivo, inicio, tamParte, tentativa) {
      var pedaco = arquivo.slice(inicio, Math.min(inicio + tamParte, arquivo.size));
      return postar({ action: 'video_parte', id: id, inicio: String(inicio) }, pedaco)
         .then(function (d) {
            if (!d.success) { throw new Error(d.mensagem || 'Falha ao enviar o vídeo.'); }
            return d.recebido;
         })
         .catch(function (erro) {
            if (tentativa >= 3) { throw erro; }
            return new Promise(function (ok) { setTimeout(ok, 1500 * tentativa); })
               .then(function () { return enviarParte(id, arquivo, inicio, tamParte, tentativa + 1); });
         });
   }

   function enviar(arquivo) {
      if (enviando) { return; }
      if (!/\.mp4$/i.test(arquivo.name) || (arquivo.type && arquivo.type !== 'video/mp4')) {
         falhar('Escolha um arquivo de vídeo MP4.');
         return;
      }
      if (arquivo.size > maximo) {
         falhar('O vídeo tem ' + mb(arquivo.size) + '. O limite é ' + mb(maximo) + '.');
         return;
      }

      enviando = true;
      botao.classList.add('logosdaempresa-desabilitado');
      mostrar('Preparando o envio de ' + arquivo.name + '...', 0);

      var idEnvio = '';
      postar({ action: 'video_iniciar', nome: arquivo.name, tamanho: String(arquivo.size) })
         .then(function (d) {
            if (!d.success) { throw new Error(d.mensagem || 'Não foi possível iniciar o envio.'); }
            idEnvio = d.id;
            var tamParte = parseInt(d.parte, 10) || 1048576;
            var inicio = 0;

            function proxima() {
               if (inicio >= arquivo.size) { return Promise.resolve(); }
               return enviarParte(idEnvio, arquivo, inicio, tamParte, 1).then(function (recebido) {
                  inicio = recebido;
                  var p = Math.min(99, Math.floor(inicio * 100 / arquivo.size));
                  mostrar('Enviando ' + mb(inicio) + ' de ' + mb(arquivo.size) + '...', p);
                  return proxima();
               });
            }
            return proxima();
         })
         .then(function () {
            mostrar('Conferindo o vídeo...', 99);
            return postar({ action: 'video_concluir', id: idEnvio });
         })
         .then(function (d) {
            if (!d.success) { throw new Error(d.mensagem || 'O vídeo não foi aceito.'); }
            mostrar('Vídeo enviado. Atualizando a página...', 100);
            window.location.reload();
         })
         .catch(function (erro) {
            if (idEnvio) {
               postar({ action: 'video_cancelar', id: idEnvio }).catch(function () { });
            }
            falhar(erro && erro.message ? erro.message : 'Falha ao enviar o vídeo.');
         });
   }

   entrada.addEventListener('change', function () {
      var arquivo = entrada.files && entrada.files[0];
      entrada.value = '';
      if (arquivo) { enviar(arquivo); }
   });
})();
