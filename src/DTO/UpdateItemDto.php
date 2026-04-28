<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateItemDto
{
    public function __construct(
        #[Assert\NotBlank(message: 'Item text cannot be empty', allowNull: true)]
        #[Assert\Length(
            max: 500,
            maxMessage: 'Item text cannot exceed 500 characters',
        )]
        public ?string $text = null,

        public ?bool $isDone = null,
    ) {}
}
