<?php

declare(strict_types=1);

namespace KSFII\AssumptionManagement;

/**
 * Exception thrown when assumption storage operations fail.
 *
 * Provides detailed error information for storage-related failures,
 * including the operation type and context information.
 */
class AssumptionStorageException extends \Exception
{
    /**
     * @param string $message The error message
     * @param string $operation The operation that failed (store, retrieve, delete, etc.)
     * @param array<string, mixed> $context Additional context information
     * @param \Throwable|null $previous The previous exception
     */
    public function __construct(
        string $message,
        public readonly string $operation,
        public readonly array $context = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Create an exception for storage operation failures.
     *
     * @param string $operation The operation that failed
     * @param string $reason The reason for failure
     * @param array<string, mixed> $context Additional context
     * @return self
     */
    public static function operationFailed(
        string $operation,
        string $reason,
        array $context = []
    ): self {
        return new self($reason, $operation, $context);
    }

    /**
     * Create an exception for connection failures.
     *
     * @param string $storageType The storage type (mysql, sqlite, file, etc.)
     * @param string $reason The connection failure reason
     * @return self
     */
    public static function connectionFailed(
        string $storageType,
        string $reason
    ): self {
        return new self(
            "Failed to connect to {$storageType} storage: {$reason}",
            'connect',
            ['storage_type' => $storageType]
        );
    }

    /**
     * Create an exception for data corruption issues.
     *
     * @param string $key The assumption key
     * @param string $reason The corruption reason
     * @return self
     */
    public static function dataCorruption(
        string $key,
        string $reason
    ): self {
        return new self(
            "Data corruption detected for assumption '{$key}': {$reason}",
            'retrieve',
            ['key' => $key]
        );
    }
}