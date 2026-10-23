<?php

namespace App\Services\Firebird;

use App\Models\FirebirdConnection;
use App\Models\SchemaSnapshot;

class SchemaInspector
{
    public function __construct(private ConnectionService $connections) {}

    public function synchronize(FirebirdConnection $connection): SchemaSnapshot
    {
        $pdo = $this->connections->connect($connection);
        $tables = [];

        foreach ($pdo->query('SELECT TRIM(R.RDB$RELATION_NAME) AS NAME, CASE WHEN R.RDB$VIEW_BLR IS NULL THEN 0 ELSE 1 END AS IS_VIEW FROM RDB$RELATIONS R WHERE COALESCE(R.RDB$SYSTEM_FLAG, 0) = 0 ORDER BY R.RDB$RELATION_NAME') as $row) {
            $name = rtrim((string) $row['NAME']);
            $tables[$name] = [
                'name' => $name,
                'kind' => (int) $row['IS_VIEW'] === 1 ? 'view' : 'table',
                'columns' => [],
                'primary_key' => [],
                'indexes' => [],
            ];
        }

        $columns = $pdo->query('SELECT TRIM(RF.RDB$RELATION_NAME) AS TABLE_NAME, TRIM(RF.RDB$FIELD_NAME) AS COLUMN_NAME, F.RDB$FIELD_TYPE AS FIELD_TYPE, F.RDB$FIELD_SUB_TYPE AS FIELD_SUB_TYPE, F.RDB$FIELD_SCALE AS FIELD_SCALE, F.RDB$FIELD_PRECISION AS FIELD_PRECISION, F.RDB$CHARACTER_LENGTH AS CHARACTER_LENGTH, COALESCE(RF.RDB$NULL_FLAG, F.RDB$NULL_FLAG, 0) AS NOT_NULL FROM RDB$RELATION_FIELDS RF JOIN RDB$FIELDS F ON F.RDB$FIELD_NAME = RF.RDB$FIELD_SOURCE JOIN RDB$RELATIONS R ON R.RDB$RELATION_NAME = RF.RDB$RELATION_NAME WHERE COALESCE(R.RDB$SYSTEM_FLAG, 0) = 0 ORDER BY RF.RDB$RELATION_NAME, RF.RDB$FIELD_POSITION');

        foreach ($columns as $row) {
            $table = rtrim((string) $row['TABLE_NAME']);
            if (! isset($tables[$table])) {
                continue;
            }

            $tables[$table]['columns'][] = [
                'name' => rtrim((string) $row['COLUMN_NAME']),
                'type_code' => (int) $row['FIELD_TYPE'],
                'subtype' => (int) $row['FIELD_SUB_TYPE'],
                'scale' => (int) $row['FIELD_SCALE'],
                'precision' => $row['FIELD_PRECISION'] === null ? null : (int) $row['FIELD_PRECISION'],
                'character_length' => $row['CHARACTER_LENGTH'] === null ? null : (int) $row['CHARACTER_LENGTH'],
                'nullable' => (int) $row['NOT_NULL'] !== 1,
            ];
        }

        $indexRows = $pdo->query('SELECT TRIM(I.RDB$RELATION_NAME) AS TABLE_NAME, TRIM(I.RDB$INDEX_NAME) AS INDEX_NAME, TRIM(S.RDB$FIELD_NAME) AS COLUMN_NAME, I.RDB$UNIQUE_FLAG AS UNIQUE_FLAG FROM RDB$INDICES I JOIN RDB$INDEX_SEGMENTS S ON S.RDB$INDEX_NAME = I.RDB$INDEX_NAME JOIN RDB$RELATIONS R ON R.RDB$RELATION_NAME = I.RDB$RELATION_NAME WHERE COALESCE(R.RDB$SYSTEM_FLAG, 0) = 0 ORDER BY I.RDB$RELATION_NAME, I.RDB$INDEX_NAME, S.RDB$FIELD_POSITION');
        foreach ($indexRows as $row) {
            $table = rtrim((string) $row['TABLE_NAME']);
            $index = rtrim((string) $row['INDEX_NAME']);
            if (isset($tables[$table])) {
                $tables[$table]['indexes'][$index]['name'] = $index;
                $tables[$table]['indexes'][$index]['unique'] = (int) $row['UNIQUE_FLAG'] === 1;
                $tables[$table]['indexes'][$index]['columns'][] = rtrim((string) $row['COLUMN_NAME']);
            }
        }

        $relationships = [];
        $constraints = $pdo->query("SELECT TRIM(RC.RDB\$RELATION_NAME) AS TABLE_NAME, TRIM(ISG.RDB\$FIELD_NAME) AS COLUMN_NAME, TRIM(RC.RDB\$CONSTRAINT_TYPE) AS TYPE, TRIM(RC.RDB\$CONSTRAINT_NAME) AS CONSTRAINT_NAME, ISG.RDB\$FIELD_POSITION AS POSITION FROM RDB\$RELATION_CONSTRAINTS RC JOIN RDB\$INDEX_SEGMENTS ISG ON ISG.RDB\$INDEX_NAME = RC.RDB\$INDEX_NAME WHERE RC.RDB\$CONSTRAINT_TYPE IN ('PRIMARY KEY', 'FOREIGN KEY') ORDER BY RC.RDB\$CONSTRAINT_NAME, ISG.RDB\$FIELD_POSITION");
        $constraintColumns = [];

        foreach ($constraints as $row) {
            $name = rtrim((string) $row['CONSTRAINT_NAME']);
            $constraintColumns[$name]['table'] = rtrim((string) $row['TABLE_NAME']);
            $constraintColumns[$name]['type'] = rtrim((string) $row['TYPE']);
            $constraintColumns[$name]['columns'][] = rtrim((string) $row['COLUMN_NAME']);

            if ($constraintColumns[$name]['type'] === 'PRIMARY KEY') {
                $tables[$constraintColumns[$name]['table']]['primary_key'][] = rtrim((string) $row['COLUMN_NAME']);
            }
        }

        $foreignKeys = $pdo->query("SELECT TRIM(RC.RDB\$CONSTRAINT_NAME) AS FK_NAME, TRIM(REF.RDB\$CONST_NAME_UQ) AS TARGET_NAME FROM RDB\$RELATION_CONSTRAINTS RC JOIN RDB\$REF_CONSTRAINTS REF ON REF.RDB\$CONSTRAINT_NAME = RC.RDB\$CONSTRAINT_NAME WHERE RC.RDB\$CONSTRAINT_TYPE = 'FOREIGN KEY'");

        foreach ($foreignKeys as $row) {
            $from = $constraintColumns[rtrim((string) $row['FK_NAME'])] ?? null;
            $to = $constraintColumns[rtrim((string) $row['TARGET_NAME'])] ?? null;
            if ($from && $to) {
                $relationships[] = ['from_table' => $from['table'], 'from_columns' => $from['columns'], 'to_table' => $to['table'], 'to_columns' => $to['columns'], 'origin' => 'declared_fk'];
            }
        }

        foreach ($tables as &$table) {
            $table['indexes'] = array_values($table['indexes']);
        }
        unset($table);

        $catalog = ['tables' => array_values($tables), 'relationships' => $relationships];
        $fingerprint = hash('sha256', json_encode($catalog, JSON_THROW_ON_ERROR));

        return $connection->snapshots()->create(['fingerprint' => $fingerprint, 'catalog' => $catalog]);
    }
}
