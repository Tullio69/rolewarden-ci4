<?php

declare(strict_types=1);

namespace RoleWarden\Install;

use RuntimeException;

/**
 * State of the install wizard and log of the changes it made to host files,
 * persisted as writable/rolewarden/install-manifest.json. Holds no secrets:
 * never a plaintext password.
 */
final class SetupState
{
    public const VERSION = 1;

    /** @var array<string, mixed> */
    private array $choices = [];

    /** @var list<string> */
    private array $done = [];

    /** @var list<array{path: string, before: string, after: string, at: string, checked: bool}> */
    private array $edits = [];

    private ?string $completedAt = null;

    public static function file(): string
    {
        return WRITEPATH . 'rolewarden/install-manifest.json';
    }

    /** A missing or unreadable file gives an empty state. */
    public static function load(?string $file = null): self
    {
        $state = new self();
        $raw   = @file_get_contents($file ?? self::file());
        $data  = $raw === false ? null : json_decode($raw, true);

        if (! is_array($data)) {
            return $state;
        }

        if (is_array($data['choices'] ?? null)) {
            $state->choices = $data['choices'];
        }

        if (is_array($data['done'] ?? null)) {
            $state->done = array_values(array_filter($data['done'], 'is_string'));
        }

        foreach (is_array($data['edits'] ?? null) ? $data['edits'] : [] as $e) {
            if (is_array($e) && is_string($e['path'] ?? null) && is_string($e['before'] ?? null) && is_string($e['after'] ?? null) && is_string($e['at'] ?? null)) {
                $state->edits[] = ['path' => $e['path'], 'before' => $e['before'], 'after' => $e['after'], 'at' => $e['at'], 'checked' => (bool) ($e['checked'] ?? true)];
            }
        }

        $state->completedAt = is_string($data['completed_at'] ?? null) ? $data['completed_at'] : null;

        return $state;
    }

    /** Atomic: temporary file in the same directory, then rename. */
    public function save(?string $file = null): void
    {
        $file ??= self::file();
        $dir    = dirname($file);

        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new RuntimeException('Cannot create ' . $dir);
        }

        $json = json_encode([
            'version'      => self::VERSION,
            'choices'      => $this->choices,
            'done'         => $this->done,
            'edits'        => $this->edits,
            'completed_at' => $this->completedAt,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';

        if (file_put_contents($tmp, $json) === false || ! rename($tmp, $file)) {
            @unlink($tmp);

            throw new RuntimeException('Cannot write ' . $file);
        }
    }

    /** @return array<string, mixed> */
    public function choices(): array
    {
        return $this->choices;
    }

    public function setChoice(string $key, mixed $value): void
    {
        $this->choices[$key] = $value;
    }

    public function done(string $step): bool
    {
        return in_array($step, $this->done, true);
    }

    public function markDone(string $step): void
    {
        if (! $this->done($step)) {
            $this->done[] = $step;
        }
    }

    public function recordEdit(string $path, string $before, string $after, bool $checked = true): void
    {
        $this->edits[] = ['path' => $path, 'before' => $before, 'after' => $after, 'at' => date('c'), 'checked' => $checked];
    }

    /** @return list<array{path: string, before: string, after: string, at: string, checked: bool}> */
    public function edits(): array
    {
        return $this->edits;
    }

    public function complete(): void
    {
        $this->completedAt = date('c');
    }

    public function completed(): bool
    {
        return $this->completedAt !== null;
    }
}
