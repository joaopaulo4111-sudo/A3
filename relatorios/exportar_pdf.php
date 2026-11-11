<?php
/**
 * ============================================================
 *  Magnata Automóveis - Exportação de Relatórios em PDF
 *  Arquivo: relatorios/exportar_pdf.php
 * ============================================================
 *  Implementa:
 *   - RF15: Exportação de listagens e relatórios em formato PDF.
 *   - HU13: Emissão de relatórios gerenciais para impressão e arquivo.
 *   - Utiliza a biblioteca Dompdf instalada via Composer.
 * ============================================================
 */

require_once __DIR__ . '/../includes/auth.php';
exigirLogin();

$tipo = get('tipo'); // 'vendas' ou 'estoque'

if (!in_array($tipo, ['vendas', 'estoque'], true)) {
    definirMensagem('danger', 'Tipo de relatório inválido para exportação.');
    redirecionar('dashboard.php');
}

// Carrega o autoloader do Composer com o Dompdf
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    die('A biblioteca Dompdf não foi encontrada. Execute o Composer na raiz do projeto.');
}
require_once $autoloadPath;

use Dompdf\Dompdf;
use Dompdf\Options;

$pdo = conectar();
$dataEmissao = date('d/m/Y H:i');
$usuarioEmissor = usuarioLogado()['nome'] ?? 'Sistema';

