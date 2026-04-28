<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateItemDto
{
    public function __construct(
        #[Assert\NotBlank(message: 'Item text cannot be empty')]
        #[Assert\Length(
            min: 1,
            max: 500,
            minMessage: 'Item text must be at least 1 character',
            maxMessage: 'Item text cannot exceed 500 characters',
        )]
        public string $text,
    ) {}
}
