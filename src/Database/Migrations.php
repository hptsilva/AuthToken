<?php

namespace AuthToken\Database;

use AuthToken\Exception\ErrorConnection;
use Dotenv\Dotenv;
use PDOException;
use Exception;
use PDO;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Make the migrations
 */
class Migrations
{

    /**
     * @throws ErrorConnection
     */
    public function makeMigrations(OutputInterface $output): Table|string
    {
        $dotenv = Dotenv::createImmutable(getcwd());
        $dotenv->load();

        $database = $_ENV['AUTHTOKEN_DB_DATABASE'];

        $host = $_ENV['AUTHTOKEN_DB_HOSTNAME'];
        try {
            if ($_ENV['AUTHTOKEN_DB_CONNECTION'] == 'mysql' || $_ENV['AUTHTOKEN_DB_CONNECTION'] == 'mariadb') {
                $queryVerifyDatabase = "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$database'";
                $options = [
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ];
                $connection = new PDO("mysql:host=$host", $_ENV['AUTHTOKEN_DB_USER'], $_ENV['AUTHTOKEN_DB_PASSWORD'], $options);
                $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $stmt = $connection->query($queryVerifyDatabase);
                if (!$stmt->fetchColumn()) {
                    $query_created_database = "CREATE DATABASE {$_ENV['AUTHTOKEN_DB_DATABASE']}";
                    $connection->query($query_created_database);
                }
                $connection = new ConnectionDB();
                $cnx = $connection->connect();
            } else if ($_ENV['AUTHTOKEN_DB_CONNECTION'] == 'sqlite') {
                $path = $_ENV['AUTHTOKEN_DB_SQLITE_PATH'] ?? __DIR__;
                $cnx = new PDO("sqlite:" . $path . "/$database.sqlite");
                $cnx->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            } else {
                return "\033[31mUnknown DB connection\033[0m\n";
            }
        } catch (PDOException $e) {
            $error = $e->getMessage();
            return "\033[31m$error\033[0m\n";
        }

        if ($_ENV['AUTHTOKEN_USER_TYPE'] !== 'int' && $_ENV['AUTHTOKEN_USER_TYPE'] !== 'INT' && !preg_match('/^varchar\(\d+\)$/', $_ENV['AUTHTOKEN_USER_TYPE']) && !preg_match('/^VARCHAR\(\d+\)$/', $_ENV['AUTHTOKEN_USER_TYPE'])) {
            throw new ErrorConnection("\033[31mUnknown AUTHTOKEN_USER_TYPE\033[0m\n");
        }

        $type = $_ENV['AUTHTOKEN_USER_TYPE'];
        $queryCreatedTableRefreshTokens = "CREATE TABLE refresh_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id $type NOT NULL,
        token VARCHAR(255) NOT NULL UNIQUE,
        expires_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";

        if ($cnx instanceof PDOException) {
            $error = $cnx->getMessage();
            throw new ErrorConnection("\033[31m$error\033[0m\n");
        }

        $table = new Table($output);
        $table->setHeaders(['Migrations', 'Status']);
        $rows = [];
        try {
            $stmt1 = $cnx->prepare($queryCreatedTableRefreshTokens);
            $stmt1->execute();
            $rows[] = ["refresh_tokens", "\033[32mOk\033[0m"];
            $table->setRows($rows);
            return $table;
        } catch (PDOException | Exception $e) {
            $rows[] = ["refresh_tokens", "\033[31mFailed\033[0m"];
            $table->setRows($rows);
            $error = $e->getMessage();
            echo "\033[31m$error\033[0m\n";
            return $table;
        }
    }
}