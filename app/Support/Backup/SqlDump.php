<?php

namespace App\Support\Backup;

use Illuminate\Support\Facades\DB;

/**
 * Writes the whole database as plain SQL, compressed (.sql.gz), using only PHP. It does not need the mysqldump program,
 * which a shared web host may not allow. The file can be restored by importing it (for example in phpMyAdmin).
 * Passwords of the website (.env) are never in it. The table "users" does hold the hashed passwords of the people, so keep the file private.
 */
class SqlDump
{
    /** Tables whose rows are not worth keeping (temporary data). Their structure is still written. */
    private const NO_DATA = ['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'];

    private const CHUNK = 200;

    /** @return array{tables: int, rows: int} */
    public static function write(string $gzPath): array
    {
        $pdo = DB::connection()->getPdo();
        $driver = DB::connection()->getDriverName();
        $gz = gzopen($gzPath, 'wb6');
        if ($gz === false) {
            throw new \RuntimeException('The backup file could not be created.');
        }

        $tables = static::tables($driver);
        $out = fn (string $text) => gzwrite($gz, $text);
        $out("-- InfoFluxes backup\n-- Made: ".now()->toDateTimeString()."\n-- Tables: ".count($tables)."\n\n");
        if ($driver === 'sqlite') {
            $out("PRAGMA foreign_keys=OFF;\n\n");
        } else {
            $out("SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");
        }

        $rows = 0;
        foreach ($tables as $table) {
            $out("-- Table: {$table}\nDROP TABLE IF EXISTS `{$table}`;\n".static::createSql($pdo, $driver, $table).";\n\n");
            if (in_array($table, self::NO_DATA, true)) {
                continue;
            }
            $offset = 0;
            while (true) {
                $batch = $pdo->query('SELECT * FROM `'.$table.'` ORDER BY 1 LIMIT '.self::CHUNK.' OFFSET '.$offset)->fetchAll(\PDO::FETCH_ASSOC);
                if ($batch === []) {
                    break;
                }
                $columns = '`'.implode('`,`', array_keys($batch[0])).'`';
                $values = array_map(fn (array $row) => '('.implode(',', array_map(fn ($v) => static::value($pdo, $v), $row)).')', $batch);
                $out("INSERT INTO `{$table}` ({$columns}) VALUES\n".implode(",\n", $values).";\n");
                $rows += count($batch);
                $offset += self::CHUNK;
            }
            $out("\n");
        }

        $out($driver === 'sqlite' ? "PRAGMA foreign_keys=ON;\n" : "SET FOREIGN_KEY_CHECKS=1;\n");
        $out('-- End of backup: '.count($tables).' tables, '.$rows." rows\n");
        gzclose($gz);

        return ['tables' => count($tables), 'rows' => $rows];
    }

    /** Is this file a complete backup? It is read from start to end and must finish with the end line. */
    public static function isComplete(string $gzPath): bool
    {
        $gz = @gzopen($gzPath, 'rb');
        if ($gz === false) {
            return false;
        }
        $last = '';
        while (! gzeof($gz)) {
            $line = gzgets($gz);
            if ($line === false) {
                break;
            }
            if (trim($line) !== '') {
                $last = $line;
            }
        }
        gzclose($gz);

        return str_starts_with($last, '-- End of backup');
    }

    /** @return list<string> */
    private static function tables(string $driver): array
    {
        if ($driver === 'sqlite') {
            return array_column(DB::select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name"), 'name');
        }

        return array_map(fn ($r) => (string) array_values((array) $r)[0], DB::select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'"));
    }

    private static function createSql(\PDO $pdo, string $driver, string $table): string
    {
        if ($driver === 'sqlite') {
            return (string) $pdo->query("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = ".$pdo->quote($table))->fetchColumn();
        }
        $row = $pdo->query('SHOW CREATE TABLE `'.$table.'`')->fetch(\PDO::FETCH_NUM);

        return (string) $row[1];
    }

    private static function value(\PDO $pdo, mixed $v): string
    {
        if ($v === null) {
            return 'NULL';
        }
        if (is_bool($v)) {
            return $v ? '1' : '0';
        }
        if (is_int($v)) {
            return (string) $v;
        }
        if (is_float($v)) {
            return rtrim(rtrim(sprintf('%.15F', $v), '0'), '.') ?: '0';
        }

        return $pdo->quote((string) $v);
    }
}
