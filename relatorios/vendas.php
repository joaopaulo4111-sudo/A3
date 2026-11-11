<?php
/**
 * ============================================================
 *  AutoGestão - Relatório Consolidado de Vendas
 *  Arquivo: relatorios/vendas.php
 * ============================================================
 *  Implementa:
 *   - RF14: Relatórios consolidados de desempenho comercial.
 *   - Filtros dinâmicos por período (início e fim), vendedor e status.
 *   - Indicadores consolidados: Quantidade de vendas, veículos vendidos,
 *     faturamento bruto, descontos, total recebido e saldo pendente.
 *   - Quadro de desempenho individual por vendedor.
 *   - Integração com exportação em PDF e impressão limpa.
 * ============================================================
 */

require_once __DIR__ . '/../includes/auth.php';
exigirLogin();

$tituloPagina = 'Relatório de Vendas';
$paginaAtiva  = 'rel_vendas';

$pdo = conectar();

// Filtros do Relatório (GET)
$dataInicio    = get('data_inicio');
$dataFim       = get('data_fim');
$idFuncionario = get('id_funcionario');
$status        = get('status');

// Montagem da query
$sql = "
    SELECT v.id_venda, v.data_venda, v.valor_total, v.desconto, v.status,
           c.nome AS cliente_nome,
           f.id_funcionario, f.nome AS vendedor_nome,
           COUNT(vv.id_veiculo) AS qtd_veiculos,
           COALESCE(SUM(CASE WHEN p.status = 'pago' THEN p.valor END), 0) AS total_pago
      FROM vendas v
      JOIN clientes c            ON c.id_cliente = v.id_cliente
      JOIN funcionarios f        ON f.id_funcionario = v.id_funcionario
      LEFT JOIN venda_veiculos vv ON vv.id_venda = v.id_venda
      LEFT JOIN pagamentos p     ON p.id_venda = v.id_venda
     WHERE 1 = 1
";
$params = [];

if ($dataInicio !== '') {
    $sql .= " AND DATE(v.data_venda) >= :data_inicio";
    $params[':data_inicio'] = $dataInicio;
}

if ($dataFim !== '') {
    $sql .= " AND DATE(v.data_venda) <= :data_fim";
    $params[':data_fim'] = $dataFim;
}

if ($idFuncionario !== '') {
    $sql .= " AND v.id_funcionario = :id_funcionario";
    $params[':id_funcionario'] = (int) $idFuncionario;
}

if ($status !== '') {
    $sql .= " AND v.status = :status";
    $params[':status'] = $status;
}

$sql .= " GROUP BY v.id_venda, v.data_venda, v.valor_total, v.desconto, v.status, c.nome, f.id_funcionario, f.nome
          ORDER BY v.data_venda DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$vendas = $stmt->fetchAll();

// Métricas Consolidadas do Período
$totalVendasQtd    = count($vendas);
$totalVeiculosQtd  = 0;
$faturamentoBruto  = 0.0;
$totalDescontos    = 0.0;
$totalRecebido     = 0.0;
$rankingVendedores = [];

foreach ($vendas as $v) {
    $vQtd   = (int) $v['qtd_veiculos'];
    $valTot = (float) $v['valor_total'];
    $valDes = (float) $v['desconto'];
    $valPag = (float) $v['total_pago'];

    $totalVeiculosQtd += $vQtd;

    if ($v['status'] !== 'cancelada') {
        $faturamentoBruto += $valTot;
        $totalDescontos   += $valDes;
        $totalRecebido    += $valPag;

        // Desempenho por vendedor
        $vNome = $v['vendedor_nome'];
        if (!isset($rankingVendedores[$vNome])) {
            $rankingVendedores[$vNome] = ['qtd' => 0, 'faturamento' => 0.0];
        }
        $rankingVendedores[$vNome]['qtd']++;
        $rankingVendedores[$vNome]['faturamento'] += $valTot;
    }
}
$totalPendente = max(0, $faturamentoBruto - $totalRecebido);

// Ordena os vendedores por faturamento decrescente
uasort($rankingVendedores, fn($a, $b) => $b['faturamento'] <=> $a['faturamento']);

