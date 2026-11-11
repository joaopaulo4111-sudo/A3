<?php
/**
 * ============================================================
 *  AutoGestão - Módulo de Veículos (Listagem e Filtros)
 *  Arquivo: veiculos/index.php
 * ============================================================
 *  Implementa:
 *   - RF03: Acesso protegido por autenticação.
 *   - RF07: Listagem e controle do estoque de veículos novos e usados.
 *   - RF12 / HU10: Filtros avançados combinados (marca, modelo, ano, tipo e situação).
 *   - RN04: Exibição visual da situação do veículo no estoque.
 * ============================================================
 */

require_once __DIR__ . '/../includes/auth.php';
exigirLogin();

$tituloPagina = 'Estoque de Veículos';
$paginaAtiva  = 'veiculos';

$pdo = conectar();

// Filtros avançados (GET)
$filtroMarca    = get('marca');
$filtroModelo   = get('modelo');
$filtroAno      = get('ano');
$filtroTipo     = get('tipo');
$filtroSituacao = get('situacao');

// Montagem dinâmica da consulta com Prepared Statements (RF12)
$sql = "SELECT ve.id_veiculo, ve.modelo, ve.ano_fabricacao, ve.ano_modelo,
               ve.tipo, ve.cor, ve.placa, ve.quilometragem, ve.preco,
               ve.situacao, ve.descricao,
               m.id_marca, m.nome AS marca_nome
          FROM veiculos ve
          JOIN marcas m ON m.id_marca = ve.id_marca
         WHERE 1 = 1";
$params = [];

if ($filtroMarca !== '') {
    $sql .= " AND ve.id_marca = :marca";
    $params[':marca'] = (int) $filtroMarca;
}

if ($filtroModelo !== '') {
    $sql .= " AND ve.modelo LIKE :modelo";
    $params[':modelo'] = '%' . $filtroModelo . '%';
}

if ($filtroAno !== '') {
    $sql .= " AND (ve.ano_fabricacao = :ano OR ve.ano_modelo = :ano)";
    $params[':ano'] = (int) $filtroAno;
}

if ($filtroTipo !== '') {
    $sql .= " AND ve.tipo = :tipo";
    $params[':tipo'] = $filtroTipo;
}

if ($filtroSituacao !== '') {
    $sql .= " AND ve.situacao = :situacao";
    $params[':situacao'] = $filtroSituacao;
}

$sql .= " ORDER BY ve.id_veiculo DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$veiculos = $stmt->fetchAll();

