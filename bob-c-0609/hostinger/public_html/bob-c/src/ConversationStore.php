<?php

declare(strict_types=1);

final class ConversationStore
{
    public function __construct(private readonly string $path)
    {
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        if (! is_file($path)) {
            file_put_contents($path, json_encode(['messages' => []], JSON_PRETTY_PRINT));
        }
    }

    public function all(): array
    {
        $raw = file_get_contents($this->path);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        $messages = is_array($decoded) ? ($decoded['messages'] ?? []) : [];

        return is_array($messages) ? $messages : [];
    }

    public function add(string $role, string $content): array
    {
        $messages = $this->all();
        $row = [
            'id' => bin2hex(random_bytes(8)),
            'role' => $role,
            'content' => $content,
            'created_at' => gmdate('c'),
        ];
        $messages[] = $row;
        $this->write($messages);

        return $row;
    }

    public function clear(): void
    {
        $this->write([]);
    }

    private function write(array $messages): void
    {
        file_put_contents(
            $this->path,
            json_encode(['messages' => $messages], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }
}
