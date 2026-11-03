<?php

namespace Best\Blueprint\Support;

final readonly class ProfileWrite
{
    public const UPDATED = 'updated';

    public const UNCHANGED = 'unchanged';

    public const UNRECORDABLE = 'unrecordable';

    public function __construct(
        public string $status,
        public ?string $old = null,
    ) {}
}
