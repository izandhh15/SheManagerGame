<?php

namespace App\Modules\Match\DTOs;

readonly class MatchNarrative
{
    public function __construct(
        public string $text,
        public string $category,
        public ?string $source = null,
        public ?string $headline = null,
        /**
         * Full press-article body: 3-4 paragraphs, each a single line, so
         * media news items read like a real article instead of a snippet.
         *
         * @var list<string>|null
         */
        public ?array $body = null,
    ) {}
}
