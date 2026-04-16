<?php

declare(strict_types=1);

namespace App\Service\AI;

use OnnxRuntime\FFI as OnnxFfi;
use OnnxRuntime\Model;
use Symfony\Component\Process\Process;

final class FaceRecognitionService
{
    private ?Model $recognitionModel = null;

    public function __construct(
        private readonly string $projectDir,
        private readonly string $recognitionModelPath,
        private readonly string $onnxRuntimeLibraryPath,
        private readonly string $pythonCommand,
        private readonly string $pythonScriptPath,
        private readonly float $similarityThreshold,
        private readonly int $inputWidth,
        private readonly int $inputHeight,
    ) {
    }

    /**
     * @return list<float>
     */
    public function extractEmbeddingFromImage(string $imageBinary): array
    {
        if (!is_file($this->recognitionModelPath)) {
            throw new \RuntimeException('Face recognition model file is missing.');
        }

        $tensor = $this->preprocessToTensor($imageBinary);
        return $this->extractEmbeddingFromTensor($tensor);
    }

    /**
     * @param array<int, mixed> $tensor
     * @return list<float>
     */
    public function extractEmbeddingFromTensor(array $tensor): array
    {
        if (!is_file($this->recognitionModelPath)) {
            throw new \RuntimeException('Face recognition model file is missing.');
        }

        if ($this->flatten($tensor) === []) {
            throw new \RuntimeException('Face tensor payload is empty.');
        }

        try {
            $prediction = $this->model()->predict([$this->resolveInputName() => $tensor]);
        } catch (\Throwable $throwable) {
            $details = trim($throwable->getMessage());
            if ($this->shouldUsePythonFallback($details)) {
                return $this->extractEmbeddingWithPython($tensor);
            }

            throw new \RuntimeException(
                'Face recognition runtime is unavailable. Ensure ext-ffi is enabled for the web SAPI and ONNX runtime library is accessible.'
                . ($details !== '' ? ' Runtime details: ' . $details : ''),
                previous: $throwable,
            );
        }
        $embedding = $this->normalizeEmbedding($this->flattenPrediction($prediction));

        if ($embedding === []) {
            throw new \RuntimeException('Unable to extract a face embedding from this image.');
        }

        return $embedding;
    }

    public function matches(array $knownEmbedding, array $probeEmbedding): bool
    {
        return $this->cosineSimilarity($knownEmbedding, $probeEmbedding) >= $this->similarityThreshold;
    }

    public function cosineSimilarity(array $knownEmbedding, array $probeEmbedding): float
    {
        if (count($knownEmbedding) === 0 || count($knownEmbedding) !== count($probeEmbedding)) {
            return 0.0;
        }

        $dot = 0.0;
        $knownNorm = 0.0;
        $probeNorm = 0.0;

        foreach ($knownEmbedding as $index => $knownValue) {
            $probeValue = (float) $probeEmbedding[$index];
            $knownValue = (float) $knownValue;
            $dot += $knownValue * $probeValue;
            $knownNorm += $knownValue * $knownValue;
            $probeNorm += $probeValue * $probeValue;
        }

        if ($knownNorm <= 0.0 || $probeNorm <= 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($knownNorm) * sqrt($probeNorm));
    }

    public function getSimilarityThreshold(): float
    {
        return $this->similarityThreshold;
    }

    private function model(): Model
    {
        if ($this->recognitionModel === null) {
            $this->configureRuntimeLibraryPath();
            $this->recognitionModel = new Model($this->recognitionModelPath);
        }

        return $this->recognitionModel;
    }

    private function configureRuntimeLibraryPath(): void
    {
        $configuredPath = trim($this->onnxRuntimeLibraryPath);
        if ($configuredPath !== '') {
            OnnxFfi::$lib = str_starts_with($configuredPath, '/')
                ? $configuredPath
                : $this->projectDir . '/' . ltrim($configuredPath, '/');

            return;
        }

        $candidates = glob($this->projectDir . '/vendor/ankane/onnxruntime/lib/*/lib/libonnxruntime.so*') ?: [];
        usort($candidates, static fn(string $left, string $right): int => filesize($right) <=> filesize($left));

        foreach ($candidates as $candidate) {
            if (!is_file($candidate) || filesize($candidate) <= 0) {
                continue;
            }

            OnnxFfi::$lib = $candidate;
            return;
        }
    }

    private function resolveInputName(): string
    {
        $inputs = $this->model()->inputs();
        if (!is_array($inputs) || $inputs === []) {
            throw new \RuntimeException('Unable to resolve ONNX model input metadata.');
        }

        $firstInput = $inputs[array_key_first($inputs)] ?? null;
        if (is_array($firstInput) && isset($firstInput['name']) && is_string($firstInput['name']) && $firstInput['name'] !== '') {
            return $firstInput['name'];
        }

        if (is_string(array_key_first($inputs))) {
            return (string) array_key_first($inputs);
        }

        throw new \RuntimeException('Unable to resolve ONNX model input name.');
    }

