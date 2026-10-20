<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * News DTO (Data Transfer Object)
 * Represents a single news article parsed from kaktus.media
 */
class News
{
    public function __construct(
        public readonly string $date,
        public readonly string $title,
        public readonly string $url,
        public readonly ?string $image = null,
    ) {}

    /**
     * Create a News instance from an associative array.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            date: $data['date'] ?? '',
            title: $data['title'] ?? '',
            url: $data['url'] ?? '',
            image: $data['image'] ?? null,
        );
    }

    /**
     * Convert to array for JSON serialization.
     */
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
