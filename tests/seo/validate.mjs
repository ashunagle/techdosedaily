import Validator from '@adobe/structured-data-validator';
import WebAutoExtractor from '@marbec/web-auto-extractor';
import fs from 'fs';
import path from 'path';
// Usage:
//   node validate.mjs '<json of name → path>'     fetch each page from BASE (default the local site) and validate
//   node validate.mjs --dir <graphs-dir>          validate JSON-LD saved by save_graphs.py (<dir>/<owner>/<name>.json),
//                                                 offline — e.g. staging pages saved on the server behind Basic Auth
const B = (process.env.BASE || 'http://127.0.0.1:8090').replace(/\/$/, '');
// Vocabulary: schemaorg-all-https.jsonld from the schemaorg/schemaorg GitHub release (v30.1), saved next to this file.
const schema = JSON.parse(fs.readFileSync(new URL('./schemaorg-all-https.jsonld', import.meta.url), 'utf8'));
const validator = new Validator(schema);
const check = async (html) => {
  const ex = new WebAutoExtractor({ addLocation: true, embedSource: ['rdfa', 'microdata'] });
  const issues = await validator.validate(ex.parse(html));
  return issues.map((i) => ({ severity: i.severity, message: i.issueMessage, path: (i.path || []).map((p) => p.type || p.property || p).join(' > '), fields: i.fieldNames }));
};
const out = {};
if (process.argv[2] === '--dir') {
  const dir = process.argv[3];
  for (const owner of fs.readdirSync(dir)) {
    for (const file of fs.readdirSync(path.join(dir, owner)).filter((f) => f.endsWith('.json'))) {
      const blocks = JSON.parse(fs.readFileSync(path.join(dir, owner, file), 'utf8'));
      const html = '<html><head>' + blocks.map((b) => `<script type="application/ld+json">${JSON.stringify(b.data)}</script>`).join('') + '</head><body></body></html>';
      out[`${owner}:${file.replace(/\.json$/, '')}`] = blocks.length ? await check(html) : [{ severity: 'INFO', message: 'no JSON-LD on this page' }];
    }
  }
} else {
  for (const [name, p] of Object.entries(JSON.parse(process.argv[2]))) {
    out[name] = await check(await (await fetch(B + p)).text());
  }
}
console.log(JSON.stringify(out, null, 1));
