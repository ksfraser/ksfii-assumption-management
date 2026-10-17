<?php

declare(strict_types=1);

namespace KSFII\AssumptionManagement;

use DateTimeImmutable;

/**
 * File-based assumption storage implementation.
 *
 * Supports CSV and JSON file formats for storing assumption definitions
 * and client-specific overrides. Useful for simple deployments or
 * version-controlled assumption data.
 */
class FileAssumptionStorage implements AssumptionStorageInterface
{
    private const ASSUMPTIONS_FILE = 'assumptions.json';
    private const CLIENT_OVERRIDES_DIR = 'client_overrides';

    /**
     * @param string $storagePath The base path for storing files
     * @param string $format The file format ('json' or 'csv')
     */
    public function __construct(
        private readonly string $storagePath,
        private readonly string $format = 'json'
    ) {
        $this->ensureStoragePathExists();
    }

    /**
     * Ensure the storage directory exists.
     */
    private function ensureStoragePathExists(): void
    {
        if (!is_dir($this->storagePath)) {
            if (!mkdir($this->storagePath, 0755, true)) {
                throw AssumptionStorageException::operationFailed(
                    'initialize_storage',
                    'Failed to create storage directory: ' . $this->storagePath
                );
            }
        }

        $clientOverridesPath = $this->getClientOverridesPath();
        if (!is_dir($clientOverridesPath)) {
            if (!mkdir($clientOverridesPath, 0755, true)) {
                throw AssumptionStorageException::operationFailed(
                    'initialize_storage',
                    'Failed to create client overrides directory: ' . $clientOverridesPath
                );
            }
        }
    }

    /**
     * Store an assumption definition.
     */
    public function store(AssumptionDefinition $assumption): bool
    {
        try {
            $assumptions = $this->loadAllAssumptions();
            $assumptions[$assumption->key] = $assumption;
            $this->saveAllAssumptions($assumptions);
            return true;
        } catch (\Exception $e) {
            throw AssumptionStorageException::operationFailed(
                'store',
                'Failed to store assumption: ' . $e->getMessage(),
                ['key' => $assumption->key]
            );
        }
    }

    /**
     * Retrieve an assumption by key.
     */
    public function retrieve(string $key): ?AssumptionDefinition
    {
        try {
            $assumptions = $this->loadAllAssumptions();
            return $assumptions[$key] ?? null;
        } catch (\Exception $e) {
            throw AssumptionStorageException::operationFailed(
                'retrieve',
                'Failed to retrieve assumption: ' . $e->getMessage(),
                ['key' => $key]
            );
        }
    }

    /**
     * Retrieve all assumptions.
     */
    public function retrieveAll(): array
    {
        try {
            return $this->loadAllAssumptions();
        } catch (\Exception $e) {
            throw AssumptionStorageException::operationFailed(
                'retrieve_all',
                'Failed to retrieve all assumptions: ' . $e->getMessage()
            );
        }
    }

    /**
     * Retrieve assumptions by category.
     */
    public function retrieveByCategory(string $category): array
    {
        try {
            $all = $this->loadAllAssumptions();
            return array_filter($all, fn(AssumptionDefinition $a) => $a->category === $category);
        } catch (\Exception $e) {
            throw AssumptionStorageException::operationFailed(
                'retrieve_by_category',
                'Failed to retrieve assumptions by category: ' . $e->getMessage(),
                ['category' => $category]
            );
        }
    }

    /**
     * Delete an assumption by key.
     */
    public function delete(string $key): bool
    {
        try {
            $assumptions = $this->loadAllAssumptions();
            if (!isset($assumptions[$key])) {
                return false;
            }

            unset($assumptions[$key]);
            $this->saveAllAssumptions($assumptions);
            return true;
        } catch (\Exception $e) {
            throw AssumptionStorageException::operationFailed(
                'delete',
                'Failed to delete assumption: ' . $e->getMessage(),
                ['key' => $key]
            );
        }
    }

    /**
     * Check if an assumption exists.
     */
    public function exists(string $key): bool
    {
        try {
            $assumptions = $this->loadAllAssumptions();
            return isset($assumptions[$key]);
        } catch (\Exception $e) {
            throw AssumptionStorageException::operationFailed(
                'exists',
                'Failed to check assumption existence: ' . $e->getMessage(),
                ['key' => $key]
            );
        }
    }

