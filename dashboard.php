<?php
/**
 * ============================================================
 *  Magnata Automóveis - Painel Principal (Dashboard)
 *  Arquivo: dashboard.php
 * ============================================================
 *  Implementa:
 *   - RF03: Bloqueio de acesso para usuários não autenticados.
 *   - RF13: Indicadores em tempo real obtidos diretamente do banco.
 *   - HU12: Visualização executiva de indicadores e atividades recentes.
 * ============================================================
 */

require_once __DIR__ . '/includes/auth.php';
exigirLogin();

$tituloPagina = 'Dashboard';
$paginaAtiva  = 'dashboard';

$pdo = conectar();

// 1. Indicadores do Dashboard
$totalVeiculosDisponiveis = (int) $pdo->query("SELECT COUNT(*) FROM veiculos WHERE situacao = 'disponível'")->fetchColumn();
$totalClientes            = (int) $pdo->query("SELECT COUNT(*) FROM clientes")->fetchColumn();
$totalFuncionarios        = (int) $pdo->query("SELECT COUNT(*) FROM funcionarios")->fetchColumn();
$totalVendasConcluidas    = (int) $pdo->query("SELECT COUNT(*) FROM vendas WHERE status = 'concluída'")->fetchColumn();
$faturamentoTotal         = (float) $pdo->query("SELECT COALESCE(SUM(valor_total), 0) FROM vendas WHERE status = 'concluída'")->fetchColumn();

// 2. Vendas Recentes (últimas 5)
$stmtVendas = $pdo->query("
    SELECT v.id_venda, v.data_venda, v.valor_total, v.status,
           c.nome AS cliente_nome,
           f.nome AS vendedor_nome
      FROM vendas v
      JOIN clientes c     ON c.id_cliente = v.id_cliente
      JOIN funcionarios f ON f.id_funcionario = v.id_funcionario
     ORDER BY v.data_venda DESC
     LIMIT 5
");
$vendasRecentes = $stmtVendas->fetchAll();

// 3. Veículos Adicionados Recentemente (últimos 5)
$stmtVeiculos = $pdo->query("
    SELECT ve.id_veiculo, ve.modelo, ve.ano_fabricacao, ve.ano_modelo,
           ve.tipo, ve.cor, ve.preco, ve.situacao,
           m.nome AS marca_nome
      FROM veiculos ve
      JOIN marcas m ON m.id_marca = ve.id_marca
     ORDER BY ve.id_veiculo DESC
     LIMIT 5
");
$veiculosRecentes = $stmtVeiculos->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="h4 fw-bold text-dark mb-1">Visão Geral &mdash; Magnata Automóveis</h2>
        <p class="text-muted small mb-0">Acompanhe em tempo real os indicadores comerciais e o estoque premium.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('vendas/cadastrar.php') ?>" class="btn btn-primary btn-sm">
            <i class="bi bi-cart-plus me-1"></i> Nova Venda
        </a>
        <a href="<?= url('veiculos/cadastrar.php') ?>" class="btn btn-navy btn-sm">
            <i class="bi bi-plus-circle me-1"></i> Cadastrar Veículo
        </a>
    </div>
</div>

<!-- Cards de Indicadores (RF13) -->
<div class="row g-3 mb-4">
    <!-- Veículos Disponíveis -->
    <div class="col-12 col-sm-6 col-xl">
        <div class="card-stat">
            <div>
                <div class="card-stat-title">Estoque Disponível</div>
                <div class="card-stat-value"><?= $totalVeiculosDisponiveis ?></div>
                <small class="text-muted">Prontos para venda</small>
            </div>
            <div class="card-stat-icon primary">
                <i class="bi bi-car-front-fill"></i>
            </div>
        </div>
    </div>

    <!-- Clientes Cadastrados -->
    <div class="col-12 col-sm-6 col-xl">
        <div class="card-stat">
            <div>
                <div class="card-stat-title">Clientes</div>
                <div class="card-stat-value"><?= $totalClientes ?></div>
                <small class="text-muted">Base cadastrada</small>
            </div>
            <div class="card-stat-icon success">
                <i class="bi bi-people-fill"></i>
            </div>
        </div>
    </div>

    <!-- Vendas Concluídas -->
    <div class="col-12 col-sm-6 col-xl">
        <div class="card-stat">
            <div>
                <div class="card-stat-title">Vendas Concluídas</div>
                <div class="card-stat-value"><?= $totalVendasConcluidas ?></div>
                <small class="text-muted">Negócios fechados</small>
            </div>
            <div class="card-stat-icon warning">
                <i class="bi bi-bag-check-fill"></i>
            </div>
        </div>
    </div>

    <!-- Faturamento Total -->
    <div class="col-12 col-sm-6 col-xl">
        <div class="card-stat">
            <div>
                <div class="card-stat-title">Faturamento Total</div>
                <div class="card-stat-value text-success fs-4"><?= formatarMoeda($faturamentoTotal) ?></div>
                <small class="text-muted">Vendas faturadas</small>
            </div>
            <div class="card-stat-icon info">
                <i class="bi bi-cash-stack"></i>
            </div>
        </div>
    </div>

    <!-- Equipe de Funcionários -->
    <div class="col-12 col-sm-6 col-xl">
        <div class="card-stat">
            <div>
                <div class="card-stat-title">Funcionários</div>
                <div class="card-stat-value"><?= $totalFuncionarios ?></div>
                <small class="text-muted">Equipe ativa</small>
            </div>
            <div class="card-stat-icon primary">
                <i class="bi bi-person-badge-fill"></i>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Vendas Recentes -->
    <div class="col-12 col-lg-7">
        <div class="card-app h-100">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="bi bi-clock-history text-primary"></i> Vendas Recentes
                </h3>
                <a href="<?= url('vendas/index.php') ?>" class="btn btn-sm btn-outline-secondary">Ver Todas</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-app align-middle mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Cliente</th>
                                <th>Vendedor</th>
                                <th>Data</th>
                                <th>Valor Total</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($vendasRecentes)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">Nenhuma venda registrada ainda.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($vendasRecentes as $v): ?>
                                    <tr>
                                        <td class="fw-bold">#<?= $v['id_venda'] ?></td>
                                        <td><?= e($v['cliente_nome']) ?></td>
                                        <td class="text-muted small"><?= e($v['vendedor_nome']) ?></td>
                                        <td><?= formatarDataHora($v['data_venda']) ?></td>
                                        <td class="fw-semibold"><?= formatarMoeda($v['valor_total']) ?></td>
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

    <!-- Veículos Recentes no Estoque -->
    <div class="col-12 col-lg-5">
        <div class="card-app h-100">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="bi bi-car-front text-primary"></i> Últimos Veículos
                </h3>
                <a href="<?= url('veiculos/index.php') ?>" class="btn btn-sm btn-outline-secondary">Ver Estoque</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-app align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Veículo</th>
                                <th>Ano</th>
                                <th>Preço</th>
                                <th>Situação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($veiculosRecentes)): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">Nenhum veículo cadastrado.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($veiculosRecentes as $ve): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold"><?= e($ve['modelo']) ?></div>
                                            <div class="small text-muted"><?= e($ve['marca_nome']) ?> &bull; <?= e(ucfirst($ve['tipo'])) ?></div>
                                        </td>
                                        <td class="small"><?= e($ve['ano_fabricacao']) ?>/<?= e($ve['ano_modelo']) ?></td>
                                        <td class="fw-semibold text-primary"><?= formatarMoeda($ve['preco']) ?></td>
                                        <td><?= badgeStatus($ve['situacao']) ?></td>
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>
