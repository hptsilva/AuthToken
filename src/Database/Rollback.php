<?php

namespace AuthToken\Database;

use AuthToken\Exception\ErrorConnection;
use Dotenv\Dotenv;
use PDOException;

class Rollback
{

    /**
     * @throws ErrorConnection
     */
    public function makeRollback():string
    {
        $dotenv = Dotenv::createImmutable(realpath(__DIR__ . '/../..'));
        $dotenv->load();

        $connection = new ConnectionDB();
        $cnx = $connection->connect();
        if ($cnx instanceof PDOException) {
            $error = $cnx->getMessage();
            return "\033[31m$error\033[0m\n";
        }

        $query_drop_table_refresh_tokens = "DROP TABLE IF EXISTS `refresh_tokens`";

        try {
            $stmt = $cnx->prepare($query_drop_table_refresh_tokens);
            $stmt->execute();
            return "\033[32mRollback successful.\033[0m\n";
        } catch (PDOException $e) {
            return $e->getMessage();
        }
    }

}