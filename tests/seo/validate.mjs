import Validator from '@adobe/structured-data-validator';
import WebAutoExtractor from '@marbec/web-auto-extractor';
import fs from 'fs';
const B = 'http://127.0.0.1:8090';
const urls = JSON.parse(process.argv[2]);
// Vocabulary: schemaorg-all-https.jsonld from the schemaorg/schemaorg GitHub release (v30.1), saved next to this file.
const schema = JSON.parse(fs.readFileSync(new URL('./schemaorg-all-https.jsonld', import.meta.url), 'utf8'));
const validator = new Validator(schema);
const out = {};
for (const [name, path] of Object.entries(urls)) {
  const html = await (await fetch(B + path)).text();
  const ex = new WebAutoExtractor({ addLocation: true, embedSource: ['rdfa', 'microdata'] });
  const data = ex.parse(html);
  const issues = await validator.validate(data);
  out[name] = issues.map((i) => ({ severity: i.severity, message: i.issueMessage, path: (i.path || []).map((p) => p.type || p.property || p).join(' > '), fields: i.fieldNames }));
}
console.log(JSON.stringify(out, null, 1));
