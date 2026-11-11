<?php
/**
 * ============================================================
 *  Magnata Automóveis - Funções reutilizáveis do sistema
 *  Arquivo: includes/functions.php
 * ============================================================
 *  Este arquivo é incluído em TODAS as páginas. Ele:
 *   1. Inicia a sessão PHP de forma segura;
 *   2. Carrega a conexão com o banco (config/database.php);
 *   3. Descobre a URL base do projeto (BASE_URL);
 *   4. Oferece funções auxiliares: escape de saída, redirecionamento,
 *      mensagens (alertas), formatação e validação de dados.
 * ============================================================
 */

// ------------------------------------------------------------
// 1. Sessão
// ------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_name('MAGNATA_AUTOMOVEIS');       // nome próprio para o cookie da sessão
    session_set_cookie_params([
        'lifetime' => 0,                      // cookie apagado ao fechar o navegador
        'path'     => '/',
        'httponly' => true,                   // JavaScript não consegue ler o cookie
        'samesite' => 'Lax',                  // dificulta ataques CSRF
    ]);
    session_start();
}

// ------------------------------------------------------------
// 2. Conexão com o banco de dados
// ------------------------------------------------------------
require_once __DIR__ . '/../config/database.php';

// ------------------------------------------------------------
// 3. URL base do projeto
// ------------------------------------------------------------
// Ex.: projeto em C:\xampp\htdocs\A3  ->  BASE_URL = "/A3"
// Assim os links funcionam em qualquer subpasta, sem caminhos fixos.
if (!defined('BASE_URL')) {
    $pastaProjeto = str_replace('\\', '/', dirname(__DIR__));
    $documentRoot = str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\'));

    if ($documentRoot !== '' && stripos($pastaProjeto, $documentRoot) === 0) {
        define('BASE_URL', substr($pastaProjeto, strlen($documentRoot)));
    } else {
        define('BASE_URL', '');
    }
}

/**
 * Monta uma URL completa a partir da raiz do projeto.
 * Ex.: url('clientes/index.php') -> "/A3/clientes/index.php"
 */
function url(string $caminho = ''): string
{
    return BASE_URL . '/' . ltrim($caminho, '/');
}

/**
 * Redireciona para outra página do sistema e encerra o script.
 * Ex.: redirecionar('clientes/index.php');
 */
function redirecionar(string $caminho): never
{
    header('Location: ' . url($caminho));
    exit;
}

// ------------------------------------------------------------
// 4. Segurança na saída de dados
// ------------------------------------------------------------

/**
 * Escapa um valor antes de exibi-lo no HTML (proteção contra XSS).
 * SEMPRE use e() ao imprimir dados vindos do banco ou do usuário.
 * Ex.: <?= e($cliente['nome']) ?>
 */
function e(mixed $valor): string
{
    return htmlspecialchars((string) ($valor ?? ''), ENT_QUOTES, 'UTF-8');
}

// ------------------------------------------------------------
// 5. Mensagens para o usuário (alertas "flash")
// ------------------------------------------------------------
// A mensagem é guardada na sessão e exibida UMA vez na próxima página.
// Tipos: 'success' (sucesso), 'danger' (erro), 'warning' (atenção), 'info'.

function definirMensagem(string $tipo, string $texto): void
{
    $_SESSION['mensagens'][] = ['tipo' => $tipo, 'texto' => $texto];
}

/**
 * Exibe (e apaga da sessão) todas as mensagens pendentes.
 * É chamada automaticamente pelo includes/header.php.
 */
function exibirMensagens(): void
{
    if (empty($_SESSION['mensagens'])) {
        return;
    }

    $icones = [
        'success' => 'bi-check-circle-fill',
        'danger'  => 'bi-x-octagon-fill',
        'warning' => 'bi-exclamation-triangle-fill',
        'info'    => 'bi-info-circle-fill',
    ];

    foreach ($_SESSION['mensagens'] as $msg) {
        $tipo  = array_key_exists($msg['tipo'], $icones) ? $msg['tipo'] : 'info';
        $icone = $icones[$tipo];

        echo '<div class="alert alert-' . $tipo . ' alert-dismissible fade show d-flex align-items-center shadow-sm" role="alert">'
           . '<i class="bi ' . $icone . ' me-2 fs-5"></i>'
           . '<div>' . e($msg['texto']) . '</div>'
           . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>'
           . '</div>';
    }

    unset($_SESSION['mensagens']);
}

// ------------------------------------------------------------
// 6. Usuário logado (os dados são gravados na sessão no login - ETAPA 5)
// ------------------------------------------------------------

/** Retorna os dados do usuário logado ou null. */
function usuarioLogado(): ?array
{
    return $_SESSION['usuario'] ?? null;
}

