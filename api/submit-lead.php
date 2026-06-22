<?php
declare(strict_types=1);

/* PHP twin of api/submit-lead.js — same request/response contract, so
   js/webhook.js needs no changes to work against either runtime. */

require_once __DIR__ . '/../php/submit-lead.php';
require_once __DIR__ . '/../php/pdf-token.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$body = json_decode($raw ?: '', true);
if (!is_array($body)) {
    $body = [];
}

$payload = submit_lead_validate_payload($body);
if ($payload === null) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_payload']);
    exit;
}

if (!submit_lead_forward_to_make($payload)) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => 'webhook_failed']);
    exit;
}

try {
    $token = pdf_issue_token();
} catch (Throwable $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server_misconfigured']);
    exit;
}

echo json_encode(['ok' => true, 'token' => $token]);
