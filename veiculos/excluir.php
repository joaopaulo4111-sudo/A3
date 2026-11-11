<?php
/**
 * ============================================================
 *  AutoGestão - Exclusão de Veículo
 *  Arquivo: veiculos/excluir.php
 * ============================================================
 *  Implementa:
 *   - RF07: Exclusão controlada de veículos do estoque.
 *   - RN08 / RN13: Preservação histórica: impede a exclusão de
 *     veículos vinculados a vendas existentes no sistema.
 *   - Proteção CSRF.
 * ============================================================
 */

require_once __DIR__ . '/../includes/auth.php';
exigirLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    definirMensagem('warning', 'Método de requisição inválido para esta operação.');
    redirecionar('veiculos/index.php');
}

validarCSRF();

$id = (int) post('id_veiculo');

if ($id <= 0) {
    definirMensagem('danger', 'Identificador de veículo inválido.');
    redirecionar('veiculos/index.php');
}

$pdo = conectar();

try {
    // 1. Verifica se o veículo existe
    $stmtVeic = $pdo->prepare('SELECT modelo FROM veiculos WHERE id_veiculo = :id LIMIT 1');
    $stmtVeic->execute([':id' => $id]);
    $veiculo = $stmtVeic->fetch();

    if (!$veiculo) {
        definirMensagem('danger', 'Veículo não encontrado no estoque.');
        redirecionar('veiculos/index.php');
    }

    // 2. RN08 / RN13: Verifica se o veículo possui registro em vendas
    $stmtVenda = $pdo->prepare('SELECT id_venda FROM venda_veiculos WHERE id_veiculo = :id LIMIT 1');
    $stmtVenda->execute([':id' => $id]);
    $vendaItem = $stmtVenda->fetch();

    if ($vendaItem) {
        definirMensagem(
            'danger',
            'Não é possível excluir o veículo "' . $veiculo['modelo'] . '" pois ele está vinculado à venda #'
            . $vendaItem['id_venda'] . ' (Regras RN08 e RN13).'
        );
        redirecionar('veiculos/index.php');
    }

    // 3. Efetua a exclusão do estoque
    $stmtDelete = $pdo->prepare('DELETE FROM veiculos WHERE id_veiculo = :id');
    $stmtDelete->execute([':id' => $id]);

    definirMensagem('success', 'Veículo "' . $veiculo['modelo'] . '" excluído do estoque com sucesso.');
} catch (PDOException $e) {
    error_log('Erro ao excluir veículo: ' . $e->getMessage());
    definirMensagem('danger', 'Ocorreu um erro ao tentar excluir o veículo. Verifique se existem dependências ativas.');
}

redirecionar('veiculos/index.php');
