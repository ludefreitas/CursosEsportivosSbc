(function (window, $) {
    const App = window.App || {};

    App.auth = Object.assign(App.auth || {}, {
        obterPaginaProtegidaAtual: function () {
            return App.core.getAppRelativePath(window.location.pathname + window.location.search + window.location.hash);
        },

        lembrarPaginaProtegidaAtual: function () {
            const destination = App.auth.obterPaginaProtegidaAtual();
            const area = App.auth.identificarAreaProtegida(destination);
            const path = window.location.pathname.replace(/\/+$/, '') || '/';
            const dashboardPath = App.core.buildUrl('/dashboard').replace(/\/+$/, '');
            if (area === 'admin' || area === 'professor' || path === dashboardPath) {
                try { window.sessionStorage.setItem('cursos_sbc_last_protected_path', destination); } catch (error) {}
            }
        },

        recuperarPaginaProtegida: function (fallback) {
            const safeFallback = App.core.getAppRelativePath(fallback || '/dashboard');
            try {
                const rawStored = window.sessionStorage.getItem('cursos_sbc_last_protected_path') || '';
                const stored = rawStored ? App.core.getAppRelativePath(rawStored) : '';
                if (stored !== '' && App.auth.identificarAreaProtegida(stored) === App.auth.identificarAreaProtegida(safeFallback)) {
                    return stored;
                }
            } catch (error) {}
            return safeFallback;
        },

        identificarAreaProtegida: function (returnTo) {
            try {
                const path = new URL(String(returnTo || '/'), window.location.origin).pathname.replace(/\/+$/, '') || '/';
                const adminPath = App.core.buildUrl('/admin').replace(/\/+$/, '');
                const professorPath = App.core.buildUrl('/professor').replace(/\/+$/, '');
                if (path === adminPath || path.indexOf(adminPath + '/') === 0) return 'admin';
                if (path === professorPath || path.indexOf(professorPath + '/') === 0) return 'professor';
            } catch (error) {
            }
            return 'autenticada';
        },

        solicitarAutenticacaoNaPaginaAtual: function (returnTo, retry) {
            let destination = App.core.getAppRelativePath(returnTo || App.auth.obterPaginaProtegidaAtual());
            const currentDestination = App.auth.obterPaginaProtegidaAtual();
            if (App.auth.identificarAreaProtegida(destination) === App.auth.identificarAreaProtegida(currentDestination)) {
                destination = currentDestination;
            }
            try { window.sessionStorage.setItem('cursos_sbc_last_protected_path', destination); } catch (error) {}
            App.state.pendingProtectedAccess = {
                area: App.auth.identificarAreaProtegida(destination),
                returnTo: destination,
                retry: typeof retry === 'function' ? retry : null
            };
            App.core.hideLoading(true);
            App.core.fecharPopup();
            App.core.abrirModalDeRota(App.core.buildUrl('/login?return_to=' + encodeURIComponent(destination)));
        },

        tratarFalhaDeAcesso: function (xhr, retry, returnTo) {
            if (!xhr || Number(xhr.status || 0) !== 401) return false;
            const erro = App.core.extrairMensagemErroAjax(xhr);
            let destination = String(returnTo || erro.redirectUrl || window.location.pathname + window.location.search);
            try {
                const parsed = new URL(destination, window.location.origin);
                destination = parsed.searchParams.get('return_to') || destination;
            } catch (error) {
            }
            App.auth.solicitarAutenticacaoNaPaginaAtual(destination, retry);
            return true;
        },

        sincronizarCabecalhoAutenticado: function (adminAccessAllowed, professorAccessAllowed) {
            const $nav = $('.site-nav').first();
            const profileCompletionRequired = App.core.pageRequiresProfileCompletion() ? '1' : '0';
            const canAccessAdmin = typeof adminAccessAllowed === 'boolean'
                ? adminAccessAllowed
                : String($('body').attr('data-admin-access-allowed') || '') === '1';
            const canAccessProfessor = typeof professorAccessAllowed === 'boolean'
                ? professorAccessAllowed
                : String($('body').attr('data-professor-access-allowed') || '') === '1';

            if ($nav.length === 0) {
                return;
            }

            $('.header-login-form').remove();
            $('.site-header-register-invite').remove();

            const navItems = [
                '<a href="' + App.core.buildUrl('/cursos') + '" class="nav-color-orange">Cursos</a>',
                '<a href="' + App.core.buildUrl('/blog') + '" class="nav-color-red">Blog</a>',
                '<a href="' + App.core.buildUrl('/dashboard') + '" class="nav-color-teal" data-profile-completion-link="' + profileCompletionRequired + '">Meu painel</a>'
            ];

            if (canAccessAdmin && profileCompletionRequired !== '1') {
                navItems.push('<a href="' + App.core.buildUrl('/admin') + '" class="nav-color-orange" data-profile-completion-link="' + profileCompletionRequired + '">Admin</a>');
            }

            if (canAccessProfessor && profileCompletionRequired !== '1') {
                navItems.push('<a href="' + App.core.buildUrl('/professor') + '" class="nav-color-green" data-profile-completion-link="' + profileCompletionRequired + '">Professor</a>');
            }

            navItems.push('<form method="POST" action="' + App.core.buildUrl('/logout') + '" class="inline-form">');
            navItems.push('<button type="submit" class="link-button nav-color-green">Sair</button>');
            navItems.push('</form>');

            $nav.html(navItems.join(''));

            $('body').attr('data-admin-access-allowed', canAccessAdmin ? '1' : '0');
            $('body').attr('data-professor-access-allowed', canAccessProfessor ? '1' : '0');

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
                const returnTo = App.auth.recuperarPaginaProtegida(parsed.searchParams.get('return_to') || window.location.pathname + window.location.search + window.location.hash);
                parsed.searchParams.set('return_to', returnTo);
                App.state.pendingProtectedAccess = App.state.pendingProtectedAccess || {
                    area: App.auth.identificarAreaProtegida(returnTo),
                    returnTo: returnTo,
                    retry: null
                };
                App.core.abrirModalDeRota(App.core.buildUrl('/login') + parsed.search.replace(/^\?/, '?'));
                return;
            }

            if (action === 'cadastro') {
                App.core.abrirModalDeRota(App.core.buildUrl('/cadastro') + parsed.search.replace(/^\?/, '?'));
                return;
            }

            if (action === 'recuperar-senha') {
                App.core.abrirModalDeRota(App.core.buildUrl('/recuperar-senha'));
                return;
            }

            if (action === 'completar-cadastro') {
                App.core.abrirConfirmacaoCompletarCadastro(parsed.searchParams.get('return_to') || '/dashboard');
            }
        },

        iniciarFormulariosAjax: function () {
            const recoveryDateIsValid = function (value) {
                const match = String(value || '').match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
                if (!match) return false;
                const day = Number(match[1]);
                const month = Number(match[2]);
                const year = Number(match[3]);
                const date = new Date(year, month - 1, day);
                return year >= 1900 && year <= new Date().getFullYear() && date.getFullYear() === year && date.getMonth() === month - 1 && date.getDate() === day;
            };

            $(document).on('input', '[data-birth-date-mask="1"]', function () {
                const digits = String($(this).val() || '').replace(/\D+/g, '').slice(0, 8);
                let masked = digits.slice(0, 2);
                if (digits.length > 2) masked += '/' + digits.slice(2, 4);
                if (digits.length > 4) masked += '/' + digits.slice(4, 8);
                $(this).val(masked);
                $(this).removeClass('is-invalid').removeAttr('aria-invalid');
                $(this).siblings('[data-birth-date-error="1"]').addClass('hidden').text('');
            });

            $(document).on('submit', '[data-password-recovery-form="1"]', function (event) {
                const $form = $(this);
                const $date = $form.find('[name="birth_date"]');
                const $dateError = $form.find('[data-birth-date-error="1"]');
                const $cpf = $form.find('[name="cpf"]');
                const $cpfError = $form.find('[data-recovery-cpf-error="1"]');
                const $confirmation = $form.find('[name="password_confirmation"]');
                const $confirmationError = $form.find('[data-password-confirmation-error="1"]');
                let valid = true;

                if (!App.core.cpfValido($cpf.val())) {
                    valid = false;
                    $cpf.addClass('is-invalid').attr('aria-invalid', 'true');
                    $cpfError.removeClass('hidden').text('Informe um CPF válido no formato 000.000.000-00.');
                } else {
                    $cpf.removeClass('is-invalid').removeAttr('aria-invalid');
                    $cpfError.addClass('hidden').text('');
                }
                if (!recoveryDateIsValid($date.val())) {
                    valid = false;
                    $date.addClass('is-invalid').attr('aria-invalid', 'true');
                    $dateError.removeClass('hidden').text('Informe uma data válida no formato dd/mm/aaaa.');
                }
                if (String($form.find('[name="password"]').val() || '') !== String($confirmation.val() || '')) {
                    valid = false;
                    $confirmation.addClass('is-invalid').attr('aria-invalid', 'true');
                    $confirmationError.removeClass('hidden').text('As senhas informadas não são iguais.');
                } else {
                    $confirmation.removeClass('is-invalid').removeAttr('aria-invalid');
                    $confirmationError.addClass('hidden').text('');
                }
                if (!valid) {
                    event.preventDefault();
                    event.stopImmediatePropagation();
                    App.core.abrirPopup('erro', 'Confira o CPF, a data de nascimento e a confirmação da nova senha antes de prosseguir.');
                }
            });

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

            $(document).on('click', '#popup-site-actions a[href]', function (event) {
                const href = String($(this).attr('href') || '').trim();

                if (href === '#fechar-popup') {
                    event.preventDefault();
                    event.stopImmediatePropagation();
                    App.core.fecharPopupCustomizado('#popup-site');
                    return;
                }

                let destination = null;
                try {
                    destination = new URL(href, window.location.href);
                } catch (error) {
                    return;
                }

                const samePage = destination.origin === window.location.origin
                    && destination.pathname.replace(/\/+$/, '') === window.location.pathname.replace(/\/+$/, '')
                    && destination.search === window.location.search;

                if (!samePage || destination.hash === '') {
                    return;
                }

                let targetId = '';
                try {
                    targetId = decodeURIComponent(destination.hash.slice(1));
                } catch (error) {
                    targetId = destination.hash.slice(1);
                }

                const target = document.getElementById(targetId);
                if (!target) {
                    return;
                }

                event.preventDefault();
                event.stopImmediatePropagation();
                App.core.fecharPopupCustomizado('#popup-site');
                window.history.pushState({}, document.title, destination.pathname + destination.search + destination.hash);
                window.requestAnimationFrame(function () {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
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
                const refreshAdminSection = String($form.data('refreshAdminSection') || '');
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
                        const mensagemErro = String(response.message || 'Não foi possível concluir a operação agora.');
                        const redirectErro = String(response.redirect || '');

                        if (response && response.human_verification_refresh) App.core.renovarVerificacaoHumana($form);

                        App.core.abrirPopup('erro', mensagemErro, function () {
                            if (redirectErro !== '') {
                                window.location.href = redirectErro;
                            }
                        });
                        return;
                    }

                    const mensagem = response && response.message ? String(response.message) : 'Operacao realizada com sucesso.';

                    if ($form.attr('id') === 'form-agendamento' && App.agenda && typeof App.agenda.recarregarOcorrenciaAberta === 'function') {
                        App.agenda.recarregarOcorrenciaAberta();
                    }

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
                        App.auth.sincronizarCabecalhoAutenticado(
                            !!response.admin_access_allowed,
                            !!response.professor_access_allowed
                        );
                        try {
                            window.localStorage.setItem('cursos_sbc_auth_event', JSON.stringify({ action: 'login', time: Date.now() }));
                        } catch (error) {
                        }
                        $('main.page-content > .flash').remove();
                        $personOptions.data('agendaAuthenticated', '1');
                        $calendar.attr('data-agenda-authenticated', '1');
                        $calendar.attr('data-agenda-needs-profile-completion', authenticationNeedsProfileCompletion ? '1' : '0');

                        if ($agendaHelper.length > 0) {
                            $agendaHelper.text(
                                authenticationNeedsProfileCompletion
                                    ? 'Complete seu cadastro para liberar os nomes disponíveis para agendamento.'
                                    : 'Selecione a pessoa que deseja agendar.'
                            );
                            $agendaHelper.toggleClass('hidden', !authenticationNeedsProfileCompletion);
                        }

                        if (authenticationNeedsProfileCompletion) {
                            $('#agenda-access-warning')
                                .removeClass('hidden')
                                .text('Para agendar um horário, você precisa completar seu cadastro.');
                        }

                        App.core.fecharPopupCustomizado('#agenda-login-reminder');
                        App.core.fecharPopupCustomizado('#agenda-profile-reminder');
                        App.core.fecharPopupCustomizado('#popup-profile-completion-confirm');

                        const pendingAccess = App.state.pendingProtectedAccess || null;
                        if (pendingAccess) {
                            const isAllowed = pendingAccess.area === 'admin'
                                ? !!response.admin_access_allowed
                                : (pendingAccess.area === 'professor' ? !!response.professor_access_allowed : true);
                            App.state.pendingProtectedAccess = null;
                            if (!isAllowed) {
                                window.location.href = App.core.buildUrl('/');
                                return;
                            }
                            if (!authenticationNeedsProfileCompletion && typeof pendingAccess.retry === 'function') {
                                window.setTimeout(pendingAccess.retry, 0);
                                response._protected_access_handled = true;
                            } else if (!authenticationNeedsProfileCompletion && pendingAccess.returnTo) {
                                response.redirect = App.core.buildUrl(String(pendingAccess.returnTo));
                            }
                        }
                    }

                    if (isInsideRouteModal) {
                        App.core.fecharPopupCustomizado('#popup-route-modal');
                    }

                    App.core.abrirPopup('sucesso', mensagem, function () {
                        if (removeClosestSelector !== '') {
                            $form.closest(removeClosestSelector).remove();
                        }

                        if (
                            refreshAdminSection !== ''
                            && App.admin
                            && typeof App.admin.activateSection === 'function'
                        ) {
                            App.admin.activateSection(refreshAdminSection, {}, { suppressGlobalLoading: true });
                        }

                        if (shouldReset) {
                            $form[0].reset();

                            if ($form.attr('id') === 'form-agendamento') {
                                $form.addClass('hidden');
                                $('#painel-evento').html('<p class="muted">Clique em um horário no calendário para ver local, vagas e regras.</p>');
                                if (App.agenda && typeof App.agenda.fecharModalDetalhesHorario === 'function') {
                                    App.agenda.fecharModalDetalhesHorario();
                                }
                            }

                            if ($form.attr('id') === 'form-site-popup') {
                                $('#popup-todas-paginas').trigger('change');
                            }
                        }

                        if (shouldFollowRedirect && response && response.redirect && !response._protected_access_handled) {
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

                    if (App.auth.tratarFalhaDeAcesso(xhr, function () { $form.trigger('submit'); }, action)) {
                        return;
                    }
                    if (xhr.status === 403) {
                        window.location.href = App.core.buildUrl('/');
                        return;
                    }

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

        iniciarSincronizacaoEntreAbas: function () {
            window.addEventListener('storage', function (event) {
                if (event.key !== 'cursos_sbc_auth_event' || !event.newValue || App.core.pageIsAuthenticated()) {
                    return;
                }

                try {
                    const authEvent = JSON.parse(event.newValue);
                    if (authEvent && authEvent.action === 'login') {
                        App.core.hideLoading(true);
                        window.location.reload();
                    }
                } catch (error) {
                }
            });
        },

        init: function () {
            App.auth.lembrarPaginaProtegidaAtual();
            App.auth.iniciarFormulariosAjax();
            App.auth.iniciarModalPelaUrl();
            App.auth.iniciarSincronizacaoEntreAbas();
        }
    });

    window.App = App;
}(window, window.jQuery));
