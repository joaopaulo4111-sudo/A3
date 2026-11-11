<?php
/**
 * ============================================================
 *  AutoGestão - Exclusão de Cliente
 *  Arquivo: clientes/excluir.php
 * ============================================================
 *  Implementa:
 *   - RF04: Exclusão controlada de clientes.
 *   - RN13: Proteção de integridade referencial: impede a exclusão
 *     física de clientes que possuem histórico de vendas associado.
 *   - Proteção CSRF.
 * ============================================================
 */

require_once __DIR__ . '/../includes/auth.php';
exigirLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    definirMensagem('warning', 'Método de requisição inválido para esta operação.');
    redirecionar('clientes/index.php');
}

validarCSRF();

$id = (int) post('id_cliente');

if ($id <= 0) {
    definirMensagem('danger', 'Identificador de cliente inválido.');
    redirecionar('clientes/index.php');
}

$pdo = conectar();

try {
    // 1. Verifica se o cliente existe
    $stmtCliente = $pdo->prepare('SELECT nome FROM clientes WHERE id_cliente = :id LIMIT 1');
    $stmtCliente->execute([':id' => $id]);
    $cliente = $stmtCliente->fetch();

    if (!$cliente) {
        definirMensagem('danger', 'Cliente não encontrado no sistema.');
        redirecionar('clientes/index.php');
    }

    // 2. RN13: Verifica se o cliente possui vendas vinculadas
    $stmtVendas = $pdo->prepare('SELECT COUNT(*) FROM vendas WHERE id_cliente = :id');
    $stmtVendas->execute([':id' => $id]);
    $totalVendas = (int) $stmtVendas->fetchColumn();

    if ($totalVendas > 0) {
        definirMensagem(
            'danger',
            'Não é possível excluir o cliente "' . $cliente['nome'] . '" pois constam '
            . $totalVendas . ' venda(s) vinculada(s) ao seu histórico (Regra de Negócio RN13).'
        );
        redirecionar('clientes/index.php');
    }

    // 3. Efetua a exclusão caso não haja vínculos
    $stmtDelete = $pdo->prepare('DELETE FROM clientes WHERE id_cliente = :id');
    $stmtDelete->execute([':id' => $id]);

    definirMensagem('success', 'Cliente "' . $cliente['nome'] . '" excluído com sucesso.');
} catch (PDOException $e) {
    error_log('Erro ao excluir cliente: ' . $e->getMessage());
    definirMensagem('danger', 'Ocorreu um erro ao tentar excluir o cliente. Verifique se existem registros dependentes.');
}

redirecionar('clientes/index.php');
