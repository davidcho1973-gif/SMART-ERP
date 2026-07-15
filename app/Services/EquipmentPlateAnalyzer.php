<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Reads an equipment DATA PLATE (nameplate) photo with Gemini and returns the
 * maker, model, serial, type, year and capacity — to auto-fill registration.
 * Mirrors ReceiptAnalyzer / BadgeAnalyzer.
 */
class EquipmentPlateAnalyzer
{
    public function isConfigured(): bool
    {
        return (bool) config('services.gemini.key');
    }

    private function generate(array $payload): ?\Illuminate\Http\Client\Response
    {
        $key = config('services.gemini.key');
        $models = (array) config('services.gemini.models', ['gemini-2.5-flash']);
        $response = null;
        foreach ($models as $m) {
            try {
                $response = Http::timeout(45)->post(
                    "https://generativelanguage.googleapis.com/v1beta/models/{$m}:generateContent?key={$key}",
                    $payload
                );
            } catch (\Throwable) {
                continue;
            }
            if ($response->successful()) {
                return $response;
            }
        }

        return null;
    }

    /**
     * @return array{maker:string,model:string,serial:string,type:string,year:string,capacity:string}|null
     */
    public function analyze(string $imageBytes, string $mime): ?array
    {
        $prompt = <<<'PROMPT'
You are reading a construction EQUIPMENT data plate / nameplate photo. Extract:
- maker: the manufacturer / brand.
- model: the model number.
- serial: the serial number (S/N).
- type: the equipment kind in plain words (e.g. "Excavator", "Welder", "Boom lift", "Generator").
- year: the manufacture year (4 digits) or "".
- capacity: the rated capacity/weight if shown (e.g. "30t", "202kW") or "".
Use empty string "" for anything unreadable. Respond with JSON only.
PROMPT;

        $response = $this->generate([
            'contents' => [[
                'parts' => [
                    ['text' => $prompt],
                    ['inline_data' => ['mime_type' => $mime, 'data' => base64_encode($imageBytes)]],
                ],
            ]],
            'generationConfig' => [
                'response_mime_type' => 'application/json',
                'response_schema' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'maker' => ['type' => 'STRING'],
                        'model' => ['type' => 'STRING'],
                        'serial' => ['type' => 'STRING'],
                        'type' => ['type' => 'STRING'],
                        'year' => ['type' => 'STRING'],
                        'capacity' => ['type' => 'STRING'],
                    ],
                    'required' => ['maker', 'model', 'serial', 'type', 'year', 'capacity'],
                ],
                'temperature' => 0,
            ],
        ]);
        if ($response === null) {
            return null;
        }
        $text = $response->json('candidates.0.content.parts.0.text');
        if (! is_string($text)) {
            return null;
        }
        $data = json_decode($text, true);
        if (! is_array($data)) {
            return null;
        }
        $clean = fn ($k) => mb_substr(trim((string) ($data[$k] ?? '')), 0, 120);

        return [
            'maker' => $clean('maker'), 'model' => $clean('model'), 'serial' => $clean('serial'),
            'type' => $clean('type'), 'year' => $clean('year'), 'capacity' => $clean('capacity'),
        ];
    }
}