    /**
     * @return array<int, float>|array<int, array<int, float>>
     */
    private function preprocessToTensor(string $imageBinary): array
    {
        if (!function_exists('imagecreatefromstring')) {
            throw new \RuntimeException('Image processing runtime is unavailable (GD extension is required).');
        }

        $source = imagecreatefromstring($imageBinary);
        if ($source === false) {
            throw new \RuntimeException('Invalid image payload.');
        }

        $resized = imagecreatetruecolor($this->inputWidth, $this->inputHeight);
        if ($resized === false) {
            imagedestroy($source);
            throw new \RuntimeException('Failed to preprocess image for face recognition.');
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $cropSize = (int) max(1, floor(min($sourceWidth, $sourceHeight) * 0.82));
        $cropX = (int) max(0, floor(($sourceWidth - $cropSize) / 2));
        $cropY = (int) max(0, floor(($sourceHeight - $cropSize) / 2));

        if (!imagecopyresampled(
            $resized,
            $source,
            0,
            0,
            $cropX,
            $cropY,
            $this->inputWidth,
            $this->inputHeight,
            $cropSize,
            $cropSize,
        )) {
            imagedestroy($source);
            imagedestroy($resized);
            throw new \RuntimeException('Failed to resize image for face recognition.');
        }

        $r = [];
        $g = [];
        $b = [];
        for ($y = 0; $y < $this->inputHeight; ++$y) {
            $rRow = [];
            $gRow = [];
            $bRow = [];
            for ($x = 0; $x < $this->inputWidth; ++$x) {
                $pixel = imagecolorat($resized, $x, $y);
                $rRow[] = ((((($pixel >> 16) & 0xFF) - 127.5) / 128.0));
                $gRow[] = ((((($pixel >> 8) & 0xFF) - 127.5) / 128.0));
                $bRow[] = (((($pixel & 0xFF) - 127.5) / 128.0));
            }
            $r[] = $rRow;
            $g[] = $gRow;
            $b[] = $bRow;
        }

        imagedestroy($source);
        imagedestroy($resized);

        // NCHW expected by common InsightFace embeddings models.
        return [[
            $r,
            $g,
            $b,
        ]];
    }

    /**
     * @param array<string, mixed>|array<int, mixed> $prediction
     * @return list<float>
     */
    private function flattenPrediction(array $prediction): array
    {
        $output = $prediction[array_key_first($prediction)] ?? [];
        $flattened = $this->flatten($output);

        return array_values(array_map(static fn(mixed $value): float => (float) $value, $flattened));
    }

    /**
     * @return list<mixed>
     */
    private function flatten(mixed $value): array
    {
        if (!is_array($value)) {
            return [$value];
        }

        $result = [];
        foreach ($value as $item) {
            array_push($result, ...$this->flatten($item));
        }

        return $result;
    }

    /**
     * @param list<float> $embedding
     * @return list<float>
     */
    private function normalizeEmbedding(array $embedding): array
    {
        if ($embedding === []) {
            return [];
        }

        $norm = 0.0;
        foreach ($embedding as $value) {
            $norm += $value * $value;
        }

        if ($norm <= 0.0) {
            return [];
        }

        $length = sqrt($norm);

        return array_map(static fn(float $value): float => $value / $length, $embedding);
    }

    private function shouldUsePythonFallback(string $details): bool
    {
        $normalized = strtolower($details);

        return str_contains($normalized, 'ffi.enable')
            || str_contains($normalized, 'ffi api is restricted')
            || str_contains($normalized, 'failed loading')
            || str_contains($normalized, 'cannot open shared object file')
            || str_contains($normalized, 'undefined symbol');
    }

    /**
     * @param array<int, mixed> $tensor
     * @return list<float>
     */
    private function extractEmbeddingWithPython(array $tensor): array
    {
        $scriptPath = $this->resolveScriptPath();
        if (!is_file($scriptPath)) {
            throw new \RuntimeException('Python fallback script is missing.');
        }

        $pythonExecutable = $this->resolvePythonExecutable();
        $process = new Process([$pythonExecutable, $scriptPath]);
        $process->setTimeout(20);
        $process->setInput((string) json_encode([
            'model_path' => $this->recognitionModelPath,
            'tensor' => $tensor,
        ], JSON_THROW_ON_ERROR));
        $process->run();

        if (!$process->isSuccessful()) {
            $error = trim($process->getErrorOutput());
            throw new \RuntimeException(
                'Face recognition runtime fallback failed. Install python dependencies: `pip install onnxruntime numpy`.'
                . ($error !== '' ? ' Runtime details: ' . $error : ''),
            );
        }

        $output = json_decode($process->getOutput(), true);
        if (!is_array($output) || !isset($output['embedding']) || !is_array($output['embedding'])) {
            throw new \RuntimeException('Face recognition runtime fallback returned invalid output.');
        }

        $embedding = [];
        foreach ($output['embedding'] as $value) {
            if (!is_numeric($value)) {
                throw new \RuntimeException('Face recognition runtime fallback returned non-numeric embedding values.');
            }
            $embedding[] = (float) $value;
        }

        if ($embedding === []) {
            throw new \RuntimeException('Face recognition runtime fallback returned empty embedding output.');
        }

        return $embedding;
    }

    private function resolveScriptPath(): string
    {
        $configured = trim($this->pythonScriptPath);
        if ($configured === '') {
            return $this->projectDir . '/bin/face_embedding_infer.py';
        }

        return str_starts_with($configured, '/')
            ? $configured
            : $this->projectDir . '/' . ltrim($configured, '/');
    }

    private function resolvePythonExecutable(): string
    {
        $configured = trim($this->pythonCommand);
        if ($configured === '') {
            return 'python3';
        }

        return str_contains($configured, '/')
            ? (str_starts_with($configured, '/') ? $configured : $this->projectDir . '/' . ltrim($configured, '/'))
            : $configured;
    }
}
