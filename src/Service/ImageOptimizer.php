<?php

namespace App\Service;

use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class ImageOptimizer
{
    private ImageManager $manager;

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver());
    }

    public function optimize(string $filePath, int $maxWidth = 1200, int $quality = 85): void
    {
        $image = $this->manager->read($filePath);

        if ($image->width() > $maxWidth) {
            $image->scale(width: $maxWidth);
        }

        $image->save($filePath, quality: $quality);
    }
}