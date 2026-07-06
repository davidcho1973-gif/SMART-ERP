<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Turns a spoken (or roughly typed) daily work update into a structured
 * report using the Gemini API. The header (date · author · team) is built
 * in PHP so only the body sections come from the model.
 */
class ReportFormatter
{
    public function isConfigured(): bool
    {
        return (bool) config('services.gemini.key');
    }

    /**
     * @param  string  $raw  the dictated/typed update, any language, unstructured
     * @param  string  $lang  en|es|ko — the language the report should be written in
     * @return array{done:string,issues:string,plan:string}|null null when the API
     *                                                           is unreachable or returns an unusable response
     */
    public function format(string $raw, string $lang): ?array
    {
        $key = config('services.gemini.key');
        $model = config('services.gemini.model', 'gemini-flash-latest');

        $langName = match ($lang) {
            'ko' => 'Korean',
            'es' => 'Spanish',
            default => 'English',
        };

        $prompt = <<<PROMPT
You are formatting a construction-site daily work report. The text below is a
spoken (voice-dictated) update from a site supervisor — it may be rambling,
colloquial, out of order, or contain speech-recognition errors.

Rewrite it as a clean daily report in {$langName} with exactly these three fields:

- done: what was accomplished today, as short bullet lines separated by "\\n"
  (each line starting with "- "). Keep quantities, locations and trade names.
- issues: problems, blockers or notable events, same bullet format. Empty string
  if none were mentioned.
- plan: tomorrow's plan / next steps, same bullet format. Empty string if none
  were mentioned.

Only reorganize and clean up what the speaker actually said — do not invent
work that was not mentioned. Fix obvious speech-to-text mistakes from context.
Respond with JSON only.

--- SPOKEN UPDATE ---
{$raw}
PROMPT;

        try {
            $response = Http::timeout(45)->post(
                "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$key}",
                [
                    'contents' => [[
                        'parts' => [['text' => $prompt]],
                    ]],
                    'generationConfig' => [
                        'response_mime_type' => 'application/json',
                        'response_schema' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'done' => ['type' => 'STRING'],
                                'issues' => ['type' => 'STRING'],
                                'plan' => ['type' => 'STRING'],
                            ],
                            'required' => ['done', 'issues', 'plan'],
                        ],
                        'temperature' => 0.2,
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
        if (! is_array($data) || trim((string) ($data['done'] ?? '')) === '') {
            return null;
        }

        return [
            'done' => trim((string) $data['done']),
            'issues' => trim((string) ($data['issues'] ?? '')),
            'plan' => trim((string) ($data['plan'] ?? '')),
        ];
    }
}
