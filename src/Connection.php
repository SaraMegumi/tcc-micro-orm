<?php

declare(strict_types=1);

namespace App\ORM;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Gerencia a instância única (padrão Singleton) da conexão PDO com o
 * PostgreSQL, conforme descrito na Seção 4.2 do TCC.
 *
 * Uso:
 *   Connection::configure(
 *       dsn: 'pgsql:host=localhost;port=5432;dbname=tcc_orm',
 *       username: 'postgres',
 *       password: 'secret'
 *   );
 *
 *   $pdo = Connection::getInstance();
 */
final class Connection
{
    private static ?PDO $instance = null;

    private static ?string $dsn = null;
    private static ?string $username = null;
    private static ?string $password = null;

    /**
     * Construtor privado: impede instanciação direta da classe.
     * A conexão deve sempre passar por configure() + getInstance().
     */
    private function __construct()
    {
    }

    /**
     * Define os parâmetros de conexão. Deve ser chamado uma única vez,
     * geralmente no bootstrap da aplicação, antes de qualquer operação
     * do ORM.
     */
    public static function configure(string $dsn, string $username, string $password): void
    {
        self::$dsn = $dsn;
        self::$username = $username;
        self::$password = $password;

        // Se já havia uma conexão ativa, ela é descartada para que uma
        // nova seja criada com os parâmetros atualizados.
        self::$instance = null;
    }

    /**
     * Retorna a instância única de PDO, criando a conexão na primeira
     * chamada (lazy initialization).
     *
     * @throws RuntimeException se configure() não foi chamado antes,
     *                          ou se a conexão com o banco falhar.
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            if (self::$dsn === null) {
                throw new RuntimeException(
                    'Conexão não configurada. Chame Connection::configure() '
                    . 'antes de usar o ORM.'
                );
            }

            try {
                self::$instance = new PDO(
                    self::$dsn,
                    self::$username,
                    self::$password,
                    [
                        // Erros do PDO viram exceções, nunca falham silenciosamente.
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        // Desativa emulação de prepared statements: o
                        // PostgreSQL executa os statements nativamente,
                        // o que é mais seguro contra SQL Injection.
                        PDO::ATTR_EMULATE_PREPARES => false,
                    ]
                );
            } catch (PDOException $e) {
                throw new RuntimeException(
                    'Falha ao conectar ao banco de dados: ' . $e->getMessage(),
                    previous: $e
                );
            }
        }

        return self::$instance;
    }

    /**
     * Reseta a conexão ativa. Útil principalmente em testes automatizados,
     * para garantir isolamento entre casos de teste.
     */
    public static function reset(): void
    {
        self::$instance = null;
    }
}
