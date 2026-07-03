<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Extracts badge-front fields from a photo using the Gemini API.
 *
 * Badge layout (HOFFMAN site badges):
 *  - big "HOFFMAN" header, red company name right under it
 *  - last name, then first name, then role (red), stacked on the left
 *  - "ISSUED ON: <date>" in small print under the face photo
 */
class BadgeAnalyzer
{
    public function isConfigured(): bool
    {
        return (bool) config('services.gemini.key');
    }

    /**
     * Badge BACK: return the unique code — the number printed directly under
     * the QR code (e.g. "00102810"). Used when browser-side QR decoding fails
     * (glare / laminate reflections).
     */
    public function analyzeBack(string $imageBytes, string $mime): ?string
    {
        $key = config('services.gemini.key');
        $model = config('services.gemini.model', 'gemini-flash-latest');

        $prompt = <<<'PROMPT'
This photo shows the BACK of a construction site badge. It has a QR code with a
human-readable code printed directly UNDER the QR (e.g. "00102810").

Return that printed code exactly as shown (digits/letters, no spaces).
Ignore the P/N line at the bottom and any brand names. If the code is
unreadable, return an empty string. Respond with JSON only.
PROMPT;

        try {
            $response = Http::timeout(45)->post(
                "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$key}",
                [
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
                            'properties' => ['code' => ['type' => 'STRING']],
                            'required' => ['code'],
                        ],
                        'temperature' => 0,
                    ],
                ]
            );
        } catch (\Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }
        $text = $response->json('candidates.0.content.parts.0.text');
        $data = is_string($text) ? json_decode($text, true) : null;
        $code = is_array($data) ? trim((string) ($data['code'] ?? '')) : '';

        return $code !== '' ? $code : null;
    }

    /**
     * @return array{company:string,last:string,first:string,role:string,issued:string}|null
     *         null when the API is unreachable or returns an unusable response
     */
    public function analyzeFront(string $imageBytes, string $mime): ?array
    {
        $key = config('services.gemini.key');
        $model = config('services.gemini.model', 'gemini-flash-latest');

        $prompt = <<<'PROMPT'
You are reading a construction-site ID badge (front side). Extract exactly these fields:

- company: the red company name printed directly UNDER the big "HOFFMAN" header
  (e.g. "AUTORICA LLC"). Not "HOFFMAN" itself, and not any sponsor logos lower on the card.
- last: the LAST name (printed above the first name).
- first: the FIRST name (printed below the last name).
- role: the job title in red below the first name (e.g. "SUPERVISOR", "ELECTRICIAN").
- issued: the date next to "ISSUED ON:" under the face photo, converted to MM/DD/YYYY
  (e.g. "MARCH 04 2026" -> "03/04/2026").
- face: the bounding box of the person's face/headshot photo on the badge, as
  normalized 0..1 fractions of the whole image: {x, y, w, h} where x,y is the
  top-left corner, w,h the width/height. Wrap the head snugly. If there is no
  visible face photo, use {x:0, y:0, w:0, h:0}.

Use empty string "" for text fields that are unreadable. Respond with JSON only.
PROMPT;

        try {
            $response = Http::timeout(45)->post(
                "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$key}",
                [
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
                                'company' => ['type' => 'STRING'],
                                'last' => ['type' => 'STRING'],
                                'first' => ['type' => 'STRING'],
                                'role' => ['type' => 'STRING'],
                                'issued' => ['type' => 'STRING'],
                                'face' => [
                                    'type' => 'OBJECT',
                                    'properties' => [
                                        'x' => ['type' => 'NUMBER'], 'y' => ['type' => 'NUMBER'],
                                        'w' => ['type' => 'NUMBER'], 'h' => ['type' => 'NUMBER'],
                                    ],
                                    'required' => ['x', 'y', 'w', 'h'],
                                ],
                            ],
                            'required' => ['company', 'last', 'first', 'role', 'issued'],
                        ],
                        'temperature' => 0,
                    ],
                ]
            );
        } catch (\Throwable) {
            return null;
        }

        if (! $response->successful()) {
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

        $clean = fn ($k) => trim((string) ($data[$k] ?? ''));

        // normalize the face box: clamp to 0..1, require positive area
        $face = null;
        $f = $data['face'] ?? null;
        if (is_array($f)) {
            $num = fn ($k) => is_numeric($f[$k] ?? null) ? max(0.0, min(1.0, (float) $f[$k])) : 0.0;
            $fx = $num('x');
            $fy = $num('y');
            $fw = $num('w');
            $fh = $num('h');
            if ($fw > 0.02 && $fh > 0.02) {
                // keep the box inside the image
                $fw = min($fw, 1 - $fx);
                $fh = min($fh, 1 - $fy);
                $face = ['x' => $fx, 'y' => $fy, 'w' => $fw, 'h' => $fh];
            }
        }

        return [
            'company' => $clean('company'),
            'last' => $clean('last'),
            'first' => $clean('first'),
            'role' => $clean('role'),
            'issued' => $clean('issued'),
            'face' => $face,
        ];
    }
}
