(function (window, $) {
    const App = window.App || {};

    App.state = App.state || {
        popupCloseCallback: null,
        agendaPendingEventData: null,
        profileCompletionReturnTo: '',
        loadingCounter: 0
    };

    App.core = Object.assign(App.core || {}, {
        buildUrl: function (path) {
            const appBaseUrl = String(window.APP_BASE_URL || '').replace(/\/$/, '');

            if (!path) {
                return appBaseUrl || '/';
            }

            return appBaseUrl + '/' + String(path).replace(/^\/+/, '');
        },

        escapeHtml: function (value) {
            return String(value == null ? '' : value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        },

        abrirPopup: function (tipo, mensagem, onClose) {
            const $popup = $('#popup-mensagem');
            const $titulo = $('#popup-titulo');
            const $texto = $('#popup-texto');
            const titulo = tipo === 'erro' ? 'Erro no formulário' : 'Mensagem do sistema';

            App.state.popupCloseCallback = typeof onClose === 'function' ? onClose : null;

            $popup.removeClass('popup-erro popup-sucesso hidden').addClass(tipo === 'erro' ? 'popup-erro' : 'popup-sucesso');
            $popup.attr('aria-hidden', 'false');
            $titulo.text(titulo);
            $texto.text(mensagem || 'Operacao concluída.');
        },

        abrirPopupHtml: function (tipo, html, onClose) {
            const $popup = $('#popup-mensagem');
            const $titulo = $('#popup-titulo');
            const $texto = $('#popup-texto');
            const titulo = tipo === 'erro' ? 'Erro no formulário' : 'Mensagem do sistema';

            App.state.popupCloseCallback = typeof onClose === 'function' ? onClose : null;

            $popup.removeClass('popup-erro popup-sucesso hidden').addClass(tipo === 'erro' ? 'popup-erro' : 'popup-sucesso');
            $popup.attr('aria-hidden', 'false');
            $titulo.text(titulo);
            $texto.html(String(html || 'Operacao concluída.'));
        },

        abrirPopupCustomizado: function (selector) {
            $(selector).removeClass('hidden').attr('aria-hidden', 'false');
        },

        fecharPopup: function () {
            const callback = App.state.popupCloseCallback;

            App.state.popupCloseCallback = null;
            $('#popup-mensagem').addClass('hidden').attr('aria-hidden', 'true');

            if (callback) {
                callback();
            }
        },

        fecharPopupCustomizado: function (selector) {
            $(selector).addClass('hidden').attr('aria-hidden', 'true');
        },

        shouldSkipLoadingForUrl: function (url, method) {
            const normalizedMethod = String(method || 'GET').toUpperCase();
            const normalizedUrl = App.core.getAppRelativePath(String(url || ''));

            if (
                normalizedUrl.indexOf('/api/ceps/validar') === 0 ||
                normalizedUrl.indexOf('/api/ceps/endereco') === 0 ||
                normalizedUrl.indexOf('/admin/locais/lista') === 0 ||
                normalizedUrl.indexOf('/api/cpf/cadastro-status') === 0
            ) {
                return true;
            }

            if (normalizedMethod === 'GET' && normalizedUrl.indexOf('/admin/pessoas/lista') === 0) {
                return true;
            }

            return false;
        },

        shouldSkipLoadingForRequest: function (settings) {
            if (settings && settings.suppressGlobalLoading === true) {
                return true;
            }

            return App.core.shouldSkipLoadingForUrl(settings && settings.url, settings && settings.type);
        },

        showLoading: function (message) {
            const $overlay = $('#app-loading-overlay');
            const $text = $('#app-loading-text');

            if ($overlay.length === 0) {
                return;
            }

            App.state.loadingCounter = Number(App.state.loadingCounter || 0) + 1;
            $text.text(String(message || 'Carregando...'));
            $('body').addClass('app-loading-active');
            $overlay.removeClass('hidden').attr('aria-hidden', 'false');
        },

        hideLoading: function (force) {
            const $overlay = $('#app-loading-overlay');

            if ($overlay.length === 0) {
                return;
            }

            if (force) {
                App.state.loadingCounter = 0;
            } else {
                App.state.loadingCounter = Math.max(0, Number(App.state.loadingCounter || 0) - 1);
            }

            if (App.state.loadingCounter > 0) {
                return;
            }

            $('body').removeClass('app-loading-active');
            $overlay.addClass('hidden').attr('aria-hidden', 'true');
        },

        getAppRelativePath: function (url) {
            try {
                const parsed = new URL(String(url || ''), window.location.origin);
                const appRoot = new URL(App.core.buildUrl('/'), window.location.origin);
                const normalizedPath = parsed.pathname;
                const basePath = appRoot.pathname.replace(/\/+$/, '');
                let relativePath = normalizedPath;

                if (basePath !== '' && normalizedPath.indexOf(basePath) === 0) {
                    relativePath = normalizedPath.slice(basePath.length) || '/';
                }

                if (parsed.search) {
                    relativePath += parsed.search;
                }

                return relativePath;
            } catch (error) {
                return String(url || '');
            }
        },

        isModalRouteUrl: function (url) {
            if (!url) {
                return false;
            }

            try {
                const parsed = new URL(url, window.location.origin);
                const appRoot = new URL(App.core.buildUrl('/'), window.location.origin);
                const normalizedPath = parsed.pathname.replace(/\/+$/, '') || '/';
                const basePath = appRoot.pathname.replace(/\/+$/, '');

                if (parsed.origin !== window.location.origin) {
                    return false;
                }

                if (basePath !== '' && normalizedPath.indexOf(basePath) !== 0) {
                    return false;
                }

                return ['/login', '/cadastro', '/perfil/completar'].indexOf(normalizedPath.slice(basePath.length) || '/') >= 0;
            } catch (error) {
                return false;
            }
        },

        pageRequiresProfileCompletion: function () {
            return String($('body').attr('data-profile-completion-required') || '') === '1';
        },

        pageIsAuthenticated: function () {
            return $('.site-nav form.inline-form[action$="/logout"]').length > 0;
        },

        profileCompletionMessage: function () {
            return String($('body').attr('data-profile-completion-message') || 'Antes de acessar esta área, você precisa completar seu cadastro.');
        },

        abrirConfirmacaoCompletarCadastro: function (returnTo) {
            // A navegação para a área protegida é interrompida para exibir este
            // diálogo. Portanto, qualquer loading iniciado pelo clique anterior
            // precisa ser encerrado antes de abrir a confirmação.
            App.core.hideLoading(true);
            App.state.profileCompletionReturnTo = App.core.getAppRelativePath(returnTo || App.core.buildUrl('/dashboard'));
            $('#popup-profile-completion-texto').text(App.core.profileCompletionMessage());
            App.core.abrirPopupCustomizado('#popup-profile-completion-confirm');
        },

        abrirModalDeRota: function (url) {
            const $popup = $('#popup-route-modal');
            const $content = $('#popup-route-content');
            let finalUrl = String(url || '');

            if ($popup.length === 0 || $content.length === 0 || !App.core.isModalRouteUrl(finalUrl)) {
                window.location.href = finalUrl || App.core.buildUrl('/');
                return;
            }

            try {
                const parsed = new URL(finalUrl, window.location.origin);
                const normalizedPath = parsed.pathname.replace(/\/+$/, '') || '/';

                if (normalizedPath === App.core.buildUrl('/login').replace(/\/+$/, '')) {
                    if (App.core.pageIsAuthenticated()) {
                        if (App.core.pageRequiresProfileCompletion()) {
                            App.core.abrirConfirmacaoCompletarCadastro(parsed.searchParams.get('return_to') || '/dashboard');
                            return;
                        }

                        window.location.href = App.core.buildUrl(parsed.searchParams.get('return_to') || '/dashboard');
                        return;
                    }

                    if (!parsed.searchParams.get('return_to')) {
                        parsed.searchParams.set('return_to', window.location.pathname + window.location.search);
                    }
                }

                finalUrl = parsed.pathname + parsed.search;
            } catch (error) {
                finalUrl = String(url || '');
            }

            $content.html('<p class="muted">Carregando formulário...</p>');
            App.core.abrirPopupCustomizado('#popup-route-modal');

            $.ajax({
                url: finalUrl,
                method: 'GET',
                data: { modal: '1' },
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).done(function (html) {
                $content.html(
                    '<button type="button" class="popup-close-icon popup-route-inline-close" data-close-popup="#popup-route-modal" aria-label="Fechar formulario">&times;</button>' +
                    String(html || '')
                );
            }).fail(function (xhr) {
                const erro = App.core.extrairMensagemErroAjax(xhr);

                App.core.fecharPopupCustomizado('#popup-route-modal');
                App.core.abrirPopup('erro', erro.mensagem, function () {
                    window.location.href = finalUrl;
                });
            });
        },

        togglePopupSection: function ($element, hasContent) {
            $element.toggleClass('hidden', !hasContent);
        },

        preencherPopupVisual: function (prefixo, dados) {
            const titulo = String(dados.titulo || '').trim();
            const textoPrincipal = String(dados.texto_principal || '').trim();
            const textoSecundario = String(dados.texto_secundario || '').trim();
            const imagemUrl = String(dados.imagem_url || '').trim();
            const rotuloAcao = String(dados.rotulo_acao || '').trim();
            const urlAcao = String(dados.url_acao || '').trim();

            const $head = $(prefixo + '-head').length ? $(prefixo + '-head') : $(prefixo + '-titulo').closest('.popup-head');
            const $media = $(prefixo + '-media').length ? $(prefixo + '-media') : $(prefixo + '-imagem').closest('.popup-site-media');
            const $actions = $(prefixo + '-actions').length ? $(prefixo + '-actions') : $(prefixo + '-acao').closest('.popup-actions');

            $(prefixo + '-titulo').text(titulo);
            $(prefixo + '-texto-principal').text(textoPrincipal);
            $(prefixo + '-texto-secundario').text(textoSecundario);
            $(prefixo + '-imagem').attr('src', imagemUrl);
            $(prefixo + '-acao').text(rotuloAcao);
            $(prefixo + '-acao').attr('href', urlAcao || '#');

            App.core.togglePopupSection($head, titulo !== '');
            App.core.togglePopupSection($(prefixo + '-texto-principal'), textoPrincipal !== '');
            App.core.togglePopupSection($(prefixo + '-texto-secundario'), textoSecundario !== '');
            App.core.togglePopupSection($media, imagemUrl !== '');
            App.core.togglePopupSection($actions, rotuloAcao !== '' && urlAcao !== '');
        },

        lerFormularioPopup: function () {
            const $form = $('#form-site-popup');

            return {
                titulo: $form.find('input[name="titulo"]').val(),
                texto_principal: $form.find('textarea[name="texto_principal"]').val(),
                texto_secundario: $form.find('textarea[name="texto_secundario"]').val(),
                imagem_url: $form.find('input[name="imagem_url"]').val(),
                rotulo_acao: $form.find('input[name="rotulo_acao"]').val(),
                url_acao: $form.find('input[name="url_acao"]').val()
            };
        },

        extrairMensagemErroAjax: function (xhr) {
            const mensagemPadrao = 'Não foi possível concluir a operação agora.';
            let mensagem = mensagemPadrao;
            let redirectUrl = '';

            if (xhr && xhr.responseJSON) {
                mensagem = String(xhr.responseJSON.message || mensagemPadrao);
                redirectUrl = String(xhr.responseJSON.redirect || '');
                return { mensagem, redirectUrl };
            }

            if (xhr && typeof xhr.responseText === 'string' && xhr.responseText.trim() !== '') {
                try {
                    const parsed = JSON.parse(xhr.responseText);
                    mensagem = String(parsed.message || mensagemPadrao);
                    redirectUrl = String(parsed.redirect || '');
                    return { mensagem, redirectUrl };
                } catch (error) {
                    mensagem = xhr.responseText.trim() || mensagemPadrao;
                    return { mensagem, redirectUrl };
                }
            }

            return { mensagem, redirectUrl };
        },

        mascararCpf: function (selector) {
            $(document).on('input', selector, function () {
                let value = $(this).val().replace(/\D+/g, '').slice(0, 11);
                value = value.replace(/(\d{3})(\d)/, '$1.$2');
                value = value.replace(/(\d{3})(\d)/, '$1.$2');
                value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
                $(this).val(value);
            });
        },

        mascararTelefone: function (selector) {
            $(document).on('input', selector, function () {
                let value = $(this).val().replace(/\D+/g, '').slice(0, 11);
                value = value.replace(/^(\d{2})(\d)/g, '($1) $2');
                value = value.replace(/(\d{5})(\d)/, '$1-$2');
                $(this).val(value);
            });
        },

        mascararCep: function (selector) {
            $(document).on('input', selector, function () {
                let value = $(this).val().replace(/\D+/g, '').slice(0, 8);
                value = value.replace(/(\d{5})(\d)/, '$1-$2');
                $(this).val(value);
            });
        },

        mascararCodigoCid: function (selector) {
            $(document).on('input', selector, function () {
                let value = String($(this).val() || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
                let letter = '';
                let digits = '';

                for (let i = 0; i < value.length; i += 1) {
                    const char = value.charAt(i);

                    if (letter === '' && /[A-Z]/.test(char)) {
                        letter = char;
                        continue;
                    }

                    if (/[0-9]/.test(char)) {
                        digits += char;
                    }
                }

                digits = digits.slice(0, 3);

                let formatted = letter;

                if (digits.length > 0) {
                    formatted += digits.slice(0, Math.min(2, digits.length));
                }

                if (digits.length >= 3) {
                    formatted += '.' + digits.slice(2, 3);
                }

                $(this).val(formatted.slice(0, 5));
            });
        },

        mascararNumeroNis: function (selector) {
            $(document).on('input', selector, function () {
                const digits = String($(this).val() || '').replace(/\D+/g, '').slice(0, 11);
                let formatted = '';

                for (let index = 0; index < digits.length; index += 1) {
                    if (index > 0 && index % 4 === 0) {
                        formatted += ' ';
                    }

                    formatted += digits.charAt(index);
                }

                $(this).val(formatted);
            });
        },

        mascararCartaoSus: function (selector) {
            $(document).on('input', selector, function () {
                const digits = String($(this).val() || '').replace(/\D+/g, '').slice(0, 15);
                let formatted = '';

                for (let index = 0; index < digits.length; index += 1) {
                    if (index > 0 && index % 4 === 0) {
                        formatted += ' ';
                    }

                    formatted += digits.charAt(index);
                }

                $(this).val(formatted);
            });
        },

        iniciarLoadingGlobal: function () {
            $(document).ajaxSend(function (event, xhr, settings) {
                if (App.core.shouldSkipLoadingForRequest(settings)) {
                    return;
                }

                App.core.showLoading('Carregando...');
            });

            $(document).ajaxComplete(function (event, xhr, settings) {
                if (App.core.shouldSkipLoadingForRequest(settings)) {
                    return;
                }

                App.core.hideLoading();
            });

            $(window).on('pageshow', function () {
                App.core.hideLoading(true);
            });

            $(window).on('beforeunload', function () {
                App.core.showLoading('Carregando...');
            });

            $(document).on('click', 'a[href]', function (event) {
                const href = String($(this).attr('href') || '').trim();

                if (
                    href === '' ||
                    href.indexOf('#') === 0 ||
                    $(this).attr('target') === '_blank' ||
                    event.ctrlKey ||
                    event.metaKey ||
                    event.shiftKey ||
                    event.altKey ||
                    $(this).is('[data-open-route-modal]') ||
                    (
                        $(this).is('[data-profile-completion-link="1"]') &&
                        App.core.pageRequiresProfileCompletion()
                    ) ||
                    App.core.isModalRouteUrl(href)
                ) {
                    return;
                }

                App.core.showLoading('Carregando página...');
            });

            $(document).on('submit', 'form:not([data-ajax-form="1"]):not([data-manual-submit="1"])', function (event) {
                const submitEvent = event;

                window.setTimeout(function () {
                    if (!submitEvent.isDefaultPrevented()) {
                        App.core.showLoading('Enviando dados...');
                    }
                }, 0);
            });
        },

        iniciarConfirmacoesExclusao: function () {
            function solicitarConfirmacao(element) {
                const $element = $(element);
                const message = String(
                    $element.attr('data-confirm-delete-message')
                    || $element.closest('form').attr('data-confirm-delete-message')
                    || 'Tem certeza de que deseja excluir este registro? Esta ação não poderá ser desfeita.'
                );

                return window.confirm(message);
            }

            $(document).on('submit', 'form', function (event) {
                const $form = $(this);
                const action = String($form.attr('action') || '').toLowerCase();
                const isDeleteAction = $form.is('[data-confirm-delete="1"]')
                    || /\/(?:excluir|remover|delete)(?:[/?#]|$)/.test(action);

                if (
                    !isDeleteAction
                    || $form.is('[data-skip-delete-confirmation="1"]')
                    || solicitarConfirmacao($form)
                ) {
                    return;
                }

                event.preventDefault();
                event.stopImmediatePropagation();
            });

            $(document).on('click', '[data-confirm-delete="1"]:not(form)', function (event) {
                if (solicitarConfirmacao(this)) {
                    return;
                }

                event.preventDefault();
                event.stopImmediatePropagation();
            });
        },

        validarCepSbc: function (selector) {
            function obterMensagem($input) {
                let $message = $input.siblings('.cep-helper');

                if ($message.length === 0) {
                    $message = $('<small class="cep-helper muted"></small>');
                    $input.after($message);
                }

                return $message;
            }

            $(document).on('input blur', selector, function () {
                const rawValue = String($(this).val() || '').replace(/\D+/g, '');
                const $message = obterMensagem($(this));

                if (rawValue.length === 0) {
                    $message.text('Aceito automaticamente somente CEPs de São Bernardo do Campo.');
                    return;
                }

                if (rawValue.length < 8) {
                    $message.text('Somente números, 8 dígitos.');
                    return;
                }

                $message.text('Consultando regras de aceitacao do CEP...');

                $.getJSON(App.core.buildUrl('/api/ceps/validar'), { cep: rawValue })
                    .done(function (response) {
                        if (!response || typeof response.mensagem === 'undefined') {
                            $message.text('Não foi possível validar o CEP neste momento.');
                            return;
                        }

                        $message.text(response.mensagem);
                    })
                    .fail(function () {
                        $message.text('Não foi possível validar o CEP neste momento.');
                    });
            });
        },

        validarCpfCadastro: function (selector) {
            function obterMensagem($input) {
                let $message = $input.closest('label').next('.cpf-cadastro-helper');

                if ($message.length === 0) {
                    $message = $('<small class="cpf-cadastro-helper muted"></small>');
                    $input.closest('label').after($message);
                }

                return $message;
            }

            function consultarCpf($input) {
                const rawValue = String($input.val() || '').replace(/\D+/g, '');
                const $message = obterMensagem($input);

                if (rawValue.length === 0) {
                    $input.data('cpfCadastroPermitido', false);
                    $input.data('cpfCadastroStatus', '');
                    $message.text('Ao informar o CPF, o sistema avisará imediatamente se a conta já existe, se o CPF pertence a um dependente ou se a criação da conta está liberada.');
                    return;
                }

                if (rawValue.length < 11) {
                    $input.data('cpfCadastroPermitido', false);
                    $input.data('cpfCadastroStatus', '');
                    $message.text('Digite os 11 números do CPF para validar o cadastro.');
                    return;
                }

                $message.text('Consultando a situação deste CPF no sistema...');

                $.getJSON(App.core.buildUrl('/api/cpf/cadastro-status'), { cpf: rawValue })
                    .done(function (response) {
                        if (!response || typeof response.status === 'undefined') {
                            $input.data('cpfCadastroPermitido', false);
                            $message.text('Não foi possível validar este CPF agora.');
                            return;
                        }

                        const status = String(response.status || '');
                        const podeCriarConta = !!response.pode_criar_conta;
                        const mensagemPopup = String(response.mensagem_popup || '');
                        const mensagemHelper = String(response.mensagem_helper || '');
                        const lastAlertKey = String($input.data('cpfCadastroAlertKey') || '');
                        const alertKey = rawValue + ':' + status;

                        $input.data('cpfCadastroPermitido', podeCriarConta);
                        $input.data('cpfCadastroStatus', status);
                        $message.text(mensagemHelper || 'Situação do CPF atualizada.');

                        if (mensagemPopup !== '' && lastAlertKey !== alertKey && status !== 'disponivel') {
                            App.core.abrirPopup(podeCriarConta ? 'sucesso' : 'erro', mensagemPopup);
                            $input.data('cpfCadastroAlertKey', alertKey);
                        }
                    })
                    .fail(function () {
                        $input.data('cpfCadastroPermitido', false);
                        $message.text('Não foi possível validar este CPF agora.');
                    });
            }

            let timeoutId = null;

            $(document).on('input', selector, function () {
                const $input = $(this);
                window.clearTimeout(timeoutId);
                timeoutId = window.setTimeout(function () {
                    consultarCpf($input);
                }, 350);
            });

            $(document).on('blur', selector, function () {
                consultarCpf($(this));
            });

            $(document).on('submit', 'form[action$="/cadastro"]', function (event) {
                const $cpfInput = $(this).find(selector).first();
                const status = String($cpfInput.data('cpfCadastroStatus') || '');

                if ($cpfInput.length === 0) {
                    return;
                }

                if (status === 'cpf_invalido' || status === 'dependente_menor_sem_conta' || status === 'conta_existente') {
                    event.preventDefault();
                    App.core.abrirPopup('erro', 'Não é possível concluir a criação da conta com este CPF. Confira o aviso exibido pelo sistema.');
                }
            });
        },

        iniciarAvisoSexoNaoDeclarado: function (selector) {
            function syncWarning($select) {
                const value = String($select.val() || '');
                const $warning = $select.siblings('[data-sexo-warning="1"]').first();

                if ($warning.length === 0) {
                    return;
                }

                $warning.toggleClass('hidden', value !== 'Sexo nao declarado');
            }

            $(document).on('change', selector, function () {
                syncWarning($(this));
            });

            $(selector).each(function () {
                syncWarning($(this));
            });
        },

        iniciarSelecaoExclusivaCondicoes: function (selector) {
            function syncGroup($changedInput) {
                const $scope = $changedInput.closest('form');
                const $group = ($scope.length > 0 ? $scope : $(document)).find(selector);
                const $helper = ($scope.length > 0 ? $scope : $(document)).find('[data-condition-helper="1"]').first();

                if ($changedInput.is(':checked')) {
                    $group.not($changedInput).prop('checked', false);
                }

                if ($helper.length > 0) {
                    $helper.text('Somente uma condição pode ser selecionada por pessoa: PCD, PVS ou PLM.');
                }
            }

            $(document).on('change', selector, function () {
                syncGroup($(this));
            });

            $(selector).each(function () {
                syncGroup($(this));
            });
        },

        iniciarSitePopups: function () {
            const $popupSite = $('#popup-site');
            const $popupPreview = $('#popup-preview-site');
            const $todasPaginas = $('#popup-todas-paginas');
            const $paginasAlvo = $('#popup-paginas-alvo');

            if ($popupSite.length > 0 && String($popupSite.data('openOnLoad') || '') === '1') {
                App.core.abrirPopupCustomizado('#popup-site');
            }

            if ($todasPaginas.length > 0) {
                const syncPagesState = function () {
                    const disabled = $todasPaginas.is(':checked');
                    $paginasAlvo.toggleClass('is-disabled', disabled);
                    $paginasAlvo.find('input[type="checkbox"]').prop('disabled', disabled);
                };

                syncPagesState();
                $(document).on('change', '#popup-todas-paginas', syncPagesState);
            }

            $(document).on('click', '#preview-site-popup', function () {
                App.core.preencherPopupVisual('#popup-preview', App.core.lerFormularioPopup());
                App.core.abrirPopupCustomizado('#popup-preview-site');
            });

            $(document).on('click', '.popup-preview-trigger', function () {
                const $button = $(this);

                App.core.preencherPopupVisual('#popup-preview', {
                    titulo: $button.data('titulo'),
                    texto_principal: $button.data('textoPrincipal'),
                    texto_secundario: $button.data('textoSecundario'),
                    imagem_url: $button.data('imagemUrl'),
                    rotulo_acao: $button.data('rotuloAcao'),
                    url_acao: $button.data('urlAcao')
                });
                App.core.abrirPopupCustomizado('#popup-preview-site');
            });

            if ($popupPreview.length > 0) {
                App.core.preencherPopupVisual('#popup-preview', {
                    titulo: '',
                    texto_principal: '',
                    texto_secundario: '',
                    imagem_url: '',
                    rotulo_acao: '',
                    url_acao: ''
                });
            }
        },

        iniciarTabelasResponsivas: function () {
            let updateTimer = null;

            function applyLabels(scope) {
                const $scope = scope ? $(scope) : $(document);
                let $tables = $scope.is('.data-table') ? $scope : $scope.find('.data-table');

                if ($scope.closest('.data-table').length > 0) {
                    $tables = $tables.add($scope.closest('.data-table'));
                }

                $tables.each(function () {
                    const $table = $(this);
                    const labels = $table.find('thead tr').first().children('th').map(function () {
                        return String($(this).text() || '').replace(/\s+/g, ' ').trim();
                    }).get();

                    $table.find('tbody tr').each(function () {
                        $(this).children('td').each(function (index) {
                            const $cell = $(this);
                            const colspan = Number($cell.attr('colspan') || 1);

                            if (colspan > 1) {
                                $cell.addClass('responsive-table-full-cell').removeAttr('data-label');
                                return;
                            }

                            $cell
                                .removeClass('responsive-table-full-cell')
                                .attr('data-label', labels[index] || 'Dado');
                        });
                    });
                });
            }

            applyLabels(document);

            if (!window.MutationObserver || !document.body) {
                return;
            }

            const observer = new window.MutationObserver(function (mutations) {
                window.clearTimeout(updateTimer);
                updateTimer = window.setTimeout(function () {
                    mutations.forEach(function (mutation) {
                        mutation.addedNodes.forEach(function (node) {
                            if (node.nodeType === 1) {
                                applyLabels(node);
                            }
                        });
                    });
                }, 0);
            });

            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        },

        iniciarMenuHeader: function () {
            const $header = $('.site-header').first();
            const $toggle = $header.find('.site-header-menu-toggle').first();
            const $navigation = $header.find('.site-nav').first();

            if ($header.length === 0 || $toggle.length === 0 || $navigation.length === 0) {
                return;
            }

            function setMenuOpen(open) {
                const isOpen = Boolean(open);

                $header.toggleClass('is-menu-open', isOpen);
                $toggle
                    .attr('aria-expanded', isOpen ? 'true' : 'false')
                    .attr('aria-label', isOpen ? 'Fechar menu de navegação' : 'Abrir menu de navegação')
                    .attr('title', isOpen ? 'Fechar menu' : 'Abrir menu');
            }

            $toggle.on('click', function () {
                setMenuOpen(!$header.hasClass('is-menu-open'));
            });

            $navigation.on('click', 'a, button[type="submit"]', function () {
                setMenuOpen(false);
            });

            $(document).on('click.siteHeaderMenu', function (event) {
                if ($header.hasClass('is-menu-open') && $(event.target).closest('.site-header').length === 0) {
                    setMenuOpen(false);
                }
            });

            $(document).on('keydown.siteHeaderMenu', function (event) {
                if (event.key === 'Escape') {
                    setMenuOpen(false);
                }
            });

            $(window).on('resize.siteHeaderMenu', function () {
                if (window.innerWidth > 980) {
                    setMenuOpen(false);
                }
            });
        },

        iniciarImportacaoPessoaExterna: function () {
            const fieldMap = {
                nome_completo: ['full_name'],
                data_nascimento: ['birth_date'],
                sexo: ['sexo'],
                telefone_whatsapp: ['phone_whatsapp'],
                email: ['email'],
                numero_cartao_sus: ['numero_cartao_sus'],
                cep: ['zip_code'],
                logradouro: ['street'],
                numero_endereco: ['address_number'],
                complemento: ['address_complement'],
                bairro: ['neighborhood'],
                cidade: ['city'],
                uf: ['state'],
                contato_emergencia_nome: ['emergency_contact_name'],
                contato_emergencia_telefone: ['emergency_contact_phone'],
                responsavel1_nome: ['responsavel1_nome', 'parent1_name'],
                responsavel1_cpf: ['responsavel1_cpf', 'parent1_cpf'],
                responsavel2_nome: ['responsavel2_nome', 'parent2_name'],
                responsavel2_cpf: ['responsavel2_cpf', 'parent2_cpf']
            };

            function escapeHtml(value) {
                return $('<div>').text(String(value || '')).html();
            }

            function getCpf($form) {
                const fixedCpf = String($form.attr('data-external-person-cpf') || '');
                return (fixedCpf || String($form.find('[name="cpf"]').first().val() || '')).replace(/\D+/g, '');
            }

            function fillForm($form, record) {
                $.each(fieldMap, function (sourceField, targetNames) {
                    const value = String(record[sourceField] || '');

                    targetNames.some(function (targetName) {
                        const $field = $form.find('[name="' + targetName + '"]').first();

                        if ($field.length === 0) {
                            return false;
                        }

                        $field.val(value).trigger('input').trigger('change');
                        return true;
                    });
                });
            }

            $(document).on('click', '[data-external-person-search="1"]', function () {
                const $button = $(this);
                const $form = $button.closest('[data-external-person-form="1"]');
                const $results = $form.find('[data-external-person-results="1"]').first();
                const cpf = getCpf($form);

                if (cpf.length !== 11) {
                    App.core.abrirPopup('erro', 'Informe um CPF válido antes de procurar os dados.');
                    return;
                }

                $button.prop('disabled', true);
                $results.removeClass('hidden').html('<p class="muted">Procurando registros...</p>');

                $.getJSON(App.core.buildUrl('/api/pessoas-externas/registros'), { cpf: cpf })
                    .done(function (response) {
                        const records = response && Array.isArray(response.registros) ? response.registros : [];

                        if (!response || response.success === false) {
                            $results.html('<p class="muted">' + escapeHtml(response && response.message ? response.message : 'Não foi possível realizar a consulta.') + '</p>');
                            return;
                        }

                        if (records.length === 0) {
                            $results.html('<p class="muted">Nenhum registro foi encontrado para este CPF.</p>');
                            return;
                        }

                        $results.html(records.map(function (record) {
                            const details = [
                                record.data_nascimento_resumida,
                                record.unidade,
                                record.situacao
                            ].filter(Boolean).map(escapeHtml).join(' • ');

                            return [
                                '<div class="external-person-result">',
                                '<div><strong>', escapeHtml(record.nome_completo || 'Registro encontrado'), '</strong>',
                                details ? '<p class="muted">' + details + '</p>' : '',
                                '</div>',
                                '<button type="button" class="btn btn-primary" data-external-person-select="1" data-record-id="',
                                Number(record.registro_id || 0), '">Usar estes dados</button>',
                                '</div>'
                            ].join('');
                        }).join(''));
                    })
                    .fail(function (xhr) {
                        const error = App.core.extrairMensagemErroAjax(xhr);
                        $results.html('<p class="muted">' + escapeHtml(error.mensagem) + '</p>');
                    })
                    .always(function () {
                        $button.prop('disabled', false);
                    });
            });

            $(document).on('click', '[data-external-person-inline-select="1"]', function () {
                const $button = $(this);
                const $form = $button.closest('[data-external-person-form="1"]');
                const cpf = getCpf($form);
                const recordId = Number($button.attr('data-record-id') || 0);

                if (!window.confirm('Deseja preencher o formulário com os dados deste registro? Os campos atuais serão substituídos, mas nada será salvo até você revisar e enviar o formulário.')) {
                    return;
                }

                $button.prop('disabled', true).text('Carregando...');

                $.getJSON(App.core.buildUrl('/api/pessoas-externas/registro'), {
                    cpf: cpf,
                    registro_id: recordId
                }).done(function (response) {
                    if (!response || response.success === false || !response.registro) {
                        App.core.abrirPopup('erro', response && response.message ? response.message : 'Não foi possível carregar o registro.');
                        return;
                    }

                    fillForm($form, response.registro);
                    $form.find('[data-external-person-results="1"]').addClass('hidden').empty();
                    App.core.abrirPopup('sucesso', 'Dados preenchidos. Revise todas as informações antes de salvar.');
                }).fail(function (xhr) {
                    App.core.abrirPopup('erro', App.core.extrairMensagemErroAjax(xhr).mensagem);
                }).always(function () {
                    $button.prop('disabled', false).text('Usar estes dados');
                });
            });

            let $activeExternalPersonForm = $();
            let restoreRouteModalAfterChoice = false;

            function closeExternalPersonChoice() {
                App.core.fecharPopupCustomizado('#external-person-choice-modal');
                $('#external-person-choice-results').empty();

                if (restoreRouteModalAfterChoice) {
                    App.core.abrirPopupCustomizado('#popup-route-modal');
                }

                restoreRouteModalAfterChoice = false;
                $activeExternalPersonForm = $();
            }

            function showExternalPersonChoices($form, records) {
                $activeExternalPersonForm = $form;
                restoreRouteModalAfterChoice = $form.closest('#popup-route-modal').length > 0
                    && !$('#popup-route-modal').hasClass('hidden');

                if (restoreRouteModalAfterChoice) {
                    App.core.fecharPopupCustomizado('#popup-route-modal');
                }

                $('#external-person-choice-results').html(records.map(function (record) {
                    const birthDate = String(record.data_nascimento_resumida || '');
                    const email = String(record.email || '');
                    const inclusionDate = String(record.data_inclusao || '');

                    return [
                        '<div class="external-person-result">',
                        '<div><strong>', escapeHtml(record.nome_completo || 'Pessoa encontrada'), '</strong>',
                        '<p class="muted">',
                        birthDate ? 'Nascimento: ' + escapeHtml(birthDate) + '<br>' : '',
                        email ? 'E-mail: ' + escapeHtml(email) + '<br>' : 'E-mail não informado<br>',
                        inclusionDate
                            ? 'Incluído no sistema em: ' + escapeHtml(inclusionDate)
                            : 'Data de inclusão não informada',
                        '</p></div>',
                        '<button type="button" class="btn btn-primary" data-external-person-select="1" data-record-id="',
                        Number(record.registro_id || 0), '">Selecionar pessoa</button>',
                        '</div>'
                    ].join('');
                }).join(''));

                App.core.abrirPopupCustomizado('#external-person-choice-modal');
            }

            function searchExternalPersonRecords($form) {
                const cpf = getCpf($form);
                const previousCpf = String($form.data('externalPersonSearchedCpf') || '');

                if (cpf.length !== 11 || previousCpf === cpf || $form.data('externalPersonSearching')) {
                    return;
                }

                $form.data('externalPersonSearching', true);
                $form.data('externalPersonSearchedCpf', cpf);

                $.getJSON(App.core.buildUrl('/api/pessoas-externas/registros'), { cpf: cpf })
                    .done(function (response) {
                        const records = response && Array.isArray(response.registros) ? response.registros : [];

                        if (response && response.success !== false && records.length > 0) {
                            showExternalPersonChoices($form, records);
                            return;
                        }

                        if (!response || response.success === false) {
                            App.core.abrirPopup(
                                'erro',
                                response && response.message
                                    ? response.message
                                    : 'Não foi possível consultar os dados existentes.'
                            );
                        }
                    })
                    .fail(function (xhr) {
                        App.core.abrirPopup('erro', App.core.extrairMensagemErroAjax(xhr).mensagem);
                    })
                    .always(function () {
                        $form.data('externalPersonSearching', false);
                    });
            }

            function scanExternalPersonForms() {
                $('[data-external-person-auto-search="1"]').each(function () {
                    const $form = $(this);

                    if (!$form.data('externalPersonAutoStarted')) {
                        $form.data('externalPersonAutoStarted', true);
                        searchExternalPersonRecords($form);
                    }
                });
            }

            $(document).on('input', '[data-external-person-form="1"] input[name="cpf"]', function () {
                const $form = $(this).closest('[data-external-person-form="1"]');

                if (getCpf($form).length < 11) {
                    $form.removeData('externalPersonSearchedCpf');
                    return;
                }

                searchExternalPersonRecords($form);
            });

            $(document).on('click', '#external-person-choice-manual, [data-external-person-choice-close="1"]', function (event) {
                event.preventDefault();
                event.stopPropagation();
                closeExternalPersonChoice();
            });

            $(document).on('click', '#external-person-choice-modal', function (event) {
                if (event.target === this) {
                    closeExternalPersonChoice();
                }
            });

            $(document).on('keydown', function (event) {
                if (event.key === 'Escape' && !$('#external-person-choice-modal').hasClass('hidden')) {
                    closeExternalPersonChoice();
                }
            });

            $(document).on('click', '[data-external-person-select="1"]', function () {
                const $button = $(this);
                const $form = $activeExternalPersonForm;
                const cpf = getCpf($form);
                const recordId = Number($button.attr('data-record-id') || 0);

                if ($form.length === 0 || cpf.length !== 11 || recordId < 1) {
                    closeExternalPersonChoice();
                    App.core.abrirPopup('erro', 'Não foi possível identificar o formulário desta pessoa.');
                    return;
                }

                $button.prop('disabled', true).text('Carregando...');

                $.getJSON(App.core.buildUrl('/api/pessoas-externas/registro'), {
                    cpf: cpf,
                    registro_id: recordId
                }).done(function (response) {
                    if (!response || response.success === false || !response.registro) {
                        App.core.abrirPopup(
                            'erro',
                            response && response.message
                                ? response.message
                                : 'Não foi possível carregar os dados selecionados.'
                        );
                        return;
                    }

                    fillForm($form, response.registro);
                    closeExternalPersonChoice();
                    App.core.abrirPopup('sucesso', 'Dados encontrados e preenchidos. Revise todas as informações antes de salvar.');
                }).fail(function (xhr) {
                    App.core.abrirPopup('erro', App.core.extrairMensagemErroAjax(xhr).mensagem);
                }).always(function () {
                    $button.prop('disabled', false).text('Selecionar pessoa');
                });
            });

            scanExternalPersonForms();
            $(document).ajaxComplete(function () {
                window.setTimeout(scanExternalPersonForms, 0);
            });
        },

        init: function () {
            App.core.mascararCpf('input[name="cpf"], input[name="parent1_cpf"], input[name="parent2_cpf"], input[name="responsavel1_cpf"], input[name="responsavel2_cpf"], input[name="new_responsible_cpf"]');
            App.core.mascararTelefone('input[name="phone_whatsapp"], input[name="emergency_contact_phone"]');
            App.core.mascararCep('input[name="zip_code"], input[name="cep"], input[name="cep_inicio"], input[name="cep_fim"]');
            App.core.mascararCodigoCid('input[data-cid-code="1"]');
            App.core.mascararNumeroNis('input[data-nis-number="1"]');
            App.core.mascararCartaoSus('input[data-sus-card="1"]');
            App.core.validarCepSbc('input[data-cep-sbc="1"]');
            App.core.validarCpfCadastro('input[data-cpf-cadastro="1"]');
            App.core.iniciarAvisoSexoNaoDeclarado('select[data-sexo-select="1"]');
            App.core.iniciarSelecaoExclusivaCondicoes('input[data-condition-exclusive="1"]');
            App.core.iniciarSitePopups();
            App.core.iniciarConfirmacoesExclusao();
            App.core.iniciarLoadingGlobal();
            App.core.iniciarTabelasResponsivas();
            App.core.iniciarMenuHeader();
            App.core.iniciarImportacaoPessoaExterna();
        }
    });

    window.App = App;
}(window, window.jQuery));
