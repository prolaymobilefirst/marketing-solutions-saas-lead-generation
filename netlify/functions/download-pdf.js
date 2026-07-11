'use strict';

const fs = require('fs');
const path = require('path');
const { verifyToken } = require('../../lib/pdf-token');

// The admin panel's "PDF (lead magnet)" section lets an admin pick which
// server/assets/*.pdf is active; content/site-settings.json is the single
// source of truth for that choice (shared with the PHP twins). Falls back
// to the original fixed filename if the setting is missing or malformed.
function resolveActivePdfFilename(baseDir) {
  try {
    const raw = fs.readFileSync(path.join(baseDir, 'content', 'site-settings.json'), 'utf8');
    const settings = JSON.parse(raw);
    if (settings && typeof settings.leadMagnetPdf === 'string' && settings.leadMagnetPdf) {
      return path.basename(settings.leadMagnetPdf);
    }
  } catch {
    // fall through to default
  }
  return 'sample.pdf';
}

exports.handler = async (event) => {
  const token = event.queryStringParameters && event.queryStringParameters.token;

  let valid;
  try {
    valid = verifyToken(token);
  } catch (err) {
    console.error(err);
    valid = false;
  }

  if (!valid) {
    return { statusCode: 302, headers: { Location: '/' }, body: '' };
  }

  /* `included_files` in netlify.toml bundles server/assets/*.pdf and
     content/site-settings.json next to the function, preserved at their
     project-relative paths under LAMBDA_TASK_ROOT. */
  const base = process.env.LAMBDA_TASK_ROOT || path.join(__dirname, '..', '..');
  const pdfPath = path.join(base, 'server', 'assets', resolveActivePdfFilename(base));

  let pdf;
  try {
    pdf = fs.readFileSync(pdfPath);
  } catch (err) {
    console.error(err);
    return { statusCode: 302, headers: { Location: '/' }, body: '' };
  }

  return {
    statusCode: 200,
    headers: {
      'Content-Type': 'application/pdf',
      'Content-Disposition': 'attachment; filename="plan-action-facturation-2026.pdf"',
      'Cache-Control': 'no-store',
    },
    body: pdf.toString('base64'),
    isBase64Encoded: true,
  };
};
