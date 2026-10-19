<?php

declare(strict_types=1);

namespace Bench;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'products')]
class DoctrineProduct
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: 'integer')]
    public ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    public string $name = '';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    public string $price = '0.00';

    #[ORM\Column(type: 'integer')]
    public int $stock = 0;
}
