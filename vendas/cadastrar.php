<?php
/**
 * ============================================================
 *  AutoGestão - Registro de Venda
 *  Arquivo: vendas/cadastrar.php
 * ============================================================
 *  Implementa:
 *   - RF08: Registro de vendas vinculando cliente, vendedor e veículos.
 *   - RF16: Atualização da situação do veículo no estoque.
 *   - RN05: Somente veículos disponíveis podem ser selecionados.
 *   - RN06: Cliente e funcionário obrigatórios.
 *   - RN07: No mínimo 1 veículo por venda.
 *   - RN08: Bloqueio de venda duplicada de veículo.
 *   - RN09: Cálculo: Total = soma(veículos) - desconto.
 *   - RN11: Atualização automática para 'vendido' (ou 'reservado').
 *   - Transações PDO para consistência atômica.
 * ============================================================
 */

require_once __DIR__ . '/../includes/auth.php';
exigirLogin();

$tituloPagina = 'Registrar Nova Venda';
$paginaAtiva  = 'vendas';

$pdo = conectar();
$usuarioLogado = usuarioLogado();

// Busca lista de clientes para seleção
$clientes = $pdo->query("SELECT id_cliente, nome, cpf FROM clientes ORDER BY nome ASC")->fetchAll();

// Busca lista de vendedores para seleção
$vendedores = $pdo->query("SELECT id_funcionario, nome, cargo FROM funcionarios ORDER BY nome ASC")->fetchAll();

