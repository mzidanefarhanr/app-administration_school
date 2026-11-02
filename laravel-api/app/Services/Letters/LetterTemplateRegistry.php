<?php

namespace App\Services\Letters;

/**
 * Registry template surat bertipe (Fase 07c, DEC-34).
 *
 * Template adalah kode di resources/views; tidak ada editor HTML atau
 * template dari pengguna. Config tidak valid dianggap kesalahan server.
 */
class LetterTemplateRegistry
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function definitions(): array
    {
        $types = config('letters.letter_types', []);

        return is_array($types) ? $types : [];
    }

    public function isKnown(string $letterType): bool
    {
        return array_key_exists($letterType, $this->definitions());
    }

    public function label(?string $letterType): ?string
    {
        if ($letterType === null) {
            return null;
        }

        $definition = $this->definitions()[$letterType] ?? null;

        return is_array($definition) && is_string($definition['label'] ?? null)
            ? $definition['label']
            : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(string $letterType): array
    {
        $definitions = $this->definitions();

        if (! array_key_exists($letterType, $definitions)) {
            throw new \InvalidArgumentException("Unknown letter type [{$letterType}].");
        }

        $definition = $definitions[$letterType];

        if (! is_array($definition)
            || ! is_string($definition['label'] ?? null)
            || ! is_string($definition['template'] ?? null)
            || ! is_string($definition['subject_type'] ?? null)) {
            throw new \RuntimeException("Letter type [{$letterType}] is not configured correctly.");
        }

        return $definition;
    }

    /**
     * @return array{code: string, version: int, view: string}
     */
    public function templateFor(string $letterType): array
    {
        $definition = $this->definition($letterType);
        $code = $definition['template'];
        $template = config('letters.templates.'.$code);

        if (! is_array($template)
            || ! is_int($template['version'] ?? null)
            || $template['version'] < 1
            || ! is_string($template['view'] ?? null)
            || $template['view'] === '') {
            throw new \RuntimeException("Template [{$code}] is not configured correctly.");
        }

        if (! view()->exists($template['view'])) {
            throw new \RuntimeException("Template view [{$template['view']}] does not exist.");
        }

        return [
            'code' => $code,
            'version' => $template['version'],
            'view' => $template['view'],
        ];
    }

    public function assertValid(): void
    {
        foreach (array_keys($this->definitions()) as $letterType) {
            $this->templateFor($letterType);
        }
    }

    /**
     * @return array<int, array{value: string, label: string, subject_type: string}>
     */
    public function options(): array
    {
        $options = [];

        foreach ($this->definitions() as $letterType => $definition) {
            $options[] = [
                'value' => $letterType,
                'label' => $definition['label'],
                'subject_type' => $definition['subject_type'],
            ];
        }

        return $options;
    }
}
