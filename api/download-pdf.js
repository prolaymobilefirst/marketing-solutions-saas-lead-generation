'use strict';

const fs = require('fs');
const path = require('path');
const { verifyToken } = require('../lib/pdf-token');

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

module.exports = async (req, res) => {
  const token = req.query && req.query.token;

  let valid;
  try {
    valid = verifyToken(token);
  } catch (err) {
    console.error(err);
    valid = false;
  }

  if (!valid) {
    res.writeHead(302, { Location: '/' });
    res.end();
    return;
  }

  const base = process.cwd();
  const pdfPath = path.join(base, 'server', 'assets', resolveActivePdfFilename(base));

  let pdf;
  try {
    pdf = fs.readFileSync(pdfPath);
  } catch (err) {
    console.error(err);
    res.writeHead(302, { Location: '/' });
    res.end();
    return;
  }

  res.writeHead(200, {
    'Content-Type': 'application/pdf',
    'Content-Disposition': 'attachment; filename="plan-action-facturation-2026.pdf"',
    'Cache-Control': 'no-store',
  });
  res.end(pdf);
};