    /**
     * Get all available categories.
     */
    public function getCategories(): array
    {
        try {
            $assumptions = $this->loadAllAssumptions();
            $categories = array_unique(array_map(
                fn(AssumptionDefinition $a) => $a->category,
                $assumptions
            ));
            sort($categories);
            return $categories;
        } catch (\Exception $e) {
            throw AssumptionStorageException::operationFailed(
                'get_categories',
                'Failed to get categories: ' . $e->getMessage()
            );
        }
    }

    /**
     * Store a client-specific assumption override.
     */
    public function storeClientOverride(string $clientId, string $assumptionKey, mixed $value): bool
    {
        try {
            $clientFile = $this->getClientOverrideFile($clientId);
            $overrides = $this->loadClientOverrides($clientId);
            $overrides[$assumptionKey] = $value;
            $this->saveClientOverrides($clientFile, $overrides);
            return true;
        } catch (\Exception $e) {
            throw AssumptionStorageException::operationFailed(
                'store_client_override',
                'Failed to store client override: ' . $e->getMessage(),
                ['client_id' => $clientId, 'key' => $assumptionKey]
            );
        }
    }

    /**
     * Retrieve client-specific assumption overrides.
     */
    public function retrieveClientOverrides(string $clientId): array
    {
        try {
            return $this->loadClientOverrides($clientId);
        } catch (\Exception $e) {
            throw AssumptionStorageException::operationFailed(
                'retrieve_client_overrides',
                'Failed to retrieve client overrides: ' . $e->getMessage(),
                ['client_id' => $clientId]
            );
        }
    }

    /**
     * Delete a client-specific assumption override.
     */
    public function deleteClientOverride(string $clientId, string $assumptionKey): bool
    {
        try {
            $overrides = $this->loadClientOverrides($clientId);
            if (!isset($overrides[$assumptionKey])) {
                return false;
            }

            unset($overrides[$assumptionKey]);
            $clientFile = $this->getClientOverrideFile($clientId);
            $this->saveClientOverrides($clientFile, $overrides);
            return true;
        } catch (\Exception $e) {
            throw AssumptionStorageException::operationFailed(
                'delete_client_override',
                'Failed to delete client override: ' . $e->getMessage(),
                ['client_id' => $clientId, 'key' => $assumptionKey]
            );
        }
    }

    /**
     * Get the path to the assumptions file.
     */
    private function getAssumptionsFile(): string
    {
        return $this->storagePath . DIRECTORY_SEPARATOR . self::ASSUMPTIONS_FILE;
    }

    /**
     * Get the path to the client overrides directory.
     */
    private function getClientOverridesPath(): string
    {
        return $this->storagePath . DIRECTORY_SEPARATOR . self::CLIENT_OVERRIDES_DIR;
    }

    /**
     * Get the path to a client's override file.
     */
    private function getClientOverrideFile(string $clientId): string
    {
        return $this->getClientOverridesPath() . DIRECTORY_SEPARATOR . $clientId . '.json';
    }

    /**
     * Load all assumptions from file.
     */
    private function loadAllAssumptions(): array
    {
        $file = $this->getAssumptionsFile();

        if (!file_exists($file)) {
            return [];
        }

        $content = file_get_contents($file);
        if ($content === false) {
            throw new \RuntimeException('Failed to read assumptions file');
        }

        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        $assumptions = [];
        foreach ($data as $item) {
            $assumption = AssumptionDefinition::fromArray($item);
            $assumptions[$assumption->key] = $assumption;
        }

        return $assumptions;
    }

    /**
     * Save all assumptions to file.
     */
    private function saveAllAssumptions(array $assumptions): void
    {
        $data = array_map(
            fn(AssumptionDefinition $a) => $a->toArray(),
            $assumptions
        );

        $content = json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        $file = $this->getAssumptionsFile();

        if (file_put_contents($file, $content) === false) {
            throw new \RuntimeException('Failed to write assumptions file');
        }
    }

    /**
     * Load client overrides from file.
     */
    private function loadClientOverrides(string $clientId): array
    {
        $file = $this->getClientOverrideFile($clientId);

        if (!file_exists($file)) {
            return [];
        }

        $content = file_get_contents($file);
        if ($content === false) {
            throw new \RuntimeException('Failed to read client overrides file');
        }

        return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Save client overrides to file.
     */
    private function saveClientOverrides(string $file, array $overrides): void
    {
        $content = json_encode($overrides, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);

        if (file_put_contents($file, $content) === false) {
            throw new \RuntimeException('Failed to write client overrides file');
        }
    }
}