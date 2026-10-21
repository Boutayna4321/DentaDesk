<?php

namespace App\Assistant;

class Reply
{
    /** @param  list<array{label:string,value:string}>  $chips */
    public function __construct(
        public string $text,
        public array $chips = [],
        public ?array $booking = null,
    ) {}

    public function toArray(): array
    {
        return ['text' => $this->text, 'chips' => $this->chips, 'booking' => $this->booking];
    }
}
