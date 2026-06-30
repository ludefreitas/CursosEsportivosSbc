(function (window, $) {
    const App = window.App || {};

    App.auth = Object.assign(App.auth || {}, {
        sincronizarCabecalhoAutenticado: function () {
            const $nav = $('.site-nav').first();
            const profileCompletionRequired = App.core.pageRequiresProfileCompletion() ? '1' : '0';

            if ($nav.length === 0) {
                return;
            }

            $nav.html([
                '<a href="' + App.core.buildUrl('/') + '">Inicio</a>',
                '<a href="' + App.core.buildUrl('/agenda') + '">Agenda</a>',
                '<a href="' + App.core.buildUrl('/blog') + '">Blog</a>',
                '<a href="' + App.core.buildUrl('/dashboard') + '" data-profile-completion-link="' + profileCompletionRequired + '">Meu painel</a>',
                '<a href="' + App.core.buildUrl('/admin') + '" data-profile-completion-link="' + profileCompletionRequired + '">Admin</a>',
                '<form method="POST" action="' + App.core.buildUrl('/logout') + '" class="inline-form">',
                '<button type="submit" class="link-button">Sair</button>',
                '</form>'
            ].join(''));

            const $heroPrimaryButton = $('.hero-actions .btn-primary').first();

            if ($heroPrimaryButton.length > 0 && $.trim($heroPrimaryButton.text()) === 'Criar conta') {
                $heroPrimaryButton.attr('href', App.core.buildUrl('/dashboard'));
                $heroPrimaryButton.text('Abrir meu painel');
            }
        },

        isAuthenticationFormAction: function (action) {
            try {
                const parsed = new URL(String(action || ''), window.location.origin);
                const normalizedPath = parsed.pathname.replace(/\/+$/, '') || '/';

                return normalizedPath === App.core.buildUrl('/login').replace(/\/+$/, '') || normalizedPath === App.core.buildUrl('/cadastro').replace(/\/+$/, '');
            } catch (error) {
                return false;
            }
        },

        iniciarModalPelaUrl: function () {
            let parsed = null;
            const action = String(new URL(window.location.href).searchParams.get('abrir') || '').trim();

            if (action === '') {
                return;
            }

            try {
                parsed = new URL(window.location.href);
            } catch (error) {
                return;
            }

            parsed.searchParams.delete('abrir');
            window.history.replaceState({}, document.title, parsed.pathname + (parsed.search ? '?' + parsed.searchParams.toString() : '') + parsed.hash);

            if (action === 'login') {
                App.core.abrirModalDeRota(App.core.buildUrl('/login') + parsed.search.replace(/^\?/, '?'));
                return;
            }

            if (action === 'cadastro') {
                App.core.abrirModalDeRota(App.core.buildUrl('/cadastro') + parsed.search.replace(/^\?/, '?'));
                return;
            }

            if (action === 'completar-cadastro') {
                App.core.abrirConfirmacaoCompletarCadastro(parsed.searchParams.get('return_to') || '/dashboard');
            }
        },

        iniciarFormulariosAjax: function () {
            $(document).on('click', '#popup-fechar', function () {
                App.core.fecharPopup();
            });

            $(document).on('click', '[data-close-popup]', function () {
                App.core.fecharPopupCustomizado(String($(this).data('closePopup') || ''));
            });

            $(document).on('click', '#popup-preview-site-close, #popup-preview-site-close-footer', function () {
                App.core.fecharPopupCustomizado('#popup-preview-site');
            });

            $(document).on('click', '#popup-mensagem', function (event) {
                if (event.target === this) {
                    App.core.fecharPopup();
                }
            });

            $(document).on('click', '#popup-site, #popup-preview-site', function (event) {
                if (event.target === this) {
                    App.core.fecharPopupCustomizado('#' + event.currentTarget.id);
                }
            });

            $(document).on('click', '#popup-profile-completion-confirm', function (event) {
                if (event.target === this) {
                    App.core.fecharPopupCustomizado('#popup-profile-completion-confirm');
                }
            });

            $(document).on('click', '[data-open-route-modal]', function () {
                const $popupContext = $(this).closest('.popup-overlay');

                if ($popupContext.length > 0) {
                    App.core.fecharPopupCustomizado('#' + $popupContext.attr('id'));
                }

                App.core.abrirModalDeRota(String($(this).data('openRouteModal') || ''));
            });

            $(document).on('click', '#popup-profile-completion-open', function () {
                const returnTo = App.state.profileCompletionReturnTo || '/dashboard';

                App.core.fecharPopupCustomizado('#popup-profile-completion-confirm');
                App.core.abrirModalDeRota(App.core.buildUrl('/perfil/completar?return_to=' + encodeURIComponent(returnTo)));
            });

            $(document).on('click', '#popup-route-modal', function (event) {
                if (event.target === this) {
                    App.core.fecharPopupCustomizado('#popup-route-modal');
                }
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
                    event.altKey
                ) {
                    return;
                }

                if (!App.core.isModalRouteUrl(href)) {
                    return;
                }

                event.preventDefault();
                App.core.abrirModalDeRota(href);
            });

            $(document).on('click', 'a[data-profile-completion-link="1"]', function (event) {
                const href = String($(this).attr('href') || '').trim();

                if (!App.core.pageRequiresProfileCompletion() || href === '') {
                    return;
                }

                event.preventDefault();
                App.core.abrirConfirmacaoCompletarCadastro(href);
            });

            $(document).on('keydown', function (event) {
                if (event.key === 'Escape' && !$('#popup-mensagem').hasClass('hidden')) {
                    App.core.fecharPopup();
                }

                if (event.key === 'Escape') {
                    App.core.fecharPopupCustomizado('#popup-site');
                    App.core.fecharPopupCustomizado('#popup-preview-site');
                    App.core.fecharPopupCustomizado('#popup-route-modal');
                    App.core.fecharPopupCustomizado('#popup-profile-completion-confirm');
                }
            });

            $(document).on('submit', 'form[data-ajax-form="1"]', function (event) {
                event.preventDefault();

                const $form = $(this);
                const action = String($form.attr('action') || window.location.href);
                const method = String($form.attr('method') || 'POST').toUpperCase();
                const shouldFollowRedirect = String($form.data('followRedirect') || '') === '1';
                const shouldReset = String($form.data('successReset') || '') === '1';
                const removeClosestSelector = String($form.data('removeClosest') || '');
                const isInsideRouteModal = $form.closest('#popup-route-modal').length > 0;
                const authenticatedSessionStarted = App.auth.isAuthenticationFormAction(action);
                const $submitButton = $form.find('button[type="submit"], input[type="submit"]').first();
                const formData = new FormData($form[0]);
                const normalizedAction = action.replace(/\/+$/, '');

                $submitButton.prop('disabled', true);

                $.ajax({
                    url: action,
                    method: method,
                    data: formData,
                    processData: false,
                    contentType: false,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                }).done(function (response) {
                    if (response && response.success === false) {
                        const mensagemErro = String(response.message || 'Nao foi possivel concluir a operacao agora.');
                        const redirectErro = String(response.redirect || '');

                        App.core.abrirPopup('erro', mensagemErro, function () {
                            if (redirectErro !== '') {
                                window.location.href = redirectErro;
                            }
                        });
                        return;
                    }

                    const mensagem = response && response.message ? String(response.message) : 'Operacao realizada com sucesso.';

                    if (authenticatedSessionStarted) {
                        const $calendar = $('#calendario-treinos');
                        const $personOptions = $('#agenda-person-options');
                        const $agendaHelper = $('#agenda-person-helper');
                        const authenticationNeedsProfileCompletion = !!(
                            response &&
                            response.redirect &&
                            String(response.redirect).indexOf('/perfil/completar') >= 0
                        );

                        $('body').attr('data-profile-completion-required', authenticationNeedsProfileCompletion ? '1' : '0');
                        App.auth.sincronizarCabecalhoAutenticado();
                        $personOptions.data('agendaAuthenticated', '1');
                        $calendar.attr('data-agenda-authenticated', '1');
                        $calendar.attr('data-agenda-needs-profile-completion', authenticationNeedsProfileCompletion ? '1' : '0');

                        if ($agendaHelper.length > 0) {
                            $agendaHelper.text(
                                authenticationNeedsProfileCompletion
                                    ? 'Complete seu cadastro para liberar os nomes disponiveis para agendamento.'
                                    : 'Selecione a pessoa que deseja agendar.'
                            );
                            $agendaHelper.toggleClass('hidden', !authenticationNeedsProfileCompletion);
                        }

                        if (authenticationNeedsProfileCompletion) {
                            $('#agenda-access-warning')
                                .removeClass('hidden')
                                .text('Para agendar um horario, voce precisa completar seu cadastro.');
                        }

                        App.core.fecharPopupCustomizado('#agenda-login-reminder');
                        App.core.fecharPopupCustomizado('#agenda-profile-reminder');
                        App.core.fecharPopupCustomizado('#popup-profile-completion-confirm');
                    }

                    if (isInsideRouteModal) {
                        App.core.fecharPopupCustomizado('#popup-route-modal');
                    }

                    App.core.abrirPopup('sucesso', mensagem, function () {
                        if (removeClosestSelector !== '') {
                            $form.closest(removeClosestSelector).remove();
                        }

                        if (shouldReset) {
                            $form[0].reset();

                            if ($form.attr('id') === 'form-agendamento') {
                                $form.addClass('hidden');
                                $('#painel-evento').html('<p class="muted">Clique em um horario no calendario para ver local, vagas e regras.</p>');
                            }

                            if ($form.attr('id') === 'form-site-popup') {
                                $('#popup-todas-paginas').trigger('change');
                            }
                        }

                        if (shouldFollowRedirect && response && response.redirect) {
                            if (normalizedAction === App.core.buildUrl('/perfil/completar').replace(/\/+$/, '')) {
                                $('body').attr('data-profile-completion-required', '0');
                                $('a[data-profile-completion-link]').attr('data-profile-completion-link', '0');
                            }

                            if (authenticatedSessionStarted) {
                                try {
                                    const redirectUrl = new URL(String(response.redirect), window.location.origin);
                                    const currentUrl = new URL(window.location.href);

                                    if (redirectUrl.pathname.replace(/\/+$/, '') === currentUrl.pathname.replace(/\/+$/, '')) {
                                        if (App.agenda && typeof App.agenda.atualizarPessoasAgendamento === 'function') {
                                            App.agenda.atualizarPessoasAgendamento(true);
                                        }

                                        if (App.state.agendaPendingEventData && !authenticationNeedsProfileCompletion && App.agenda && typeof App.agenda.renderizarDetalhesAgenda === 'function') {
                                            App.agenda.renderizarDetalhesAgenda(App.state.agendaPendingEventData);
                                        }
                                        return;
                                    }
                                } catch (error) {
                                }
                            }

                            if (normalizedAction === App.core.buildUrl('/perfil/completar').replace(/\/+$/, '') && App.agenda && typeof App.agenda.atualizarPessoasAgendamento === 'function') {
                                App.agenda.atualizarPessoasAgendamento(true);
                            }

                            if (normalizedAction === App.core.buildUrl('/perfil/completar').replace(/\/+$/, '')) {
                                try {
                                    const redirectUrl = new URL(String(response.redirect), window.location.origin);
                                    const currentUrl = new URL(window.location.href);

                                    if (redirectUrl.pathname.replace(/\/+$/, '') === currentUrl.pathname.replace(/\/+$/, '')) {
                                        return;
                                    }
                                } catch (error) {
                                }
                            }

                            if (App.core.isModalRouteUrl(String(response.redirect))) {
                                App.core.abrirModalDeRota(String(response.redirect));
                                return;
                            }

                            window.location.href = String(response.redirect);
                        }
                    });
                }).fail(function (xhr) {
                    const erro = App.core.extrairMensagemErroAjax(xhr);

                    App.core.abrirPopup('erro', erro.mensagem, function () {
                        if (erro.redirectUrl !== '' && (xhr.status === 401 || xhr.status === 403)) {
                            window.location.href = erro.redirectUrl;
                        }
                    });
                }).always(function () {
                    $submitButton.prop('disabled', false);
                });
            });
        },

        init: function () {
            App.auth.iniciarFormulariosAjax();
            App.auth.iniciarModalPelaUrl();
        }
    });

    window.App = App;
}(window, window.jQuery));
