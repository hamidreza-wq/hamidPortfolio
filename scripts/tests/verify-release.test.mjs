import { createHash, generateKeyPairSync, sign } from 'node:crypto';
import { mkdtempSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { spawnSync } from 'node:child_process';
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { fileURLToPath } from 'node:url';

test('signed releases require matching bytes, identity, schema and trust', () => {
  const directory = mkdtempSync(join(tmpdir(), 'portfolio-release-test-'));
  try {
    const { privateKey, publicKey } = generateKeyPairSync('ed25519');
    const archive = Buffer.from('test archive bytes');
    const digest = createHash('sha256').update(archive).digest('hex');
    const manifest = { repository: 'hamidreza-wq/hamidPortfolio', tag: 'v1.0.0', version: '1.0.0',
      asset: { name: 'portfolio.zip', sha256: digest }, schema_from: 3, schema_to: 3,
      minimum_php: '8.3.0', required_extensions: ['sodium'], database_engine: 'mysql' };
    const bytes = Buffer.from(JSON.stringify(manifest));
    writeFileSync(join(directory, 'portfolio.zip'), archive);
    writeFileSync(join(directory, 'portfolio-manifest.json'), bytes);
    writeFileSync(join(directory, 'portfolio-manifest.sig'), sign(null, bytes, privateKey).toString('base64'));
    const env = { ...process.env, RELEASE_PUBLIC_KEY: publicKey.export({ type: 'spki', format: 'der' }).subarray(-32).toString('base64') };
    const run = (tag = 'v1.0.0', installed = '0.2.0-dev') => spawnSync(process.execPath,
      [fileURLToPath(new URL('../verify-release.mjs', import.meta.url)),
        directory, tag, digest, installed], { env });
    assert.equal(run().status, 0);
    assert.notEqual(run('v2.0.0').status, 0);
    assert.notEqual(run('v1.0.0', '1.0.0').status, 0);
    writeFileSync(join(directory, 'portfolio.zip'), 'tampered archive');
    assert.notEqual(run().status, 0);
    writeFileSync(join(directory, 'portfolio.zip'), archive);
    writeFileSync(join(directory, 'portfolio-manifest.json'), JSON.stringify({ ...manifest, schema_to: 4 }));
    assert.notEqual(run().status, 0);
  } finally {
    rmSync(directory, { recursive: true });
  }
});
