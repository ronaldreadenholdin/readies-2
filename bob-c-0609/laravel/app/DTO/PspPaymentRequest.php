<?php

namespace App\DTO;

final class PspPaymentRequest
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

    public function without(string $path): self
    {
        $data = $this->data;
        $cursor = &$data;
        $segments = explode('.', $path);
        $last = array_pop($segments);

        foreach ($segments as $segment) {
            if (! isset($cursor[$segment]) || ! is_array($cursor[$segment])) {
                return new self($data);
            }
            $cursor = &$cursor[$segment];
        }

        unset($cursor[$last]);

        return new self($data);
    }
}
