(function (window, $) {
    const App = window.App || {};

    App.dashboard = Object.assign(App.dashboard || {}, {
        init: function () {
            function getModal() {
                return $('#dashboard-certificates-modal');
            }

            function getContent() {
                return $('#dashboard-certificates-modal-content');
            }

            function getDependentModal() {
                return $('#dashboard-dependent-modal');
            }

            function getDependentContent() {
                return $('#dashboard-dependent-modal-content');
            }

            function getDependentCreateModal() {
                return $('#dashboard-dependent-create-modal');
            }

            function getDependentCreateContent() {
                return $('#dashboard-dependent-create-modal-content');
            }

            function getHealthCertificatesModal() {
                return $('#dashboard-health-certificates-modal');
            }

            function getHealthCertificatesContent() {
                return $('#dashboard-health-certificates-modal-content');
            }

            function openModal(html) {
                const $modal = getModal();
                const $content = getContent();

                if ($modal.length === 0 || $content.length === 0) {
                    return;
                }

                $content.html(String(html || ''));
                $modal.removeClass('hidden').attr('aria-hidden', 'false');
            }

            function focusConditionSection(conditionSlug) {
                const normalizedSlug = String(conditionSlug || '').trim().toLowerCase();

                if (!normalizedSlug) {
                    return;
                }

                const $content = getContent();
                const $target = $content.find('[data-condition-section="' + normalizedSlug + '"]').first();

                if ($target.length === 0) {
                    return;
                }

                $content.find('.dashboard-certificate-card.is-targeted').removeClass('is-targeted');
                $target.addClass('is-targeted');

                const targetElement = $target.get(0);
                if (targetElement && typeof targetElement.scrollIntoView === 'function') {
                    targetElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }

            function closeModal() {
                const $modal = getModal();
                const $content = getContent();

                if ($modal.length === 0 || $content.length === 0) {
                    return;
                }

                $modal.addClass('hidden').attr('aria-hidden', 'true');
                $content.html('');
            }

            function openDependentModal(html) {
                const $modal = getDependentModal();
                const $content = getDependentContent();

                if ($modal.length === 0 || $content.length === 0) {
                    return;
                }

                $content.html(String(html || ''));
                $modal.removeClass('hidden').attr('aria-hidden', 'false');
            }

            function closeDependentModal() {
                const $modal = getDependentModal();
                const $content = getDependentContent();

                if ($modal.length === 0 || $content.length === 0) {
                    return;
                }

                $modal.addClass('hidden').attr('aria-hidden', 'true');
                $content.html('');
            }

            function openDependentCreateModal() {
                const $modal = getDependentCreateModal();

                if ($modal.length === 0) {
                    return;
                }

                $modal.removeClass('hidden').attr('aria-hidden', 'false');
            }

            function closeDependentCreateModal() {
                const $modal = getDependentCreateModal();
                const $content = getDependentCreateContent();
                const $form = $content.find('#dashboard-dependent-create-form');

                if ($modal.length === 0) {
                    return;
                }

                if ($form.length > 0) {
                    $form[0].reset();
                    $content.find('[data-condition-exclusive="1"]').prop('checked', false);
                    $content.find('[data-sexo-warning="1"]').addClass('hidden');
                }

                $modal.addClass('hidden').attr('aria-hidden', 'true');
            }

            function openHealthCertificatesModal(html) {
                const $modal = getHealthCertificatesModal();
                const $content = getHealthCertificatesContent();

                if ($modal.length === 0 || $content.length === 0) {
                    return;
                }

                $content.html(String(html || ''));
                $modal.removeClass('hidden').attr('aria-hidden', 'false');
            }

            function closeHealthCertificatesModal() {
                const $modal = getHealthCertificatesModal();
                const $content = getHealthCertificatesContent();

                if ($modal.length === 0 || $content.length === 0) {
                    return;
                }

                $modal.addClass('hidden').attr('aria-hidden', 'true');
                $content.html('');
            }

            function focusHealthCertificateSection(certificateType) {
                const normalizedType = String(certificateType || '').trim().toLowerCase();
                const $content = getHealthCertificatesContent();
                const $cards = $content.find('[data-health-certificate-section]');
                const $title = $content.find('#dashboard-health-certificates-modal-title');

                $cards.removeClass('is-targeted hidden');

                if (!normalizedType) {
                    if ($title.length > 0) {
                        $title.text('Atualizar atestados');
                    }
                    return;
                }

                const $target = $content.find('[data-health-certificate-section="' + normalizedType + '"]').first();

                if ($target.length === 0) {
                    return;
                }

                if ($title.length > 0) {
                    if (normalizedType === 'clinico') {
                        $title.text('Atualizar atestado clinico');
                    } else if (normalizedType === 'dermatologico') {
                        $title.text('Atualizar atestado dermatologico');
                    }
                }

                $content.find('input[name="target_certificate_type"]').val(normalizedType);

                $content.find('.dashboard-certificate-card.is-targeted').removeClass('is-targeted');
                $cards.not($target).addClass('hidden');
                $target.addClass('is-targeted');

                const targetElement = $target.get(0);
                if (targetElement && typeof targetElement.scrollIntoView === 'function') {
                    targetElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }

            function showDependentPanel(panel) {
                const targetPanel = String(panel || 'view');
                const $content = getDependentContent();

                $content.find('[data-dependent-modal-panel]').addClass('hidden');
                $content.find('[data-dependent-modal-panel="' + targetPanel + '"]').removeClass('hidden');
                $content.find('[data-show-dependent-edit="1"]').toggleClass('hidden', targetPanel === 'edit');
                $content.find('#dashboard-dependent-save-footer').toggleClass('hidden', targetPanel !== 'edit');
            }

            function replaceHeaderAlerts(html) {
                const $region = $('#site-header-certificate-alerts-region');

                if ($region.length === 0) {
                    return;
                }

                $region.html(String(html || ''));
            }

            $(document).on('click', '[data-open-certificates-modal="1"]', function () {
                const personId = Number($(this).data('personId') || 0);
                const conditionSlug = String($(this).data('conditionSlug') || '').trim().toLowerCase();

                if (!personId) {
                    App.core.abrirPopup('erro', 'Nao foi possivel identificar a pessoa selecionada.');
                    return;
                }

                $.getJSON(App.core.buildUrl('/perfil/certificados/modal'), { person_id: personId })
                    .done(function (response) {
                        if (!response || response.success === false || !response.html) {
                            App.core.abrirPopup('erro', String((response && response.message) || 'Nao foi possivel carregar a documentacao desta pessoa.'));
                            return;
                        }

                        openModal(response.html);
                        focusConditionSection(conditionSlug);
                    })
                    .fail(function (xhr) {
                        const erro = App.core.extrairMensagemErroAjax(xhr);
                        App.core.abrirPopup('erro', erro.mensagem);
                    });
            });

            $(document).on('click', '[data-open-dependent-modal="1"]', function () {
                const personId = Number($(this).data('personId') || 0);

                if (!personId) {
                    App.core.abrirPopup('erro', 'Nao foi possivel identificar a pessoa selecionada.');
                    return;
                }

                $.getJSON(App.core.buildUrl('/dependentes/detalhe'), { person_id: personId })
                    .done(function (response) {
                        if (!response || response.success === false || !response.html) {
                            App.core.abrirPopup('erro', String((response && response.message) || 'Nao foi possivel carregar os dados desta pessoa.'));
                            return;
                        }

                        openDependentModal(response.html);
                        showDependentPanel('view');
                    })
                    .fail(function (xhr) {
                        const erro = App.core.extrairMensagemErroAjax(xhr);
                        App.core.abrirPopup('erro', erro.mensagem);
                    });
            });

            $(document).on('click', '[data-open-dependent-create-modal="1"]', function () {
                openDependentCreateModal();
            });

            $(document).on('click', '[data-open-health-certificates-modal="1"]', function () {
                const personId = Number($(this).data('personId') || 0);
                const certificateType = String($(this).data('certificateType') || '').trim().toLowerCase();

                if (!personId) {
                    App.core.abrirPopup('erro', 'Nao foi possivel identificar a pessoa selecionada.');
                    return;
                }

                $.getJSON(App.core.buildUrl('/perfil/atestados/modal'), { person_id: personId })
                    .done(function (response) {
                        if (!response || response.success === false || !response.html) {
                            App.core.abrirPopup('erro', String((response && response.message) || 'Nao foi possivel carregar os atestados desta pessoa.'));
                            return;
                        }

                        openHealthCertificatesModal(response.html);
                        focusHealthCertificateSection(certificateType);
                    })
                    .fail(function (xhr) {
                        const erro = App.core.extrairMensagemErroAjax(xhr);
                        App.core.abrirPopup('erro', erro.mensagem);
                    });
            });

            $(document).on('click', '#dashboard-certificates-modal-close, #dashboard-certificates-modal-close-footer', function () {
                closeModal();
            });

            $(document).on('click', '#dashboard-certificates-modal', function (event) {
                if (event.target === this) {
                    closeModal();
                }
            });

            $(document).on('click', '#dashboard-dependent-modal-close, #dashboard-dependent-modal-close-footer', function () {
                closeDependentModal();
            });

            $(document).on('click', '#dashboard-dependent-create-modal-close, #dashboard-dependent-create-modal-close-footer', function () {
                closeDependentCreateModal();
            });

            $(document).on('click', '#dashboard-health-certificates-modal-close, #dashboard-health-certificates-modal-close-footer', function () {
                closeHealthCertificatesModal();
            });

            $(document).on('click', '#dashboard-dependent-modal', function (event) {
                if (event.target === this) {
                    closeDependentModal();
                }
            });

            $(document).on('click', '#dashboard-dependent-create-modal', function (event) {
                if (event.target === this) {
                    closeDependentCreateModal();
                }
            });

            $(document).on('click', '#dashboard-health-certificates-modal', function (event) {
                if (event.target === this) {
                    closeHealthCertificatesModal();
                }
            });

            $(document).on('click', '[data-show-dependent-edit="1"]', function () {
                showDependentPanel('edit');
            });

            $(document).on('click focus', '[data-locked-support-alert="1"]', function () {
                const fieldLabel = String($(this).data('lockedFieldLabel') || 'esse dado');
                App.core.abrirPopup(
                    'erro',
                    'Para alterar ' + fieldLabel + ', entre em contato com o suporte pelo whatsapp e envie uma imagem do documento da pessoa para validacao da mudanca.'
                );
            });

            $(document).on('submit', '.dashboard-certificate-form', function (event) {
                event.preventDefault();

                const $form = $(this);
                const $submitButton = $form.find('button[type="submit"]').first();
                const formData = new FormData($form[0]);

                $submitButton.prop('disabled', true);

                $.ajax({
                    url: String($form.attr('action') || ''),
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                }).done(function (response) {
                    if (!response || response.success === false || !response.html) {
                        App.core.abrirPopup('erro', String((response && response.message) || 'Nao foi possivel atualizar a documentacao.'));
                        return;
                    }

                    openModal(response.html);
                    replaceHeaderAlerts(response.header_alerts_html || '');
                    App.core.abrirPopup('sucesso', String(response.message || 'Documentacao atualizada com sucesso.'));
                }).fail(function (xhr) {
                    const erro = App.core.extrairMensagemErroAjax(xhr);
                    App.core.abrirPopup('erro', erro.mensagem);
                }).always(function () {
                    $submitButton.prop('disabled', false);
                });
            });

            $(document).on('submit', '.dashboard-dependent-edit-form', function (event) {
                event.preventDefault();

                const $form = $(this);
                const $submitButton = $form.find('button[type="submit"]').first();
                const formData = new FormData($form[0]);

                $submitButton.prop('disabled', true);

                $.ajax({
                    url: String($form.attr('action') || ''),
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                }).done(function (response) {
                    if (!response || response.success === false || !response.html) {
                        App.core.abrirPopup('erro', String((response && response.message) || 'Nao foi possivel atualizar os dados da pessoa.'));
                        return;
                    }

                    if (response.person_id && response.row_html) {
                        $('[data-dependent-row-id="' + String(response.person_id) + '"]').replaceWith(String(response.row_html));
                    }

                    closeDependentModal();
                    App.core.abrirPopup('sucesso', String(response.message || 'Dependente atualizado com sucesso.'));
                }).fail(function (xhr) {
                    const erro = App.core.extrairMensagemErroAjax(xhr);
                    App.core.abrirPopup('erro', erro.mensagem);
                }).always(function () {
                    $submitButton.prop('disabled', false);
                });
            });

            $(document).on('submit', '.dashboard-dependent-create-form', function (event) {
                event.preventDefault();

                const $form = $(this);
                const $submitButton = $('#dashboard-dependent-create-modal-content').find('button[type="submit"][form="dashboard-dependent-create-form"]').first();
                const formData = new FormData($form[0]);

                $submitButton.prop('disabled', true);

                $.ajax({
                    url: String($form.attr('action') || ''),
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                }).done(function (response) {
                    if (!response || response.success === false || !response.row_html) {
                        App.core.abrirPopup('erro', String((response && response.message) || 'Nao foi possivel salvar o dependente.'));
                        return;
                    }

                    $('section.dashboard-main-grid .data-table tbody').append(String(response.row_html));

                    if (response.option_html) {
                        $('select[name="dependent_person_id"]').append(String(response.option_html));
                    }

                    closeDependentCreateModal();
                    App.core.abrirPopup('sucesso', String(response.message || 'Dependente salvo com sucesso.'));
                }).fail(function (xhr) {
                    const erro = App.core.extrairMensagemErroAjax(xhr);
                    App.core.abrirPopup('erro', erro.mensagem);
                }).always(function () {
                    $submitButton.prop('disabled', false);
                });
            });

            $(document).on('submit', '.dashboard-health-certificate-form', function (event) {
                event.preventDefault();

                const $form = $(this);
                const $submitButton = $form.closest('.popup-card').find('button[type="submit"][form="dashboard-health-certificate-form"]').first();
                const formData = new FormData($form[0]);
                const targetCertificateType = String($form.find('input[name="target_certificate_type"]').val() || '').trim().toLowerCase();

                $submitButton.prop('disabled', true);

                $.ajax({
                    url: String($form.attr('action') || ''),
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                }).done(function (response) {
                    if (!response || response.success === false || !response.html) {
                        App.core.abrirPopup('erro', String((response && response.message) || 'Nao foi possivel atualizar os atestados.'));
                        return;
                    }

                    openHealthCertificatesModal(response.html);
                    focusHealthCertificateSection(targetCertificateType);
                    App.core.abrirPopup('sucesso', String(response.message || 'Atestados atualizados com sucesso.'));
                }).fail(function (xhr) {
                    const erro = App.core.extrairMensagemErroAjax(xhr);
                    App.core.abrirPopup('erro', erro.mensagem);
                }).always(function () {
                    $submitButton.prop('disabled', false);
                });
            });
        }
    });

    window.App = App;
}(window, window.jQuery));
