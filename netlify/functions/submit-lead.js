'use strict';

const { issueToken } = require('../../lib/pdf-token');
const { forwardToMake, validatePayload } = require('../../lib/submit-lead');

exports.handler = async (event) => {
  if (event.httpMethod !== 'POST') {
    return { statusCode: 405, body: JSON.stringify({ ok: false, error: 'method_not_allowed' }) };
  }

  let body;
  try {
    body = JSON.parse(event.body || '{}');
  } catch {
    return { statusCode: 400, body: JSON.stringify({ ok: false, error: 'invalid_json' }) };
  }

  const payload = validatePayload(body);
  if (!payload) {
    return { statusCode: 400, body: JSON.stringify({ ok: false, error: 'invalid_payload' }) };
  }

  let success = false;
  try {
    success = await forwardToMake(payload);
  } catch {
    success = false;
  }

  if (!success) {
    return { statusCode: 502, body: JSON.stringify({ ok: false, error: 'webhook_failed' }) };
  }

  let token;
  try {
    token = issueToken();
  } catch (err) {
    console.error(err);
    return { statusCode: 500, body: JSON.stringify({ ok: false, error: 'server_misconfigured' }) };
  }

  return {
    statusCode: 200,
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ ok: true, token }),
  };
};