// Lista de marcas para o select de filtro
$marcas = $pdo->query("SELECT id_marca, nome FROM marcas ORDER BY nome ASC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="h4 fw-bold text-dark mb-1">Controle de Estoque</h2>
        <p class="text-muted small mb-0">Gerencie os veículos novos e usados disponíveis para comercialização.</p>
    </div>
    <div>
        <a href="<?= url('veiculos/cadastrar.php') ?>" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i> Cadastrar Veículo
        </a>
    </div>
</div>

<!-- Filtros Avançados de Pesquisa (RF12 / HU10) -->
<div class="card-app mb-4">
    <div class="card-header bg-light py-2">
        <span class="fw-semibold text-secondary small">
            <i class="bi bi-funnel me-1"></i> Filtros de Busca Avançada
        </span>
    </div>
    <div class="card-body">
        <form method="get" action="<?= url('veiculos/index.php') ?>" class="row g-3">
            <div class="col-12 col-md-3">
                <label for="filtroMarca" class="form-label small fw-semibold text-secondary">MARCA</label>
                <select class="form-select" id="filtroMarca" name="marca">
                    <option value="">Todas as marcas</option>
                    <?php foreach ($marcas as $m): ?>
                        <option value="<?= $m['id_marca'] ?>" <?= (string) $filtroMarca === (string) $m['id_marca'] ? 'selected' : '' ?>>
                            <?= e($m['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12 col-md-3">
                <label for="filtroModelo" class="form-label small fw-semibold text-secondary">MODELO</label>
                <input type="text" class="form-control" id="filtroModelo" name="modelo"
                       placeholder="Ex.: Corolla, Civic..." value="<?= e($filtroModelo) ?>">
            </div>

            <div class="col-6 col-md-2">
                <label for="filtroAno" class="form-label small fw-semibold text-secondary">ANO</label>
                <input type="number" class="form-control" id="filtroAno" name="ano"
                       placeholder="Ex.: 2024" value="<?= e($filtroAno) ?>">
            </div>

            <div class="col-6 col-md-2">
                <label for="filtroTipo" class="form-label small fw-semibold text-secondary">TIPO</label>
                <select class="form-select" id="filtroTipo" name="tipo">
                    <option value="">Todos</option>
                    <option value="novo" <?= $filtroTipo === 'novo' ? 'selected' : '' ?>>Novo (0 km)</option>
                    <option value="usado" <?= $filtroTipo === 'usado' ? 'selected' : '' ?>>Usado</option>
                </select>
            </div>

            <div class="col-12 col-md-2">
                <label for="filtroSituacao" class="form-label small fw-semibold text-secondary">SITUAÇÃO</label>
                <select class="form-select" id="filtroSituacao" name="situacao">
                    <option value="">Todas</option>
                    <option value="disponível" <?= $filtroSituacao === 'disponível' ? 'selected' : '' ?>>Disponível</option>
                    <option value="reservado"  <?= $filtroSituacao === 'reservado' ? 'selected' : '' ?>>Reservado</option>
                    <option value="vendido"    <?= $filtroSituacao === 'vendido' ? 'selected' : '' ?>>Vendido</option>
                </select>
            </div>

            <div class="col-12 d-flex justify-content-end gap-2 pt-2 border-top">
                <?php if ($filtroMarca !== '' || $filtroModelo !== '' || $filtroAno !== '' || $filtroTipo !== '' || $filtroSituacao !== ''): ?>
                    <a href="<?= url('veiculos/index.php') ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-x-circle me-1"></i> Limpar Filtros
                    </a>
                <?php endif; ?>
                <button type="submit" class="btn btn-navy btn-sm">
                    <i class="bi bi-search me-1"></i> Aplicar Filtros
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Tabela de Veículos -->
<div class="card-app">
    <div class="card-header">
        <h3 class="card-title">
            <i class="bi bi-car-front text-primary"></i> Veículos no Catálogo
        </h3>
        <span class="badge bg-light text-secondary border">Total: <?= count($veiculos) ?></span>
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
                        <th>Preço</th>
                        <th>Situação</th>
                        <th class="text-end" style="width: 140px;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($veiculos)): ?>
                        <tr>
                            <td colspan="10" class="text-center text-muted py-4">
                                <i class="bi bi-inbox fs-3 d-block mb-2 text-secondary"></i>
                                Nenhum veículo encontrado para os filtros selecionados.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($veiculos as $v): ?>
                            <tr>
                                <td class="fw-bold text-muted">#<?= $v['id_veiculo'] ?></td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= e($v['modelo']) ?></div>
                                    <small class="text-muted">Cor: <?= e($v['cor']) ?></small>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?= e($v['marca_nome']) ?></span></td>
                                <td class="small"><?= e($v['ano_fabricacao']) ?>/<?= e($v['ano_modelo']) ?></td>
                                <td><?= badgeStatus($v['tipo']) ?></td>
                                <td>
                                    <?php if (!empty($v['placa'])): ?>
                                        <span class="badge bg-dark-subtle text-dark font-monospace"><?= e($v['placa']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted small">0 km</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted"><?= formatarKm($v['quilometragem']) ?></td>
                                <td class="fw-bold text-primary"><?= formatarMoeda($v['preco']) ?></td>
                                <td><?= badgeStatus($v['situacao']) ?></td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= url('veiculos/editar.php?id=' . $v['id_veiculo']) ?>"
                                           class="btn btn-outline-primary" title="Editar Veículo">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form method="post" action="<?= url('veiculos/excluir.php') ?>"
                                              class="form-confirmar d-inline"
                                              data-mensagem="Deseja realmente excluir o veículo <?= e($v['modelo']) ?>?">
                                            <?= campoCSRF() ?>
                                            <input type="hidden" name="id_veiculo" value="<?= $v['id_veiculo'] ?>">
                                            <button type="submit" class="btn btn-outline-danger" title="Excluir Veículo">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
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
