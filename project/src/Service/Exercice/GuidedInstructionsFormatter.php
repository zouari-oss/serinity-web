<?php

declare(strict_types=1);

namespace App\Service\Exercice;

final class GuidedInstructionsFormatter
{
    /**
     * @return list<array{title: string, description: string}>
     */
    public function textToStructured(?string $text): array
    {
        $text = trim((string) $text);
        if ($text === '') {
            return [];
        }

        $lines = preg_split('/\R+/', $text) ?: [];
        $rows = [];
        $autoStep = 1;

        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }

            $title = '';
            $description = '';

            if (str_contains($line, ':')) {
                [$candidateTitle, $candidateDescription] = array_pad(explode(':', $line, 2), 2, '');
                $candidateTitle = trim($candidateTitle);
                $candidateDescription = trim($candidateDescription);

                if ($candidateTitle !== '' && $candidateDescription !== '') {
                    $title = $candidateTitle;
                    $description = $candidateDescription;
                }
            }

            if ($title === '' || $description === '') {
                $title = 'Step ' . $autoStep;
                $description = $line;
            }

            $rows[] = [
                'title' => $title,
                'description' => $description,
            ];
            $autoStep++;
        }

        return $rows;
    }

    /**
     * @param list<array{title?: mixed, description?: mixed}>|null $rows
     */
    public function structuredToText(?array $rows): string
    {
        if (!is_array($rows) || $rows === []) {
            return '';
        }

        $lines = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $title = trim((string) ($row['title'] ?? ''));
            $description = trim((string) ($row['description'] ?? ''));
            if ($description === '') {
                continue;
            }

            $lines[] = $title !== '' ? sprintf('%s: %s', $title, $description) : $description;
        }

        return implode("\n", $lines);
    }
}
