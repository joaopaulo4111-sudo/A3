/**
 * ============================================================
 *  AutoGestão - Scripts JavaScript do Sistema
 *  Arquivo: assets/js/script.js
 * ============================================================
 *  Recursos inclusos:
 *   1. Controle da sidebar responsiva (mobile drawer e backdrop).
 *   2. Modal de confirmação reutilizável para formulários de exclusão/cancelamento.
 *   3. Máscaras de entrada em JavaScript puro (CPF, telefone, moeda, placa).
 *   4. Fechamento automático de mensagens flash.
 * ============================================================
 */

document.addEventListener('DOMContentLoaded', function () {

    // ------------------------------------------------------------
    // 1. Sidebar Responsiva (Mobile Drawer)
    // ------------------------------------------------------------
    const btnSidebar = document.getElementById('btnSidebar');
    const sidebar = document.getElementById('sidebar');
    const backdrop = document.getElementById('sidebarBackdrop');

    if (btnSidebar && sidebar && backdrop) {
        btnSidebar.addEventListener('click', function () {
            sidebar.classList.toggle('show');
            backdrop.classList.toggle('show');
        });

        backdrop.addEventListener('click', function () {
            sidebar.classList.remove('show');
            backdrop.classList.remove('show');
        });
    }

    // ------------------------------------------------------------
    // 2. Modal de Confirmação Reutilizável
    // ------------------------------------------------------------
    // Qualquer formulário com a classe .form-confirmar abrirá o modal
    // antes do envio. A mensagem pode ser customizada via data-mensagem="...".
    const modalEl = document.getElementById('modalConfirmar');
    const modalTexto = document.getElementById('modalConfirmarTexto');
    const modalBotao = document.getElementById('modalConfirmarBotao');

    if (modalEl && modalTexto && modalBotao && typeof bootstrap !== 'undefined') {
        const bsModal = new bootstrap.Modal(modalEl);
        let formPendente = null;

        document.querySelectorAll('form.form-confirmar').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                formPendente = form;

                const msg = form.getAttribute('data-mensagem') || 'Deseja realmente continuar esta operação?';
                modalTexto.textContent = msg;
                bsModal.show();
            });
        });

        modalBotao.addEventListener('click', function () {
            if (formPendente) {
                // Remove a classe para não interceptar o envio novamente
                formPendente.classList.remove('form-confirmar');
                formPendente.submit();
            }
        });
    }

    // ------------------------------------------------------------
    // 3. Fechamento Automático de Alertas Flash
    // ------------------------------------------------------------
    document.querySelectorAll('.alert.alert-dismissible').forEach(function (alert) {
        setTimeout(function () {
            try {
                if (typeof bootstrap !== 'undefined') {
                    const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
                    bsAlert.close();
                }
            } catch (err) {
                // Alerta já fechado pelo usuário
            }
        }, 5000);
    });

    // ------------------------------------------------------------
    // 4. Máscaras de Entrada de Dados (Input Masks)
    // ------------------------------------------------------------

    // CPF: 000.000.000-00
    document.querySelectorAll('.mascara-cpf').forEach(function (input) {
        input.addEventListener('input', function () {
            let v = input.value.replace(/\D/g, '').substring(0, 11);
            if (v.length > 9) {
                v = v.replace(/(\d{3})(\d{3})(\d{3})(\d{1,2})/, '$1.$2.$3-$4');
            } else if (v.length > 6) {
                v = v.replace(/(\d{3})(\d{3})(\d{1,3})/, '$1.$2.$3');
            } else if (v.length > 3) {
                v = v.replace(/(\d{3})(\d{1,3})/, '$1.$2');
            }
            input.value = v;
        });
    });

    // Telefone: (00) 0000-0000 ou (00) 00000-0000
    document.querySelectorAll('.mascara-telefone').forEach(function (input) {
        input.addEventListener('input', function () {
            let v = input.value.replace(/\D/g, '').substring(0, 11);
            if (v.length > 10) {
                v = v.replace(/^(\d{2})(\d{5})(\d{4})$/, '($1) $2-$3');
            } else if (v.length > 6) {
                v = v.replace(/^(\d{2})(\d{4})(\d{0,4})$/, '($1) $2-$3');
            } else if (v.length > 2) {
                v = v.replace(/^(\d{2})(\d{0,5})$/, '($1) $2');
            } else if (v.length > 0) {
                v = v.replace(/^(\d*)$/, '($1');
            }
            input.value = v;
        });
    });

    // Placa Mercosul / Tradicional: auto-maiúsculo e sem caracteres especiais
    document.querySelectorAll('.mascara-placa').forEach(function (input) {
        input.addEventListener('input', function () {
            let v = input.value.toUpperCase().replace(/[^A-Z0-9]/g, '').substring(0, 7);
            input.value = v;
        });
    });

    // Moeda Real (R$ 1.234,56)
    document.querySelectorAll('.mascara-moeda').forEach(function (input) {
        input.addEventListener('input', function () {
            let v = input.value.replace(/\D/g, '');
            if (!v) {
                input.value = '';
                return;
            }
            v = (parseInt(v, 10) / 100).toFixed(2) + '';
            v = v.replace('.', ',');
            v = v.replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1.');
            input.value = v;
        });
    });

});
