<?php
/**
 * ============================================================
 *  AutoGestão - Detalhes da Venda
 *  Arquivo: vendas/visualizar.php
 * ============================================================
 *  Implementa:
 *   - RF09: Consulta completa e detalhada da venda.
 *   - Exibição de veículos associados e valores negociados.
 *   - Exibição do histórico de pagamentos e saldo devedor.
 * ============================================================
 */

require_once __DIR__ . '/../includes/auth.php';
exigirLogin();

$tituloPagina = 'Detalhes da Venda';
$paginaAtiva  = 'vendas';

$pdo = conectar();
$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    definirMensagem('danger', 'Venda não identificada.');
    redirecionar('vendas/index.php');
}

// 1. Cabeçalho da Venda e Partes
$stmtVenda = $pdo->prepare("
    SELECT v.*,
           c.nome AS cliente_nome, c.cpf AS cliente_cpf, c.telefone AS cliente_telefone, c.email AS cliente_email, c.endereco AS cliente_endereco,
           f.nome AS vendedor_nome, f.cargo AS vendedor_cargo, f.email AS vendedor_email
      FROM vendas v
      JOIN clientes c     ON c.id_cliente = v.id_cliente
      JOIN funcionarios f ON f.id_funcionario = v.id_funcionario
     WHERE v.id_venda = :id
     LIMIT 1
");
$stmtVenda->execute([':id' => $id]);
$venda = $stmtVenda->fetch();

if (!$venda) {
    definirMensagem('danger', 'Venda não encontrada no sistema.');
    redirecionar('vendas/index.php');
}

// 2. Veículos desta venda (tabela venda_veiculos)
$stmtItens = $pdo->prepare("
    SELECT vv.valor_vendido,
           ve.id_veiculo, ve.modelo, ve.ano_fabricacao, ve.ano_modelo,
           ve.tipo, ve.cor, ve.placa, ve.quilometragem, ve.situacao,
           m.nome AS marca_nome
      FROM venda_veiculos vv
      JOIN veiculos ve ON ve.id_veiculo = vv.id_veiculo
      JOIN marcas m    ON m.id_marca = ve.id_marca
     WHERE vv.id_venda = :id
     ORDER BY ve.modelo ASC
");
$stmtItens->execute([':id' => $id]);
$veiculosVenda = $stmtItens->fetchAll();

// 3. Pagamentos efetuados desta venda
$stmtPag = $pdo->prepare("
    SELECT id_pagamento, valor, forma_pagamento, data_pagamento, status
      FROM pagamentos
     WHERE id_venda = :id
     ORDER BY data_pagamento DESC
");
$stmtPag->execute([':id' => $id]);
$pagamentos = $stmtPag->fetchAll();

// Cálculos financeiros consolidados
$valorTotal   = (float) $venda['valor_total'];
$desconto     = (float) $venda['desconto'];
$subtotal     = 0.0;
foreach ($veiculosVenda as $it) {
    $subtotal += (float) $it['valor_vendido'];
}

$totalRecebido = 0.0;
foreach ($pagamentos as $p) {
    if ($p['status'] === 'pago') {
        $totalRecebido += (float) $p['valor'];
    }
}
$saldoDevedor = max(0, $valorTotal - $totalRecebido);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <h2 class="h4 fw-bold text-dark mb-0">Venda #<?= $venda['id_venda'] ?></h2>
            <?= badgeStatus($venda['status']) ?>
        </div>
        <p class="text-muted small mb-0">
            Realizada em <?= formatarDataHora($venda['data_venda']) ?> &bull; Vendedor: <?= e($venda['vendedor_nome']) ?>
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('vendas/index.php') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Voltar às Vendas
        </a>
        <?php if ($venda['status'] !== 'cancelada' && $saldoDevedor > 0): ?>
            <a href="<?= url('pagamentos/cadastrar.php?id_venda=' . $venda['id_venda']) ?>" class="btn btn-success">
                <i class="bi bi-cash-coin me-1"></i> Lançar Pagamento
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Card Cliente -->
    <div class="col-12 col-md-6">
        <div class="card-app h-100">
            <div class="card-header">
                <h3 class="card-title"><i class="bi bi-person text-primary"></i> Dados do Cliente</h3>
                <a href="<?= url('clientes/editar.php?id=' . $venda['id_cliente']) ?>" class="btn btn-sm btn-outline-secondary">Editar</a>
            </div>
            <div class="card-body">
                <div class="fs-5 fw-bold text-dark mb-1"><?= e($venda['cliente_nome']) ?></div>
                <div class="text-muted small mb-3">CPF: <code><?= e($venda['cliente_cpf']) ?></code></div>

                <div class="row g-2 small">
                    <div class="col-sm-6">
                        <span class="text-secondary fw-semibold">Telefone:</span><br>
                        <?= e($venda['cliente_telefone']) ?>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-secondary fw-semibold">E-mail:</span><br>
                        <?= e($venda['cliente_email'] ?? '-') ?>
                    </div>
                    <div class="col-12 mt-2">
                        <span class="text-secondary fw-semibold">Endereço:</span><br>
                        <?= e($venda['cliente_endereco'] ?? 'Não informado') ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Card Vendedor e Resumo Financeiro -->
    <div class="col-12 col-md-6">
        <div class="card-app h-100">
            <div class="card-header">
                <h3 class="card-title"><i class="bi bi-receipt text-primary"></i> Resumo da Venda</h3>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Vendedor Responsável:</span>
                    <span class="fw-semibold text-dark"><?= e($venda['vendedor_nome']) ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Subtotal dos Veículos:</span>
                    <span class="fw-semibold text-dark"><?= formatarMoeda($subtotal) ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Desconto Aplicado:</span>
                    <span class="text-danger">- <?= formatarMoeda($desconto) ?></span>
                </div>
                <hr>
                <div class="d-flex justify-content-between mb-2">
                    <span class="fs-6 fw-bold text-dark">Valor Total:</span>
                    <span class="fs-5 fw-bold text-primary"><?= formatarMoeda($valorTotal) ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Total Pago:</span>
                    <span class="fw-bold text-success"><?= formatarMoeda($totalRecebido) ?></span>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-muted">Saldo Pendente:</span>
                    <span class="fw-bold <?= $saldoDevedor > 0 ? 'text-danger' : 'text-muted' ?>">
                        <?= formatarMoeda($saldoDevedor) ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tabela de Veículos Vendidos -->
<div class="card-app mb-4">
    <div class="card-header">
        <h3 class="card-title"><i class="bi bi-car-front text-primary"></i> Veículo(s) Incluído(s) na Venda</h3>
        <span class="badge bg-light text-secondary border"><?= count($veiculosVenda) ?> item(ns)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-app align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 60px;">#</th>
                        <th>Veículo / Modelo</th>
                        <th>Marca</th>
                        <th>Ano</th>
                        <th>Tipo</th>
                        <th>Placa</th>
                        <th>Km</th>
                        <th class="text-end">Valor Negociado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($veiculosVenda)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                Nenhum veículo associado atualmente a esta venda (possivelmente cancelada).
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($veiculosVenda as $it): ?>
                            <tr>
                                <td class="fw-bold text-muted">#<?= $it['id_veiculo'] ?></td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= e($it['modelo']) ?></div>
                                    <small class="text-muted">Cor: <?= e($it['cor']) ?></small>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?= e($it['marca_nome']) ?></span></td>
                                <td class="small"><?= e($it['ano_fabricacao']) ?>/<?= e($it['ano_modelo']) ?></td>
                                <td><?= badgeStatus($it['tipo']) ?></td>
                                <td class="font-monospace small"><?= e($it['placa'] ?? '0 km') ?></td>
                                <td class="small text-muted"><?= formatarKm($it['quilometragem']) ?></td>
                                <td class="text-end fw-bold text-primary"><?= formatarMoeda($it['valor_vendido']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Histórico de Pagamentos -->
<div class="card-app mb-4">
    <div class="card-header">
        <h3 class="card-title"><i class="bi bi-cash-coin text-primary"></i> Pagamentos Registrados</h3>
        <?php if ($venda['status'] !== 'cancelada' && $saldoDevedor > 0): ?>
            <a href="<?= url('pagamentos/cadastrar.php?id_venda=' . $venda['id_venda']) ?>" class="btn btn-sm btn-success">
                <i class="bi bi-plus-lg me-1"></i> Registrar Pagamento
            </a>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-app align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 60px;">#</th>
                        <th>Data do Pagamento</th>
                        <th>Forma de Pagamento</th>
                        <th>Valor</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pagamentos)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                                Nenhum pagamento lançado para esta venda até o momento.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($pagamentos as $p): ?>
                            <tr>
                                <td class="fw-bold text-muted">#<?= $p['id_pagamento'] ?></td>
                                <td><?= formatarDataHora($p['data_pagamento']) ?></td>
                                <td><span class="badge bg-light text-dark border text-uppercase"><?= e($p['forma_pagamento']) ?></span></td>
                                <td class="fw-bold text-success"><?= formatarMoeda($p['valor']) ?></td>
                                <td><?= badgeStatus($p['status']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if (!empty($venda['observacao'])): ?>
    <div class="card-app mb-4">
        <div class="card-header">
            <h3 class="card-title"><i class="bi bi-chat-text text-secondary"></i> Observações</h3>
        </div>
        <div class="card-body">
            <p class="mb-0 text-secondary"><?= nl2br(e($venda['observacao'])) ?></p>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
