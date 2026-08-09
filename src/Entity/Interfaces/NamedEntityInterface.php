<?php

namespace App\Entity\Interfaces;

/** Справочная сущность с уникальным названием (Developer, Publisher, Genre, Platform). */
interface NamedEntityInterface
{
    public function getName(): string;
}
