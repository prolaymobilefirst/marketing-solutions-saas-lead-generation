'use strict';

const fs = require('fs');
const path = require('path');
const { verifyToken } = require('../lib/pdf-token');

module.exports = async (req, res) => {
  const token = req.query && req.query.token;

  if (!verifyToken(token)) {
    res.writeHead(302, { Location: '/' });
    res.end();
    return;
  }

  const pdfPath = path.join(process.cwd(), 'server', 'assets', 'sample.pdf');
  const pdf = fs.readFileSync(pdfPath);

  res.writeHead(200, {
    'Content-Type': 'application/pdf',
    'Content-Disposition': 'attachment; filename="plan-action-facturation-2026.pdf"',
    'Cache-Control': 'no-store',
  });
  res.end(pdf);
};
