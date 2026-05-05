<?php

declare(strict_types=1);

namespace App\Admin\Application\Query\GetUserDetail;

final readonly class GetUserDetailQuery
{
    public function __construct(public string $userId)
    {
    }
}
