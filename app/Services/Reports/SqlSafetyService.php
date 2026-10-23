<?php

namespace App\Services\Reports;

class SqlSafetyService
{
    /**
     * This is a fail-closed screening step, not a Firebird SQL parser or execution authorization.
     *
     * @param  array<string, mixed>  $catalog
     * @return array<int, string>
     */
    public function screen(string $sql, array $catalog): array
    {
        $errors = [];
        $cleaned = trim($sql);

        if (! preg_match('/^SELECT\s/i', $cleaned)) {
            $errors[] = 'A consulta precisa começar com SELECT.';
        }

        if (preg_match('/;|--|\/\*|\*\//', $cleaned)) {
            $errors[] = 'Comentários e múltiplas instruções não são aceitos.';
        }

        if (preg_match('/\b(INSERT|UPDATE|DELETE|MERGE|CREATE|ALTER|DROP|EXECUTE|PROCEDURE|TRIGGER|GRANT|REVOKE|COMMIT|ROLLBACK)\b/i', $cleaned)) {
            $errors[] = 'A consulta contém comandos não permitidos.';
        }

        $availableTables = array_map(fn (array $table): string => mb_strtoupper($table['name']), $catalog['tables'] ?? []);
        preg_match_all('/\b(?:FROM|JOIN)\s+("(?:[^"]|"")+"|[A-Z_][A-Z0-9_$]*)/i', $cleaned, $matches);
        if ($matches[1] === []) {
            $errors[] = 'Nenhuma tabela conhecida foi identificada.';
        }

        foreach ($matches[1] as $identifier) {
            $name = trim($identifier, '"');
            if (! in_array(mb_strtoupper($name), $availableTables, true)) {
                $errors[] = "A tabela {$name} não consta no catálogo.";
            }
        }

        return array_values(array_unique($errors));
    }
}
