<?php
/**
 * ============================================================
 *  AutoGestão - Edição de Cliente
 *  Arquivo: clientes/editar.php
 * ============================================================
 *  Implementa:
 *   - RF04: Atualização dos dados cadastrais de clientes.
 *   - RN01: Validação de unicidade do CPF excluindo o próprio registro.
 * ============================================================
 */

require_once __DIR__ . '/../includes/auth.php';
exigirLogin();

$tituloPagina = 'Editar Cliente';
$paginaAtiva  = 'clientes';

$pdo = conectar();
$id = (int) ($_GET['id'] ?? $_POST['id_cliente'] ?? 0);

if ($id <= 0) {
    definirMensagem('danger', 'Cliente não identificado para edição.');
    redirecionar('clientes/index.php');
}

// Busca os dados atuais do cliente
$stmt = $pdo->prepare('SELECT * FROM clientes WHERE id_cliente = :id LIMIT 1');
$stmt->execute([':id' => $id]);
$cliente = $stmt->fetch();

if (!$cliente) {
    definirMensagem('danger', 'Cliente não encontrado no sistema.');
    redirecionar('clientes/index.php');
}

$erros = [];
$nome     = $cliente['nome'];
$cpf      = $cliente['cpf'];
$telefone = $cliente['telefone'];
$email    = $cliente['email'] ?? '';
$endereco = $cliente['endereco'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCSRF();

    $nome     = post('nome');
    $cpf      = post('cpf');
    $telefone = post('telefone');
    $email    = post('email');
    $endereco = post('endereco');

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

    if (empty($erros)) {
        $cpfFormatado = formatarCPF($cpf);

        // RN01: CPF único excluindo o próprio cliente
        $stmtCpf = $pdo->prepare('SELECT id_cliente FROM clientes WHERE cpf = :cpf AND id_cliente <> :id LIMIT 1');
        $stmtCpf->execute([':cpf' => $cpfFormatado, ':id' => $id]);
        if ($stmtCpf->fetch()) {
            $erros[] = 'Já existe outro cliente cadastrado com este CPF (' . $cpfFormatado . ').';
        }

        // E-mail único excluindo o próprio cliente
        if (!empty($email)) {
            $stmtEmail = $pdo->prepare('SELECT id_cliente FROM clientes WHERE email = :email AND id_cliente <> :id LIMIT 1');
            $stmtEmail->execute([':email' => $email, ':id' => $id]);
            if ($stmtEmail->fetch()) {
                $erros[] = 'Já existe outro cliente cadastrado com este e-mail (' . $email . ').';
            }
        }

        if (empty($erros)) {
            try {
                $stmtUpdate = $pdo->prepare('
                    UPDATE clientes
                       SET nome = :nome,
                           cpf = :cpf,
                           telefone = :telefone,
                           email = :email,
                           endereco = :endereco
                     WHERE id_cliente = :id
                ');
                $stmtUpdate->execute([
                    ':nome'     => $nome,
                    ':cpf'      => $cpfFormatado,
                    ':telefone' => $telefone,
                    ':email'    => !empty($email) ? $email : null,
                    ':endereco' => !empty($endereco) ? $endereco : null,
                    ':id'       => $id,
                ]);

                definirMensagem('success', 'Dados do cliente "' . $nome . '" atualizados com sucesso!');
                redirecionar('clientes/index.php');
            } catch (PDOException $e) {
                error_log('Erro ao atualizar cliente: ' . $e->getMessage());
                $erros[] = 'Erro ao atualizar o cliente no banco de dados. Tente novamente.';
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="h4 fw-bold text-dark mb-1">Editar Cliente</h2>
        <p class="text-muted small mb-0">Atualize as informações cadastrais do cliente #<?= $id ?>.</p>
    </div>
    <div>
        <a href="<?= url('clientes/index.php') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Voltar à Listagem
        </a>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-12 col-lg-8">
        <div class="card-app">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="bi bi-pencil-square text-primary"></i> Alterar Dados do Cliente
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

                <form method="post" action="<?= url('clientes/editar.php?id=' . $id) ?>" novalidate>
                    <?= campoCSRF() ?>
                    <input type="hidden" name="id_cliente" value="<?= $id ?>">

                    <div class="row g-3">
                        <div class="col-12">
                            <label for="nome" class="form-label fw-semibold text-secondary small">
                                NOME COMPLETO <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="nome" name="nome"
                                   placeholder="Ex.: Carlos Alberto Souza" value="<?= e($nome) ?>" required autofocus>
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
                                TELEFONE / WHATSAPP <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control mascara-telefone" id="telefone" name="telefone"
                                   placeholder="(00) 00000-0000" value="<?= e($telefone) ?>" required>
                        </div>

                        <div class="col-12">
                            <label for="email" class="form-label fw-semibold text-secondary small">E-MAIL</label>
                            <input type="email" class="form-control" id="email" name="email"
                                   placeholder="cliente@email.com" value="<?= e($email) ?>">
                        </div>

                        <div class="col-12">
                            <label for="endereco" class="form-label fw-semibold text-secondary small">ENDEREÇO COMPLETO</label>
                            <input type="text" class="form-control" id="endereco" name="endereco"
                                   placeholder="Rua, número, complemento, bairro, cidade/UF" value="<?= e($endereco) ?>">
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                        <a href="<?= url('clientes/index.php') ?>" class="btn btn-light border">Cancelar</a>
                        <button type="submit" class="btn btn-navy">
                            <i class="bi bi-save me-1"></i> Atualizar Cliente
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
