<?php
/**
 * ============================================================
 *  AutoGestão - Módulo de Clientes (Listagem e Pesquisa)
 *  Arquivo: clientes/index.php
 * ============================================================
 *  Implementa:
 *   - RF03: Acesso protegido por autenticação.
 *   - RF04: Listagem completa de clientes.
 *   - RF12 / HU09: Busca dinâmica combinada por Nome, CPF e Telefone.
 *   - RN01 / RN13: Integridade de dados e proteção referencial.
 * ============================================================
 */

require_once __DIR__ . '/../includes/auth.php';
exigirLogin();

$tituloPagina = 'Clientes';
$paginaAtiva  = 'clientes';

$pdo = conectar();

// Filtros de busca (GET)
$buscaNome     = get('nome');
$buscaCpf      = get('cpf');
$buscaTelefone = get('telefone');

// Montagem dinâmica da consulta com Prepared Statements (RF12)
$sql = "SELECT id_cliente, nome, cpf, telefone, email, endereco, data_cadastro
          FROM clientes
         WHERE 1 = 1";
$params = [];

if ($buscaNome !== '') {
    $sql .= " AND nome LIKE :nome";
    $params[':nome'] = '%' . $buscaNome . '%';
}

if ($buscaCpf !== '') {
    $cpfLimpo = somenteNumeros($buscaCpf);
    $sql .= " AND (cpf LIKE :cpf OR REPLACE(REPLACE(cpf, '.', ''), '-', '') LIKE :cpfLimpo)";
    $params[':cpf']      = '%' . $buscaCpf . '%';
    $params[':cpfLimpo'] = '%' . $cpfLimpo . '%';
}

if ($buscaTelefone !== '') {
    $telLimpo = somenteNumeros($buscaTelefone);
    $sql .= " AND (telefone LIKE :telefone OR REPLACE(REPLACE(REPLACE(REPLACE(telefone, '(', ''), ')', ''), '-', ''), ' ', '') LIKE :telLimpo)";
    $params[':telefone'] = '%' . $buscaTelefone . '%';
    $params[':telLimpo'] = '%' . $telLimpo . '%';
}

$sql .= " ORDER BY nome ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$clientes = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="h4 fw-bold text-dark mb-1">Gerenciamento de Clientes</h2>
        <p class="text-muted small mb-0">Consulte, cadastre e gerencie a base de clientes da concessionária.</p>
    </div>
    <div>
        <a href="<?= url('clientes/cadastrar.php') ?>" class="btn btn-primary">
            <i class="bi bi-person-plus me-1"></i> Novo Cliente
        </a>
    </div>
</div>

<!-- Filtros Avançados de Pesquisa (RF12) -->
<div class="card-app mb-4">
    <div class="card-body">
        <form method="get" action="<?= url('clientes/index.php') ?>" class="row g-3 align-items-end">
            <div class="col-12 col-md-4">
                <label for="filtroNome" class="form-label small fw-semibold text-secondary">NOME</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" class="form-control" id="filtroNome" name="nome"
                           placeholder="Pesquisar por nome..." value="<?= e($buscaNome) ?>">
                </div>
            </div>

            <div class="col-12 col-md-3">
                <label for="filtroCpf" class="form-label small fw-semibold text-secondary">CPF</label>
                <input type="text" class="form-control mascara-cpf" id="filtroCpf" name="cpf"
                       placeholder="000.000.000-00" value="<?= e($buscaCpf) ?>">
            </div>

            <div class="col-12 col-md-3">
                <label for="filtroTelefone" class="form-label small fw-semibold text-secondary">TELEFONE</label>
                <input type="text" class="form-control mascara-telefone" id="filtroTelefone" name="telefone"
                       placeholder="(00) 00000-0000" value="<?= e($buscaTelefone) ?>">
            </div>

            <div class="col-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-navy w-100">
                    <i class="bi bi-funnel me-1"></i> Filtrar
                </button>
                <?php if ($buscaNome !== '' || $buscaCpf !== '' || $buscaTelefone !== ''): ?>
                    <a href="<?= url('clientes/index.php') ?>" class="btn btn-outline-secondary" title="Limpar Filtros">
                        <i class="bi bi-x-lg"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Listagem de Clientes -->
<div class="card-app">
    <div class="card-header">
        <h3 class="card-title">
            <i class="bi bi-people text-primary"></i> Clientes Cadastrados
        </h3>
        <span class="badge bg-light text-secondary border">Total: <?= count($clientes) ?></span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-app align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 60px;">#</th>
                        <th>Nome</th>
                        <th>CPF</th>
                        <th>Telefone</th>
                        <th>E-mail</th>
                        <th>Endereço</th>
                        <th>Cadastro</th>
                        <th class="text-end" style="width: 140px;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($clientes)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                <i class="bi bi-inbox fs-3 d-block mb-2 text-secondary"></i>
                                Nenhum cliente encontrado para os critérios pesquisados.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($clientes as $c): ?>
                            <tr>
                                <td class="fw-bold text-muted">#<?= $c['id_cliente'] ?></td>
                                <td class="fw-semibold text-dark"><?= e($c['nome']) ?></td>
                                <td><code><?= e($c['cpf']) ?></code></td>
                                <td><?= e($c['telefone']) ?></td>
                                <td>
                                    <?php if (!empty($c['email'])): ?>
                                        <a href="mailto:<?= e($c['email']) ?>" class="text-decoration-none">
                                            <?= e($c['email']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-truncate small" style="max-width: 220px;" title="<?= e($c['endereco']) ?>">
                                    <?= e($c['endereco'] ?? '-') ?>
                                </td>
                                <td class="small text-muted"><?= formatarData($c['data_cadastro']) ?></td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= url('clientes/editar.php?id=' . $c['id_cliente']) ?>"
                                           class="btn btn-outline-primary" title="Editar Cliente">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form method="post" action="<?= url('clientes/excluir.php') ?>"
                                              class="form-confirmar d-inline"
                                              data-mensagem="Deseja realmente excluir o cliente <?= e($c['nome']) ?>?">
                                            <?= campoCSRF() ?>
                                            <input type="hidden" name="id_cliente" value="<?= $c['id_cliente'] ?>">
                                            <button type="submit" class="btn btn-outline-danger" title="Excluir Cliente">
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
