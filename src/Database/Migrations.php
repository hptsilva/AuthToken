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
        $dotenv = Dotenv::createImmutable(realpath(__DIR__ . '/../'));
        $dotenv->load();

        $database = $_ENV['DB_DATABASE'];

        $host = $_ENV['DB_HOSTNAME'];
        try {
            if ($_ENV['DB_CONNECTION'] == 'mysql' || $_ENV['DB_CONNECTION'] == 'mariadb') {
                $query_verify_database = "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$database'";
                $options = [
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ];
                $connection = new PDO("mysql:host=$host", $_ENV['DB_USER'], $_ENV['DB_PASSWORD'], $options);
                $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $stmt = $connection->query($query_verify_database);
                if (!$stmt->fetchColumn()) {
                    $query_created_database = "CREATE DATABASE {$_ENV['DB_DATABASE']}";
                    $connection->query($query_created_database);
                }
                $connection = new ConnectionDB();
                $cnx = $connection->connect();
            } else if ($_ENV['DB_CONNECTION'] == 'sqlite') {
                $cnx = new PDO("sqlite:".__DIR__."/$database.sqlite");
                $cnx->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            } else {
                return "\033[31mUnknown DB connection\033[0m\n";
            }
        } catch (PDOException $e) {
            $error = $e->getMessage();
            return "\033[31m$error\033[0m\n";
        }

        $query_created_table_tokens = "CREATE TABLE tokens (
        token VARCHAR(300) PRIMARY KEY NOT NULL,
        user_id int NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP 
        )";

        $query_created_table_blacklist = "CREATE TABLE blacklist_tokens (
        token VARCHAR(300) PRIMARY KEY NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";

        if ($cnx instanceof PDOException) {
            $error = $cnx->getMessage();
            throw new ErrorConnection("\033[31m$error\033[0m\n");
        }
        $table = new Table($output);
        $table->setHeaders(['Migrations', 'Status',]);
        $rows = [];
        $counter = 0;
        try {
            $stmt1 = $cnx->prepare($query_created_table_tokens);
            if ($stmt1->execute()) {
                $counter++;
            };
            $rows[] = ["tokens", "\033[32mOk\033[0m"];
            $stmt2 = $cnx->prepare($query_created_table_blacklist);
            if ($stmt2->execute()) {
                $counter++;
            };
            $rows[] = ['blacklist_tokens', "\033[32mOk\033[0m"];
            $table->setRows($rows);
            return $table;
        } catch (PDOException | Exception $e) {
            $tables = ['tokens', 'blacklist_tokens'];
            for ($i = $counter; $i < count($tables); $i++) {
                $rows[] = [$tables[$i], "\033[31mFailed\033[0m" ];
            }
            $table->setRows($rows);
            $error = $e->getMessage();
            echo "\033[31m$error\033[0m\n";
            return $table;
        }
    }
}