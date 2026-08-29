<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'sessions')]
class Session
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 128, name: 'sess_id')]
    private ?string $sessId = null;

    #[ORM\Column(type: Types::BLOB, name: 'sess_data')]
    private $sessData;

    #[ORM\Column(type: Types::INTEGER, name: 'sess_time')]
    private ?int $sessTime = null;

    #[ORM\Column(type: Types::INTEGER, name: 'sess_lifetime')]
    private ?int $sessLifetime = null;
}
