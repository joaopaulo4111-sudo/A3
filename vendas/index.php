<?php
/**
 * ============================================================
 *  AutoGestão - Módulo de Vendas (Listagem e Filtros)
 *  Arquivo: vendas/index.php
 * ============================================================
 *  Implementa:
 *   - RF03: Acesso protegido por autenticação.
 *   - RF08 / RF09: Consulta e acompanhamento de vendas comerciais.
 *   - RF12 / HU11: Filtros avançados por período, funcionário e situação.
 *   - Integração com pagamentos para exibir saldo recebido e pendente.
 * ============================================================
 */

require_once __DIR__ . '/../includes/auth.php';
exigirLogin();

$tituloPagina = 'Vendas';
$paginaAtiva  = 'vendas';

$pdo = conectar();

// Filtros avançados (GET)
$filtroDataInicio  = get('data_inicio');
$filtroDataFim     = get('data_fim');
$filtroFuncionario = get('id_funcionario');
$filtroStatus      = get('status');

// Montagem dinâmica da consulta com Prepared Statements (RF12 / HU11)
$sql = "
    SELECT v.id_venda, v.data_venda, v.valor_total, v.desconto, v.status, v.observacao,
           c.nome AS cliente_nome, c.telefone AS cliente_telefone,
           f.nome AS vendedor_nome,
           GROUP_CONCAT(CONCAT(m.nome, ' ', ve.modelo) SEPARATOR ', ') AS veiculos_nomes,
           COUNT(vv.id_veiculo) AS qtd_veiculos,
           COALESCE(SUM(CASE WHEN p.status = 'pago' THEN p.valor END), 0) AS total_pago
      FROM vendas v
      JOIN clientes c            ON c.id_cliente = v.id_cliente
      JOIN funcionarios f        ON f.id_funcionario = v.id_funcionario
      LEFT JOIN venda_veiculos vv ON vv.id_venda = v.id_venda
      LEFT JOIN veiculos ve      ON ve.id_veiculo = vv.id_veiculo
      LEFT JOIN marcas m         ON m.id_marca = ve.id_marca
      LEFT JOIN pagamentos p     ON p.id_venda = v.id_venda
     WHERE 1 = 1
";
$params = [];

if ($filtroDataInicio !== '') {
    $sql .= " AND DATE(v.data_venda) >= :data_inicio";
    $params[':data_inicio'] = $filtroDataInicio;
}

if ($filtroDataFim !== '') {
    $sql .= " AND DATE(v.data_venda) <= :data_fim";
    $params[':data_fim'] = $filtroDataFim;
}

if ($filtroFuncionario !== '') {
    $sql .= " AND v.id_funcionario = :id_funcionario";
    $params[':id_funcionario'] = (int) $filtroFuncionario;
}

if ($filtroStatus !== '') {
    $sql .= " AND v.status = :status";
    $params[':status'] = $filtroStatus;
}

$sql .= " GROUP BY v.id_venda, v.data_venda, v.valor_total, v.desconto, v.status, v.observacao, c.nome, c.telefone, f.nome
          ORDER BY v.data_venda DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$vendas = $stmt->fetchAll();

// Totais consolidados da listagem filtrada
$somaFaturamento = 0.0;
$somaRecebido    = 0.0;
foreach ($vendas as $v) {
    if ($v['status'] !== 'cancelada') {
        $somaFaturamento += (float) $v['valor_total'];
        $somaRecebido    += (float) $v['total_pago'];
    }
}
$saldoPendente = max(0, $somaFaturamento - $somaRecebido);

