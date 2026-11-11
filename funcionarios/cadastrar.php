<?php
/**
 * ============================================================
 *  AutoGestão - Cadastro de Funcionário
 *  Arquivo: funcionarios/cadastrar.php
 * ============================================================
 *  Implementa:
 *   - RF05: Cadastro de novos funcionários com controle de cargo e salário.
 *   - RN02: Validação de CPF único.
 *   - Relacionamento Funcionários 1:0..1 Usuários (criação opcional de login).
 *   - Restrito a administradores (exigirAdmin).
 * ============================================================
 */

require_once __DIR__ . '/../includes/auth.php';
exigirAdmin();

$tituloPagina = 'Novo Funcionário';
$paginaAtiva  = 'funcionarios';

$erros = [];
$nome          = '';
$cpf           = '';
$telefone      = '';
$email         = '';
$cargo         = '';
$salario       = '';
$dataAdmissao  = date('Y-m-d');

// Opções de criação de usuário de acesso
$criarUsuario  = false;
$perfilUsuario = 'vendedor';
$senhaUsuario  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCSRF();

    $nome          = post('nome');
    $cpf           = post('cpf');
    $telefone      = post('telefone');
    $email         = post('email');
    $cargo         = post('cargo');
    $salarioStr    = post('salario');
    $dataAdmissao  = post('data_admissao');
    $criarUsuario  = isset($_POST['criar_usuario']);
    $perfilUsuario = post('perfil_usuario');
    $senhaUsuario  = post('senha_usuario');

    $salario = converterValor($salarioStr);

    // Validações de dados do funcionário
    if (empty($nome)) {
        $erros[] = 'O campo Nome é obrigatório.';
    } elseif (mb_strlen($nome) > 120) {
        $erros[] = 'O nome não pode ultrapassar 120 caracteres.';
    }

    if (empty($cpf)) {
        $erros[] = 'O campo CPF é obrigatório.';
    } elseif (!validarCPF($cpf)) {
        $erros[] = 'O CPF informado é inválido.';
    }

    if (empty($telefone)) {
        $erros[] = 'O campo Telefone é obrigatório.';
    } elseif (!validarTelefone($telefone)) {
        $erros[] = 'Informe um telefone válido com DDD (10 ou 11 dígitos).';
    }

    if (!empty($email) && !validarEmail($email)) {
        $erros[] = 'O endereço de e-mail informado é inválido.';
    }

    if (empty($cargo)) {
        $erros[] = 'O campo Cargo é obrigatório.';
    }

    if ($salario < 0) {
        $erros[] = 'O salário não pode ser um valor negativo.';
    }

    if (empty($dataAdmissao) || !validarData($dataAdmissao)) {
        $erros[] = 'Informe uma data de admissão válida (AAAA-MM-DD).';
    }

    // Se optou por criar usuário
    if ($criarUsuario) {
        if (empty($email)) {
            $erros[] = 'Para criar um login no sistema, o preenchimento do e-mail é obrigatório.';
        }
        if (mb_strlen($senhaUsuario) < 6) {
            $erros[] = 'A senha de acesso deve ter no mínimo 6 caracteres.';
        }
        if (!in_array($perfilUsuario, ['administrador', 'vendedor'], true)) {
            $erros[] = 'Selecione um perfil de acesso válido.';
        }
    }

    // Validações no banco de dados
    if (empty($erros)) {
        $pdo = conectar();
        $cpfFormatado = formatarCPF($cpf);

        // RN02: CPF de funcionário deve ser único
        $stmtCpf = $pdo->prepare('SELECT id_funcionario FROM funcionarios WHERE cpf = :cpf LIMIT 1');
        $stmtCpf->execute([':cpf' => $cpfFormatado]);
        if ($stmtCpf->fetch()) {
            $erros[] = 'Já existe um funcionário cadastrado com este CPF (' . $cpfFormatado . ').';
        }

        // E-mail único na tabela de funcionários
        if (!empty($email)) {
            $stmtEmail = $pdo->prepare('SELECT id_funcionario FROM funcionarios WHERE email = :email LIMIT 1');
            $stmtEmail->execute([':email' => $email]);
            if ($stmtEmail->fetch()) {
                $erros[] = 'Já existe um funcionário cadastrado com este e-mail (' . $email . ').';
            }

            // E se for criar usuário, o e-mail também deve ser único em usuarios
            if ($criarUsuario) {
                $stmtUsr = $pdo->prepare('SELECT id_usuario FROM usuarios WHERE email = :email LIMIT 1');
                $stmtUsr->execute([':email' => $email]);
                if ($stmtUsr->fetch()) {
                    $erros[] = 'Já existe um usuário no sistema utilizando este e-mail para login.';
                }
            }
        }

        // Se todas as regras foram atendidas, realiza a gravação
        if (empty($erros)) {
            try {
                $pdo->beginTransaction();

                $stmtInsert = $pdo->prepare('
                    INSERT INTO funcionarios (nome, cpf, telefone, email, cargo, salario, data_admissao)
                    VALUES (:nome, :cpf, :telefone, :email, :cargo, :salario, :data_admissao)
                ');
                $stmtInsert->execute([
                    ':nome'          => $nome,
                    ':cpf'           => $cpfFormatado,
                    ':telefone'      => $telefone,
                    ':email'         => !empty($email) ? $email : null,
                    ':cargo'         => $cargo,
                    ':salario'       => $salario,
                    ':data_admissao' => $dataAdmissao,
                ]);

                $idFuncionario = (int) $pdo->lastInsertId();

                // Cria o usuário de acesso caso solicitado
                if ($criarUsuario) {
                    $hashSenha = password_hash($senhaUsuario, PASSWORD_DEFAULT);
                    $stmtUserInsert = $pdo->prepare('
                        INSERT INTO usuarios (id_funcionario, nome, email, senha, perfil, status)
                        VALUES (:id_funcionario, :nome, :email, :senha, :perfil, "ativo")
                    ');
                    $stmtUserInsert->execute([
                        ':id_funcionario' => $idFuncionario,
                        ':nome'           => $nome,
                        ':email'          => $email,
                        ':senha'          => $hashSenha,
                        ':perfil'         => $perfilUsuario,
                    ]);
                }

                $pdo->commit();

                definirMensagem('success', 'Funcionário "' . $nome . '" cadastrado com sucesso!');
                redirecionar('funcionarios/index.php');
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Erro ao cadastrar funcionário: ' . $e->getMessage());
                $erros[] = 'Erro ao salvar funcionário no banco de dados. Tente novamente.';
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="h4 fw-bold text-dark mb-1">Cadastrar Funcionário</h2>
        <p class="text-muted small mb-0">Adicione um novo colaborador à equipe da concessionária.</p>
    </div>
    <div>
        <a href="<?= url('funcionarios/index.php') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Voltar à Listagem
        </a>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-12 col-lg-8">
        <div class="card-app">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="bi bi-person-plus text-primary"></i> Dados do Colaborador
                </h3>
            </div>
            <div class="card-body">

                <?php if (!empty($erros)): ?>
                    <div class="alert alert-danger shadow-sm mb-4">
                        <div class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-2"></i>Corrija os erros abaixo:</div>
                        <ul class="mb-0 ps-3">
                            <?php foreach ($erros as $erro): ?>
                                <li><?= e($erro) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="post" action="<?= url('funcionarios/cadastrar.php') ?>" novalidate>
                    <?= campoCSRF() ?>

                    <div class="row g-3">
                        <div class="col-12">
                            <label for="nome" class="form-label fw-semibold text-secondary small">
                                NOME COMPLETO <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="nome" name="nome"
                                   placeholder="Ex.: Mariana Souza Rocha" value="<?= e($nome) ?>" required autofocus>
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="cpf" class="form-label fw-semibold text-secondary small">
                                CPF <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control mascara-cpf" id="cpf" name="cpf"
                                   placeholder="000.000.000-00" value="<?= e($cpf) ?>" required>
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="telefone" class="form-label fw-semibold text-secondary small">
                                TELEFONE / CELULAR <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control mascara-telefone" id="telefone" name="telefone"
                                   placeholder="(00) 00000-0000" value="<?= e($telefone) ?>" required>
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="email" class="form-label fw-semibold text-secondary small">E-MAIL</label>
                            <input type="email" class="form-control" id="email" name="email"
                                   placeholder="colaborador@autogestao.com" value="<?= e($email) ?>">
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="cargo" class="form-label fw-semibold text-secondary small">
                                CARGO <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="cargo" name="cargo"
                                   placeholder="Ex.: Vendedor, Gerente Comercial" value="<?= e($cargo) ?>" required>
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="salario" class="form-label fw-semibold text-secondary small">
                                SALÁRIO (R$) <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control mascara-moeda" id="salario" name="salario"
                                   placeholder="0,00" value="<?= e($salarioStr ?: '') ?>" required>
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="data_admissao" class="form-label fw-semibold text-secondary small">
                                DATA DE ADMISSÃO <span class="text-danger">*</span>
                            </label>
                            <input type="date" class="form-control" id="data_admissao" name="data_admissao"
                                   value="<?= e($dataAdmissao) ?>" required>
                        </div>
                    </div>

                    <!-- Sessão de Criação de Acesso (Relacionamento 1:0..1) -->
                    <div class="mt-4 p-3 bg-light rounded border">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" role="switch" id="criar_usuario"
                                   name="criar_usuario" value="1" <?= $criarUsuario ? 'checked' : '' ?>
                                   onchange="document.getElementById('blocoUsuario').style.display = this.checked ? 'block' : 'none'">
                            <label class="form-check-label fw-bold text-dark" for="criar_usuario">
                                Criar conta de acesso ao sistema para este funcionário
                            </label>
                        </div>
                        <div class="text-muted small mb-3">
                            Permite que o funcionário faça login no sistema com o e-mail informado acima.
                        </div>

                        <div id="blocoUsuario" style="display: <?= $criarUsuario ? 'block' : 'none' ?>;">
                            <div class="row g-3">
                                <div class="col-12 col-md-6">
                                    <label for="perfil_usuario" class="form-label fw-semibold text-secondary small">PERFIL DE ACESSO</label>
                                    <select class="form-select" id="perfil_usuario" name="perfil_usuario">
                                        <option value="vendedor" <?= $perfilUsuario === 'vendedor' ? 'selected' : '' ?>>Vendedor (Operacional)</option>
                                        <option value="administrador" <?= $perfilUsuario === 'administrador' ? 'selected' : '' ?>>Administrador (Gestão Total)</option>
                                    </select>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="senha_usuario" class="form-label fw-semibold text-secondary small">SENHA INICIAL</label>
                                    <input type="password" class="form-control" id="senha_usuario" name="senha_usuario"
                                           placeholder="Mínimo de 6 caracteres">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                        <a href="<?= url('funcionarios/index.php') ?>" class="btn btn-light border">Cancelar</a>
                        <button type="submit" class="btn btn-navy">
                            <i class="bi bi-check-lg me-1"></i> Salvar Funcionário
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
