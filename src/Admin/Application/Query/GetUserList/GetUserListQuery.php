<?php

declare(strict_types=1);

namespace App\Admin\Application\Query\GetUserList;

final readonly class GetUserListQuery
{
    public function __construct(
        public string $search,
        public string $status,
        public int $page,
    ) {
    }
}
