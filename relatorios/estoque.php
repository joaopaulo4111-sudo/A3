<?php
/**
 * ============================================================
 *  AutoGestão - Relatório Consolidado de Estoque
 *  Arquivo: relatorios/estoque.php
 * ============================================================
 *  Implementa:
 *   - RF14: Relatório consolidado de estoque de veículos.
 *   - Filtros por marca, tipo (novo/usado) e situação.
 *   - Métricas: Total em catálogo, disponíveis, valor patrimonial do
 *     estoque disponível e ticket médio de preço.
 *   - Exportação para PDF e layout otimizado para impressão.
 * ============================================================
 */

require_once __DIR__ . '/../includes/auth.php';
exigirLogin();

$tituloPagina = 'Relatório de Estoque';
$paginaAtiva  = 'rel_estoque';

$pdo = conectar();

// Filtros do Relatório (GET)
$filtroMarca    = get('marca');
$filtroTipo     = get('tipo');
$filtroSituacao = get('situacao');

$sql = "
    SELECT ve.id_veiculo, ve.modelo, ve.ano_fabricacao, ve.ano_modelo,
           ve.tipo, ve.cor, ve.placa, ve.quilometragem, ve.preco, ve.situacao,
           m.nome AS marca_nome
      FROM veiculos ve
      JOIN marcas m ON m.id_marca = ve.id_marca
     WHERE 1 = 1
";
$params = [];

if ($filtroMarca !== '') {
    $sql .= " AND ve.id_marca = :marca";
    $params[':marca'] = (int) $filtroMarca;
}

if ($filtroTipo !== '') {
    $sql .= " AND ve.tipo = :tipo";
    $params[':tipo'] = $filtroTipo;
}

if ($filtroSituacao !== '') {
    $sql .= " AND ve.situacao = :situacao";
    $params[':situacao'] = $filtroSituacao;
}

$sql .= " ORDER BY ve.situacao ASC, m.nome ASC, ve.modelo ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$veiculos = $stmt->fetchAll();

// Métricas Consolidadas do Estoque
$totalGeral      = count($veiculos);
$totalDisponivel = 0;
$totalReservado  = 0;
$totalVendido    = 0;
$patrimonioDisp  = 0.0;

foreach ($veiculos as $v) {
    $pr = (float) $v['preco'];
    if ($v['situacao'] === 'disponível') {
        $totalDisponivel++;
        $patrimonioDisp += $pr;
    } elseif ($v['situacao'] === 'reservado') {
        $totalReservado++;
    } elseif ($v['situacao'] === 'vendido') {
        $totalVendido++;
    }
}
$precoMedioDisp = ($totalDisponivel > 0) ? ($patrimonioDisp / $totalDisponivel) : 0.0;

