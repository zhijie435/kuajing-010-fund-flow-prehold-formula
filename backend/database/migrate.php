<?php

require_once __DIR__ . '/../autoload.php';

use App\Services\Database;

class MigrationRunner
{
    private $db;
    private $migrationsDir;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->migrationsDir = __DIR__ . '/migrations';
        $this->ensureMigrationsTable();
    }

    private function ensureMigrationsTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS migrations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            migration VARCHAR(255) NOT NULL,
            batch INTEGER NOT NULL,
            ran_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );";
        $this->db->query($sql);
    }

    public function run(): void
    {
        $files = glob($this->migrationsDir . '/*.php');
        sort($files);
        
        $ranMigrations = $this->getRanMigrations();
        $batch = $this->getNextBatch();
        
        foreach ($files as $file) {
            $migrationName = basename($file, '.php');
            
            if (in_array($migrationName, $ranMigrations)) {
                continue;
            }
            
            require_once $file;
            $className = $this->getClassNameFromFile($file);
            
            if (!class_exists($className)) {
                echo "Warning: Class $className not found in $file\n";
                continue;
            }
            
            $migration = new $className();
            $batch = $migration->batch();
            
            try {
                $this->db->beginTransaction();
                $migration->up($this->db);
                $this->logMigration($migrationName, $batch);
                $this->db->commit();
                echo "Migrated: $migrationName (batch $batch)\n";
                echo "  Description: " . $migration->description() . "\n";
            } catch (Exception $e) {
                $this->db->rollBack();
                echo "Failed: $migrationName\n";
                echo "  Error: " . $e->getMessage() . "\n";
                exit(1);
            }
        }
        
        echo "\nAll migrations completed successfully.\n";
    }

    public function rollback(int $steps = 1): void
    {
        $batches = $this->getRollbackBatches($steps);
        
        foreach (array_reverse($batches) as $batch) {
            $migrations = $this->getMigrationsByBatch($batch);
            
            foreach (array_reverse($migrations) as $migration) {
                $file = $this->migrationsDir . '/' . $migration . '.php';
                
                if (!file_exists($file)) {
                    echo "Warning: Migration file $migration.php not found\n";
                    continue;
                }
                
                require_once $file;
                $className = $this->getClassNameFromFile($file);
                
                if (!class_exists($className)) {
                    echo "Warning: Class $className not found\n";
                    continue;
                }
                
                $migrationInstance = new $className();
                
                try {
                    $this->db->beginTransaction();
                    $migrationInstance->down($this->db);
                    $this->deleteMigration($migration);
                    $this->db->commit();
                    echo "Rolled back: $migration\n";
                } catch (Exception $e) {
                    $this->db->rollBack();
                    echo "Failed to rollback: $migration\n";
                    echo "  Error: " . $e->getMessage() . "\n";
                    exit(1);
                }
            }
        }
        
        echo "\nRollback completed.\n";
    }

    public function status(): void
    {
        $files = glob($this->migrationsDir . '/*.php');
        sort($files);
        $ranMigrations = $this->getRanMigrations();
        
        echo "Migration Status:\n";
        echo str_repeat('-', 60) . "\n";
        
        foreach ($files as $file) {
            $migrationName = basename($file, '.php');
            $status = in_array($migrationName, $ranMigrations) ? '✓ Ran' : '✗ Pending';
            echo sprintf("%-50s %s\n", $migrationName, $status);
        }
    }

    private function getRanMigrations(): array
    {
        $sql = "SELECT migration FROM migrations ORDER BY id";
        return array_column($this->db->fetchAll($sql), 'migration');
    }

    private function getNextBatch(): int
    {
        $sql = "SELECT MAX(batch) as max_batch FROM migrations";
        $result = $this->db->fetch($sql);
        return ($result['max_batch'] ?? 0) + 1;
    }

    private function getRollbackBatches(int $steps): array
    {
        $sql = "SELECT DISTINCT batch FROM migrations ORDER BY batch DESC LIMIT ?";
        return array_column($this->db->fetchAll($sql, [$steps]), 'batch');
    }

    private function getMigrationsByBatch(int $batch): array
    {
        $sql = "SELECT migration FROM migrations WHERE batch = ? ORDER BY id";
        return array_column($this->db->fetchAll($sql, [$batch]), 'migration');
    }

    private function logMigration(string $migrationName, int $batch): void
    {
        $sql = "INSERT INTO migrations (migration, batch) VALUES (?, ?)";
        $this->db->query($sql, [$migrationName, $batch]);
    }

    private function deleteMigration(string $migrationName): void
    {
        $sql = "DELETE FROM migrations WHERE migration = ?";
        $this->db->query($sql, [$migrationName]);
    }

    private function getClassNameFromFile(string $file): string
    {
        $basename = basename($file, '.php');
        $parts = explode('_', $basename);
        $parts = array_slice($parts, 4);
        return implode('', array_map('ucfirst', $parts));
    }
}

$command = $argv[1] ?? 'run';
$runner = new MigrationRunner();

switch ($command) {
    case 'run':
        $runner->run();
        break;
    case 'rollback':
        $steps = isset($argv[2]) ? (int)$argv[2] : 1;
        $runner->rollback($steps);
        break;
    case 'status':
        $runner->status();
        break;
    default:
        echo "Usage: php migrate.php [run|rollback|status] [steps]\n";
        exit(1);
}
