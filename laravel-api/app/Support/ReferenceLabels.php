<?php

namespace App\Support;

class ReferenceLabels
{
    /**
     * Translate exact catalog names for presentation without changing stored values.
     */
    public static function display(string $domain, ?string $name): ?string
    {
        if ($name === null) {
            return null;
        }

        static $labels;
        $labels ??= json_decode(file_get_contents(resource_path('reference-labels.json')), true, 512, JSON_THROW_ON_ERROR);

        return $labels[$domain][$name] ?? $name;
    }
}
