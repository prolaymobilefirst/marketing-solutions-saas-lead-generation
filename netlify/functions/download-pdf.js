'use strict';

const fs = require('fs');
const path = require('path');
const { verifyToken } = require('../../lib/pdf-token');

exports.handler = async (event) => {
  const token = event.queryStringParameters && event.queryStringParameters.token;

  if (!verifyToken(token)) {
    return { statusCode: 302, headers: { Location: '/' }, body: '' };
  }

  /* `included_files` in netlify.toml bundles server/assets/sample.pdf next to
     the function, preserved at its project-relative path under LAMBDA_TASK_ROOT. */
  const base = process.env.LAMBDA_TASK_ROOT || path.join(__dirname, '..', '..');
  const pdfPath = path.join(base, 'server', 'assets', 'sample.pdf');
  const pdf = fs.readFileSync(pdfPath);

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