// Lista de vendedores para o select de filtro
$vendedores = $pdo->query("SELECT id_funcionario, nome FROM funcionarios ORDER BY nome ASC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="h4 fw-bold text-dark mb-1">Registro de Vendas</h2>
        <p class="text-muted small mb-0">Consulte, registre e acompanhe as vendas de veículos e recebimentos.</p>
    </div>
    <div>
        <a href="<?= url('vendas/cadastrar.php') ?>" class="btn btn-primary">
            <i class="bi bi-cart-plus me-1"></i> Nova Venda
        </a>
    </div>
</div>

<!-- Filtros Avançados de Pesquisa (RF12 / HU11) -->
<div class="card-app mb-4">
    <div class="card-header bg-light py-2">
        <span class="fw-semibold text-secondary small">
            <i class="bi bi-funnel me-1"></i> Filtros de Vendas
        </span>
    </div>
    <div class="card-body">
        <form method="get" action="<?= url('vendas/index.php') ?>" class="row g-3">
            <div class="col-6 col-md-3">
                <label for="filtroDataInicio" class="form-label small fw-semibold text-secondary">DATA INICIAL</label>
                <input type="date" class="form-control" id="filtroDataInicio" name="data_inicio" value="<?= e($filtroDataInicio) ?>">
            </div>

            <div class="col-6 col-md-3">
                <label for="filtroDataFim" class="form-label small fw-semibold text-secondary">DATA FINAL</label>
                <input type="date" class="form-control" id="filtroDataFim" name="data_fim" value="<?= e($filtroDataFim) ?>">
            </div>

            <div class="col-12 col-md-3">
                <label for="filtroFuncionario" class="form-label small fw-semibold text-secondary">VENDEDOR RESPONSÁVEL</label>
                <select class="form-select" id="filtroFuncionario" name="id_funcionario">
                    <option value="">Todos os vendedores</option>
                    <?php foreach ($vendedores as $vend): ?>
                        <option value="<?= $vend['id_funcionario'] ?>" <?= (string) $filtroFuncionario === (string) $vend['id_funcionario'] ? 'selected' : '' ?>>
                            <?= e($vend['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12 col-md-3">
                <label for="filtroStatus" class="form-label small fw-semibold text-secondary">SITUAÇÃO DA VENDA</label>
                <select class="form-select" id="filtroStatus" name="status">
                    <option value="">Todas</option>
                    <option value="concluída" <?= $filtroStatus === 'concluída' ? 'selected' : '' ?>>Concluída</option>
                    <option value="pendente"  <?= $filtroStatus === 'pendente' ? 'selected' : '' ?>>Pendente</option>
                    <option value="cancelada" <?= $filtroStatus === 'cancelada' ? 'selected' : '' ?>>Cancelada</option>
                </select>
            </div>

            <div class="col-12 d-flex justify-content-end gap-2 pt-2 border-top">
                <?php if ($filtroDataInicio !== '' || $filtroDataFim !== '' || $filtroFuncionario !== '' || $filtroStatus !== ''): ?>
                    <a href="<?= url('vendas/index.php') ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-x-circle me-1"></i> Limpar Filtros
                    </a>
                <?php endif; ?>
                <button type="submit" class="btn btn-navy btn-sm">
                    <i class="bi bi-search me-1"></i> Filtrar Vendas
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Cards de Resumo Rápido da Consulta -->
<div class="row g-3 mb-4">
    <div class="col-12 col-md-4">
        <div class="p-3 bg-white border rounded shadow-sm">
            <div class="text-secondary small fw-semibold text-uppercase">Vendas Listadas</div>
            <div class="fs-4 fw-bold text-dark"><?= count($vendas) ?> negócio(s)</div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="p-3 bg-white border rounded shadow-sm">
            <div class="text-secondary small fw-semibold text-uppercase">Total Negociado</div>
            <div class="fs-4 fw-bold text-primary"><?= formatarMoeda($somaFaturamento) ?></div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="p-3 bg-white border rounded shadow-sm">
            <div class="text-secondary small fw-semibold text-uppercase">Total Recebido</div>
            <div class="fs-4 fw-bold text-success"><?= formatarMoeda($somaRecebido) ?></div>
        </div>
    </div>
</div>

<!-- Listagem de Vendas -->
<div class="card-app">
    <div class="card-header">
        <h3 class="card-title">
            <i class="bi bi-cart-check text-primary"></i> Histórico de Vendas
        </h3>
        <span class="badge bg-light text-secondary border">Total: <?= count($vendas) ?></span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-app align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 70px;">#</th>
                        <th>Data</th>
                        <th>Cliente</th>
                        <th>Vendedor</th>
                        <th>Veículo(s)</th>
                        <th>Valor Total</th>
                        <th>Pago / Saldo</th>
                        <th>Status</th>
                        <th class="text-end" style="width: 140px;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($vendas)): ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">
                                <i class="bi bi-inbox fs-3 d-block mb-2 text-secondary"></i>
                                Nenhuma venda encontrada para os critérios informados.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($vendas as $v): ?>
                            <?php
                            $total    = (float) $v['valor_total'];
                            $pago     = (float) $v['total_pago'];
                            $pendente = max(0, $total - $pago);
                            ?>
                            <tr>
                                <td class="fw-bold text-muted">#<?= $v['id_venda'] ?></td>
                                <td class="small"><?= formatarDataHora($v['data_venda']) ?></td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= e($v['cliente_nome']) ?></div>
                                    <small class="text-muted"><?= e($v['cliente_telefone']) ?></small>
                                </td>
                                <td class="small text-muted"><?= e($v['vendedor_nome']) ?></td>
                                <td>
                                    <div class="small fw-semibold text-truncate" style="max-width: 220px;"
                                         title="<?= e($v['veiculos_nomes'] ?? 'Veículo liberado') ?>">
                                        <?= e($v['veiculos_nomes'] ?? 'Veículo liberado') ?>
                                    </div>
                                    <small class="text-muted"><?= $v['qtd_veiculos'] ?> item(ns)</small>
                                </td>
                                <td class="fw-bold text-dark"><?= formatarMoeda($total) ?></td>
                                <td class="small">
                                    <div class="text-success fw-semibold"><?= formatarMoeda($pago) ?></div>
                                    <?php if ($v['status'] !== 'cancelada' && $pendente > 0): ?>
                                        <div class="text-danger small">Falta: <?= formatarMoeda($pendente) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= badgeStatus($v['status']) ?></td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= url('vendas/visualizar.php?id=' . $v['id_venda']) ?>"
                                           class="btn btn-outline-primary" title="Visualizar Detalhes">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <?php if ($v['status'] !== 'cancelada'): ?>
                                            <form method="post" action="<?= url('vendas/cancelar.php') ?>"
                                                  class="form-confirmar d-inline"
                                                  data-mensagem="Deseja realmente cancelar a venda #<?= $v['id_venda'] ?>? Os veículos serão devolvidos ao estoque disponível.">
                                                <?= campoCSRF() ?>
                                                <input type="hidden" name="id_venda" value="<?= $v['id_venda'] ?>">
                                                <button type="submit" class="btn btn-outline-danger" title="Cancelar Venda">
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
