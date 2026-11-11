<?php
/**
 * ============================================================
 *  AutoGestão - Edição de Funcionário
 *  Arquivo: funcionarios/editar.php
 * ============================================================
 *  Implementa:
 *   - RF05: Atualização de funcionários, cargos e salários.
 *   - RN02: Unicidade de CPF excluindo o próprio registro.
 *   - Gerenciamento de status/perfil de usuário vinculado.
 *   - Restrito a administradores (exigirAdmin).
 * ============================================================
 */

require_once __DIR__ . '/../includes/auth.php';
exigirAdmin();

$tituloPagina = 'Editar Funcionário';
$paginaAtiva  = 'funcionarios';

$pdo = conectar();
$id = (int) ($_GET['id'] ?? $_POST['id_funcionario'] ?? 0);

if ($id <= 0) {
    definirMensagem('danger', 'Funcionário não identificado para edição.');
    redirecionar('funcionarios/index.php');
}

// Busca o funcionário e eventual usuário vinculado
$stmt = $pdo->prepare('
    SELECT f.*, u.id_usuario, u.perfil AS usuario_perfil, u.status AS usuario_status
      FROM funcionarios f
      LEFT JOIN usuarios u ON u.id_funcionario = f.id_funcionario
     WHERE f.id_funcionario = :id
     LIMIT 1
');
$stmt->execute([':id' => $id]);
$funcionario = $stmt->fetch();

if (!$funcionario) {
    definirMensagem('danger', 'Funcionário não encontrado no sistema.');
    redirecionar('funcionarios/index.php');
}

$erros = [];
$nome         = $funcionario['nome'];
$cpf          = $funcionario['cpf'];
$telefone     = $funcionario['telefone'];
$email        = $funcionario['email'] ?? '';
$cargo        = $funcionario['cargo'];
$salarioStr   = number_format((float) $funcionario['salario'], 2, ',', '.');
$dataAdmissao = $funcionario['data_admissao'];

$temUsuario    = !empty($funcionario['id_usuario']);
$usuarioStatus = $funcionario['usuario_status'] ?? 'ativo';
$usuarioPerfil = $funcionario['usuario_perfil'] ?? 'vendedor';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCSRF();

    $nome          = post('nome');
    $cpf           = post('cpf');
    $telefone      = post('telefone');
    $email         = post('email');
    $cargo         = post('cargo');
    $salarioStr    = post('salario');
    $dataAdmissao  = post('data_admissao');
    $usuarioStatus = post('usuario_status');
    $usuarioPerfil = post('usuario_perfil');
    $novaSenha     = post('nova_senha');

    $salario = converterValor($salarioStr);

    // Validações
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
        $erros[] = 'O salário não pode ser negativo.';
    }

    if (empty($dataAdmissao) || !validarData($dataAdmissao)) {
        $erros[] = 'Informe uma data de admissão válida (AAAA-MM-DD).';
    }

    if (!empty($novaSenha) && mb_strlen($novaSenha) < 6) {
        $erros[] = 'A nova senha deve ter no mínimo 6 caracteres.';
    }

    if (empty($erros)) {
        $cpfFormatado = formatarCPF($cpf);

        // RN02: CPF único excluindo o próprio registro
        $stmtCpf = $pdo->prepare('SELECT id_funcionario FROM funcionarios WHERE cpf = :cpf AND id_funcionario <> :id LIMIT 1');
        $stmtCpf->execute([':cpf' => $cpfFormatado, ':id' => $id]);
        if ($stmtCpf->fetch()) {
            $erros[] = 'Já existe outro funcionário cadastrado com este CPF (' . $cpfFormatado . ').';
        }

        // E-mail único excluindo o próprio funcionário
        if (!empty($email)) {
            $stmtEmail = $pdo->prepare('SELECT id_funcionario FROM funcionarios WHERE email = :email AND id_funcionario <> :id LIMIT 1');
            $stmtEmail->execute([':email' => $email, ':id' => $id]);
            if ($stmtEmail->fetch()) {
                $erros[] = 'Já existe outro funcionário cadastrado com este e-mail (' . $email . ').';
            }
        }

        if (empty($erros)) {
            try {
                $pdo->beginTransaction();

                $stmtUpdate = $pdo->prepare('
                    UPDATE funcionarios
                       SET nome = :nome,
                           cpf = :cpf,
                           telefone = :telefone,
                           email = :email,
                           cargo = :cargo,
                           salario = :salario,
                           data_admissao = :data_admissao
                     WHERE id_funcionario = :id
                ');
                $stmtUpdate->execute([
                    ':nome'          => $nome,
                    ':cpf'           => $cpfFormatado,
                    ':telefone'      => $telefone,
                    ':email'         => !empty($email) ? $email : null,
                    ':cargo'         => $cargo,
                    ':salario'       => $salario,
                    ':data_admissao' => $dataAdmissao,
                    ':id'            => $id,
                ]);

                // Atualiza o usuário vinculado caso exista
                if ($temUsuario) {
                    $sqlUser = 'UPDATE usuarios SET nome = :nome, email = :email, perfil = :perfil, status = :status';
                    $userParams = [
                        ':nome'   => $nome,
                        ':email'  => $email,
                        ':perfil' => $usuarioPerfil,
                        ':status' => in_array($usuarioStatus, ['ativo', 'inativo'], true) ? $usuarioStatus : 'ativo',
                        ':id_usr' => $funcionario['id_usuario'],
                    ];

                    if (!empty($novaSenha)) {
                        $sqlUser .= ', senha = :senha';
                        $userParams[':senha'] = password_hash($novaSenha, PASSWORD_DEFAULT);
                    }

                    $sqlUser .= ' WHERE id_usuario = :id_usr';
                    $stmtUser = $pdo->prepare($sqlUser);
                    $stmtUser->execute($userParams);
                }

                $pdo->commit();

                definirMensagem('success', 'Dados do funcionário "' . $nome . '" atualizados com sucesso!');
                redirecionar('funcionarios/index.php');
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Erro ao atualizar funcionário: ' . $e->getMessage());
                $erros[] = 'Erro ao atualizar funcionário no banco de dados. Tente novamente.';
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="h4 fw-bold text-dark mb-1">Editar Funcionário</h2>
        <p class="text-muted small mb-0">Atualize as informações cadastrais do colaborador #<?= $id ?>.</p>
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
                    <i class="bi bi-pencil-square text-primary"></i> Alterar Dados do Colaborador
                </h3>
                <span class="badge bg-light text-secondary border">ID: #<?= $id ?></span>
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

                <form method="post" action="<?= url('funcionarios/editar.php?id=' . $id) ?>" novalidate>
                    <?= campoCSRF() ?>
                    <input type="hidden" name="id_funcionario" value="<?= $id ?>">

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
                                   placeholder="0,00" value="<?= e($salarioStr) ?>" required>
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="data_admissao" class="form-label fw-semibold text-secondary small">
                                DATA DE ADMISSÃO <span class="text-danger">*</span>
                            </label>
                            <input type="date" class="form-control" id="data_admissao" name="data_admissao"
                                   value="<?= e($dataAdmissao) ?>" required>
                        </div>
                    </div>

                    <!-- Bloco de Gerenciamento do Acesso Vinculado -->
                    <?php if ($temUsuario): ?>
                        <div class="mt-4 p-3 bg-light rounded border">
                            <h5 class="fs-6 fw-bold text-dark mb-3">
                                <i class="bi bi-shield-lock text-primary me-1"></i> Acesso Vinculado ao Sistema
                            </h5>
                            <div class="row g-3">
                                <div class="col-12 col-md-4">
                                    <label for="usuario_perfil" class="form-label fw-semibold text-secondary small">PERFIL DE ACESSO</label>
                                    <select class="form-select" id="usuario_perfil" name="usuario_perfil">
                                        <option value="vendedor" <?= $usuarioPerfil === 'vendedor' ? 'selected' : '' ?>>Vendedor</option>
                                        <option value="administrador" <?= $usuarioPerfil === 'administrador' ? 'selected' : '' ?>>Administrador</option>
                                    </select>
                                </div>
                                <div class="col-12 col-md-4">
                                    <label for="usuario_status" class="form-label fw-semibold text-secondary small">STATUS DA CONTA (RN12)</label>
                                    <select class="form-select" id="usuario_status" name="usuario_status">
                                        <option value="ativo" <?= $usuarioStatus === 'ativo' ? 'selected' : '' ?>>Ativo (Liberado)</option>
                                        <option value="inativo" <?= $usuarioStatus === 'inativo' ? 'selected' : '' ?>>Inativo (Bloqueado)</option>
                                    </select>
                                </div>
                                <div class="col-12 col-md-4">
                                    <label for="nova_senha" class="form-label fw-semibold text-secondary small">ALTERAR SENHA (OPCIONAL)</label>
                                    <input type="password" class="form-control" id="nova_senha" name="nova_senha"
                                           placeholder="Deixe em branco para manter">
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                        <a href="<?= url('funcionarios/index.php') ?>" class="btn btn-light border">Cancelar</a>
                        <button type="submit" class="btn btn-navy">
                            <i class="bi bi-save me-1"></i> Atualizar Funcionário
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
