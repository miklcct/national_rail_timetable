<?php
declare(strict_types=1);

namespace App\Models;

use function Safe\preg_replace;

interface Location {
    public function getName() : ?string;
    public function getCrsCode() : ?string;
}

function get_short_name(string $name) : string {
    if (str_contains($name, 'MAESTEG')) {
        return $name;
    }
    return preg_replace('/ \(.*\)$/', '', $name);
}

