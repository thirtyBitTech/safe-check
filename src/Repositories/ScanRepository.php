<?php

namespace Thirtybittech\SafeCheck\Repositories;

use Illuminate\Support\Facades\Storage;

class ScanRepository
{
    private string $path = 'safe-check/latest-scan.json';

    public function save(array $payload): void
    {
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);

        $temp = $this->path . '.tmp';

        Storage::disk('local')->put($temp, $json);
        Storage::disk('local')->move($temp, $this->path);
    }

    public function latest(): ?array
    {
        if (!Storage::disk('local')->exists($this->path)) {
            return null;
        }

        try {
            $data = Storage::disk('local')->get($this->path);
            return json_decode($data, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }

    // Only keep if strictly necessary
    public function downloadPath(): string
    {
        return Storage::disk('local')->path($this->path);
    }
}
