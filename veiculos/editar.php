<?php
/**
 * ============================================================
 *  AutoGestão - Edição de Veículo
 *  Arquivo: veiculos/editar.php
 * ============================================================
 *  Implementa:
 *   - RF07: Atualização dos dados de veículos no estoque.
 *   - RN03: Validação da marca.
 *   - RN04: Atualização da situação do estoque.
 *   - Validação de unicidade de placa excluindo o próprio veículo.
 * ============================================================
 */

require_once __DIR__ . '/../includes/auth.php';
exigirLogin();

$tituloPagina = 'Editar Veículo';
$paginaAtiva  = 'veiculos';

$pdo = conectar();
$id = (int) ($_GET['id'] ?? $_POST['id_veiculo'] ?? 0);

if ($id <= 0) {
    definirMensagem('danger', 'Veículo não identificado para edição.');
    redirecionar('veiculos/index.php');
}

$stmt = $pdo->prepare('SELECT * FROM veiculos WHERE id_veiculo = :id LIMIT 1');
$stmt->execute([':id' => $id]);
$veiculo = $stmt->fetch();

if (!$veiculo) {
    definirMensagem('danger', 'Veículo não encontrado no estoque.');
    redirecionar('veiculos/index.php');
}

$marcas = $pdo->query("SELECT id_marca, nome FROM marcas ORDER BY nome ASC")->fetchAll();