// RN05: Busca APENAS veículos que estejam com situação 'disponível'
$veiculosDisponiveis = $pdo->query("
    SELECT ve.id_veiculo, ve.modelo, ve.ano_modelo, ve.tipo, ve.cor, ve.placa, ve.preco,
           m.nome AS marca_nome
      FROM veiculos ve
      JOIN marcas m ON m.id_marca = ve.id_marca
     WHERE ve.situacao = 'disponível'
     ORDER BY m.nome ASC, ve.modelo ASC
")->fetchAll();

$erros = [];
$idCliente     = '';
$idFuncionario = $usuarioLogado['id_funcionario'] ?? '';
$veiculosSel   = [];
$descontoStr   = '0,00';
$statusVenda   = 'concluída';
$observacao    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCSRF();

    $idCliente     = (int) post('id_cliente');
    $idFuncionario = (int) post('id_funcionario');
    $veiculosSel   = $_POST['veiculos'] ?? [];
    $descontoStr   = post('desconto');
    $statusVenda   = post('status');
    $observacao    = post('observacao');

    $desconto = converterValor($descontoStr);

    // RN06: Cliente e Funcionário obrigatórios
    if ($idCliente <= 0) {
        $erros[] = 'Selecione o cliente comprador (Regra RN06).';
    }

    if ($idFuncionario <= 0) {
        $erros[] = 'Selecione o vendedor responsável pela venda (Regra RN06).';
    }

    // RN07: Pelo menos um veículo selecionado
    if (empty($veiculosSel) || !is_array($veiculosSel)) {
        $erros[] = 'Selecione pelo menos um veículo para realizar a venda (Regra RN07).';
    }

    if ($desconto < 0) {
        $erros[] = 'O valor de desconto não pode ser negativo.';
    }

    if (!in_array($statusVenda, ['concluída', 'pendente'], true)) {
        $erros[] = 'Selecione uma situação válida para a venda.';
    }

    if (empty($erros)) {
        // Validação e cálculo dos veículos selecionados
        $veiculosValidos = [];
        $somaVeiculos = 0.0;

        // Prepara query para validar se os veículos selecionados estão de fato DISPONÍVEIS (RN05 / RN08)
        $stmtChecaVeic = $pdo->prepare("
            SELECT id_veiculo, modelo, preco, situacao
              FROM veiculos
             WHERE id_veiculo = :id
             FOR UPDATE
        ");

        try {
            // Inicia Transação PDO Atômica
            $pdo->beginTransaction();

            foreach ($veiculosSel as $idVeiculo) {
                $idV = (int) $idVeiculo;
                $stmtChecaVeic->execute([':id' => $idV]);
                $vRow = $stmtChecaVeic->fetch();

                if (!$vRow) {
                    throw new Exception('Veículo #' . $idV . ' não encontrado no catálogo.');
                }

                // RN05: Somente veículos disponíveis podem ser vendidos
                if ($vRow['situacao'] !== 'disponível') {
                    throw new Exception('O veículo "' . $vRow['modelo'] . '" não está disponível para venda (situação atual: ' . $vRow['situacao'] . ').');
                }

                $veiculosValidos[] = $vRow;
                $somaVeiculos += (float) $vRow['preco'];
            }

            // RN09: Valor total = soma dos veículos - desconto
            if ($desconto > $somaVeiculos) {
                throw new Exception('O desconto (R$ ' . number_format($desconto, 2, ',', '.') . ') não pode ser superior ao valor dos veículos (R$ ' . number_format($somaVeiculos, 2, ',', '.') . ').');
            }

            $valorTotalVenda = $somaVeiculos - $desconto;

            // 1. Grava o cabeçalho na tabela vendas
            $stmtVenda = $pdo->prepare('
                INSERT INTO vendas (id_cliente, id_funcionario, data_venda, valor_total, desconto, status, observacao)
                VALUES (:id_cliente, :id_funcionario, NOW(), :valor_total, :desconto, :status, :observacao)
            ');
            $stmtVenda->execute([
                ':id_cliente'     => $idCliente,
                ':id_funcionario' => $idFuncionario,
                ':valor_total'    => $valorTotalVenda,
                ':desconto'       => $desconto,
                ':status'         => $statusVenda,
                ':observacao'     => !empty($observacao) ? $observacao : null,
            ]);

            $idVendaGerada = (int) $pdo->lastInsertId();

            // 2. Grava cada veículo na tabela associativa venda_veiculos
            $stmtVendaVeic = $pdo->prepare('
                INSERT INTO venda_veiculos (id_venda, id_veiculo, valor_vendido)
                VALUES (:id_venda, :id_veiculo, :valor_vendido)
            ');

            // 3. Atualiza a situação do estoque do veículo (RN11 / RF16)
            $novaSituacao = ($statusVenda === 'concluída') ? 'vendido' : 'reservado';
            $stmtUpdVeic = $pdo->prepare('
                UPDATE veiculos
                   SET situacao = :situacao
                 WHERE id_veiculo = :id
            ');

            foreach ($veiculosValidos as $vItem) {
                $stmtVendaVeic->execute([
                    ':id_venda'       => $idVendaGerada,
                    ':id_veiculo'     => $vItem['id_veiculo'],
                    ':valor_vendido'  => $vItem['preco'],
                ]);

                $stmtUpdVeic->execute([
                    ':situacao' => $novaSituacao,
                    ':id'       => $vItem['id_veiculo'],
                ]);
            }

            // Confirma a transação com sucesso!
            $pdo->commit();

            definirMensagem('success', 'Venda #' . $idVendaGerada . ' registrada com sucesso! Total: R$ ' . number_format($valorTotalVenda, 2, ',', '.'));
            redirecionar('vendas/visualizar.php?id=' . $idVendaGerada);

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Erro na transação de venda: ' . $e->getMessage());
            $erros[] = $e->getMessage();
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="h4 fw-bold text-dark mb-1">Registrar Venda</h2>
        <p class="text-muted small mb-0">Selecione o comprador, vendedor e os veículos em estoque para emissão da venda.</p>
    </div>
    <div>
        <a href="<?= url('vendas/index.php') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Voltar ao Histórico
        </a>
    </div>
</div>

<?php if (empty($veiculosDisponiveis)): ?>
    <div class="alert alert-warning shadow-sm">
        <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
        <strong>Não há veículos disponíveis no estoque no momento.</strong>
        Cadastre novos veículos ou libere reservas antes de registrar uma nova venda.
        <a href="<?= url('veiculos/cadastrar.php') ?>" class="alert-link ms-2">Cadastrar Veículo</a>
    </div>
<?php endif; ?>

<form method="post" action="<?= url('vendas/cadastrar.php') ?>" id="formVenda" novalidate>
    <?= campoCSRF() ?>

    <div class="row g-4">
        <!-- Coluna da Esquerda: Dados da Venda e Veículos -->
        <div class="col-12 col-lg-8">
            
            <?php if (!empty($erros)): ?>
                <div class="alert alert-danger shadow-sm mb-4">
                    <div class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-2"></i>Não foi possível concluir a venda:</div>
                    <ul class="mb-0 ps-3">
                        <?php foreach ($erros as $erro): ?>
                            <li><?= e($erro) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Dados Comerciais -->
            <div class="card-app mb-4">
                <div class="card-header">
                    <h3 class="card-title"><i class="bi bi-person-check text-primary"></i> Partes da Negociação</h3>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label for="id_cliente" class="form-label fw-semibold text-secondary small">
                                CLIENTE COMPRADOR <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="id_cliente" name="id_cliente" required autofocus>
                                <option value="">Selecione o cliente...</option>
                                <?php foreach ($clientes as $c): ?>
                                    <option value="<?= $c['id_cliente'] ?>" <?= (string) $idCliente === (string) $c['id_cliente'] ? 'selected' : '' ?>>
                                        <?= e($c['nome']) ?> (CPF: <?= e($c['cpf']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">
                                Cliente não encontrado? <a href="<?= url('clientes/cadastrar.php') ?>" target="_blank">Cadastrar novo</a>
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="id_funcionario" class="form-label fw-semibold text-secondary small">
                                VENDEDOR RESPONSÁVEL <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="id_funcionario" name="id_funcionario" required>
                                <option value="">Selecione o vendedor...</option>
                                <?php foreach ($vendedores as $vend): ?>
                                    <option value="<?= $vend['id_funcionario'] ?>" <?= (string) $idFuncionario === (string) $vend['id_funcionario'] ? 'selected' : '' ?>>
                                        <?= e($vend['nome']) ?> (<?= e($vend['cargo']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Seleção de Veículos Disponíveis (RN05 / RN07) -->
            <div class="card-app">
                <div class="card-header">
                    <h3 class="card-title"><i class="bi bi-car-front text-primary"></i> Veículos Disponíveis no Estoque</h3>
                    <span class="badge bg-light text-secondary border">Apenas situação 'disponível' (RN05)</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 380px; overflow-y: auto;">
                        <table class="table table-app align-middle mb-0">
                            <thead class="sticky-top bg-light">
                                <tr>
                                    <th style="width: 40px;"></th>
                                    <th>Veículo / Modelo</th>
                                    <th>Marca</th>
                                    <th>Tipo</th>
                                    <th>Placa / Km</th>
                                    <th class="text-end">Preço Unitário</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($veiculosDisponiveis)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">Nenhum veículo disponível no estoque.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($veiculosDisponiveis as $ve): ?>
                                        <?php $marcado = in_array((string) $ve['id_veiculo'], array_map('strval', $veiculosSel), true); ?>
                                        <tr class="item-veiculo-row">
                                            <td class="text-center">
                                                <input class="form-check-input check-veiculo" type="checkbox"
                                                       name="veiculos[]" value="<?= $ve['id_veiculo'] ?>"
                                                       data-preco="<?= (float) $ve['preco'] ?>"
                                                       id="chk_<?= $ve['id_veiculo'] ?>"
                                                       <?= $marcado ? 'checked' : '' ?>>
                                            </td>
                                            <td>
                                                <label class="form-check-label fw-semibold text-dark d-block cursor-pointer" for="chk_<?= $ve['id_veiculo'] ?>">
                                                    <?= e($ve['modelo']) ?>
                                                </label>
                                                <small class="text-muted">Ano: <?= e($ve['ano_modelo']) ?> &bull; Cor: <?= e($ve['cor']) ?></small>
                                            </td>
                                            <td><span class="badge bg-light text-dark border"><?= e($ve['marca_nome']) ?></span></td>
                                            <td><?= badgeStatus($ve['tipo']) ?></td>
                                            <td class="small font-monospace"><?= e($ve['placa'] ?? '0 km') ?></td>
                                            <td class="text-end fw-bold text-primary">
                                                <?= formatarMoeda($ve['preco']) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>

        <!-- Coluna da Direita: Fechamento de Valores e Status -->
        <div class="col-12 col-lg-4">
            <div class="card-app sticky-top" style="top: 80px;">
                <div class="card-header">
                    <h3 class="card-title"><i class="bi bi-calculator text-primary"></i> Resumo Financeiro</h3>
                </div>
                <div class="card-body">

                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Veículos Selecionados:</span>
                        <span class="fw-bold text-dark" id="txtQtdVeiculos">0 item(ns)</span>
                    </div>

                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Subtotal:</span>
                        <span class="fw-bold text-dark" id="txtSubtotal">R$ 0,00</span>
                    </div>

                    <div class="mb-3">
                        <label for="desconto" class="form-label fw-semibold text-secondary small">DESCONTO (R$)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted">R$</span>
                            <input type="text" class="form-control mascara-moeda text-end" id="desconto" name="desconto"
                                   placeholder="0,00" value="<?= e($descontoStr) ?>">
                        </div>
                    </div>

                    <hr>

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <span class="fs-6 fw-bold text-dark">VALOR TOTAL:</span>
                        <span class="fs-4 fw-bold text-primary" id="txtTotal">R$ 0,00</span>
                    </div>

                    <div class="mb-3">
                        <label for="status" class="form-label fw-semibold text-secondary small">SITUAÇÃO INICIAL DA VENDA</label>
                        <select class="form-select" id="status" name="status">
                            <option value="concluída" <?= $statusVenda === 'concluída' ? 'selected' : '' ?>>Concluída (Veículo passa a 'vendido')</option>
                            <option value="pendente"  <?= $statusVenda === 'pendente' ? 'selected' : '' ?>>Pendente (Veículo passa a 'reservado')</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label for="observacao" class="form-label fw-semibold text-secondary small">OBSERVAÇÕES DA NEGOCIAÇÃO</label>
                        <textarea class="form-control" id="observacao" name="observacao" rows="3"
                                  placeholder="Detalhes sobre pagamento, entrada, prazo..."><?= e($observacao) ?></textarea>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-navy btn-lg fs-6 fw-semibold">
                            <i class="bi bi-check2-circle me-1"></i> Concluir e Salvar Venda
                        </button>
                        <a href="<?= url('vendas/index.php') ?>" class="btn btn-light border">Cancelar</a>
                    </div>

                </div>
            </div>
        </div>
    </div>
</form>

<script>
// Cálculo Dinâmico em tempo real de Subtotal, Desconto e Total da Venda
function formatarMoedaJS(valor) {
    return 'R$ ' + valor.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function parseMoedaJS(str) {
    if (!str) return 0;
    str = (str + '').trim().replace(/\./g, '').replace(',', '.');
    return parseFloat(str) || 0;
}

function recalcularTotais() {
    let subtotal = 0;
    let qtd = 0;

    document.querySelectorAll('.check-veiculo:checked').forEach(function(chk) {
        subtotal += parseFloat(chk.getAttribute('data-preco')) || 0;
        qtd++;
    });

    const campoDesc = document.getElementById('desconto');
    const desconto = parseMoedaJS(campoDesc ? campoDesc.value : '0');

    let total = subtotal - desconto;
    if (total < 0) total = 0;

    document.getElementById('txtQtdVeiculos').textContent = qtd + ' item(ns)';
    document.getElementById('txtSubtotal').textContent = formatarMoedaJS(subtotal);
    document.getElementById('txtTotal').textContent = formatarMoedaJS(total);
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.check-veiculo').forEach(function(chk) {
        chk.addEventListener('change', recalcularTotais);
    });

    const campoDesc = document.getElementById('desconto');
    if (campoDesc) {
        campoDesc.addEventListener('input', recalcularTotais);
        campoDesc.addEventListener('blur', recalcularTotais);
    }

    recalcularTotais();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
