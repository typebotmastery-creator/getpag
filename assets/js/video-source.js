/* ==========================================================================
   GatewayPro - player de video por URL (HLS / MP4)
   ==========================================================================
   O sistema so sabia tocar video do YouTube. Este arquivo adiciona suporte a
   video progressivo (.mp4) e a transmissao HLS (.m3u8), que e o formato
   servido pelo Gumlet (video.gumlet.io/.../main.m3u8).

   Por que hls.js: so Safari toca .m3u8 nativamente. No Chrome, Edge e Firefox
   o navegador nao entende o formato e precisa de um player que monte a lista
   de segmentos (.ts) - e o que o hls.js faz.

   Como usar:
       if (GatewayProVideo.mount(playerHost, lesson.url_video)) {
           // video montado, nao tenta o YouTube
       }

   A funcao sempre devolve true ou false, para o chamador saber se pode
   cair no caso "sem video".
   ========================================================================== */
(function (global) {
    'use strict';

    // Registro do player atual, para destruir antes de trocar de aula
    // (senao o video anterior continua tocando em segundo plano).
    let hlsInstance = null;
    let currentVideoEl = null;

    function destroy() {
        if (hlsInstance) {
            try { hlsInstance.destroy(); } catch (e) { /* ignora */ }
            hlsInstance = null;
        }
        if (currentVideoEl) {
            try {
                currentVideoEl.pause();
                currentVideoEl.removeAttribute('src');
                // limpa os <source> que o browser pode ter criado
                while (currentVideoEl.firstChild) {
                    currentVideoEl.removeChild(currentVideoEl.firstChild);
                }
                currentVideoEl.load();
            } catch (e) { /* ignora */ }
            currentVideoEl = null;
        }
    }

    /* /\.m3u8(\?|$)/i  -> link de transmissao HLS (Gumlet, Mux, Cloudflare...)
       /\.mp4|\.webm|\.ogv|\.mov(\?|$)/i -> arquivo de video comum */
    function kind(url) {
        if (!url) return null;
        if (/\.m3u8(\?|$)/i.test(url)) return 'hls';
        if (/\.(mp4|webm|ogv|mov)(\?|$)/i.test(url)) return 'file';
        return null;
    }

    /* Extrai o ID de um video do YouTube de qualquer formato de link.
       O regex antigo (repetido nas telas) aceitava watch, shorts, embed, v e
       youtu.be - mas NAO /live/, que e como o YouTube entrega transmissao ao
       vivo. Link de live caia no "esta aula nao contem video".
       Formatos cobertos aqui:
         youtube.com/watch?v=ID      youtube.com/live/ID
         youtube.com/shorts/ID       youtube.com/embed/ID
         youtube.com/v/ID            youtu.be/ID
         youtube.com/?v=ID           ID puro (so os 11 caracteres) */
    function youtubeId(url) {
        if (!url) return null;
        const u = String(url).trim();

        const m = u.match(
            /(?:youtube\.com\/(?:watch\?(?:.*&)?v=|live\/|shorts\/|embed\/|v\/)|youtu\.be\/)([A-Za-z0-9_-]{11})/i
        );
        if (m && m[1]) return m[1];

        // link solto com o ID, sem dominio
        if (/^[A-Za-z0-9_-]{11}$/.test(u)) return u;

        return null;
    }

    function errorBox(host, message, detail) {
        host.innerHTML = '';
        const box = document.createElement('div');
        box.className = 'w-full aspect-video bg-black flex flex-col items-center justify-center rounded-xl p-6 text-center';
        box.innerHTML =
            '<p class="text-lg font-semibold text-red-400">' + message + '</p>' +
            (detail ? '<p class="text-sm text-gray-500 mt-2 break-all">' + detail + '</p>' : '');
        host.appendChild(box);
    }

    /* Um video de celular (9:16) dentro de uma caixa 16:9 deixa duas faixas
       pretas enormes nas laterais. Aqui o player descobre a proporcao real do
       arquivo e se adapta:

         - horizontal (>= 1.3): continua ocupando a largura toda, como antes
         - vertical/quadrado:   passa a limitar pela ALTURA e fica centralizado,
                                sem tarja preta

       A proporcao vem de videoWidth/videoHeight, que o <video> preenche sozinho
       quando carrega os metadados. Funciona igual para HLS, MP4 e YouTube, e nao
       exige pedir nada ao servidor antes de montar o player. */
    function aplicarProporcao(video) {
        const w = video.videoWidth;
        const h = video.videoHeight;
        if (!w || !h) return;

        const s = video.style;
        // comecou de 16:9 para nao pular o layout antes de saber a proporcao
        s.aspectRatio = '';

        if (w / h >= 1.3) {
            // horizontal: preenche a largura, 16:9 como antes
            s.width = '100%';
            s.height = '';
            s.maxHeight = '';
            s.maxWidth = '';
            s.marginLeft = 'auto';
            s.marginRight = 'auto';
        } else {
            // vertical ou quadrado: limita pela altura e centraliza.
            // maxWidth 100% impede que estoure a coluna em telas largas.
            s.width = 'auto';
            s.height = '';
            s.maxHeight = '75vh';
            s.maxWidth = '100%';
            s.marginLeft = 'auto';
            s.marginRight = 'auto';
        }
    }

    function mount(host, url) {
        // Derruba o player anterior ANTES de decidir o que montar. Sem isso, ir de
        // uma aula do Gumlet para uma do YouTube deixava a instance do hls.js viva
        // presa num <video> que ja saiu da pagina.
        destroy();

        const type = kind(url);
        if (!type) return false;

        host.innerHTML = '';

        const video = document.createElement('video');
        video.className = 'w-full bg-black rounded-xl';
        // formato padrao enquanto nao sabe a proporcao real
        video.style.aspectRatio = '16 / 9';
        video.controls = true;
        video.playsInline = true;
        video.preload = 'metadata';
        // HLS nao serve bem sem estes dois em alguns navegadores
        video.setAttribute('playsinline', '');
        video.setAttribute('webkit-playsinline', '');

        if (type === 'hls') {
            // O hls.js vem PRIMEIRO de proposito.
            //
            // O Chrome responde "maybe" para 'application/vnd.apple.mpegurl', mas
            // isso NAO significa que ele reproduza: o valor e truthy, entao a
            // ordem antiga (canPlayType antes do hls.js) fazia o Chrome colocar
            // o .m3u8 direto no <video>, nao reproduzir e disparar 'error'.
            // Quem reproduz nativamente e so Safari/iOS, que o hls.js nao cobre.
            if (global.Hls && global.Hls.isSupported()) {
                const hls = new global.Hls({
                    // comeca em qualidade automatica e deixa subir sozinho
                    startLevel: -1,
                    capLevelToPlayerSize: true,
                    enableWorker: true,
                    lowLatencyMode: false
                });
                hlsInstance = hls;
                hls.on(global.Hls.Events.ERROR, function (_evt, data) {
                    if (!data || !data.fatal) return;
                    // tenta recuperar antes de desistir
                    switch (data.type) {
                        case global.Hls.ErrorTypes.NETWORK_ERROR:
                            hls.startLoad();
                            break;
                        case global.Hls.ErrorTypes.MEDIA_ERROR:
                            hls.recoverMediaError();
                            break;
                        default:
                            destroy();
                            errorBox(host, 'Nao foi possivel reproduzir este video.',
                                     'O endereco do arquivo pode estar errado ou expirado.');
                    }
                });
                hls.loadSource(url);
                hls.attachMedia(video);
            } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
                // Safari e iOS tocam .m3u8 direto, sem biblioteca.
                video.src = url;
            } else {
                errorBox(host, 'Seu navegador nao suporta video HLS.',
                         'Use Chrome, Edge ou Firefox em uma versao atualizada.');
                return true;
            }
        } else {
            video.src = url;
        }

        // assim que o navegador sabe o tamanho real do arquivo, ajusta a caixa
        video.addEventListener('loadedmetadata', function () {
            try { aplicarProporcao(video); } catch (e) { /* ignora */ }
        });

        video.addEventListener('error', function () {
            if (host.querySelector('video')) {
                destroy();
                errorBox(host, 'Nao foi possivel carregar este video.',
                         'Confira se o link esta correto e se o arquivo continua disponivel.');
            }
        });

        currentVideoEl = video;
        host.appendChild(video);
        return true;
    }

    global.GatewayProVideo = { mount: mount, destroy: destroy, kind: kind, youtubeId: youtubeId };
})(window);
