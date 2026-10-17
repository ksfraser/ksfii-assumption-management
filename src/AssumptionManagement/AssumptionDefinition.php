<?php

declare(strict_types=1);

namespace KSFII\AssumptionManagement;

use DateTimeImmutable;

/**
 * Defines an assumption used in financial calculations.
 *
 * Assumptions represent default values, rates, and parameters that can be
 * customized per client or globally. They support versioning and validation.
 */
class AssumptionDefinition
{
    /**
     * @param string $key Unique identifier for the assumption
     * @param string $name Human-readable name
     * @param string $description Detailed description
     * @param mixed $defaultValue The default value
     * @param string $dataType The data type (string, int, float, bool, array)
     * @param array<string, mixed> $validationRules Validation constraints
     * @param string $category Grouping category (e.g., 'inflation', 'tax_rates', 'investment')
     * @param bool $clientOverrideable Whether clients can override this assumption
     * @param DateTimeImmutable|null $effectiveFrom When this assumption becomes effective
     * @param DateTimeImmutable|null $effectiveUntil When this assumption expires
     * @param string|null $version Version identifier for change tracking
     */
    public function __construct(
        public readonly string $key,
        public readonly string $name,
        public readonly string $description,
        public readonly mixed $defaultValue,
        public readonly string $dataType,
        public readonly array $validationRules = [],
        public readonly string $category = 'general',
        public readonly bool $clientOverrideable = true,
        public readonly ?DateTimeImmutable $effectiveFrom = null,
        public readonly ?DateTimeImmutable $effectiveUntil = null,
        public readonly ?string $version = null
    ) {}

    /**
     * Validate a value against this assumption's rules.
     *
     * @param mixed $value The value to validate
     * @return bool True if valid
     */
    public function validate(mixed $value): bool
    {
        // Type validation
        if (!$this->validateType($value)) {
            return false;
        }

        // Range validation for numeric types
        if (isset($this->validationRules['min']) && is_numeric($value)) {
            if ($value < $this->validationRules['min']) {
                return false;
            }
        }

        if (isset($this->validationRules['max']) && is_numeric($value)) {
            if ($value > $this->validationRules['max']) {
                return false;
            }
        }

        // Enum validation
        if (isset($this->validationRules['enum']) && is_array($this->validationRules['enum'])) {
            if (!in_array($value, $this->validationRules['enum'], true)) {
                return false;
            }
        }

        // Pattern validation for strings
        if (isset($this->validationRules['pattern']) && is_string($value)) {
            if (!preg_match($this->validationRules['pattern'], $value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate the data type of a value.
     *
     * @param mixed $value The value to check
     * @return bool True if type matches
     */
    private function validateType(mixed $value): bool
    {
        return match ($this->dataType) {
            'int', 'integer' => is_int($value),
            'float', 'double' => is_float($value) || is_int($value),
            'string' => is_string($value),
            'bool', 'boolean' => is_bool($value),
            'array' => is_array($value),
            'number' => is_numeric($value),
            default => true // Allow any type for custom types
        };
    }

    /**
     * Check if this assumption is currently effective.
     *
     * @param DateTimeImmutable|null $checkDate Date to check (defaults to now)
     * @return bool True if effective
     */
    public function isEffective(?DateTimeImmutable $checkDate = null): bool
    {
        $now = $checkDate ?? new DateTimeImmutable();

        if ($this->effectiveFrom !== null && $now < $this->effectiveFrom) {
            return false;
        }

        if ($this->effectiveUntil !== null && $now > $this->effectiveUntil) {
            return false;
        }

        return true;
    }

    /**
     * Convert the assumption to an array for serialization.
     *
     * @return array<string, mixed> Array representation
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'name' => $this->name,
            'description' => $this->description,
            'default_value' => $this->defaultValue,
            'data_type' => $this->dataType,
            'validation_rules' => $this->validationRules,
            'category' => $this->category,
            'client_overrideable' => $this->clientOverrideable,
            'effective_from' => $this->effectiveFrom?->format('c'),
            'effective_until' => $this->effectiveUntil?->format('c'),
            'version' => $this->version,
        ];
    }

    /**
     * Create an assumption from an array.
     *
     * @param array<string, mixed> $data Array data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['key'] ?? '',
            $data['name'] ?? '',
            $data['description'] ?? '',
            $data['default_value'] ?? null,
            $data['data_type'] ?? 'string',
            $data['validation_rules'] ?? [],
            $data['category'] ?? 'general',
            $data['client_overrideable'] ?? true,
            isset($data['effective_from']) ? new DateTimeImmutable($data['effective_from']) : null,
            isset($data['effective_until']) ? new DateTimeImmutable($data['effective_until']) : null,
            $data['version'] ?? null
        );
    }
}