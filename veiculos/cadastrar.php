<?php
/**
 * ============================================================
 *  AutoGestão - Cadastro de Veículo
 *  Arquivo: veiculos/cadastrar.php
 * ============================================================
 *  Implementa:
 *   - RF07: Cadastro de veículos novos e usados.
 *   - RN03: Vínculo obrigatório com marca cadastrada.
 *   - RN04: Situação de estoque obrigatória (disponível por padrão).
 *   - Validações de integridade e unicidade de placa.
 * ============================================================
 */

require_once __DIR__ . '/../includes/auth.php';
exigirLogin();

$tituloPagina = 'Cadastrar Veículo';
$paginaAtiva  = 'veiculos';

$pdo = conectar();
$marcas = $pdo->query("SELECT id_marca, nome FROM marcas ORDER BY nome ASC")->fetchAll();

$erros = [];
$idMarca       = '';
$modelo        = '';
$anoFabricacao = (int) date('Y');
$anoModelo     = (int) date('Y');
$tipo          = 'novo';
$cor           = '';
$placa         = '';
$quilometragem = 0;
$precoStr      = '';
$situacao      = 'disponível';
$descricao     = '';

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
        $erros[] = 'Selecione uma marca/fabricante para o veículo (RN03).';
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
        $erros[] = 'Selecione uma situação válida para o veículo (RN04).';
    }

    // Valida unicidade de placa se preenchida
    if (!empty($placa)) {
        $stmtPlaca = $pdo->prepare('SELECT id_veiculo FROM veiculos WHERE placa = :placa LIMIT 1');
        $stmtPlaca->execute([':placa' => $placa]);
        if ($stmtPlaca->fetch()) {
            $erros[] = 'Já existe um veículo cadastrado com a placa "' . $placa . '".';
        }
    }

    if (empty($erros)) {
        try {
            $stmtInsert = $pdo->prepare('
                INSERT INTO veiculos (
                    id_marca, modelo, ano_fabricacao, ano_modelo, tipo,
                    cor, placa, quilometragem, preco, situacao, descricao
                ) VALUES (
                    :id_marca, :modelo, :ano_fabricacao, :ano_modelo, :tipo,
                    :cor, :placa, :quilometragem, :preco, :situacao, :descricao
                )
            ');

            $stmtInsert->execute([
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
            ]);

            definirMensagem('success', 'Veículo "' . $modelo . '" cadastrado com sucesso no estoque!');
            redirecionar('veiculos/index.php');
        } catch (PDOException $e) {
            error_log('Erro ao cadastrar veículo: ' . $e->getMessage());
            $erros[] = 'Erro ao cadastrar o veículo no banco de dados. Tente novamente.';
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="h4 fw-bold text-dark mb-1">Cadastrar Veículo</h2>
        <p class="text-muted small mb-0">Preencha os dados do veículo para adicioná-lo ao catálogo da concessionária.</p>
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
                    <i class="bi bi-car-front text-primary"></i> Ficha do Veículo
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

                <form method="post" action="<?= url('veiculos/cadastrar.php') ?>" novalidate>
                    <?= campoCSRF() ?>

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
                            <textarea class="form-control" id="descricao" name="descricao" rows="3"
                                      placeholder="Descreva detalhes como equipamentos, laudo, revisões, garantia..."><?= e($descricao) ?></textarea>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                        <a href="<?= url('veiculos/index.php') ?>" class="btn btn-light border">Cancelar</a>
                        <button type="submit" class="btn btn-navy">
                            <i class="bi bi-check-lg me-1"></i> Salvar Veículo
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
