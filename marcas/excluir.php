<?php
/**
 * ============================================================
 *  AutoGestão - Exclusão de Marca
 *  Arquivo: marcas/excluir.php
 * ============================================================
 *  Implementa:
 *   - RF06: Exclusão controlada de marcas.
 *   - RN03: Proteção de integridade referencial: impede a exclusão
 *     de marcas que possuam veículos cadastrados no estoque.
 *   - Proteção CSRF.
 * ============================================================
 */

require_once __DIR__ . '/../includes/auth.php';
exigirLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    definirMensagem('warning', 'Método de requisição inválido para esta operação.');
    redirecionar('marcas/index.php');
}

validarCSRF();

$id = (int) post('id_marca');

if ($id <= 0) {
    definirMensagem('danger', 'Identificador de marca inválido.');
    redirecionar('marcas/index.php');
}

$pdo = conectar();

try {
    // 1. Verifica se a marca existe
    $stmtMarca = $pdo->prepare('SELECT nome FROM marcas WHERE id_marca = :id LIMIT 1');
    $stmtMarca->execute([':id' => $id]);
    $marca = $stmtMarca->fetch();

    if (!$marca) {
        definirMensagem('danger', 'Marca não encontrada no sistema.');
        redirecionar('marcas/index.php');
    }

    // 2. RN03: Verifica se a marca possui veículos cadastrados
    $stmtVeiculos = $pdo->prepare('SELECT COUNT(*) FROM veiculos WHERE id_marca = :id');
    $stmtVeiculos->execute([':id' => $id]);
    $totalVeiculos = (int) $stmtVeiculos->fetchColumn();

    if ($totalVeiculos > 0) {
        definirMensagem(
            'danger',
            'Não é possível excluir a marca "' . $marca['nome'] . '" pois existem '
            . $totalVeiculos . ' veículo(s) associado(s) a ela no estoque (Regra RN03).'
        );
        redirecionar('marcas/index.php');
    }

    // 3. Efetua a exclusão
    $stmtDelete = $pdo->prepare('DELETE FROM marcas WHERE id_marca = :id');
    $stmtDelete->execute([':id' => $id]);

    definirMensagem('success', 'Marca "' . $marca['nome'] . '" excluída com sucesso.');
} catch (PDOException $e) {
    error_log('Erro ao excluir marca: ' . $e->getMessage());
    definirMensagem('danger', 'Ocorreu um erro ao tentar excluir a marca. Verifique se existem veículos vinculados.');
}

redirecionar('marcas/index.php');
