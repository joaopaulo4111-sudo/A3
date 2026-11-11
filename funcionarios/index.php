<?php
/**
 * ============================================================
 *  AutoGestão - Módulo de Funcionários (Listagem e Pesquisa)
 *  Arquivo: funcionarios/index.php
 * ============================================================
 *  Implementa:
 *   - RF03: Acesso protegido por autenticação.
 *   - Controle de perfil: exclusivo para administradores (exigirAdmin).
 *   - RF05: Listagem completa de funcionários.
 *   - HU03: Controle da equipe, cargos e salários.
 * ============================================================
 */

require_once __DIR__ . '/../includes/auth.php';
exigirAdmin();

$tituloPagina = 'Funcionários';
$paginaAtiva  = 'funcionarios';

$pdo = conectar();

// Filtros de busca (GET)
$buscaTermo = get('busca');
$buscaCargo = get('cargo');

// Montagem dinâmica da consulta com Prepared Statements
$sql = "SELECT f.id_funcionario, f.nome, f.cpf, f.telefone, f.email, f.cargo, f.salario, f.data_admissao,
               u.id_usuario, u.perfil AS usuario_perfil, u.status AS usuario_status
          FROM funcionarios f
          LEFT JOIN usuarios u ON u.id_funcionario = f.id_funcionario
         WHERE 1 = 1";
$params = [];

if ($buscaTermo !== '') {
    $sql .= " AND (f.nome LIKE :termo OR f.cpf LIKE :termo OR f.email LIKE :termo)";
    $params[':termo'] = '%' . $buscaTermo . '%';
}

if ($buscaCargo !== '') {
    $sql .= " AND f.cargo LIKE :cargo";
    $params[':cargo'] = '%' . $buscaCargo . '%';
}

$sql .= " ORDER BY f.nome ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$funcionarios = $stmt->fetchAll();

// Lista de cargos distintos para o filtro
$cargosCadastrados = $pdo->query("SELECT DISTINCT cargo FROM funcionarios ORDER BY cargo ASC")->fetchAll(PDO::FETCH_COLUMN);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="h4 fw-bold text-dark mb-1">Gestão de Funcionários</h2>
        <p class="text-muted small mb-0">Administre os colaboradores da concessionária, cargos, salários e contas de acesso.</p>
    </div>
    <div>
        <a href="<?= url('funcionarios/cadastrar.php') ?>" class="btn btn-primary">
            <i class="bi bi-person-plus me-1"></i> Novo Funcionário
        </a>
    </div>
</div>

<!-- Filtros de Pesquisa -->
<div class="card-app mb-4">
    <div class="card-body">
        <form method="get" action="<?= url('funcionarios/index.php') ?>" class="row g-3 align-items-end">
            <div class="col-12 col-md-6">
                <label for="filtroBusca" class="form-label small fw-semibold text-secondary">PESQUISAR</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" class="form-control" id="filtroBusca" name="busca"
                           placeholder="Pesquisar por nome, CPF ou e-mail..." value="<?= e($buscaTermo) ?>">
                </div>
            </div>

            <div class="col-12 col-md-4">
                <label for="filtroCargo" class="form-label small fw-semibold text-secondary">CARGO</label>
                <select class="form-select" id="filtroCargo" name="cargo">
                    <option value="">Todos os cargos</option>
                    <?php foreach ($cargosCadastrados as $c): ?>
                        <option value="<?= e($c) ?>" <?= $buscaCargo === $c ? 'selected' : '' ?>>
                            <?= e($c) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-navy w-100">
                    <i class="bi bi-funnel me-1"></i> Filtrar
                </button>
                <?php if ($buscaTermo !== '' || $buscaCargo !== ''): ?>
                    <a href="<?= url('funcionarios/index.php') ?>" class="btn btn-outline-secondary" title="Limpar Filtros">
                        <i class="bi bi-x-lg"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Listagem de Funcionários -->
<div class="card-app">
    <div class="card-header">
        <h3 class="card-title">
            <i class="bi bi-person-badge text-primary"></i> Equipe Cadastrada
        </h3>
        <span class="badge bg-light text-secondary border">Total: <?= count($funcionarios) ?></span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-app align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 60px;">#</th>
                        <th>Colaborador</th>
                        <th>CPF</th>
                        <th>Telefone / E-mail</th>
                        <th>Cargo</th>
                        <th>Salário</th>
                        <th>Admissão</th>
                        <th>Acesso Sistema</th>
                        <th class="text-end" style="width: 140px;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($funcionarios)): ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">
                                <i class="bi bi-inbox fs-3 d-block mb-2 text-secondary"></i>
                                Nenhum funcionário encontrado.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($funcionarios as $f): ?>
                            <tr>
                                <td class="fw-bold text-muted">#<?= $f['id_funcionario'] ?></td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= e($f['nome']) ?></div>
                                    <small class="text-muted"><?= e($f['email'] ?? 'Sem e-mail cadastrado') ?></small>
                                </td>
                                <td><code><?= e($f['cpf']) ?></code></td>
                                <td><?= e($f['telefone']) ?></td>
                                <td><span class="badge bg-light text-dark border"><?= e($f['cargo']) ?></span></td>
                                <td class="fw-semibold text-success"><?= formatarMoeda($f['salario']) ?></td>
                                <td class="small text-muted"><?= formatarData($f['data_admissao']) ?></td>
                                <td>
                                    <?php if ($f['id_usuario']): ?>
                                        <span class="badge text-bg-<?= $f['usuario_status'] === 'ativo' ? 'primary' : 'secondary' ?>"
                                              title="Perfil: <?= e(ucfirst($f['usuario_perfil'])) ?>">
                                            <i class="bi bi-shield-check me-1"></i><?= e(ucfirst($f['usuario_perfil'])) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted small">Sem login</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= url('funcionarios/editar.php?id=' . $f['id_funcionario']) ?>"
                                           class="btn btn-outline-primary" title="Editar Funcionário">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form method="post" action="<?= url('funcionarios/excluir.php') ?>"
                                              class="form-confirmar d-inline"
                                              data-mensagem="Deseja realmente excluir o funcionário <?= e($f['nome']) ?>?">
                                            <?= campoCSRF() ?>
                                            <input type="hidden" name="id_funcionario" value="<?= $f['id_funcionario'] ?>">
                                            <button type="submit" class="btn btn-outline-danger" title="Excluir Funcionário">
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
