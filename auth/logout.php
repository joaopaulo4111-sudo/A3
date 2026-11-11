<?php
/**
 * ============================================================
 *  AutoGestão - Módulo de Logout
 *  Arquivo: auth/logout.php
 * ============================================================
 *  Implementa:
 *   - RF02: Encerramento seguro da sessão do usuário.
 *   - HU14: Proteção de acesso com invalidação completa dos cookies.
 * ============================================================
 */

require_once __DIR__ . '/../includes/functions.php';

// Limpa todas as variáveis de sessão
$_SESSION = [];

// Apaga o cookie de sessão do navegador, se existir
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Destrói a sessão no servidor
session_destroy();

// Inicia uma nova sessão limpa apenas para exibir o alerta informativo de logout
session_start();
definirMensagem('info', 'Sua sessão foi encerrada com sucesso.');

redirecionar('auth/login.php');
