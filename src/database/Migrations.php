<?php

namespace AuthToken\database;

use Dotenv\Dotenv;
use PDOException;
use Exception;

/**
 * Make the migrations
 */
class Migrations
{

    public function makeMigrations(): string
    {

        // Cria uma instância do Dotenv
        $dotenv = Dotenv::createImmutable(realpath(__DIR__ . '/../'));
        // Carrega as variáveis do arquivo .env
        $dotenv->load();

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

        $connection = new ConnectionDB();
        $cnx = $connection->connect($_ENV['DB_HOSTNAME'], $_ENV['DB_USER'], $_ENV['DB_PASSWORD'], $_ENV['DB_DATABASE']);
        if (!$cnx) {
            return "\033[31mUnable to connect to the database.\033[0m\n";
        }
        try {
            // Execute as queries corretamente
            $stmt1 = $cnx->prepare($query_created_table_tokens);
            $stmt1->execute();

            $stmt2 = $cnx->prepare($query_created_table_blacklist);
            $stmt2->execute();
            return "\033[32mMigrations performed successfully.\033[0m\n";
        } catch (PDOException | Exception $e) {
            // Registre o erro e relance a exceção
            error_log("\033[31m".$e->getMessage()."\033[0m\n");
            die;
        }

    }

}