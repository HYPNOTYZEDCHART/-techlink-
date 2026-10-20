<?php

declare(strict_types=1);

namespace App\Enum;

enum ProductCondition: string
{
    case NEW_SEALED = 'NEW_SEALED';
    case REFURBISHED = 'REFURBISHED';
    case OPEN_BOX = 'OPEN_BOX';

    public function label(): string
    {
        return match ($this) {
            self::NEW_SEALED => 'Neuf Scellé',
            self::REFURBISHED => 'Reconditionné Certifié',
            self::OPEN_BOX => 'Boîte Ouverte (Open Box)',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::NEW_SEALED => 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20',
            self::REFURBISHED => 'bg-cyan-500/10 text-cyan-400 border border-cyan-500/20',
            self::OPEN_BOX => 'bg-amber-500/10 text-amber-400 border border-amber-500/20',
        };
    }
}
