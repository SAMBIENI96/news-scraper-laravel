<?php

namespace App\Classes;

class News
{
    public function __construct(
        public readonly string $date,
        public readonly string $title,
        public readonly string $url,
        public readonly ?string $image = null,
    ) {}

    public function toArray(): array
    {
        return [
            'date'  => $this->date,
            'title' => $this->title,
            'url'   => $this->url,
            'image' => $this->image,
        ];
    }
}
