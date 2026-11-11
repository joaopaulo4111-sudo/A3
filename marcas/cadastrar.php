<?php
/**
 * ============================================================
 *  AutoGestão - Cadastro de Marca
 *  Arquivo: marcas/cadastrar.php
 * ============================================================
 *  Implementa:
 *   - RF06: Cadastro de marcas/fabricantes.
 *   - Validação de unicidade do nome da marca.
 * ============================================================
 */

require_once __DIR__ . '/../includes/auth.php';
exigirLogin();

$tituloPagina = 'Nova Marca';
$paginaAtiva  = 'marcas';

$erros = [];
$nome       = '';
$paisOrigem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCSRF();

    $nome       = post('nome');
    $paisOrigem = post('pais_origem');

    if (empty($nome)) {
        $erros[] = 'O campo Nome da Marca é obrigatório.';
    } elseif (mb_strlen($nome) > 80) {
        $erros[] = 'O nome da marca não pode ultrapassar 80 caracteres.';
    }

    if (!empty($paisOrigem) && mb_strlen($paisOrigem) > 60) {
        $erros[] = 'O país de origem não pode ultrapassar 60 caracteres.';
    }

    if (empty($erros)) {
        $pdo = conectar();

        // Verifica unicidade do nome da marca
        $stmtCheck = $pdo->prepare('SELECT id_marca FROM marcas WHERE nome = :nome LIMIT 1');
        $stmtCheck->execute([':nome' => $nome]);
        if ($stmtCheck->fetch()) {
            $erros[] = 'Já existe uma marca cadastrada com o nome "' . $nome . '".';
        } else {
            try {
                $stmtInsert = $pdo->prepare('
                    INSERT INTO marcas (nome, pais_origem)
                    VALUES (:nome, :pais_origem)
                ');
                $stmtInsert->execute([
                    ':nome'        => $nome,
                    ':pais_origem' => !empty($paisOrigem) ? $paisOrigem : null,
                ]);

                definirMensagem('success', 'Marca "' . $nome . '" cadastrada com sucesso!');
                redirecionar('marcas/index.php');
            } catch (PDOException $e) {
                error_log('Erro ao cadastrar marca: ' . $e->getMessage());
                $erros[] = 'Erro ao salvar a marca no banco de dados. Tente novamente.';
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="h4 fw-bold text-dark mb-1">Cadastrar Nova Marca</h2>
        <p class="text-muted small mb-0">Adicione uma fabricante de veículos ao sistema.</p>
    </div>
    <div>
        <a href="<?= url('marcas/index.php') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Voltar à Listagem
        </a>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-12 col-lg-6">
        <div class="card-app">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="bi bi-tag text-primary"></i> Informações da Fabricante
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

                <form method="post" action="<?= url('marcas/cadastrar.php') ?>" novalidate>
                    <?= campoCSRF() ?>

                    <div class="mb-3">
                        <label for="nome" class="form-label fw-semibold text-secondary small">
                            NOME DA MARCA / FABRICANTE <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" id="nome" name="nome"
                               placeholder="Ex.: Toyota, Honda, Ford..." value="<?= e($nome) ?>" required autofocus>
                    </div>

                    <div class="mb-4">
                        <label for="pais_origem" class="form-label fw-semibold text-secondary small">
                            PAÍS DE ORIGEM
                        </label>
                        <input type="text" class="form-control" id="pais_origem" name="pais_origem"
                               placeholder="Ex.: Japão, Alemanha, Estados Unidos..." value="<?= e($paisOrigem) ?>">
                    </div>

                    <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                        <a href="<?= url('marcas/index.php') ?>" class="btn btn-light border">Cancelar</a>
                        <button type="submit" class="btn btn-navy">
                            <i class="bi bi-check-lg me-1"></i> Salvar Marca
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
