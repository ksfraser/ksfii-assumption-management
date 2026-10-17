<?php

declare(strict_types=1);

namespace KSFII\AssumptionManagement;

/**
 * Contract for assumption storage implementations.
 *
 * Defines the interface that all assumption storage backends must implement,
 * allowing for different storage mechanisms (database, file, etc.) to be used
 * interchangeably through dependency injection.
 */
interface AssumptionStorageInterface
{
    /**
     * Store an assumption definition.
     *
     * @param AssumptionDefinition $assumption The assumption to store
     * @return bool True on success
     * @throws AssumptionStorageException If storage fails
     */
    public function store(AssumptionDefinition $assumption): bool;

    /**
     * Retrieve an assumption by key.
     *
     * @param string $key The assumption key
     * @return AssumptionDefinition|null The assumption or null if not found
     * @throws AssumptionStorageException If retrieval fails
     */
    public function retrieve(string $key): ?AssumptionDefinition;

    /**
     * Retrieve all assumptions.
     *
     * @return array<string, AssumptionDefinition> Map of keys to assumptions
     * @throws AssumptionStorageException If retrieval fails
     */
    public function retrieveAll(): array;

    /**
     * Retrieve assumptions by category.
     *
     * @param string $category The category to filter by
     * @return array<string, AssumptionDefinition> Map of keys to assumptions
     * @throws AssumptionStorageException If retrieval fails
     */
    public function retrieveByCategory(string $category): array;

    /**
     * Delete an assumption by key.
     *
     * @param string $key The assumption key
     * @return bool True if deleted, false if not found
     * @throws AssumptionStorageException If deletion fails
     */
    public function delete(string $key): bool;

    /**
     * Check if an assumption exists.
     *
     * @param string $key The assumption key
     * @return bool True if exists
     * @throws AssumptionStorageException If check fails
     */
    public function exists(string $key): bool;

    /**
     * Get all available categories.
     *
     * @return array<string> List of categories
     * @throws AssumptionStorageException If retrieval fails
     */
    public function getCategories(): array;

    /**
     * Store a client-specific assumption override.
     *
     * @param string $clientId The client identifier
     * @param string $assumptionKey The assumption key
     * @param mixed $value The overridden value
     * @return bool True on success
     * @throws AssumptionStorageException If storage fails
     */
    public function storeClientOverride(string $clientId, string $assumptionKey, mixed $value): bool;

    /**
     * Retrieve client-specific assumption overrides.
     *
     * @param string $clientId The client identifier
     * @return array<string, mixed> Map of assumption keys to overridden values
     * @throws AssumptionStorageException If retrieval fails
     */
    public function retrieveClientOverrides(string $clientId): array;

    /**
     * Delete a client-specific assumption override.
     *
     * @param string $clientId The client identifier
     * @param string $assumptionKey The assumption key
     * @return bool True if deleted, false if not found
     * @throws AssumptionStorageException If deletion fails
     */
    public function deleteClientOverride(string $clientId, string $assumptionKey): bool;
}