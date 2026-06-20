'use strict';

/* Server-side proxy to the Make.com webhook. The browser never calls
   Make.com directly — this keeps the webhook URL off the client and lets
   us gate the PDF download on a genuine successful response. */

const WEBHOOK_URL = process.env.MAKE_WEBHOOK_URL
  || 'https://hook.eu1.make.com/1675shwpb93c4uvbdbcmmusoabm7b1h7';

async function forwardToMake(payload) {
  const resp = await fetch(WEBHOOK_URL, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  });
  return resp.ok;
}

function validatePayload(body) {
  const firstName = typeof body.first_name === 'string' ? body.first_name.trim() : '';
  const email = typeof body.email === 'string' ? body.email.trim() : '';
  const gdpr = body.gdpr === true;

  if (!firstName) return null;
  if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return null;
  if (!gdpr) return null;

  return {
    first_name: firstName,
    email,
    clientele: typeof body.clientele === 'string' ? body.clientele : '',
    statut: typeof body.statut === 'string' ? body.statut : '',
    volume: typeof body.volume === 'string' ? body.volume : '',
    connexion: typeof body.connexion === 'string' ? body.connexion : '',
    timestamp: new Date().toISOString(),
  };
}

module.exports = { forwardToMake, validatePayload };
