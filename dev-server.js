'use strict';

/* Local development only — Netlify/Vercel use their own serverless runtime
   in production. This just emulates enough of Vercel's (req, res) API to
   exercise api/submit-lead.js unmodified, without a Netlify/Vercel account.

   Usage: node dev-server.js [port]   (defaults to 8080) */

const http = require('http');
const fs = require('fs');
const path = require('path');
const { URL } = require('url');

const ROOT = __dirname;
const PORT = process.argv[2] || process.env.PORT || 8080;

if (!process.env.MAKE_WEBHOOK_URL) {
  console.warn('MAKE_WEBHOOK_URL not set — submit-lead will call the live URL hardcoded in lib/submit-lead.js.');
  console.warn('Set MAKE_WEBHOOK_URL to a test endpoint (e.g. https://webhook.site/...) to avoid hitting production.');
}

const apiHandlers = {
  '/api/submit-lead': require('./api/submit-lead'),
};

const MIME = {
  '.html': 'text/html; charset=utf-8',
  '.css': 'text/css',
  '.js': 'application/javascript',
  '.json': 'application/json',
  '.webp': 'image/webp',
  '.png': 'image/png',
  '.jpg': 'image/jpeg',
  '.svg': 'image/svg+xml',
  '.pdf': 'application/pdf',
};

function serveStatic(req, res, pathname) {
  const safePath = path.normalize(decodeURIComponent(pathname)).replace(/^(\.\.[/\\])+/, '');
  let filePath = path.join(ROOT, safePath === '/' ? 'index.html' : safePath);

  fs.readFile(filePath, (err, data) => {
    if (err) {
      res.writeHead(404, { 'Content-Type': 'text/plain' });
      res.end('404 Not Found');
      return;
    }
    res.writeHead(200, { 'Content-Type': MIME[path.extname(filePath)] || 'application/octet-stream' });
    res.end(data);
  });
}

function addVercelShims(req, res) {
  res.status = function (code) { res.statusCode = code; return res; };
  res.json = function (body) {
    res.setHeader('Content-Type', 'application/json');
    res.end(JSON.stringify(body));
  };
  return new Promise((resolve, reject) => {
    const chunks = [];
    req.on('data', (c) => chunks.push(c));
    req.on('end', () => {
      const raw = Buffer.concat(chunks).toString('utf8');
      try { req.body = raw ? JSON.parse(raw) : {}; } catch { req.body = {}; }
      resolve();
    });
    req.on('error', reject);
  });
}

const server = http.createServer(async (req, res) => {
  const url = new URL(req.url, `http://${req.headers.host}`);
  const handler = apiHandlers[url.pathname];

  if (handler) {
    req.query = Object.fromEntries(url.searchParams);
    try {
      await addVercelShims(req, res);
      await handler(req, res);
    } catch (err) {
      console.error(err);
      if (!res.headersSent) res.status(500).json({ ok: false, error: 'internal_error' });
    }
    return;
  }

  serveStatic(req, res, url.pathname);
});

server.listen(PORT, () => {
  console.log(`Dev server (with /api/* emulation) running at http://localhost:${PORT}`);
});
