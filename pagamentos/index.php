<?php
/**
 * ============================================================
 *  AutoGestão - Módulo de Pagamentos (Listagem e Filtros)
 *  Arquivo: pagamentos/index.php
 * ============================================================
 *  Implementa:
 *   - RF03: Acesso protegido por autenticação.
 *   - RF11: Consulta e acompanhamento de pagamentos vinculados às vendas.
 *   - HU08: Controle financeiro dos valores recebidos e pendentes.
 *   - Filtros por status, forma de pagamento e período.
 * ============================================================
 */

require_once __DIR__ . '/../includes/auth.php';
exigirLogin();

$tituloPagina = 'Pagamentos';
$paginaAtiva  = 'pagamentos';

$pdo = conectar();

// Filtros de busca (GET)
$filtroStatus = get('status');
$filtroForma  = get('forma_pagamento');
$filtroInicio = get('data_inicio');
$filtroFim    = get('data_fim');
$filtroBusca  = get('busca');

$sql = "
    SELECT p.id_pagamento, p.valor, p.forma_pagamento, p.data_pagamento, p.status,
           v.id_venda, v.valor_total AS venda_total, v.status AS venda_status,
           c.id_cliente, c.nome AS cliente_nome,
           f.nome AS vendedor_nome
      FROM pagamentos p
      JOIN vendas v       ON v.id_venda = p.id_venda
      JOIN clientes c     ON c.id_cliente = v.id_cliente
      JOIN funcionarios f ON f.id_funcionario = v.id_funcionario
     WHERE 1 = 1
";
$params = [];

if ($filtroStatus !== '') {
    $sql .= " AND p.status = :status";
    $params[':status'] = $filtroStatus;
}

if ($filtroForma !== '') {
    $sql .= " AND p.forma_pagamento = :forma";
    $params[':forma'] = $filtroForma;
}

if ($filtroInicio !== '') {
    $sql .= " AND DATE(p.data_pagamento) >= :inicio";
    $params[':inicio'] = $filtroInicio;
}

if ($filtroFim !== '') {
    $sql .= " AND DATE(p.data_pagamento) <= :fim";
    $params[':fim'] = $filtroFim;
}

if ($filtroBusca !== '') {
    $sql .= " AND (c.nome LIKE :busca OR p.id_venda = :id_venda)";
    $params[':busca']    = '%' . $filtroBusca . '%';
    $params[':id_venda'] = (int) $filtroBusca;
}

$sql .= " ORDER BY p.data_pagamento DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pagamentos = $stmt->fetchAll();

// Totais dos pagamentos filtrados
$totalPago      = 0.0;
$totalPendente   = 0.0;
$totalCancelado  = 0.0;

