'use strict';

/* Short-lived signed token proving a Make.com webhook call just succeeded.
   Stateless (HMAC-based) — no database needed. Validity window is short on
   purpose since there is no server-side revocation/replay tracking. */

const crypto = require('crypto');

const TOKEN_TTL_SECONDS = 120;

function getSecret() {
  const secret = process.env.PDF_TOKEN_SECRET;
  if (!secret) throw new Error('PDF_TOKEN_SECRET environment variable is not set');
  return secret;
}

function sign(expiresAt) {
  return crypto.createHmac('sha256', getSecret()).update(String(expiresAt)).digest('hex');
}

function issueToken() {
  const expiresAt = Date.now() + TOKEN_TTL_SECONDS * 1000;
  const sig = sign(expiresAt);
  return Buffer.from(`${expiresAt}.${sig}`).toString('base64url');
}

function verifyToken(token) {
  if (!token) return false;
  let decoded;
  try {
    decoded = Buffer.from(token, 'base64url').toString('utf8');
  } catch {
    return false;
  }
  const [expiresAtStr, sig] = decoded.split('.');
  if (!expiresAtStr || !sig) return false;
  const expiresAt = Number(expiresAtStr);
  if (!Number.isFinite(expiresAt) || Date.now() > expiresAt) return false;

  const expectedSig = sign(expiresAt);
  const sigBuf = Buffer.from(sig);
  const expectedBuf = Buffer.from(expectedSig);
  if (sigBuf.length !== expectedBuf.length) return false;
  return crypto.timingSafeEqual(sigBuf, expectedBuf);
}

module.exports = { issueToken, verifyToken };
