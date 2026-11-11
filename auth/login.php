<?php
/**
 * ============================================================
 *  Magnata Automóveis - Módulo de Login
 *  Arquivo: auth/login.php
 * ============================================================
 *  Implementa:
 *   - RF01: Autenticação segura por email e senha.
 *   - Validação com password_verify() (senhas com bcrypt).
 *   - Regeneração de ID de sessão (session_regenerate_id).
 *   - RN12: Bloqueio de acesso para usuários com status "inativo".
 * ============================================================
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Se já estiver logado, vai direto para o dashboard
if (usuarioLogado()) {
    redirecionar('dashboard.php');
}

$erro = '';
$emailInformado = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCSRF();

    $email = filter_var(post('email'), FILTER_SANITIZE_EMAIL);
    $senha = post('senha');
    $emailInformado = $email;

    if (empty($email) || empty($senha)) {
        $erro = 'Por favor, preencha o e-mail e a senha.';
    } elseif (!validarEmail($email)) {
        $erro = 'Por favor, informe um endereço de e-mail válido.';
    } else {
        try {
            $pdo = conectar();
            $stmt = $pdo->prepare('SELECT id_usuario, id_funcionario, nome, email, senha, perfil, status FROM usuarios WHERE email = :email LIMIT 1');
            $stmt->execute([':email' => $email]);
            $usuario = $stmt->fetch();

            if ($usuario && password_verify($senha, $usuario['senha'])) {
                // RN12: Usuários inativos não podem acessar o sistema
                if ($usuario['status'] !== 'ativo') {
                    $erro = 'Seu usuário está inativo no sistema. Entre em contato com o administrador.';
                } else {
                    // Regenera o ID da sessão contra Session Fixation
                    session_regenerate_id(true);

                    // Salva os dados do usuário autenticado na sessão
                    $_SESSION['usuario'] = [
                        'id_usuario'     => (int) $usuario['id_usuario'],
                        'id_funcionario' => $usuario['id_funcionario'] ? (int) $usuario['id_funcionario'] : null,
                        'nome'           => $usuario['nome'],
                        'email'          => $usuario['email'],
                        'perfil'         => $usuario['perfil'],
                    ];

                    definirMensagem('success', 'Bem-vindo ao sistema, ' . $usuario['nome'] . '!');
                    redirecionar('dashboard.php');
                }
            } else {
                $erro = 'E-mail ou senha incorretos. Verifique suas credenciais.';
            }
        } catch (PDOException $e) {
            error_log('Erro no login: ' . $e->getMessage());
            $erro = 'Ocorreu um erro ao processar seu login. Tente novamente em instantes.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login | Magnata Automóveis</title>

    <link rel="icon" type="image/svg+xml" href="<?= url('assets/img/logo.svg') ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="<?= url('assets/css/style.css') ?>" rel="stylesheet">
</head>
<body>

<div class="login-page">
    <div class="login-card">

        <div class="login-header">
            <div class="d-flex align-items-center justify-content-center gap-2 mb-2">
                <img src="<?= url('assets/img/logo.svg') ?>" alt="Magnata Automóveis" width="48" height="48">
                <span class="fs-3 fw-bold tracking-tight text-white" style="letter-spacing: -0.5px;">
                    <span style="color: #fae69e;">Magnata</span> Automóveis
                </span>
            </div>
            <p class="mb-0 small" style="color: #d4af37; text-transform: uppercase; letter-spacing: 1.5px; font-size: 0.72rem; font-weight: 600;">
                Concessionária Premium Multimarcas
            </p>
        </div>

        <div class="login-body">

            <?php exibirMensagens(); ?>

            <?php if (!empty($erro)): ?>
                <div class="alert alert-danger d-flex align-items-center shadow-sm" role="alert">
                    <i class="bi bi-exclamation-octagon-fill me-2 fs-5"></i>
                    <div><?= e($erro) ?></div>
                </div>
            <?php endif; ?>

            <form method="post" action="<?= url('auth/login.php') ?>" novalidate>
                <?= campoCSRF() ?>

                <div class="mb-3">
                    <label for="email" class="form-label fw-bold text-secondary small">E-MAIL</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-muted">
                            <i class="bi bi-envelope"></i>
                        </span>
                        <input type="email" class="form-control border-start-0" id="email" name="email"
                               placeholder="nome@magnata.com" value="<?= e($emailInformado) ?>" required autofocus>
                    </div>
                </div>

                <div class="mb-4">
                    <label for="senha" class="form-label fw-bold text-secondary small">SENHA</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-muted">
                            <i class="bi bi-lock"></i>
                        </span>
                        <input type="password" class="form-control border-start-0" id="senha" name="senha"
                               placeholder="Digite sua senha" required>
                    </div>
                </div>

                <div class="d-grid mb-3">
                    <button type="submit" class="btn btn-primary btn-lg fs-6 fw-bold shadow-sm">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Entrar no Sistema
                    </button>
                </div>
            </form>

            <!-- Card com credenciais de demonstração (A3) -->
            <div class="mt-4 p-3 bg-light rounded border small">
                <div class="fw-bold text-dark mb-1"><i class="bi bi-info-circle-fill text-gold me-1"></i> Acessos de Demonstração (A3):</div>
                <div class="text-muted">
                    <div class="d-flex justify-content-between py-1 border-bottom">
                        <span><strong>Admin:</strong> admin@autogestao.com</span>
                        <code>admin123</code>
                    </div>
                    <div class="d-flex justify-content-between py-1 border-bottom">
                        <span><strong>Vendedor:</strong> carlos@autogestao.com</span>
                        <code>vendedor123</code>
                    </div>
                    <div class="d-flex justify-content-between pt-1">
                        <span class="text-danger"><strong>Inativo (RN12):</strong> pedro@autogestao.com</span>
                        <code>inativo123</code>
                    </div>
                </div>
            </div>

        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= url('assets/js/script.js') ?>"></script>
</body>
</html>
