<?php
/**
 * ============================================================
 *  AutoGestão - Cadastro de Cliente
 *  Arquivo: clientes/cadastrar.php
 * ============================================================
 *  Implementa:
 *   - RF04: Cadastro de novo cliente.
 *   - RN01: Validação de CPF único e algoritmo de dígitos verificadores.
 *   - Proteção CSRF e validação estrita no servidor.
 * ============================================================
 */

require_once __DIR__ . '/../includes/auth.php';
exigirLogin();

$tituloPagina = 'Novo Cliente';
$paginaAtiva  = 'clientes';

$erros = [];
$nome     = '';
$cpf      = '';
$telefone = '';
$email    = '';
$endereco = '';

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

    // Se as validações básicas passaram, verifica duplicidades no banco
    if (empty($erros)) {
        $pdo = conectar();
        $cpfFormatado = formatarCPF($cpf);

        // RN01: CPF deve ser único
        $stmtCpf = $pdo->prepare('SELECT id_cliente FROM clientes WHERE cpf = :cpf LIMIT 1');
        $stmtCpf->execute([':cpf' => $cpfFormatado]);
        if ($stmtCpf->fetch()) {
            $erros[] = 'Já existe um cliente cadastrado com este CPF (' . $cpfFormatado . ').';
        }

        // E-mail único (se preenchido)
        if (!empty($email)) {
            $stmtEmail = $pdo->prepare('SELECT id_cliente FROM clientes WHERE email = :email LIMIT 1');
            $stmtEmail->execute([':email' => $email]);
            if ($stmtEmail->fetch()) {
                $erros[] = 'Já existe um cliente cadastrado com este e-mail (' . $email . ').';
            }
        }

        // Se não houver duplicidades, insere no banco
        if (empty($erros)) {
            try {
                $stmtInsert = $pdo->prepare('
                    INSERT INTO clientes (nome, cpf, telefone, email, endereco)
                    VALUES (:nome, :cpf, :telefone, :email, :endereco)
                ');
                $stmtInsert->execute([
                    ':nome'     => $nome,
                    ':cpf'      => $cpfFormatado,
                    ':telefone' => $telefone,
                    ':email'    => !empty($email) ? $email : null,
                    ':endereco' => !empty($endereco) ? $endereco : null,
                ]);

                definirMensagem('success', 'Cliente "' . $nome . '" cadastrado com sucesso!');
                redirecionar('clientes/index.php');
            } catch (PDOException $e) {
                error_log('Erro ao cadastrar cliente: ' . $e->getMessage());
                $erros[] = 'Erro ao salvar o cliente no banco de dados. Tente novamente.';
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="h4 fw-bold text-dark mb-1">Cadastrar Cliente</h2>
        <p class="text-muted small mb-0">Preencha os dados do cliente para adicioná-lo à base do sistema.</p>
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
                    <i class="bi bi-person-plus text-primary"></i> Informações do Cliente
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

                <form method="post" action="<?= url('clientes/cadastrar.php') ?>" novalidate>
                    <?= campoCSRF() ?>

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
                            <div class="form-text">Apenas números ou com pontuação.</div>
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
                            <i class="bi bi-check-lg me-1"></i> Salvar Cliente
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
