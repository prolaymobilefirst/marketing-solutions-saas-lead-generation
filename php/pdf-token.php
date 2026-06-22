<?php
declare(strict_types=1);

/* PHP twin of lib/pdf-token.js — same scheme, same TTL, same wire format,
   so a token issued by either runtime is interchangeable. Short-lived
   signed token proving a Make.com webhook call just succeeded. Stateless
   (HMAC-based) — no database needed. Validity window is short on purpose
   since there is no server-side revocation/replay tracking. */

require_once __DIR__ . '/config.php';

const PDF_TOKEN_TTL_SECONDS = 120;

function pdf_token_secret(): string
{
    $secret = lmoh_config('PDF_TOKEN_SECRET');
    if ($secret === null || $secret === '') {
        throw new RuntimeException('PDF_TOKEN_SECRET is not configured');
    }
    return $secret;
}

function pdf_token_sign(int $expiresAtMs): string
{
    return hash_hmac('sha256', (string) $expiresAtMs, pdf_token_secret());
}

function base64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode(string $data): string|false
{
    $padded = str_pad(strtr($data, '-_', '+/'), strlen($data) % 4 === 0 ? strlen($data) : strlen($data) + (4 - strlen($data) % 4), '=');
    return base64_decode($padded, true);
}

function pdf_issue_token(): string
{
    $expiresAtMs = (int) (microtime(true) * 1000) + PDF_TOKEN_TTL_SECONDS * 1000;
    $sig = pdf_token_sign($expiresAtMs);
    return base64url_encode("$expiresAtMs.$sig");
}

function pdf_verify_token(?string $token): bool
{
    if (!$token) {
        return false;
    }
    $decoded = base64url_decode($token);
    if ($decoded === false) {
        return false;
    }
    $parts = explode('.', $decoded, 2);
    if (count($parts) !== 2) {
        return false;
    }
    [$expiresAtStr, $sig] = $parts;
    if (!ctype_digit($expiresAtStr)) {
        return false;
    }
    $expiresAtMs = (int) $expiresAtStr;
    $nowMs = (int) (microtime(true) * 1000);
    if ($nowMs > $expiresAtMs) {
        return false;
    }
    return hash_equals(pdf_token_sign($expiresAtMs), $sig);
}