$erros = [];
$idMarca       = $veiculo['id_marca'];
$modelo        = $veiculo['modelo'];
$anoFabricacao = $veiculo['ano_fabricacao'];
$anoModelo     = $veiculo['ano_modelo'];
$tipo          = $veiculo['tipo'];
$cor           = $veiculo['cor'];
$placa         = $veiculo['placa'] ?? '';
$quilometragem = $veiculo['quilometragem'];
$precoStr      = number_format((float) $veiculo['preco'], 2, ',', '.');
$situacao      = $veiculo['situacao'];
$descricao     = $veiculo['descricao'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCSRF();

    $idMarca       = (int) post('id_marca');
    $modelo        = post('modelo');
    $anoFabricacao = (int) post('ano_fabricacao');
    $anoModelo     = (int) post('ano_modelo');
    $tipo          = post('tipo');
    $cor           = post('cor');
    $placa         = mb_strtoupper(post('placa'));
    $quilometragem = (int) post('quilometragem');
    $precoStr      = post('preco');
    $situacao      = post('situacao');
    $descricao     = post('descricao');

    $preco = converterValor($precoStr);

    // Validações
    if ($idMarca <= 0) {
        $erros[] = 'Selecione uma marca/fabricante para o veículo.';
    }

    if (empty($modelo)) {
        $erros[] = 'O campo Modelo é obrigatório.';
    } elseif (mb_strlen($modelo) > 100) {
        $erros[] = 'O modelo não pode ultrapassar 100 caracteres.';
    }

    if ($anoFabricacao < 1900 || $anoFabricacao > ((int) date('Y') + 1)) {
        $erros[] = 'Ano de fabricação inválido.';
    }

    if ($anoModelo < $anoFabricacao || $anoModelo > ($anoFabricacao + 1)) {
        $erros[] = 'O ano do modelo deve ser igual ao de fabricação ou no máximo 1 ano posterior.';
    }

    if (!in_array($tipo, ['novo', 'usado'], true)) {
        $erros[] = 'Selecione o tipo do veículo (novo ou usado).';
    }

    if (empty($cor)) {
        $erros[] = 'O campo Cor é obrigatório.';
    }

    if ($preco <= 0) {
        $erros[] = 'O preço de venda do veículo deve ser maior que zero.';
    }

    if ($quilometragem < 0) {
        $erros[] = 'A quilometragem não pode ser negativa.';
    }

    if (!in_array($situacao, ['disponível', 'vendido', 'reservado'], true)) {
        $erros[] = 'Selecione uma situação válida para o veículo.';
    }

    // Valida placa única excluindo o próprio veículo
    if (!empty($placa)) {
        $stmtPlaca = $pdo->prepare('SELECT id_veiculo FROM veiculos WHERE placa = :placa AND id_veiculo <> :id LIMIT 1');
        $stmtPlaca->execute([':placa' => $placa, ':id' => $id]);
        if ($stmtPlaca->fetch()) {
            $erros[] = 'Já existe outro veículo cadastrado com a placa "' . $placa . '".';
        }
    }

    if (empty($erros)) {
        try {
            $stmtUpdate = $pdo->prepare('
                UPDATE veiculos
                   SET id_marca       = :id_marca,
                       modelo         = :modelo,
                       ano_fabricacao = :ano_fabricacao,
                       ano_modelo     = :ano_modelo,
                       tipo           = :tipo,
                       cor            = :cor,
                       placa          = :placa,
                       quilometragem  = :quilometragem,
                       preco          = :preco,
                       situacao       = :situacao,
                       descricao      = :descricao
                 WHERE id_veiculo     = :id
            ');

            $stmtUpdate->execute([
                ':id_marca'       => $idMarca,
                ':modelo'         => $modelo,
                ':ano_fabricacao' => $anoFabricacao,
                ':ano_modelo'     => $anoModelo,
                ':tipo'           => $tipo,
                ':cor'            => $cor,
                ':placa'          => !empty($placa) ? $placa : null,
                ':quilometragem'  => $tipo === 'novo' ? 0 : $quilometragem,
                ':preco'          => $preco,
                ':situacao'       => $situacao,
                ':descricao'      => !empty($descricao) ? $descricao : null,
                ':id'             => $id,
            ]);

            definirMensagem('success', 'Veículo "' . $modelo . '" atualizado com sucesso!');
            redirecionar('veiculos/index.php');
        } catch (PDOException $e) {
            error_log('Erro ao atualizar veículo: ' . $e->getMessage());
            $erros[] = 'Erro ao atualizar o veículo no banco de dados. Tente novamente.';
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="h4 fw-bold text-dark mb-1">Editar Veículo</h2>
        <p class="text-muted small mb-0">Atualize os dados e a situação do veículo #<?= $id ?> no catálogo.</p>
    </div>
    <div>
        <a href="<?= url('veiculos/index.php') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Voltar ao Estoque
        </a>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-12 col-lg-9">
        <div class="card-app">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="bi bi-pencil-square text-primary"></i> Ficha do Veículo
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

                <form method="post" action="<?= url('veiculos/editar.php?id=' . $id) ?>" novalidate>
                    <?= campoCSRF() ?>
                    <input type="hidden" name="id_veiculo" value="<?= $id ?>">

                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <label for="id_marca" class="form-label fw-semibold text-secondary small">
                                MARCA / FABRICANTE <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="id_marca" name="id_marca" required autofocus>
                                <option value="">Selecione a marca...</option>
                                <?php foreach ($marcas as $m): ?>
                                    <option value="<?= $m['id_marca'] ?>" <?= (string) $idMarca === (string) $m['id_marca'] ? 'selected' : '' ?>>
                                        <?= e($m['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12 col-md-8">
                            <label for="modelo" class="form-label fw-semibold text-secondary small">
                                MODELO / VERSÃO <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="modelo" name="modelo"
                                   placeholder="Ex.: Corolla XEi 2.0 Flex Automático" value="<?= e($modelo) ?>" required>
                        </div>

                        <div class="col-6 col-md-3">
                            <label for="ano_fabricacao" class="form-label fw-semibold text-secondary small">
                                ANO FABRICAÇÃO <span class="text-danger">*</span>
                            </label>
                            <input type="number" class="form-control" id="ano_fabricacao" name="ano_fabricacao"
                                   min="1900" max="<?= date('Y') + 1 ?>" value="<?= e($anoFabricacao) ?>" required>
                        </div>

                        <div class="col-6 col-md-3">
                            <label for="ano_modelo" class="form-label fw-semibold text-secondary small">
                                ANO MODELO <span class="text-danger">*</span>
                            </label>
                            <input type="number" class="form-control" id="ano_modelo" name="ano_modelo"
                                   min="1900" max="<?= date('Y') + 2 ?>" value="<?= e($anoModelo) ?>" required>
                        </div>

                        <div class="col-6 col-md-3">
                            <label for="tipo" class="form-label fw-semibold text-secondary small">
                                TIPO <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="tipo" name="tipo"
                                    onchange="document.getElementById('divKm').style.display = (this.value === 'novo') ? 'none' : 'block'">
                                <option value="novo" <?= $tipo === 'novo' ? 'selected' : '' ?>>Novo (0 km)</option>
                                <option value="usado" <?= $tipo === 'usado' ? 'selected' : '' ?>>Usado</option>
                            </select>
                        </div>

                        <div class="col-6 col-md-3">
                            <label for="cor" class="form-label fw-semibold text-secondary small">
                                COR <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="cor" name="cor"
                                   placeholder="Ex.: Prata, Preto, Branco" value="<?= e($cor) ?>" required>
                        </div>

                        <div class="col-6 col-md-3">
                            <label for="placa" class="form-label fw-semibold text-secondary small">
                                PLACA (SE HOUVER)
                            </label>
                            <input type="text" class="form-control mascara-placa font-monospace text-uppercase"
                                   id="placa" name="placa" placeholder="ABC1D23" value="<?= e($placa) ?>" maxlength="7">
                            <div class="form-text">Opcional para 0 km.</div>
                        </div>

                        <div class="col-6 col-md-3" id="divKm" style="display: <?= $tipo === 'novo' ? 'none' : 'block' ?>;">
                            <label for="quilometragem" class="form-label fw-semibold text-secondary small">
                                QUILOMETRAGEM (KM)
                            </label>
                            <input type="number" class="form-control" id="quilometragem" name="quilometragem"
                                   min="0" value="<?= e($quilometragem) ?>">
                        </div>

                        <div class="col-6 col-md-3">
                            <label for="preco" class="form-label fw-semibold text-secondary small">
                                PREÇO (R$) <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control mascara-moeda fw-bold text-primary"
                                   id="preco" name="preco" placeholder="0,00" value="<?= e($precoStr) ?>" required>
                        </div>

                        <div class="col-6 col-md-3">
                            <label for="situacao" class="form-label fw-semibold text-secondary small">
                                SITUAÇÃO DO ESTOQUE <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="situacao" name="situacao" required>
                                <option value="disponível" <?= $situacao === 'disponível' ? 'selected' : '' ?>>Disponível</option>
                                <option value="reservado"  <?= $situacao === 'reservado' ? 'selected' : '' ?>>Reservado</option>
                                <option value="vendido"    <?= $situacao === 'vendido' ? 'selected' : '' ?>>Vendido</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label for="descricao" class="form-label fw-semibold text-secondary small">
                                DESCRIÇÃO / OPCIONAIS DO VEÍCULO
                            </label>
                            <textarea class="form-control" id="descricao" name="descricao" rows="3"><?= e($descricao) ?></textarea>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                        <a href="<?= url('veiculos/index.php') ?>" class="btn btn-light border">Cancelar</a>
                        <button type="submit" class="btn btn-navy">
                            <i class="bi bi-save me-1"></i> Atualizar Veículo
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
