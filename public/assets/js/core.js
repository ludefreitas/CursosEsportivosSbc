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
            const titulo = tipo === 'erro' ? 'Erro no formulario' : 'Mensagem do sistema';

            App.state.popupCloseCallback = typeof onClose === 'function' ? onClose : null;

            $popup.removeClass('popup-erro popup-sucesso hidden').addClass(tipo === 'erro' ? 'popup-erro' : 'popup-sucesso');
            $popup.attr('aria-hidden', 'false');
            $titulo.text(titulo);
            $texto.text(mensagem || 'Operacao concluida.');
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

            if (normalizedUrl.indexOf('/api/ceps/validar') === 0 || normalizedUrl.indexOf('/api/cpf/cadastro-status') === 0) {
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
            return String($('body').attr('data-profile-completion-message') || 'Antes de acessar esta area, voce precisa completar seu cadastro.');
        },

        abrirConfirmacaoCompletarCadastro: function (returnTo) {
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

            $content.html('<p class="muted">Carregando formulario...</p>');
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
            const mensagemPadrao = 'Nao foi possivel concluir a operacao agora.';
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
                const digits = String($(this).val() || '').replace(/\D+/g, '').slice(0, 16);
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
                    App.core.isModalRouteUrl(href)
                ) {
                    return;
                }

                App.core.showLoading('Carregando pagina...');
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
                    $message.text('Aceito automaticamente para o intervalo de CEPs de Sao Bernardo do Campo: 09600000 a 09899999. Excecoes dependem de cadastro administrativo.');
                    return;
                }

                if (rawValue.length < 8) {
                    $message.text('Complete os 8 digitos do CEP.');
                    return;
                }

                $message.text('Consultando regras de aceitacao do CEP...');

                $.getJSON(App.core.buildUrl('/api/ceps/validar'), { cep: rawValue })
                    .done(function (response) {
                        if (!response || typeof response.mensagem === 'undefined') {
                            $message.text('Nao foi possivel validar o CEP neste momento.');
                            return;
                        }

                        $message.text(response.mensagem);
                    })
                    .fail(function () {
                        $message.text('Nao foi possivel validar o CEP neste momento.');
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
                    $message.text('Ao informar o CPF, o sistema avisara imediatamente se a conta ja existe, se o CPF pertence a um dependente ou se a criacao da conta esta liberada.');
                    return;
                }

                if (rawValue.length < 11) {
                    $input.data('cpfCadastroPermitido', false);
                    $input.data('cpfCadastroStatus', '');
                    $message.text('Digite os 11 numeros do CPF para validar o cadastro.');
                    return;
                }

                $message.text('Consultando a situacao deste CPF no sistema...');

                $.getJSON(App.core.buildUrl('/api/cpf/cadastro-status'), { cpf: rawValue })
                    .done(function (response) {
                        if (!response || typeof response.status === 'undefined') {
                            $input.data('cpfCadastroPermitido', false);
                            $message.text('Nao foi possivel validar este CPF agora.');
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
                        $message.text(mensagemHelper || 'Situacao do CPF atualizada.');

                        if (mensagemPopup !== '' && lastAlertKey !== alertKey && status !== 'disponivel') {
                            App.core.abrirPopup(podeCriarConta ? 'sucesso' : 'erro', mensagemPopup);
                            $input.data('cpfCadastroAlertKey', alertKey);
                        }
                    })
                    .fail(function () {
                        $input.data('cpfCadastroPermitido', false);
                        $message.text('Nao foi possivel validar este CPF agora.');
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
                    App.core.abrirPopup('erro', 'Nao e possivel concluir a criacao da conta com este CPF. Confira o aviso exibido pelo sistema.');
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

                $warning.toggleClass('hidden', value !== 'Sexo nÃ£o declarado');
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
                    $helper.text('Somente uma condicao pode ser selecionada por pessoa: PCD, PVS ou PLM.');
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
            App.core.iniciarLoadingGlobal();
        }
    });

    window.App = App;
}(window, window.jQuery));
