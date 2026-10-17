<?php

declare(strict_types=1);

namespace KSFII\AssumptionManagement;

class AssumptionUiHelpers
{
    public static function parseValue(string $value, string $type)
    {
        switch ($type) {
            case 'int': return (int)$value;
            case 'float': return (float)$value;
            case 'bool': return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'array': return json_decode($value, true) ?? [];
            default: return $value;
        }
    }

    public static function setFlashMessage(string $message, string $type): void
    {
        // Generic flash implementation for PHP applications
        if (!isset($_SESSION)) {
            @session_start();
        }
        $_SESSION['ksfii_message'] = ['text' => $message, 'type' => $type];
    }

    public static function renderCategorySelect(array $categories, string $selected = ''): string
    {
        $html = '<label>Filter by Category:</label>';
        $html .= '<select name="category_filter" onchange="this.form.submit()">';
        $html .= '<option value="">All Categories</option>';
        foreach ($categories as $category) {
            $sel = $selected === $category ? 'selected' : '';
            $html .= "<option value=\"{$category}\" {$sel}>{$category}</option>";
        }
        $html .= '</select>';
        return $html;
    }

    public static function renderAssumptionsTable(array $assumptions, string $categoryFilter = ''): string
    {
        $html = '<table class="list view table-responsive">';
        $html .= '<thead><tr><th>Key</th><th>Name</th><th>Category</th><th>Default Value</th><th>Client Overrideable</th><th>Actions</th></tr></thead>';
        $html .= '<tbody>';
        foreach ($assumptions as $assumption) {
            if ($categoryFilter && $assumption->category !== $categoryFilter) continue;
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($assumption->key) . '</td>';
            $html .= '<td>' . htmlspecialchars($assumption->name) . '</td>';
            $html .= '<td>' . htmlspecialchars($assumption->category) . '</td>';
            $html .= '<td>' . htmlspecialchars((string)$assumption->defaultValue) . '</td>';
            $html .= '<td>' . ($assumption->clientOverrideable ? 'Yes' : 'No') . '</td>';
            $html .= '<td>';
            $html .= '<a href="index.php?module=CanadaLife&action=edit_assumption&key=' . urlencode($assumption->key) . '" class="btn btn-primary">Edit</a> ';
            $html .= '<a href="index.php?module=CanadaLife&action=delete_assumption&key=' . urlencode($assumption->key) . '" class="btn btn-danger" onclick="return confirm(\'Are you sure?\')">Delete</a>';
            $html .= '</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
        return $html;
    }
}
