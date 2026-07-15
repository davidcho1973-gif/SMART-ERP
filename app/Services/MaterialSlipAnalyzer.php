<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Reads a materials DELIVERY SLIP / invoice photo with Gemini and returns the
 * vendor, date and the LINE ITEMS (name · qty · unit · unit price · amount) —
 * so a whole delivery is registered from one photo. Mirrors ReceiptAnalyzer.
 */
class MaterialSlipAnalyzer
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
                $response = Http::timeout(60)->post(
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
     * @return array{vendor:string,date:string,lines:array<int,array{name:string,qty:float,unit:string,unitPrice:float,amount:float}>}|null
     */
    public function analyze(string $imageBytes, string $mime): ?array
    {
        $prompt = <<<'PROMPT'
You are reading a construction MATERIALS delivery slip / supplier invoice photo.
Extract:

- vendor: the supplier / store name at the top.
- date: the delivery or invoice date in YYYY-MM-DD (or "" if unreadable).
- lines: an array, one object PER line item on the slip:
    - name: the material description exactly as printed (keep the size/spec, e.g. "3/4in Copper Pipe").
    - qty: the quantity as a number.
    - unit: the unit of measure ("ea","m","ft","box","kg","roll","set","pc"); use "ea" if none.
    - unitPrice: the unit price as a number (0 if not shown).
    - amount: the line total as a number (qty × unitPrice if not printed).

Ignore subtotal/tax/total summary rows — only real material line items.
Use 0 for unreadable numbers. Respond with JSON only.
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
                        'vendor' => ['type' => 'STRING'],
                        'date' => ['type' => 'STRING'],
                        'lines' => [
                            'type' => 'ARRAY',
                            'items' => [
                                'type' => 'OBJECT',
                                'properties' => [
                                    'name' => ['type' => 'STRING'],
                                    'qty' => ['type' => 'NUMBER'],
                                    'unit' => ['type' => 'STRING'],
                                    'unitPrice' => ['type' => 'NUMBER'],
                                    'amount' => ['type' => 'NUMBER'],
                                ],
                                'required' => ['name', 'qty', 'unit', 'unitPrice', 'amount'],
                            ],
                        ],
                    ],
                    'required' => ['vendor', 'date', 'lines'],
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

        $date = trim((string) ($data['date'] ?? ''));
        if ($date !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = '';
        }
        $lines = [];
        foreach ((array) ($data['lines'] ?? []) as $ln) {
            if (! is_array($ln)) {
                continue;
            }
            $name = trim((string) ($ln['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $qty = max(0.0, (float) ($ln['qty'] ?? 0));
            $unitPrice = max(0.0, (float) ($ln['unitPrice'] ?? 0));
            $amount = (float) ($ln['amount'] ?? 0);
            if ($amount <= 0 && $qty > 0 && $unitPrice > 0) {
                $amount = round($qty * $unitPrice, 2);
            }
            $lines[] = [
                'name' => mb_substr($name, 0, 160),
                'qty' => $qty,
                'unit' => mb_substr(trim((string) ($ln['unit'] ?? 'ea')) ?: 'ea', 0, 12),
                'unitPrice' => $unitPrice,
                'amount' => max(0.0, $amount),
            ];
        }

        return [
            'vendor' => mb_substr(trim((string) ($data['vendor'] ?? '')), 0, 120),
            'date' => $date,
            'lines' => $lines,
        ];
    }
}
