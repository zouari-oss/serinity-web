<?php

declare(strict_types=1);

namespace App\Dto\Common;

final readonly class ServiceResult
{
    private function __construct(
        public bool $success,
        public string $message,
        public mixed $data = null,
    ) {
    }

    public static function success(string $message, mixed $data = null): self
    {
        return new self(true, $message, $data);
    }

    public static function failure(string $message, mixed $data = null): self
    {
        return new self(false, $message, $data);
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'message' => $this->message,
            'data' => $this->data,
        ];
    }
}
