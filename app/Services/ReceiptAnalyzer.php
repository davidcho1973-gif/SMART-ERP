<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Reads a receipt photo with Gemini and returns the vendor, amount, date and a
 * best-guess expense category — so a field user only has to snap the receipt.
 * Mirrors BadgeAnalyzer (same model fall-through + JSON schema response).
 */
class ReceiptAnalyzer
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
     * @return array{vendor:string,amount:float,date:string,category:string}|null
     *         date is YYYY-MM-DD or ''; category is one of Expense::CATEGORIES; null on failure
     */
    public function analyze(string $imageBytes, string $mime): ?array
    {
        $prompt = <<<'PROMPT'
You are reading a purchase RECEIPT photo (construction field expense). Extract:

- vendor: the store / business name at the top of the receipt.
- amount: the GRAND TOTAL actually paid, as a plain number (no currency symbol,
  no thousands separators). Prefer the final total after tax. If unclear, 0.
- date: the purchase date in YYYY-MM-DD. If unreadable, "".
- category: classify the purchase as ONE of exactly:
  "fuel" (gas station / diesel), "meal" (restaurant / food), "transport"
  (parking / toll / delivery / freight), "tool" (hardware / tools),
  "supply" (consumables / small materials), "rental" (equipment rental),
  or "other" if none fit.

Respond with JSON only.
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
                        'amount' => ['type' => 'NUMBER'],
                        'date' => ['type' => 'STRING'],
                        'category' => ['type' => 'STRING'],
                    ],
                    'required' => ['vendor', 'amount', 'date', 'category'],
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

        $cat = strtolower(trim((string) ($data['category'] ?? 'other')));
        if (! in_array($cat, \App\Models\Expense::CATEGORIES, true)) {
            $cat = 'other';
        }
        $date = trim((string) ($data['date'] ?? ''));
        if ($date !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = '';
        }

        return [
            'vendor' => trim((string) ($data['vendor'] ?? '')),
            'amount' => max(0.0, (float) ($data['amount'] ?? 0)),
            'date' => $date,
            'category' => $cat,
        ];
    }
}