// Lista de marcas para o filtro
$marcas = $pdo->query("SELECT id_marca, nome FROM marcas ORDER BY nome ASC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="h4 fw-bold text-dark mb-1">Relatório Patrimonial de Estoque</h2>
        <p class="text-muted small mb-0">Visão analítica dos veículos novos, usados, disponíveis e comercializados.</p>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()">
            <i class="bi bi-printer me-1"></i> Imprimir
        </button>
        <a href="<?= url('relatorios/exportar_pdf.php?tipo=estoque&' . http_build_query($_GET)) ?>"
           class="btn btn-danger btn-sm" target="_blank">
            <i class="bi bi-file-earmark-pdf me-1"></i> Exportar em PDF
        </a>
    </div>
</div>

<!-- Filtros de Consulta do Estoque -->
<div class="card-app mb-4">
    <div class="card-header bg-light py-2">
        <span class="fw-semibold text-secondary small">
            <i class="bi bi-funnel me-1"></i> Parâmetros do Relatório
        </span>
    </div>
    <div class="card-body">
        <form method="get" action="<?= url('relatorios/estoque.php') ?>" class="row g-3">
            <div class="col-12 col-md-4">
                <label for="filtroMarca" class="form-label small fw-semibold text-secondary">MARCA / MONTADORA</label>
                <select class="form-select" id="filtroMarca" name="marca">
                    <option value="">Todas as marcas</option>
                    <?php foreach ($marcas as $m): ?>
                        <option value="<?= $m['id_marca'] ?>" <?= (string) $filtroMarca === (string) $m['id_marca'] ? 'selected' : '' ?>>
                            <?= e($m['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-6 col-md-4">
                <label for="filtroTipo" class="form-label small fw-semibold text-secondary">TIPO</label>
                <select class="form-select" id="filtroTipo" name="tipo">
                    <option value="">Todos os tipos</option>
                    <option value="novo" <?= $filtroTipo === 'novo' ? 'selected' : '' ?>>Novo (0 km)</option>
                    <option value="usado" <?= $filtroTipo === 'usado' ? 'selected' : '' ?>>Usado</option>
                </select>
            </div>

            <div class="col-6 col-md-4">
                <label for="filtroSituacao" class="form-label small fw-semibold text-secondary">SITUAÇÃO</label>
                <select class="form-select" id="filtroSituacao" name="situacao">
                    <option value="">Todas as situações</option>
                    <option value="disponível" <?= $filtroSituacao === 'disponível' ? 'selected' : '' ?>>Disponível</option>
                    <option value="reservado"  <?= $filtroSituacao === 'reservado' ? 'selected' : '' ?>>Reservado</option>
                    <option value="vendido"    <?= $filtroSituacao === 'vendido' ? 'selected' : '' ?>>Vendido</option>
                </select>
            </div>

            <div class="col-12 d-flex justify-content-end gap-2 pt-2 border-top">
                <?php if ($filtroMarca !== '' || $filtroTipo !== '' || $filtroSituacao !== ''): ?>
                    <a href="<?= url('relatorios/estoque.php') ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-x-circle me-1"></i> Limpar Filtros
                    </a>
                <?php endif; ?>
                <button type="submit" class="btn btn-navy btn-sm">
                    <i class="bi bi-funnel me-1"></i> Filtrar Estoque
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Indicadores de Estoque (RF14) -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card-stat">
            <div>
                <div class="card-stat-title">Total no Catálogo</div>
                <div class="card-stat-value"><?= $totalGeral ?></div>
                <small class="text-muted">Veículos listados</small>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="card-stat">
            <div>
                <div class="card-stat-title">Disponíveis (Pronta-Entrega)</div>
                <div class="card-stat-value text-success"><?= $totalDisponivel ?></div>
                <small class="text-muted">Aguardando comprador</small>
            </div>
        </div>
    </div>

    <div class="col-12 col-md-3">
        <div class="card-stat">
            <div>
                <div class="card-stat-title">Valor Patrimonial Disponível</div>
                <div class="card-stat-value text-primary fs-5"><?= formatarMoeda($patrimonioDisp) ?></div>
                <small class="text-muted">Capital em veículos livres</small>
            </div>
        </div>
    </div>

    <div class="col-12 col-md-3">
        <div class="card-stat">
            <div>
                <div class="card-stat-title">Preço Médio (Disponíveis)</div>
                <div class="card-stat-value text-dark fs-5"><?= formatarMoeda($precoMedioDisp) ?></div>
                <small class="text-muted">Ticket médio por unidade</small>
            </div>
        </div>
    </div>
</div>

<!-- Tabela Analítica de Veículos -->
<div class="card-app mb-4">
    <div class="card-header">
        <h3 class="card-title">
            <i class="bi bi-car-front text-primary"></i> Detalhamento dos Veículos em Estoque
        </h3>
        <span class="badge bg-light text-secondary border">Total: <?= count($veiculos) ?></span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-app align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 60px;">#</th>
                        <th>Marca / Modelo</th>
                        <th>Ano</th>
                        <th>Tipo</th>
                        <th>Cor</th>
                        <th>Placa</th>
                        <th>Quilometragem</th>
                        <th class="text-end">Preço</th>
                        <th>Situação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($veiculos)): ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">Nenhum veículo encontrado para os filtros selecionados.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($veiculos as $v): ?>
                            <tr>
                                <td class="fw-bold text-muted">#<?= $v['id_veiculo'] ?></td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= e($v['modelo']) ?></div>
                                    <small class="text-muted"><?= e($v['marca_nome']) ?></small>
                                </td>
                                <td class="small"><?= e($v['ano_fabricacao']) ?>/<?= e($v['ano_modelo']) ?></td>
                                <td><?= badgeStatus($v['tipo']) ?></td>
                                <td><?= e($v['cor']) ?></td>
                                <td class="font-monospace small"><?= e($v['placa'] ?? '0 km') ?></td>
                                <td class="small text-muted"><?= formatarKm($v['quilometragem']) ?></td>
                                <td class="text-end fw-bold text-primary"><?= formatarMoeda($v['preco']) ?></td>
                                <td><?= badgeStatus($v['situacao']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
