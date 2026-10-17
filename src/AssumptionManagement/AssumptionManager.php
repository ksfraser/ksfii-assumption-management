<?php

declare(strict_types=1);

namespace KSFII\AssumptionManagement;

use DateTimeImmutable;
use Psr\Log\LoggerInterface;

/**
 * Main service class for managing financial calculation assumptions.
 *
 * Provides high-level operations for assumption management, including
 * CRUD operations, client overrides, and assumption resolution.
 * Uses dependency injection for storage backends.
 */
class AssumptionManager
{
    /**
     * @param AssumptionStorageInterface $storage The storage backend
     * @param LoggerInterface|null $logger Optional logger
     */
    public function __construct(
        private readonly AssumptionStorageInterface $storage,
        private readonly ?LoggerInterface $logger = null
    ) {}

    /**
     * Define a new assumption.
     *
     * @param AssumptionDefinition $assumption The assumption to define
     * @return bool True on success
     * @throws AssumptionStorageException If storage fails
     */
    public function defineAssumption(AssumptionDefinition $assumption): bool
    {
        $this->logger?->info('Defining assumption', [
            'key' => $assumption->key,
            'category' => $assumption->category
        ]);

        if (!$assumption->validate($assumption->defaultValue)) {
            throw AssumptionStorageException::operationFailed(
                'define_assumption',
                'Default value fails validation',
                ['key' => $assumption->key, 'default_value' => $assumption->defaultValue]
            );
        }

        return $this->storage->store($assumption);
    }

    /**
     * Get an assumption definition by key.
     *
     * @param string $key The assumption key
     * @return AssumptionDefinition|null The assumption or null if not found
     */
    public function getAssumption(string $key): ?AssumptionDefinition
    {
        return $this->storage->retrieve($key);
    }

    /**
     * Get all assumption definitions.
     *
     * @return array<string, AssumptionDefinition> Map of keys to assumptions
     */
    public function getAllAssumptions(): array
    {
        return $this->storage->retrieveAll();
    }

    /**
     * Get assumptions by category.
     *
     * @param string $category The category to filter by
     * @return array<string, AssumptionDefinition> Map of keys to assumptions
     */
    public function getAssumptionsByCategory(string $category): array
    {
        return $this->storage->retrieveByCategory($category);
    }

    /**
     * Update an existing assumption.
     *
     * @param string $key The assumption key
     * @param AssumptionDefinition $updatedAssumption The updated assumption
     * @return bool True on success
     * @throws AssumptionStorageException If update fails
     */
    public function updateAssumption(string $key, AssumptionDefinition $updatedAssumption): bool
    {
        if ($key !== $updatedAssumption->key) {
            throw AssumptionStorageException::operationFailed(
                'update_assumption',
                'Cannot change assumption key during update',
                ['old_key' => $key, 'new_key' => $updatedAssumption->key]
            );
        }

        $this->logger?->info('Updating assumption', ['key' => $key]);
        return $this->storage->store($updatedAssumption);
    }

    /**
     * Delete an assumption.
     *
     * @param string $key The assumption key
     * @return bool True if deleted
     */
    public function deleteAssumption(string $key): bool
    {
        $this->logger?->info('Deleting assumption', ['key' => $key]);
        return $this->storage->delete($key);
    }

    /**
     * Check if an assumption exists.
     *
     * @param string $key The assumption key
     * @return bool True if exists
     */
    public function assumptionExists(string $key): bool
    {
        return $this->storage->exists($key);
    }

    /**
     * Get all available categories.
     *
     * @return array<string> List of categories
     */
    public function getCategories(): array
    {
        return $this->storage->getCategories();
    }

    /**
     * Set a client-specific assumption override.
     *
     * @param string $clientId The client identifier
     * @param string $assumptionKey The assumption key
     * @param mixed $value The override value
     * @return bool True on success
     * @throws AssumptionStorageException If the assumption doesn't exist or validation fails
     */
    public function setClientOverride(string $clientId, string $assumptionKey, mixed $value): bool
    {
        $assumption = $this->storage->retrieve($assumptionKey);
        if (!$assumption) {
            throw AssumptionStorageException::operationFailed(
                'set_client_override',
                'Assumption does not exist',
                ['client_id' => $clientId, 'key' => $assumptionKey]
            );
        }

        if (!$assumption->clientOverrideable) {
            throw AssumptionStorageException::operationFailed(
                'set_client_override',
                'Assumption does not allow client overrides',
                ['client_id' => $clientId, 'key' => $assumptionKey]
            );
        }

        if (!$assumption->validate($value)) {
            throw AssumptionStorageException::operationFailed(
                'set_client_override',
                'Override value fails validation',
                ['client_id' => $clientId, 'key' => $assumptionKey, 'value' => $value]
            );
        }

        $this->logger?->info('Setting client override', [
            'client_id' => $clientId,
            'key' => $assumptionKey
        ]);

        return $this->storage->storeClientOverride($clientId, $assumptionKey, $value);
    }

