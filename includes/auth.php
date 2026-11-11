<?php
/**
 * ============================================================
 *  AutoGestão - Módulo de Autenticação e Controle de Acesso
 *  Arquivo: includes/auth.php
 * ============================================================
 *  Funções de proteção:
 *   - exigirLogin(): bloqueia o acesso a páginas internas para
 *     usuários que não estejam autenticados (RF03 / RN12).
 *   - exigirAdmin(): restringe funcionalidades sensíveis (como
 *     cadastro de funcionários) exclusivamente a administradores.
 *   - campoCSRF() e validarCSRF(): proteção contra ataques CSRF
 *     em todos os formulários que modificam o banco de dados.
 * ============================================================
 */

require_once __DIR__ . '/functions.php';

/**
 * Exige que o usuário esteja autenticado para acessar a página atual.
 * Caso contrário, grava mensagem e redireciona para o login (RF03).
 */
function exigirLogin(): void
{
    if (!usuarioLogado()) {
        definirMensagem('warning', 'Por favor, realize login para acessar o sistema.');
        redirecionar('auth/login.php');
    }
}

/**
 * Exige que o usuário logado possua perfil de "administrador".
 * Vendedores recebem aviso de acesso não autorizado e voltam ao dashboard.
 */
function exigirAdmin(): void
{
    exigirLogin();

    if (!ehAdministrador()) {
        definirMensagem('danger', 'Acesso negado: esta funcionalidade é exclusiva para administradores.');
        redirecionar('dashboard.php');
    }
}

/**
 * Retorna o token CSRF atual da sessão (ou gera um novo se não existir).
 */
function obterTokenCSRF(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Imprime um campo <input type="hidden"> com o token CSRF.
 * Deve ser incluído dentro de todas as tags <form method="post">.
 */
function campoCSRF(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(obterTokenCSRF()) . '">';
}

/**
 * Valida o token CSRF enviado no $_POST.
 * Se inválido, encerra a requisição com código HTTP 403 e mensagem de erro.
 */
function validarCSRF(): void
{
    $tokenEnviado = $_POST['csrf_token'] ?? '';
    $tokenSessao  = $_SESSION['csrf_token'] ?? '';

    if (empty($tokenEnviado) || empty($tokenSessao) || !hash_equals($tokenSessao, $tokenEnviado)) {
        http_response_code(403);
        die('<div style="font-family:sans-serif;padding:30px;color:#842029;background:#f8d7da;border-radius:8px;max-width:600px;margin:40px auto;">'
          . '<h4>Requisição inválida (CSRF detectado ou sessão expirada)</h4>'
          . '<p>Por favor, volte e recarregue a página antes de reenviar o formulário.</p>'
          . '<p><a href="javascript:history.back()" style="color:#0f2744;font-weight:bold;">&laquo; Voltar</a></p>'
          . '</div>');
    }
}
