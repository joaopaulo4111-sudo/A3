<?php
/**
 * ============================================================
 *  AutoGestão - Módulo de Marcas (Listagem e Pesquisa)
 *  Arquivo: marcas/index.php
 * ============================================================
 *  Implementa:
 *   - RF03: Acesso protegido por autenticação.
 *   - RF06: Listagem completa de marcas fabricantes.
 *   - HU04: Organização de veículos por fabricante.
 *   - Contagem de veículos por marca no estoque.
 * ============================================================
 */

require_once __DIR__ . '/../includes/auth.php';
exigirLogin();

$tituloPagina = 'Marcas';
$paginaAtiva  = 'marcas';

$pdo = conectar();

// Filtro de busca por nome (GET)
$busca = get('busca');

$sql = "SELECT m.id_marca, m.nome, m.pais_origem,
               COUNT(v.id_veiculo) AS total_veiculos
          FROM marcas m
          LEFT JOIN veiculos v ON v.id_marca = m.id_marca
         WHERE 1 = 1";
$params = [];

if ($busca !== '') {
    $sql .= " AND m.nome LIKE :busca";
    $params[':busca'] = '%' . $busca . '%';
}

$sql .= " GROUP BY m.id_marca, m.nome, m.pais_origem
          ORDER BY m.nome ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$marcas = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="h4 fw-bold text-dark mb-1">Fabricantes e Marcas</h2>
        <p class="text-muted small mb-0">Cadastre e gerencie as montadoras dos veículos comercializados.</p>
    </div>
    <div>
        <a href="<?= url('marcas/cadastrar.php') ?>" class="btn btn-primary">
            <i class="bi bi-tag me-1"></i> Nova Marca
        </a>
    </div>
</div>

<!-- Filtro de Pesquisa -->
<div class="card-app mb-4">
    <div class="card-body">
        <form method="get" action="<?= url('marcas/index.php') ?>" class="row g-3 align-items-end">
            <div class="col-12 col-md-8">
                <label for="filtroBusca" class="form-label small fw-semibold text-secondary">PESQUISAR MARCA</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" class="form-control" id="filtroBusca" name="busca"
                           placeholder="Digite o nome da montadora (ex.: Toyota, Honda...)" value="<?= e($busca) ?>">
                </div>
            </div>

            <div class="col-12 col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-navy w-100">
                    <i class="bi bi-funnel me-1"></i> Filtrar
                </button>
                <?php if ($busca !== ''): ?>
                    <a href="<?= url('marcas/index.php') ?>" class="btn btn-outline-secondary" title="Limpar Filtro">
                        <i class="bi bi-x-lg"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Listagem de Marcas -->
<div class="card-app">
    <div class="card-header">
        <h3 class="card-title">
            <i class="bi bi-tags text-primary"></i> Marcas Cadastradas
        </h3>
        <span class="badge bg-light text-secondary border">Total: <?= count($marcas) ?></span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-app align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 70px;">#</th>
                        <th>Marca / Montadora</th>
                        <th>País de Origem</th>
                        <th>Veículos no Estoque</th>
                        <th class="text-end" style="width: 140px;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($marcas)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                                <i class="bi bi-inbox fs-3 d-block mb-2 text-secondary"></i>
                                Nenhuma marca encontrada.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($marcas as $m): ?>
                            <tr>
                                <td class="fw-bold text-muted">#<?= $m['id_marca'] ?></td>
                                <td class="fw-semibold text-dark fs-6"><?= e($m['nome']) ?></td>
                                <td>
                                    <?php if (!empty($m['pais_origem'])): ?>
                                        <i class="bi bi-geo-alt text-muted me-1"></i><?= e($m['pais_origem']) ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($m['total_veiculos'] > 0): ?>
                                        <a href="<?= url('veiculos/index.php?marca=' . $m['id_marca']) ?>"
                                           class="badge text-bg-primary text-decoration-none" title="Ver veículos desta marca">
                                            <?= $m['total_veiculos'] ?> veículo(s)
                                        </a>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border">0 veículos</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= url('marcas/editar.php?id=' . $m['id_marca']) ?>"
                                           class="btn btn-outline-primary" title="Editar Marca">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form method="post" action="<?= url('marcas/excluir.php') ?>"
                                              class="form-confirmar d-inline"
                                              data-mensagem="Deseja realmente excluir a marca <?= e($m['nome']) ?>?">
                                            <?= campoCSRF() ?>
                                            <input type="hidden" name="id_marca" value="<?= $m['id_marca'] ?>">
                                            <button type="submit" class="btn btn-outline-danger" title="Excluir Marca">
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
