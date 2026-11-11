<?php
/**
 * ============================================================
 *  AutoGestão - Cancelamento de Venda
 *  Arquivo: vendas/cancelar.php
 * ============================================================
 *  Implementa:
 *   - Cancelamento seguro e atômico de venda com PDO Transaction.
 *   - Devolução automática dos veículos para 'disponível' no estoque.
 *   - Cancelamento de parcelas/pagamentos pendentes.
 *   - Proteção CSRF.
 * ============================================================
 */

require_once __DIR__ . '/../includes/auth.php';
exigirLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    definirMensagem('warning', 'Método de requisição inválido para esta operação.');
    redirecionar('vendas/index.php');
}

validarCSRF();

$id = (int) post('id_venda');

if ($id <= 0) {
    definirMensagem('danger', 'Identificador de venda inválido.');
    redirecionar('vendas/index.php');
}

$pdo = conectar();

try {
    // 1. Verifica se a venda existe
    $stmtVenda = $pdo->prepare('SELECT id_venda, status FROM vendas WHERE id_venda = :id LIMIT 1');
    $stmtVenda->execute([':id' => $id]);
    $venda = $stmtVenda->fetch();

    if (!$venda) {
        definirMensagem('danger', 'Venda não encontrada no sistema.');
        redirecionar('vendas/index.php');
    }

    if ($venda['status'] === 'cancelada') {
        definirMensagem('warning', 'Esta venda já se encontra cancelada.');
        redirecionar('vendas/visualizar.php?id=' . $id);
    }

    // 2. Inicia Transação PDO Atômica para cancelamento e devolução de estoque
    $pdo->beginTransaction();

    // Obtém a lista de veículos associados à venda
    $stmtItens = $pdo->prepare('SELECT id_veiculo FROM venda_veiculos WHERE id_venda = :id');
    $stmtItens->execute([':id' => $id]);
    $veiculosIds = $stmtItens->fetchAll(PDO::FETCH_COLUMN);

    // Devolve os veículos para 'disponível' no estoque
    if (!empty($veiculosIds)) {
        $stmtDevolve = $pdo->prepare('UPDATE veiculos SET situacao = "disponível" WHERE id_veiculo = :id_veiculo');
        foreach ($veiculosIds as $idV) {
            $stmtDevolve->execute([':id_veiculo' => $idV]);
        }
    }

    // Atualiza o status da venda para 'cancelada'
    $stmtCancelaVenda = $pdo->prepare('UPDATE vendas SET status = "cancelada" WHERE id_venda = :id');
    $stmtCancelaVenda->execute([':id' => $id]);

    // Cancela pagamentos pendentes vinculados à venda
    $stmtCancelaPag = $pdo->prepare('UPDATE pagamentos SET status = "cancelado" WHERE id_venda = :id AND status = "pendente"');
    $stmtCancelaPag->execute([':id' => $id]);

    $pdo->commit();

    definirMensagem('success', 'Venda #' . $id . ' cancelada com sucesso. Os veículos retornaram para a situação "disponível" no estoque.');
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Erro ao cancelar venda: ' . $e->getMessage());
    definirMensagem('danger', 'Erro ao cancelar a venda: ' . $e->getMessage());
}

redirecionar('vendas/visualizar.php?id=' . $id);
