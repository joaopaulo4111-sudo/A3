<?php
/**
 * ============================================================
 *  AutoGestão - Configuração da conexão com o banco de dados
 *  Arquivo: config/database.php
 * ============================================================
 *  Este arquivo cria UMA conexão PDO com o MySQL e a
 *  disponibiliza para todo o sistema através da função conectar().
 *
 *  Por que PDO?
 *   - Permite usar "prepared statements", que protegem contra
 *     SQL Injection (os valores nunca são colados no SQL).
 *   - Suporta transações (beginTransaction / commit / rollBack),
 *     usadas nas vendas e nos pagamentos.
 * ============================================================
 */

// Fuso horário do Brasil (usado por date() e pelas datas das vendas/pagamentos)
date_default_timezone_set('America/Sao_Paulo');

// ---------- Dados de acesso ao MySQL ----------
// Altere aqui caso o banco esteja em outra máquina, porta ou senha.
define('DB_HOST', '127.0.0.1');   // servidor do MySQL
define('DB_PORT', '3306');        // porta padrão do MySQL
define('DB_NAME', 'autogestao');  // nome do banco criado pelo database.sql
define('DB_USER', 'root');        // usuário do MySQL
define('DB_PASS', 'ceub123456');  // senha do MySQL

/**
 * Retorna a conexão PDO com o banco de dados.
 *
 * A palavra "static" faz a variável $pdo ser lembrada entre as chamadas.
 * Assim, mesmo que conectar() seja chamada várias vezes na mesma página,
 * apenas UMA conexão é aberta com o MySQL.
 *
 * @return PDO
 */
function conectar(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        // DSN = "endereço" do banco. charset=utf8mb4 garante os acentos.
        $dsn = 'mysql:host=' . DB_HOST
             . ';port=' . DB_PORT
             . ';dbname=' . DB_NAME
             . ';charset=utf8mb4';

        $opcoes = [
            // Lança exceções (PDOException) quando ocorrer erro no SQL
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            // fetch() e fetchAll() retornam arrays associativos: $linha['nome']
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Usa prepared statements reais do MySQL (mais seguro)
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $opcoes);
            // Alinha o fuso horário do MySQL com o do PHP (NOW(), CURRENT_TIMESTAMP)
            $pdo->exec("SET time_zone = '-03:00'");
        } catch (PDOException $e) {
            // Registra o erro técnico no log do servidor (C:\xampp\apache\logs\error.log)
            error_log('Erro de conexão com o banco: ' . $e->getMessage());

            // Mostra ao usuário uma mensagem amigável, sem expor a senha/detalhes
            http_response_code(500);
            die('<div style="font-family:Arial;padding:30px;color:#842029;background:#f8d7da;">'
              . '<h3>Não foi possível conectar ao banco de dados.</h3>'
              . '<p>Verifique se o MySQL está em execução e se os dados em '
              . '<code>config/database.php</code> estão corretos.</p></div>');
        }
    }

    return $pdo;
}