// Lista de vendedores para o filtro
$vendedores = $pdo->query("SELECT id_funcionario, nome FROM funcionarios ORDER BY nome ASC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="h4 fw-bold text-dark mb-1">Relatório Comercial de Vendas</h2>
        <p class="text-muted small mb-0">Consolidação de receitas, descontos aplicados, recebimentos e desempenho da equipe.</p>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()">
            <i class="bi bi-printer me-1"></i> Imprimir
        </button>
        <a href="<?= url('relatorios/exportar_pdf.php?tipo=vendas&' . http_build_query($_GET)) ?>"
           class="btn btn-danger btn-sm" target="_blank">
            <i class="bi bi-file-earmark-pdf me-1"></i> Exportar em PDF
        </a>
    </div>
</div>

<!-- Filtros de Consulta do Relatório -->
<div class="card-app mb-4">
    <div class="card-header bg-light py-2">
        <span class="fw-semibold text-secondary small">
            <i class="bi bi-funnel me-1"></i> Parâmetros do Relatório
        </span>
    </div>
    <div class="card-body">
        <form method="get" action="<?= url('relatorios/vendas.php') ?>" class="row g-3">
            <div class="col-6 col-md-3">
                <label for="filtroDataInicio" class="form-label small fw-semibold text-secondary">DATA INICIAL</label>
                <input type="date" class="form-control" id="filtroDataInicio" name="data_inicio" value="<?= e($dataInicio) ?>">
            </div>

            <div class="col-6 col-md-3">
                <label for="filtroDataFim" class="form-label small fw-semibold text-secondary">DATA FINAL</label>
                <input type="date" class="form-control" id="filtroDataFim" name="data_fim" value="<?= e($dataFim) ?>">
            </div>

            <div class="col-12 col-md-3">
                <label for="filtroFuncionario" class="form-label small fw-semibold text-secondary">VENDEDOR</label>
                <select class="form-select" id="filtroFuncionario" name="id_funcionario">
                    <option value="">Todos os vendedores</option>
                    <?php foreach ($vendedores as $vend): ?>
                        <option value="<?= $vend['id_funcionario'] ?>" <?= (string) $idFuncionario === (string) $vend['id_funcionario'] ? 'selected' : '' ?>>
                            <?= e($vend['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12 col-md-3">
                <label for="filtroStatus" class="form-label small fw-semibold text-secondary">SITUAÇÃO</label>
                <select class="form-select" id="filtroStatus" name="status">
                    <option value="">Todas as situações</option>
                    <option value="concluída" <?= $status === 'concluída' ? 'selected' : '' ?>>Concluída</option>
                    <option value="pendente"  <?= $status === 'pendente' ? 'selected' : '' ?>>Pendente</option>
                    <option value="cancelada" <?= $status === 'cancelada' ? 'selected' : '' ?>>Cancelada</option>
                </select>
            </div>

            <div class="col-12 d-flex justify-content-end gap-2 pt-2 border-top">
                <?php if ($dataInicio !== '' || $dataFim !== '' || $idFuncionario !== '' || $status !== ''): ?>
                    <a href="<?= url('relatorios/vendas.php') ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-x-circle me-1"></i> Limpar Filtros
                    </a>
                <?php endif; ?>
                <button type="submit" class="btn btn-navy btn-sm">
                    <i class="bi bi-funnel me-1"></i> Gerar Relatório
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Indicadores Consolidados (RF14) -->
<div class="row g-3 mb-4">
    <div class="col-6 col-xl-2">
        <div class="card-stat">
            <div>
                <div class="card-stat-title">Vendas</div>
                <div class="card-stat-value"><?= $totalVendasQtd ?></div>
                <small class="text-muted"><?= $totalVeiculosQtd ?> veículo(s)</small>
            </div>
        </div>
    </div>

    <div class="col-6 col-xl-2">
        <div class="card-stat">
            <div>
                <div class="card-stat-title">Faturamento</div>
                <div class="card-stat-value text-primary fs-5"><?= formatarMoeda($faturamentoBruto) ?></div>
                <small class="text-muted">Valor total faturado</small>
            </div>
        </div>
    </div>

    <div class="col-6 col-xl-2">
        <div class="card-stat">
            <div>
                <div class="card-stat-title">Descontos</div>
                <div class="card-stat-value text-danger fs-5"><?= formatarMoeda($totalDescontos) ?></div>
                <small class="text-muted">Concedidos em vendas</small>
            </div>
        </div>
    </div>

    <div class="col-6 col-xl-3">
        <div class="card-stat">
            <div>
                <div class="card-stat-title">Total Recebido</div>
                <div class="card-stat-value text-success fs-5"><?= formatarMoeda($totalRecebido) ?></div>
                <small class="text-muted">Pagamentos liquidados</small>
            </div>
        </div>
    </div>

    <div class="col-6 col-xl-3">
        <div class="card-stat">
            <div>
                <div class="card-stat-title">Valores Pendentes</div>
                <div class="card-stat-value text-warning fs-5"><?= formatarMoeda($totalPendente) ?></div>
                <small class="text-muted">Aguardando recebimento</small>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Tabela Analítica de Vendas -->
    <div class="col-12 col-xl-8">
        <div class="card-app h-100">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="bi bi-table text-primary"></i> Detalhamento Analítico das Vendas
                </h3>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-app align-middle mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Data</th>
                                <th>Cliente</th>
                                <th>Vendedor</th>
                                <th>Total</th>
                                <th>Recebido</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($vendas)): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">Nenhuma venda encontrada no período.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($vendas as $v): ?>
                                    <tr>
                                        <td class="fw-bold">#<?= $v['id_venda'] ?></td>
                                        <td class="small"><?= formatarData($v['data_venda']) ?></td>
                                        <td><?= e($v['cliente_nome']) ?></td>
                                        <td class="small text-muted"><?= e($v['vendedor_nome']) ?></td>
                                        <td class="fw-bold text-dark"><?= formatarMoeda($v['valor_total']) ?></td>
                                        <td class="text-success fw-semibold"><?= formatarMoeda($v['total_pago']) ?></td>
                                        <td><?= badgeStatus($v['status']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Desempenho por Consultor/Vendedor -->
    <div class="col-12 col-xl-4">
        <div class="card-app h-100">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="bi bi-trophy text-primary"></i> Vendas por Vendedor
                </h3>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-app align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Vendedor</th>
                                <th class="text-center">Qtd</th>
                                <th class="text-end">Faturamento</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($rankingVendedores)): ?>
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-4">Sem dados no período.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($rankingVendedores as $nomeVend => $dados): ?>
                                    <tr>
                                        <td class="fw-semibold text-dark"><?= e($nomeVend) ?></td>
                                        <td class="text-center"><span class="badge bg-light text-dark border"><?= $dados['qtd'] ?></span></td>
                                        <td class="text-end fw-bold text-primary"><?= formatarMoeda($dados['faturamento']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