    /**
     * Get client-specific assumption overrides.
     *
     * @param string $clientId The client identifier
     * @return array<string, mixed> Map of assumption keys to override values
     */
    public function getClientOverrides(string $clientId): array
    {
        return $this->storage->retrieveClientOverrides($clientId);
    }

    /**
     * Remove a client-specific assumption override.
     *
     * @param string $clientId The client identifier
     * @param string $assumptionKey The assumption key
     * @return bool True if removed
     */
    public function removeClientOverride(string $clientId, string $assumptionKey): bool
    {
        $this->logger?->info('Removing client override', [
            'client_id' => $clientId,
            'key' => $assumptionKey
        ]);

        return $this->storage->deleteClientOverride($clientId, $assumptionKey);
    }

    /**
     * Get the effective value for an assumption, considering client overrides.
     *
     * @param string $assumptionKey The assumption key
     * @param string|null $clientId Optional client ID for overrides
     * @param DateTimeImmutable|null $effectiveDate Optional date for effective assumptions
     * @return mixed The effective value or null if assumption not found
     */
    public function getEffectiveValue(
        string $assumptionKey,
        ?string $clientId = null,
        ?DateTimeImmutable $effectiveDate = null
    ): mixed {
        $assumption = $this->storage->retrieve($assumptionKey);
        if (!$assumption) {
            return null;
        }

        // Check if assumption is effective
        if (!$assumption->isEffective($effectiveDate)) {
            return null;
        }

        // Check for client override
        if ($clientId !== null) {
            $overrides = $this->storage->retrieveClientOverrides($clientId);
            if (isset($overrides[$assumptionKey])) {
                return $overrides[$assumptionKey];
            }
        }

        return $assumption->defaultValue;
    }

    /**
     * Get all effective values for a client, merging defaults with overrides.
     *
     * @param string|null $clientId Optional client ID for overrides
     * @param DateTimeImmutable|null $effectiveDate Optional date for effective assumptions
     * @return array<string, mixed> Map of assumption keys to effective values
     */
    public function getAllEffectiveValues(
        ?string $clientId = null,
        ?DateTimeImmutable $effectiveDate = null
    ): array {
        $allAssumptions = $this->storage->retrieveAll();
        $effectiveValues = [];

        // Retrieve client overrides once if needed
        $clientOverrides = null;
        if ($clientId !== null) {
            $clientOverrides = $this->storage->retrieveClientOverrides($clientId);
        }

        foreach ($allAssumptions as $assumption) {
            if ($assumption->isEffective($effectiveDate)) {
                $value = $assumption->defaultValue;

                // Apply client override if available
                if ($clientOverrides !== null && isset($clientOverrides[$assumption->key])) {
                    $value = $clientOverrides[$assumption->key];
                }

                $effectiveValues[$assumption->key] = $value;
            }
        }

        return $effectiveValues;
    }

    /**
     * Validate all assumptions and their current values.
     *
     * @return array<string, string> Map of assumption keys to validation errors
     */
    public function validateAllAssumptions(): array
    {
        $assumptions = $this->storage->retrieveAll();
        $errors = [];

        foreach ($assumptions as $assumption) {
            if (!$assumption->validate($assumption->defaultValue)) {
                $errors[$assumption->key] = 'Default value fails validation';
            }
        }

        return $errors;
    }

    /**
     * Get assumptions that need updating (expired or soon to expire).
     *
     * @param int $daysAhead Number of days to look ahead for expiration
     * @return array<string, AssumptionDefinition> Assumptions needing attention
     */
    public function getAssumptionsNeedingUpdate(int $daysAhead = 30): array
    {
        $allAssumptions = $this->storage->retrieveAll();
        $now = new DateTimeImmutable();
        $futureDate = $now->modify("+{$daysAhead} days");

        $needingUpdate = [];

        foreach ($allAssumptions as $assumption) {
            if ($assumption->effectiveUntil !== null &&
                $assumption->effectiveUntil <= $futureDate) {
                $needingUpdate[$assumption->key] = $assumption;
            }
        }

        return $needingUpdate;
    }
}