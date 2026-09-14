<?php

declare(strict_types=1);

namespace josworld\services\admin;

defined('ABSPATH') || exit;

final class SubmissionRepository
{
    public const LIMIT = 5;

    /** @return list<array<string, mixed>> */
    public function latest(): array
    {
        return [];
    }
}