ob_start();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatório <?= ucfirst($tipo) ?> - Magnata Automóveis</title>
    <style>
        @page {
            margin: 20mm 15mm 20mm 15mm;
            size: A4 landscape;
        }
        body {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            color: #1e293b;
            font-size: 11px;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-bottom: 2px solid #d4af37;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .header-title {
            font-size: 18px;
            font-weight: bold;
            color: #0f0f13;
        }
        .header-sub {
            font-size: 10px;
            color: #64748b;
            text-transform: uppercase;
        }
        .meta-info {
            text-align: right;
            font-size: 9px;
            color: #64748b;
        }
        .metrics-table {
            width: 100%;
            margin-bottom: 15px;
            border-collapse: collapse;
        }
        .metric-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 8px 12px;
            text-align: center;
        }
        .metric-label {
            font-size: 9px;
            text-transform: uppercase;
            color: #64748b;
            font-weight: bold;
        }
        .metric-val {
            font-size: 14px;
            font-weight: bold;
            color: #0f2744;
            margin-top: 4px;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .data-table th {
            background-color: #0f2744;
            color: #ffffff;
            font-size: 9px;
            text-transform: uppercase;
            padding: 7px 8px;
            text-align: left;
        }
        .data-table td {
            padding: 7px 8px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 10px;
        }
        .data-table tr:nth-child(even) td {
            background-color: #f8fafc;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            font-size: 8px;
            font-weight: bold;
            border-radius: 4px;
            text-transform: uppercase;
        }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-danger  { background: #fee2e2; color: #991b1b; }
        .badge-info    { background: #e0f2fe; color: #075985; }
        .badge-light   { background: #f1f5f9; color: #475569; }
        .footer {
            position: fixed;
            bottom: -10mm;
            left: 0;
            right: 0;
            font-size: 8px;
            color: #94a3b8;
            text-align: center;
            border-top: 1px solid #e2e8f0;
            padding-top: 5px;
        }
    </style>
</head>
<body>

<div class="footer">
    Magnata Automóveis &bull; Concessionária Multimarcas &bull; Documento gerado para controle interno em <?= $dataEmissao ?> por <?= e($usuarioEmissor) ?>
</div>

<!-- Cabeçalho Institucional -->
<table class="header-table">
    <tr>
        <td style="width: 60%;">
            <div class="header-title">MAGNATA AUTOMÓVEIS &mdash; VEÍCULOS PREMIUM</div>
            <div class="header-sub">
                <?= $tipo === 'vendas' ? 'Relatório Analítico de Vendas e Desempenho Comercial' : 'Relatório Patrimonial e Situação de Estoque de Veículos' ?>
            </div>
        </td>
        <td class="meta-info" style="width: 40%;">
            Emissão: <strong><?= $dataEmissao ?></strong><br>
            Responsável: <strong><?= e($usuarioEmissor) ?></strong>
        </td>
    </tr>
</table>

<?php if ($tipo === 'vendas'): ?>
    <?php
    $dataInicio    = get('data_inicio');
    $dataFim       = get('data_fim');
    $idFuncionario = get('id_funcionario');
    $status        = get('status');

    $sql = "
        SELECT v.id_venda, v.data_venda, v.valor_total, v.desconto, v.status,
               c.nome AS cliente_nome,
               f.nome AS vendedor_nome,
               COUNT(vv.id_veiculo) AS qtd_veiculos,
               COALESCE(SUM(CASE WHEN p.status = 'pago' THEN p.valor END), 0) AS total_pago
          FROM vendas v
          JOIN clientes c            ON c.id_cliente = v.id_cliente
          JOIN funcionarios f        ON f.id_funcionario = v.id_funcionario
          LEFT JOIN venda_veiculos vv ON vv.id_venda = v.id_venda
          LEFT JOIN pagamentos p     ON p.id_venda = v.id_venda
         WHERE 1 = 1
    ";
    $params = [];
    if ($dataInicio !== '')    { $sql .= " AND DATE(v.data_venda) >= :ini"; $params[':ini'] = $dataInicio; }
    if ($dataFim !== '')       { $sql .= " AND DATE(v.data_venda) <= :fim"; $params[':fim'] = $dataFim; }
    if ($idFuncionario !== '') { $sql .= " AND v.id_funcionario = :func";  $params[':func'] = (int) $idFuncionario; }
    if ($status !== '')        { $sql .= " AND v.status = :status";        $params[':status'] = $status; }
    $sql .= " GROUP BY v.id_venda, v.data_venda, v.valor_total, v.desconto, v.status, c.nome, f.nome
              ORDER BY v.data_venda DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $vendas = $stmt->fetchAll();

    $totFaturamento = 0.0;
    $totRecebido    = 0.0;
    $totDescontos   = 0.0;
    foreach ($vendas as $v) {
        if ($v['status'] !== 'cancelada') {
            $totFaturamento += (float) $v['valor_total'];
            $totRecebido    += (float) $v['total_pago'];
            $totDescontos   += (float) $v['desconto'];
        }
    }
    $totPendente = max(0, $totFaturamento - $totRecebido);
    ?>

    <!-- Cards de Resumo -->
    <table class="metrics-table">
        <tr>
            <td class="metric-box" style="width: 20%;">
                <div class="metric-label">Vendas Realizadas</div>
                <div class="metric-val"><?= count($vendas) ?></div>
            </td>
            <td class="metric-box" style="width: 20%;">
                <div class="metric-label">Faturamento Bruto</div>
                <div class="metric-val" style="color: #0284c7;"><?= formatarMoeda($totFaturamento) ?></div>
            </td>
            <td class="metric-box" style="width: 20%;">
                <div class="metric-label">Descontos</div>
                <div class="metric-val" style="color: #dc2626;"><?= formatarMoeda($totDescontos) ?></div>
            </td>
            <td class="metric-box" style="width: 20%;">
                <div class="metric-label">Total Recebido</div>
                <div class="metric-val" style="color: #059669;"><?= formatarMoeda($totRecebido) ?></div>
            </td>
            <td class="metric-box" style="width: 20%;">
                <div class="metric-label">Saldo Pendente</div>
                <div class="metric-val" style="color: #d97706;"><?= formatarMoeda($totPendente) ?></div>
            </td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 45px;">Nº</th>
                <th style="width: 80px;">Data</th>
                <th>Cliente Comprador</th>
                <th>Vendedor Responsável</th>
                <th class="text-center" style="width: 45px;">Itens</th>
                <th class="text-right" style="width: 100px;">Valor Total</th>
                <th class="text-right" style="width: 100px;">Valor Pago</th>
                <th class="text-center" style="width: 80px;">Situação</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($vendas)): ?>
                <tr><td colspan="8" class="text-center" style="padding: 20px;">Nenhum registro encontrado para os filtros selecionados.</td></tr>
            <?php else: ?>
                <?php foreach ($vendas as $v): ?>
                    <?php
                    $cls = 'badge-light';
                    if ($v['status'] === 'concluída') $cls = 'badge-success';
                    if ($v['status'] === 'pendente')  $cls = 'badge-warning';
                    if ($v['status'] === 'cancelada') $cls = 'badge-danger';
                    ?>
                    <tr>
                        <td class="text-center"><strong>#<?= $v['id_venda'] ?></strong></td>
                        <td><?= formatarDataHora($v['data_venda']) ?></td>
                        <td><?= e($v['cliente_nome']) ?></td>
                        <td><?= e($v['vendedor_nome']) ?></td>
                        <td class="text-center"><?= $v['qtd_veiculos'] ?></td>
                        <td class="text-right"><strong><?= formatarMoeda($v['valor_total']) ?></strong></td>
                        <td class="text-right" style="color: #059669;"><?= formatarMoeda($v['total_pago']) ?></td>
                        <td class="text-center"><span class="badge <?= $cls ?>"><?= ucfirst($v['status']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

<?php elseif ($tipo === 'estoque'): ?>
    <?php
    $filtroMarca    = get('marca');
    $filtroTipo     = get('tipo');
    $filtroSituacao = get('situacao');

    $sql = "
        SELECT ve.*, m.nome AS marca_nome
          FROM veiculos ve
          JOIN marcas m ON m.id_marca = ve.id_marca
         WHERE 1 = 1
    ";
    $params = [];
    if ($filtroMarca !== '')    { $sql .= " AND ve.id_marca = :m";    $params[':m'] = (int) $filtroMarca; }
    if ($filtroTipo !== '')     { $sql .= " AND ve.tipo = :t";        $params[':t'] = $filtroTipo; }
    if ($filtroSituacao !== '') { $sql .= " AND ve.situacao = :s";    $params[':s'] = $filtroSituacao; }
    $sql .= " ORDER BY ve.situacao ASC, m.nome ASC, ve.modelo ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $veiculos = $stmt->fetchAll();

    $totGeral = count($veiculos);
    $totDisp  = 0;
    $valDisp  = 0.0;
    foreach ($veiculos as $ve) {
        if ($ve['situacao'] === 'disponível') {
            $totDisp++;
            $valDisp += (float) $ve['preco'];
        }
    }
    $ticketMedio = ($totDisp > 0) ? ($valDisp / $totDisp) : 0.0;
    ?>

    <table class="metrics-table">
        <tr>
            <td class="metric-box" style="width: 25%;">
                <div class="metric-label">Total de Veículos</div>
                <div class="metric-val"><?= $totGeral ?></div>
            </td>
            <td class="metric-box" style="width: 25%;">
                <div class="metric-label">Disponíveis (Pronta-Entrega)</div>
                <div class="metric-val" style="color: #059669;"><?= $totDisp ?></div>
            </td>
            <td class="metric-box" style="width: 25%;">
                <div class="metric-label">Valor Patrimonial Disponível</div>
                <div class="metric-val" style="color: #0284c7;"><?= formatarMoeda($valDisp) ?></div>
            </td>
            <td class="metric-box" style="width: 25%;">
                <div class="metric-label">Preço Médio (Disponíveis)</div>
                <div class="metric-val"><?= formatarMoeda($ticketMedio) ?></div>
            </td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 40px;">#</th>
                <th>Marca / Montadora</th>
                <th>Modelo / Versão</th>
                <th class="text-center" style="width: 65px;">Ano Fab/Mod</th>
                <th class="text-center" style="width: 55px;">Tipo</th>
                <th>Cor</th>
                <th class="text-center" style="width: 65px;">Placa</th>
                <th class="text-right" style="width: 70px;">Km</th>
                <th class="text-right" style="width: 95px;">Preço</th>
                <th class="text-center" style="width: 75px;">Situação</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($veiculos)): ?>
                <tr><td colspan="10" class="text-center" style="padding: 20px;">Nenhum veículo encontrado para os filtros selecionados.</td></tr>
            <?php else: ?>
                <?php foreach ($veiculos as $ve): ?>
                    <?php
                    $cls = 'badge-light';
                    if ($ve['situacao'] === 'disponível') $cls = 'badge-success';
                    if ($ve['situacao'] === 'reservado')  $cls = 'badge-warning';
                    if ($ve['situacao'] === 'vendido')    $cls = 'badge-light';
                    ?>
                    <tr>
                        <td class="text-center"><strong>#<?= $ve['id_veiculo'] ?></strong></td>
                        <td><?= e($ve['marca_nome']) ?></td>
                        <td><strong><?= e($ve['modelo']) ?></strong></td>
                        <td class="text-center"><?= $ve['ano_fabricacao'] ?>/<?= $ve['ano_modelo'] ?></td>
                        <td class="text-center"><span class="badge <?= $ve['tipo'] === 'novo' ? 'badge-info' : 'badge-light' ?>"><?= ucfirst($ve['tipo']) ?></span></td>
                        <td><?= e($ve['cor']) ?></td>
                        <td class="text-center" style="font-family: monospace;"><?= e($ve['placa'] ?? '-') ?></td>
                        <td class="text-right"><?= formatarKm($ve['quilometragem']) ?></td>
                        <td class="text-right" style="color: #0284c7;"><strong><?= formatarMoeda($ve['preco']) ?></strong></td>
                        <td class="text-center"><span class="badge <?= $cls ?>"><?= ucfirst($ve['situacao']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

<?php endif; ?>

</body>
</html>
<?php
$html = ob_get_clean();

// Configura o Dompdf
$options = new Options();
$options->set('isRemoteEnabled', false);
$options->set('defaultFont', 'DejaVu Sans');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

// Envia o PDF para download/visualização no navegador
$nomeArquivo = 'relatorio_' . $tipo . '_' . date('Ymd_His') . '.pdf';
$dompdf->stream($nomeArquivo, ['Attachment' => false]);
