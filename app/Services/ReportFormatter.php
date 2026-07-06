<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Turns a spoken (or roughly typed) daily work update into a structured
 * report using the Gemini API.
 *
 * Language policy: the report body is written in the language the speaker
 * actually used (detected from the text, ko/es/en). When that language is
 * NOT Korean, Korean translations of every section are returned as well so
 * HQ can read the report alongside the original. The header (date · author
 * · team) is built in PHP so only the body sections come from the model.
 */
class ReportFormatter
{
    public function isConfigured(): bool
    {
        return (bool) config('services.gemini.key');
    }

    /**
     * @param  string  $raw  the dictated/typed update, any language, unstructured
     * @param  string  $hintLang  en|es|ko — the UI language, used only as a tie-breaker
     *                            when the text is too short to detect reliably
     * @return array{lang:string,done:string,issues:string,plan:string,done_ko:string,issues_ko:string,plan_ko:string}|null
     *                                                                                                                      null when the API is unreachable or returns an unusable response
     */
    public function format(string $raw, string $hintLang): ?array
    {
        $key = config('services.gemini.key');
        $model = config('services.gemini.model', 'gemini-flash-latest');

        $prompt = <<<PROMPT
You are formatting a construction-site daily work report. The text below is a
spoken (voice-dictated) update from a site supervisor — it may be rambling,
colloquial, out of order, or contain speech-recognition errors.

First, detect which language the speaker used: Korean ("ko"), Spanish ("es")
or English ("en"). If genuinely ambiguous (e.g. only numbers), use "{$hintLang}".

Write the report in the SPEAKER'S OWN language with exactly these fields:

- lang: the detected language code ("ko", "es" or "en").
- done: what was accomplished today, as short bullet lines separated by "\\n"
  (each line starting with "- "). Keep quantities, locations and trade names.
- issues: problems, blockers or notable events, same bullet format. Empty string
  if none were mentioned.
- plan: tomorrow's plan / next steps, same bullet format. Empty string if none
  were mentioned.

Translation fields — ONLY when lang is NOT "ko": also provide natural Korean
translations of each section as done_ko / issues_ko / plan_ko (same bullet
format, translating every line). When lang is "ko", return empty strings for
all three *_ko fields.

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
                                'lang' => ['type' => 'STRING'],
                                'done' => ['type' => 'STRING'],
                                'issues' => ['type' => 'STRING'],
                                'plan' => ['type' => 'STRING'],
                                'done_ko' => ['type' => 'STRING'],
                                'issues_ko' => ['type' => 'STRING'],
                                'plan_ko' => ['type' => 'STRING'],
                            ],
                            'required' => ['lang', 'done', 'issues', 'plan'],
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

        $lang = strtolower(trim((string) ($data['lang'] ?? '')));
        if (! in_array($lang, ['ko', 'es', 'en'], true)) {
            $lang = $hintLang;
        }

        return [
            'lang' => $lang,
            'done' => trim((string) $data['done']),
            'issues' => trim((string) ($data['issues'] ?? '')),
            'plan' => trim((string) ($data['plan'] ?? '')),
            'done_ko' => $lang === 'ko' ? '' : trim((string) ($data['done_ko'] ?? '')),
            'issues_ko' => $lang === 'ko' ? '' : trim((string) ($data['issues_ko'] ?? '')),
            'plan_ko' => $lang === 'ko' ? '' : trim((string) ($data['plan_ko'] ?? '')),
        ];
    }
}
