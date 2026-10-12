<?php

namespace App\Models\Concerns;

trait HasInitials
{
    public function getInitialsAttribute(): string
    {
        $words = preg_split('/\s+/', trim((string) $this->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return collect($words)->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
