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
        $dotenv = Dotenv::createImmutable(realpath(__DIR__ . '/../'));
        $dotenv->load();

        $connection = new ConnectionDB();
        $cnx = $connection->connect();
        if ($cnx instanceof PDOException) {
            $error = $cnx->getMessage();
            return "\033[31m$error\033[0m\n";
        }

        $query_drop_table_tokens = "DROP TABLE IF EXISTS `tokens`";
        $query_drop_table_blacklist = "DROP TABLE IF EXISTS `blacklist_tokens`";

        try {
            $stmt1 = $cnx->prepare($query_drop_table_tokens);
            $stmt1->execute();
            $stmt2 = $cnx->prepare($query_drop_table_blacklist);
            $stmt2->execute();
            return "\033[32mRollback successful.\033[0m\n";
        } catch (PDOException $e) {
            return $e->getMessage();
        }
    }

}