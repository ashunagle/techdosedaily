#!/usr/bin/env node
// Packages techdosedaily/ into dist/techdosedaily-<version>.zip for upload to staging.
import { execSync } from 'node:child_process';
import { mkdirSync, readFileSync } from 'node:fs';
const v = /Version:\s*([\d.]+)/.exec(readFileSync('theme/techdosedaily/style.css', 'utf8'))[1];
mkdirSync('dist', { recursive: true });
execSync(`git archive --format=zip --output=dist/techdosedaily-${v}.zip HEAD:theme/techdosedaily --prefix=techdosedaily/`, { stdio: 'inherit' });
console.log(`dist/techdosedaily-${v}.zip`);
