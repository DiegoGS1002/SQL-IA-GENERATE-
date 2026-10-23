<?php

namespace App\Services\Firebird;

use App\Models\FirebirdConnection;
use PDO;
use RuntimeException;

class ConnectionService
{
    public function connect(FirebirdConnection $connection): PDO
    {
        if (! in_array('firebird', PDO::getAvailableDrivers(), true)) {
            throw new RuntimeException('O driver pdo_firebird não está instalado neste PHP.');
        }

        $database = $connection->database_path;

        if ($database === '' || str_contains($database, "\0") || preg_match('/[;\r\n]/', $database)) {
            throw new RuntimeException('O caminho ou alias do banco Firebird é inválido.');
        }

        $dsn = sprintf(
            'firebird:dbname=%s/%d:%s;charset=%s',
            $connection->host,
            $connection->port,
            $database,
            $connection->charset,
        );

        return new PDO($dsn, $connection->username, $connection->password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 5,
        ]);
    }

    public function test(FirebirdConnection $connection): string
    {
        $statement = $this->connect($connection)->query(
            "SELECT RDB\$GET_CONTEXT('SYSTEM', 'ENGINE_VERSION') AS VERSION FROM RDB\$DATABASE"
        );

        return (string) ($statement->fetch()['VERSION'] ?? 'Versão não identificada');
    }
}
