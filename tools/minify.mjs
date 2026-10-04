#!/usr/bin/env node
// Phase 7: safe minification of the theme's front-end CSS and JS.
// Writes x.min.css / x.min.js NEXT TO each source (same directory, so relative url() paths to
// ../fonts and ../images still resolve). Sources stay readable and remain the files you edit/build.
// The theme serves .min files automatically unless SCRIPT_DEBUG is true (inc/performance.php).
//   CSS: lightningcss minify only — whitespace/comments/shorter colour + number forms. No targets,
//        so no prefixing, nesting lowering or rule merging that could change the cascade.
//   JS:  esbuild minify (whitespace, identifiers, syntax) of each file on its own — no bundling,
//        no format change; every file is an IIFE or plain script and stays one.
// Needs: npm install (devDependencies lightningcss + esbuild). Run via `npm run build` or `npm run minify`.
import { readdirSync, readFileSync, writeFileSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { transform as css } from 'lightningcss';
import { transformSync as js } from 'esbuild';

const root = join(dirname(fileURLToPath(import.meta.url)), '..', 'theme/techdosedaily/assets');
const SKIP = new Set(['editor.css', 'editor-blocks.js']); // Editor-only: loaded by the block editor, not the public site.
let before = 0;
let after = 0;
for (const [dir, ext] of [['css', '.css'], ['js', '.js']]) {
  for (const f of readdirSync(join(root, dir))) {
    if (!f.endsWith(ext) || f.endsWith(`.min${ext}`) || SKIP.has(f)) continue;
    const src = readFileSync(join(root, dir, f));
    const out = ext === '.css'
      ? css({ filename: f, code: src, minify: true }).code
      : Buffer.from(js(src.toString('utf8'), { minify: true, loader: 'js', legalComments: 'none' }).code);
    writeFileSync(join(root, dir, f.replace(ext, `.min${ext}`)), out);
    before += src.length;
    after += out.length;
  }
}
console.log(`minified: ${Math.round(before / 1024)} KB → ${Math.round(after / 1024)} KB (uncompressed)`);
