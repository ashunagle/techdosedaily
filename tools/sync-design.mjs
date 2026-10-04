#!/usr/bin/env node
// Copies the approved design-system sources into design-source/ so builds are reproducible.
// Run from D:\TechDoseDaily\06-WordPress after any approved design-system change.
import { copyFileSync, existsSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const ds = join(root, '..', '01-Design-System');
for (const f of ['bundle.css', 'tokens.json']) {
  const from = join(ds, f);
  if (!existsSync(from)) { console.error(`missing ${from}`); process.exit(1); }
  copyFileSync(from, join(root, 'design-source', f));
  console.log(`synced ${f}`);
}