foreach ($pagamentos as $pg) {
    $val = (float) $pg['valor'];
    if ($pg['status'] === 'pago') {
        $totalPago += $val;
    } elseif ($pg['status'] === 'pendente') {
        $totalPendente += $val;
    } elseif ($pg['status'] === 'cancelado') {
        $totalCancelado += $val;
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="h4 fw-bold text-dark mb-1">Controle Financeiro de Pagamentos</h2>
        <p class="text-muted small mb-0">Gerencie as entradas financeiras, baixas e saldos vinculados às vendas.</p>
    </div>
    <div>
        <a href="<?= url('pagamentos/cadastrar.php') ?>" class="btn btn-primary">
            <i class="bi bi-cash-stack me-1"></i> Novo Pagamento
        </a>
    </div>
</div>

<!-- Filtros de Pesquisa -->
<div class="card-app mb-4">
    <div class="card-header bg-light py-2">
        <span class="fw-semibold text-secondary small">
            <i class="bi bi-funnel me-1"></i> Filtros de Pagamentos
        </span>
    </div>
    <div class="card-body">
        <form method="get" action="<?= url('pagamentos/index.php') ?>" class="row g-3">
            <div class="col-12 col-md-3">
                <label for="filtroBusca" class="form-label small fw-semibold text-secondary">CLIENTE OU Nº DA VENDA</label>
                <input type="text" class="form-control" id="filtroBusca" name="busca"
                       placeholder="Nome do cliente ou ID da venda..." value="<?= e($filtroBusca) ?>">
            </div>

            <div class="col-6 col-md-2">
                <label for="filtroForma" class="form-label small fw-semibold text-secondary">FORMA DE PAGTO</label>
                <select class="form-select" id="filtroForma" name="forma_pagamento">
                    <option value="">Todas</option>
                    <option value="pix"           <?= $filtroForma === 'pix' ? 'selected' : '' ?>>PIX</option>
                    <option value="transferência" <?= $filtroForma === 'transferência' ? 'selected' : '' ?>>Transferência</option>
                    <option value="cartão"        <?= $filtroForma === 'cartão' ? 'selected' : '' ?>>Cartão</option>
                    <option value="dinheiro"      <?= $filtroForma === 'dinheiro' ? 'selected' : '' ?>>Dinheiro</option>
                </select>
            </div>

            <div class="col-6 col-md-2">
                <label for="filtroStatus" class="form-label small fw-semibold text-secondary">STATUS</label>
                <select class="form-select" id="filtroStatus" name="status">
                    <option value="">Todos</option>
                    <option value="pago"      <?= $filtroStatus === 'pago' ? 'selected' : '' ?>>Pago</option>
                    <option value="pendente"  <?= $filtroStatus === 'pendente' ? 'selected' : '' ?>>Pendente</option>
                    <option value="cancelado" <?= $filtroStatus === 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                </select>
            </div>

            <div class="col-6 col-md-2">
                <label for="filtroInicio" class="form-label small fw-semibold text-secondary">DATA INICIAL</label>
                <input type="date" class="form-control" id="filtroInicio" name="data_inicio" value="<?= e($filtroInicio) ?>">
            </div>

            <div class="col-6 col-md-2">
                <label for="filtroFim" class="form-label small fw-semibold text-secondary">DATA FINAL</label>
                <input type="date" class="form-control" id="filtroFim" name="data_fim" value="<?= e($filtroFim) ?>">
            </div>

            <div class="col-12 col-md-1 d-flex align-items-end">
                <button type="submit" class="btn btn-navy w-100" title="Aplicar Filtros">
                    <i class="bi bi-search"></i>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Resumo dos Valores Filtrados -->
<div class="row g-3 mb-4">
    <div class="col-12 col-md-4">
        <div class="p-3 bg-white border rounded shadow-sm">
            <div class="text-secondary small fw-semibold text-uppercase">Total Recebido (Pago)</div>
            <div class="fs-4 fw-bold text-success"><?= formatarMoeda($totalPago) ?></div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="p-3 bg-white border rounded shadow-sm">
            <div class="text-secondary small fw-semibold text-uppercase">Total Pendente (A Receber)</div>
            <div class="fs-4 fw-bold text-warning"><?= formatarMoeda($totalPendente) ?></div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="p-3 bg-white border rounded shadow-sm">
            <div class="text-secondary small fw-semibold text-uppercase">Total Cancelado</div>
            <div class="fs-4 fw-bold text-danger"><?= formatarMoeda($totalCancelado) ?></div>
        </div>
    </div>
</div>

<!-- Tabela de Pagamentos -->
<div class="card-app">
    <div class="card-header">
        <h3 class="card-title">
            <i class="bi bi-cash-coin text-primary"></i> Lançamentos Financeiros
        </h3>
        <span class="badge bg-light text-secondary border">Total: <?= count($pagamentos) ?></span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-app align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 70px;">#</th>
                        <th>Data / Hora</th>
                        <th>Venda</th>
                        <th>Cliente</th>
                        <th>Forma de Pagamento</th>
                        <th>Valor</th>
                        <th>Status</th>
                        <th class="text-end" style="width: 120px;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pagamentos)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                <i class="bi bi-inbox fs-3 d-block mb-2 text-secondary"></i>
                                Nenhum pagamento encontrado para os critérios pesquisados.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($pagamentos as $p): ?>
                            <tr>
                                <td class="fw-bold text-muted">#<?= $p['id_pagamento'] ?></td>
                                <td class="small"><?= formatarDataHora($p['data_pagamento']) ?></td>
                                <td>
                                    <a href="<?= url('vendas/visualizar.php?id=' . $p['id_venda']) ?>" class="fw-bold text-decoration-none">
                                        Venda #<?= $p['id_venda'] ?>
                                    </a>
                                    <small class="text-muted d-block"><?= formatarMoeda($p['venda_total']) ?></small>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= e($p['cliente_nome']) ?></div>
                                    <small class="text-muted">Vendedor: <?= e($p['vendedor_nome']) ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border text-uppercase">
                                        <i class="bi bi-wallet2 me-1"></i><?= e($p['forma_pagamento']) ?>
                                    </span>
                                </td>
                                <td class="fw-bold text-success fs-6"><?= formatarMoeda($p['valor']) ?></td>
                                <td><?= badgeStatus($p['status']) ?></td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= url('vendas/visualizar.php?id=' . $p['id_venda']) ?>"
                                           class="btn btn-outline-primary" title="Ver Venda">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <?php if ($p['status'] !== 'cancelado'): ?>
                                            <form method="post" action="<?= url('pagamentos/cancelar.php') ?>"
                                                  class="form-confirmar d-inline"
                                                  data-mensagem="Deseja realmente cancelar este pagamento de <?= formatarMoeda($p['valor']) ?>?">
                                                <?= campoCSRF() ?>
                                                <input type="hidden" name="id_pagamento" value="<?= $p['id_pagamento'] ?>">
                                                <button type="submit" class="btn btn-outline-danger" title="Cancelar Pagamento">
                                                    <i class="bi bi-x-circle"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
