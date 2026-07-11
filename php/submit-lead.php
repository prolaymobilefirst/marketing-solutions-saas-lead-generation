<?php
declare(strict_types=1);

/* PHP twin of lib/submit-lead.js — same validation rules and Make.com
   payload shape. The browser never calls Make.com directly, and never
   receives the PDF — that's delivered solely by the automated email
   Make.com sends on a genuine webhook success. */

require_once __DIR__ . '/config.php';

function submit_lead_webhook_url(): string
{
    return lmoh_config('MAKE_WEBHOOK_URL', 'https://hook.eu1.make.com/1675shwpb93c4uvbdbcmmusoabm7b1h7');
}

function submit_lead_forward_to_make(array $payload): bool
{
    $ch = curl_init(submit_lead_webhook_url());
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        error_log("submit-lead: Make.com request failed: $error");
        return false;
    }
    return $status >= 200 && $status < 300;
}

/* gmdate()'s 'v' (milliseconds) format char only works against a real
   sub-second timestamp, not the implicit time()-based one — build it from
   microtime() explicitly to match JS's Date.toISOString() precision. */
function submit_lead_iso8601_now(): string
{
    $micro = microtime(true);
    $ms = sprintf('%03d', ($micro - floor($micro)) * 1000);
    return gmdate('Y-m-d\TH:i:s', (int) $micro) . ".$ms" . 'Z';
}

/** @return array|null validated payload, or null if invalid */
function submit_lead_validate_payload(array $body): ?array
{
    $firstName = is_string($body['first_name'] ?? null) ? trim($body['first_name']) : '';
    $email = is_string($body['email'] ?? null) ? trim($body['email']) : '';
    $gdpr = ($body['gdpr'] ?? null) === true;

    if ($firstName === '') {
        return null;
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return null;
    }
    if (!$gdpr) {
        return null;
    }

    $connexion = is_string($body['connexion'] ?? null) ? $body['connexion'] : '';

    return [
        'first_name' => $firstName,
        'email' => $email,
        'clientele' => is_string($body['clientele'] ?? null) ? $body['clientele'] : '',
        'statut' => is_string($body['statut'] ?? null) ? $body['statut'] : '',
        'volume' => is_string($body['volume'] ?? null) ? $body['volume'] : '',
        'connexion' => $connexion,
        // "logiciel" mirrors connexion (Step 3's current-software answer) under
        // the label the Make.com sheet mapping expects; kept alongside
        // connexion rather than renamed, so existing mappings don't break.
        'logiciel' => $connexion,
        // Always "Oui": submit_lead_forward_to_make only runs on a genuine
        // lead submission, and Make.com only emails the PDF on that same
        // successful call, so every row that reaches the sheet corresponds
        // to a report having been sent.
        'rapport' => 'Oui',
        'timestamp' => submit_lead_iso8601_now(),
    ];
}
