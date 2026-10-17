<?php

declare(strict_types=1);

namespace KSFII\AssumptionManagement;

use PDO;
use PDOException;

/**
 * Database-backed assumption storage implementation.
 *
 * Supports both MySQL and SQLite databases for storing assumption definitions
 * and client-specific overrides. Uses PDO for database abstraction.
 */
class DatabaseAssumptionStorage implements AssumptionStorageInterface
{
    private const ASSUMPTIONS_TABLE = 'canada_life_assumptions';
    private const CLIENT_OVERRIDES_TABLE = 'canada_life_client_assumption_overrides';

    /**
     * @param PDO $pdo The database connection
     * @param string $tablePrefix Optional table prefix
     */
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $tablePrefix = ''
    ) {
        $this->initializeTables();
    }

    /**
     * Initialize database tables if they don't exist.
     */
    private function initializeTables(): void
    {
        try {
            $this->pdo->beginTransaction();

            // Create assumptions table
            $assumptionsTable = $this->tablePrefix . self::ASSUMPTIONS_TABLE;
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS {$assumptionsTable} (
                    assumption_key VARCHAR(255) PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    description TEXT,
                    default_value TEXT,
                    data_type VARCHAR(50) NOT NULL,
                    validation_rules TEXT,
                    category VARCHAR(100) DEFAULT 'general',
                    client_overrideable BOOLEAN DEFAULT 1,
                    effective_from DATETIME NULL,
                    effective_until DATETIME NULL,
                    version VARCHAR(50) NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            ");

            // Create client overrides table
            $overridesTable = $this->tablePrefix . self::CLIENT_OVERRIDES_TABLE;
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS {$overridesTable} (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    client_id VARCHAR(255) NOT NULL,
                    assumption_key VARCHAR(255) NOT NULL,
                    override_value TEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE(client_id, assumption_key)
                )
            ");

            $this->pdo->commit();
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw AssumptionStorageException::operationFailed(
                'initialize_tables',
                'Failed to initialize database tables: ' . $e->getMessage(),
                ['pdo_error' => $e->errorInfo]
            );
        }
    }

    /**
     * Store an assumption definition.
     */
    public function store(AssumptionDefinition $assumption): bool
    {
        try {
            $table = $this->tablePrefix . self::ASSUMPTIONS_TABLE;

            $stmt = $this->pdo->prepare("
                INSERT OR REPLACE INTO {$table} (
                    assumption_key, name, description, default_value, data_type,
                    validation_rules, category, client_overrideable,
                    effective_from, effective_until, version, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ");

            $result = $stmt->execute([
                $assumption->key,
                $assumption->name,
                $assumption->description,
                $this->serializeValue($assumption->defaultValue),
                $assumption->dataType,
                json_encode($assumption->validationRules),
                $assumption->category,
                $assumption->clientOverrideable ? 1 : 0,
                $assumption->effectiveFrom?->format('Y-m-d H:i:s'),
                $assumption->effectiveUntil?->format('Y-m-d H:i:s'),
                $assumption->version,
            ]);

            return $result !== false;
        } catch (PDOException $e) {
            throw AssumptionStorageException::operationFailed(
                'store',
                'Failed to store assumption: ' . $e->getMessage(),
                ['key' => $assumption->key, 'pdo_error' => $e->errorInfo]
            );
        }
    }

    /**
     * Retrieve an assumption by key.
     */
    public function retrieve(string $key): ?AssumptionDefinition
    {
        try {
            $table = $this->tablePrefix . self::ASSUMPTIONS_TABLE;

            $stmt = $this->pdo->prepare("
                SELECT * FROM {$table} WHERE assumption_key = ?
            ");
            $stmt->execute([$key]);

            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return null;
            }

            return $this->rowToAssumption($row);
        } catch (PDOException $e) {
            throw AssumptionStorageException::operationFailed(
                'retrieve',
                'Failed to retrieve assumption: ' . $e->getMessage(),
                ['key' => $key, 'pdo_error' => $e->errorInfo]
            );
        }
    }

    /**
     * Retrieve all assumptions.
     */
    public function retrieveAll(): array
    {
        try {
            $table = $this->tablePrefix . self::ASSUMPTIONS_TABLE;

            $stmt = $this->pdo->query("SELECT * FROM {$table} ORDER BY category, assumption_key");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $assumptions = [];
            foreach ($rows as $row) {
                $assumption = $this->rowToAssumption($row);
                $assumptions[$assumption->key] = $assumption;
            }

            return $assumptions;
        } catch (PDOException $e) {
            throw AssumptionStorageException::operationFailed(
                'retrieve_all',
                'Failed to retrieve all assumptions: ' . $e->getMessage(),
                ['pdo_error' => $e->errorInfo]
            );
        }
    }

    /**
     * Retrieve assumptions by category.
     */
    public function retrieveByCategory(string $category): array
    {
        try {
            $table = $this->tablePrefix . self::ASSUMPTIONS_TABLE;

            $stmt = $this->pdo->prepare("
                SELECT * FROM {$table} WHERE category = ? ORDER BY assumption_key
            ");
            $stmt->execute([$category]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $assumptions = [];
            foreach ($rows as $row) {
                $assumption = $this->rowToAssumption($row);
                $assumptions[$assumption->key] = $assumption;
            }

            return $assumptions;
        } catch (PDOException $e) {
            throw AssumptionStorageException::operationFailed(
                'retrieve_by_category',
                'Failed to retrieve assumptions by category: ' . $e->getMessage(),
                ['category' => $category, 'pdo_error' => $e->errorInfo]
            );
        }
    }

    /**
     * Delete an assumption by key.
     */
    public function delete(string $key): bool
    {
        try {
            $table = $this->tablePrefix . self::ASSUMPTIONS_TABLE;

            $stmt = $this->pdo->prepare("DELETE FROM {$table} WHERE assumption_key = ?");
            $stmt->execute([$key]);

            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            throw AssumptionStorageException::operationFailed(
                'delete',
                'Failed to delete assumption: ' . $e->getMessage(),
                ['key' => $key, 'pdo_error' => $e->errorInfo]
            );
        }
    }

    /**
     * Check if an assumption exists.
     */
    public function exists(string $key): bool
    {
        try {
            $table = $this->tablePrefix . self::ASSUMPTIONS_TABLE;

            $stmt = $this->pdo->prepare("SELECT 1 FROM {$table} WHERE assumption_key = ?");
            $stmt->execute([$key]);

            return (bool) $stmt->fetchColumn();
        } catch (PDOException $e) {
            throw AssumptionStorageException::operationFailed(
                'exists',
                'Failed to check assumption existence: ' . $e->getMessage(),
                ['key' => $key, 'pdo_error' => $e->errorInfo]
            );
        }
    }

    /**
     * Get all available categories.
     */
    public function getCategories(): array
    {
        try {
            $table = $this->tablePrefix . self::ASSUMPTIONS_TABLE;

            $stmt = $this->pdo->query("SELECT DISTINCT category FROM {$table} ORDER BY category");
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            throw AssumptionStorageException::operationFailed(
                'get_categories',
                'Failed to get categories: ' . $e->getMessage(),
                ['pdo_error' => $e->errorInfo]
            );
        }
    }

    /**
     * Store a client-specific assumption override.
     */
    public function storeClientOverride(string $clientId, string $assumptionKey, mixed $value): bool
    {
        try {
            $table = $this->tablePrefix . self::CLIENT_OVERRIDES_TABLE;

            $stmt = $this->pdo->prepare("
                INSERT OR REPLACE INTO {$table} (client_id, assumption_key, override_value, created_at, updated_at)
                VALUES (?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ");

            $result = $stmt->execute([
                $clientId,
                $assumptionKey,
                $this->serializeValue($value),
            ]);

            return $result !== false;
        } catch (PDOException $e) {
            throw AssumptionStorageException::operationFailed(
                'store_client_override',
                'Failed to store client override: ' . $e->getMessage(),
                ['client_id' => $clientId, 'key' => $assumptionKey, 'pdo_error' => $e->errorInfo]
            );
        }
    }

    /**
     * Retrieve client-specific assumption overrides.
     */
    public function retrieveClientOverrides(string $clientId): array
    {
        try {
            $table = $this->tablePrefix . self::CLIENT_OVERRIDES_TABLE;

            $stmt = $this->pdo->prepare("
                SELECT assumption_key, override_value FROM {$table} WHERE client_id = ?
            ");
            $stmt->execute([$clientId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $overrides = [];
            foreach ($rows as $row) {
                $overrides[$row['assumption_key']] = $this->unserializeValue($row['override_value']);
            }

            return $overrides;
        } catch (PDOException $e) {
            throw AssumptionStorageException::operationFailed(
                'retrieve_client_overrides',
                'Failed to retrieve client overrides: ' . $e->getMessage(),
                ['client_id' => $clientId, 'pdo_error' => $e->errorInfo]
            );
        }
    }

    /**
     * Delete a client-specific assumption override.
     */
    public function deleteClientOverride(string $clientId, string $assumptionKey): bool
    {
        try {
            $table = $this->tablePrefix . self::CLIENT_OVERRIDES_TABLE;

            $stmt = $this->pdo->prepare("
                DELETE FROM {$table} WHERE client_id = ? AND assumption_key = ?
            ");
            $stmt->execute([$clientId, $assumptionKey]);

            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            throw AssumptionStorageException::operationFailed(
                'delete_client_override',
                'Failed to delete client override: ' . $e->getMessage(),
                ['client_id' => $clientId, 'key' => $assumptionKey, 'pdo_error' => $e->errorInfo]
            );
        }
    }

    /**
     * Convert a database row to an AssumptionDefinition.
     */
    private function rowToAssumption(array $row): AssumptionDefinition
    {
        return new AssumptionDefinition(
            $row['assumption_key'],
            $row['name'],
            $row['description'],
            $this->unserializeValue($row['default_value']),
            $row['data_type'],
            json_decode($row['validation_rules'] ?? '{}', true),
            $row['category'],
            (bool) $row['client_overrideable'],
            $row['effective_from'] ? new \DateTimeImmutable($row['effective_from']) : null,
            $row['effective_until'] ? new \DateTimeImmutable($row['effective_until']) : null,
            $row['version']
        );
    }

    /**
     * Serialize a value for database storage.
     */
    private function serializeValue(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    /**
     * Unserialize a value from database storage.
     */
    private function unserializeValue(string $value): mixed
    {
        return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
    }
}