/** Verifica se o usuário logado é administrador. */
function ehAdministrador(): bool
{
    return (usuarioLogado()['perfil'] ?? '') === 'administrador';
}

// ------------------------------------------------------------
// 7. Leitura de dados enviados por formulário
// ------------------------------------------------------------

/** Lê um campo de $_POST já sem espaços nas pontas. */
function post(string $campo): string
{
    return trim((string) ($_POST[$campo] ?? ''));
}

/** Lê um campo de $_GET já sem espaços nas pontas (usado nos filtros). */
function get(string $campo): string
{
    return trim((string) ($_GET[$campo] ?? ''));
}

// ------------------------------------------------------------
// 8. Formatação de valores para exibição
// ------------------------------------------------------------

/** 1234.5 -> "R$ 1.234,50" */
function formatarMoeda(mixed $valor): string
{
    return 'R$ ' . number_format((float) $valor, 2, ',', '.');
}

/** "2026-11-11" -> "11/11/2026" */
function formatarData(?string $data): string
{
    return $data ? date('d/m/Y', strtotime($data)) : '-';
}

/** "2026-11-11 15:30:00" -> "11/11/2026 15:30" */
function formatarDataHora(?string $dataHora): string
{
    return $dataHora ? date('d/m/Y H:i', strtotime($dataHora)) : '-';
}

/** 15000 -> "15.000 km" */
function formatarKm(mixed $km): string
{
    return number_format((int) $km, 0, ',', '.') . ' km';
}

/**
 * Converte um valor digitado em formato brasileiro para float.
 * Aceita "1.234,56", "1234,56" e "1234.56".
 */
function converterValor(string $valor): float
{
    $valor = trim($valor);
    if (str_contains($valor, ',')) {
        $valor = str_replace('.', '', $valor);   // remove separador de milhar
        $valor = str_replace(',', '.', $valor);  // vírgula vira ponto decimal
    }
    return (float) $valor;
}

/**
 * Gera um "badge" colorido para situações e status do sistema.
 * Ex.: badgeStatus('disponível') -> etiqueta verde.
 */
function badgeStatus(string $status): string
{
    $cores = [
        // veículos
        'disponível'    => 'success',
        'reservado'     => 'warning',
        'vendido'       => 'secondary',
        // vendas
        'concluída'     => 'success',
        'pendente'      => 'warning',
        'cancelada'     => 'danger',
        // pagamentos
        'pago'          => 'success',
        'cancelado'     => 'danger',
        // usuários
        'ativo'         => 'success',
        'inativo'       => 'secondary',
        // tipo de veículo
        'novo'          => 'primary',
        'usado'         => 'info',
    ];

    $cor = $cores[$status] ?? 'light';
    return '<span class="badge rounded-pill text-bg-' . $cor . ' badge-status">' . e(ucfirst($status)) . '</span>';
}

// ------------------------------------------------------------
// 9. Validação de dados
// ------------------------------------------------------------

/** Remove tudo que não for dígito. "123.456.789-09" -> "12345678909" */
function somenteNumeros(string $texto): string
{
    return preg_replace('/\D/', '', $texto);
}

/**
 * Valida um CPF pelo cálculo dos dígitos verificadores.
 * Aceita com ou sem pontuação.
 */
function validarCPF(string $cpf): bool
{
    $cpf = somenteNumeros($cpf);

    // Precisa ter 11 dígitos e não pode ser sequência repetida (111.111.111-11)
    if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
        return false;
    }

    // Calcula o 1º e o 2º dígito verificador
    for ($t = 9; $t < 11; $t++) {
        $soma = 0;
        for ($i = 0; $i < $t; $i++) {
            $soma += (int) $cpf[$i] * (($t + 1) - $i);
        }
        $digito = ((10 * $soma) % 11) % 10;
        if ((int) $cpf[$t] !== $digito) {
            return false;
        }
    }

    return true;
}

/** "12345678909" -> "123.456.789-09" (formato salvo no banco) */
function formatarCPF(string $cpf): string
{
    $cpf = somenteNumeros($cpf);
    if (strlen($cpf) !== 11) {
        return $cpf;
    }
    return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
}

/** Valida o formato de um e-mail. */
function validarEmail(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/** Valida telefone com 10 ou 11 dígitos (fixo ou celular com DDD). */
function validarTelefone(string $telefone): bool
{
    $digitos = strlen(somenteNumeros($telefone));
    return $digitos === 10 || $digitos === 11;
}

/** Valida uma data no formato do banco (AAAA-MM-DD). */
function validarData(string $data): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $data);
    return $d !== false && $d->format('Y-m-d') === $data;
}
