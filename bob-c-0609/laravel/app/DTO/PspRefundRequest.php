<?php

namespace App\DTO;

final class PspRefundRequest
{
    public function __construct(private array $data)
    {
    }

    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    public function get(string $path, mixed $default = null): mixed
    {
        $value = $this->data;
        foreach (explode('.', $path) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public function toArray(): array
    {
        return $this->data;
    }
}
