<?php

declare(strict_types=1);

namespace InventoryAgent;

/**
 * Validates that a modifier/username ends with the required '-adm' suffix,
 * enforcing that only authorised admin IDs can execute stock transactions.
 */
class AdminValidator
{
    /**
     * Returns true when $modifier (after lower-casing and trimming) ends with '-adm'.
     */
    public static function isValid(string $modifier): bool
    {
        return str_ends_with(strtolower(trim($modifier)), '-adm');
    }
}
