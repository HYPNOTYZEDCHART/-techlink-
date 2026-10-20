<?php

declare(strict_types=1);

namespace App\Exception;

class InsufficientStockException extends \RuntimeException
{
    public function __construct(
        string $productName,
        int $requestedQuantity,
        int $availableStock,
        ?string $colorName = null,
        ?\Throwable $previous = null,
    ) {
        $message = $colorName !== null
            ? sprintf(
                'Stock insuffisant pour "%s" (Coloris : %s). Quantité demandée : %d, Stock restant : %d.',
                $productName,
                $colorName,
                $requestedQuantity,
                $availableStock
            )
            : sprintf(
                'Stock insuffisant pour "%s". Quantité demandée : %d, Stock restant : %d.',
                $productName,
                $requestedQuantity,
                $availableStock
            );

        parent::__construct($message, 400, $previous);
    }
}
