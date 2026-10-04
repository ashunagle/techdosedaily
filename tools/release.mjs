#!/usr/bin/env node
// Builds the two release packages from the COMMITTED tree (git archive HEAD), never from local edits:
//   dist/techdosedaily-<version>.zip        (theme, top folder techdosedaily/)
//   dist/techdosedaily-core-<version>.zip   (plugin, top folder techdosedaily-core/)
// plus dist/SHA256SUMS. Refuses to run with uncommitted changes, so what is deployed is exactly a
// commit you can point to. Upload via WP Admin → Themes/Plugins → Add New → Upload (or wp-cli).
import { execSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';

const sh = (c) => execSync(c, { encoding: 'utf8' }).trim();
if (sh('git status --porcelain')) {
  console.error('Uncommitted changes: commit first, then build the release.');
  process.exit(1);
}
const tv = /Version:\s*([\d.]+)/.exec(readFileSync('theme/techdosedaily/style.css', 'utf8'))[1];
const pv = /Version:\s*([\d.]+)/.exec(readFileSync('plugins/techdosedaily-core/techdosedaily-core.php', 'utf8'))[1];
mkdirSync('dist', { recursive: true });
const out = [
  [`dist/techdosedaily-${tv}.zip`, 'HEAD:theme/techdosedaily', 'techdosedaily/'],
  [`dist/techdosedaily-core-${pv}.zip`, 'HEAD:plugins/techdosedaily-core', 'techdosedaily-core/'],
];
const sums = [];
for (const [file, tree, prefix] of out) {
  execSync(`git archive --format=zip --output=${file} ${tree} --prefix=${prefix}`, { stdio: 'inherit' });
  sums.push(`${createHash('sha256').update(readFileSync(file)).digest('hex')}  ${file.replace('dist/', '')}`);
  console.log(file);
}
writeFileSync('dist/SHA256SUMS', sums.join('\n') + '\n');
console.log(`commit ${sh('git rev-parse --short HEAD')} · SHA256SUMS written`);
