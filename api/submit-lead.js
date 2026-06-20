'use strict';

const { issueToken } = require('../lib/pdf-token');
const { forwardToMake, validatePayload } = require('../lib/submit-lead');

module.exports = async (req, res) => {
  if (req.method !== 'POST') {
    res.status(405).json({ ok: false, error: 'method_not_allowed' });
    return;
  }

  const payload = validatePayload(req.body || {});
  if (!payload) {
    res.status(400).json({ ok: false, error: 'invalid_payload' });
    return;
  }

  let success = false;
  try {
    success = await forwardToMake(payload);
  } catch {
    success = false;
  }

  if (!success) {
    res.status(502).json({ ok: false, error: 'webhook_failed' });
    return;
  }

  let token;
  try {
    token = issueToken();
  } catch (err) {
    console.error(err);
    res.status(500).json({ ok: false, error: 'server_misconfigured' });
    return;
  }

  res.status(200).json({ ok: true, token });
};
