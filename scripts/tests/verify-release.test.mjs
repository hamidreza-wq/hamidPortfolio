import { createHash, generateKeyPairSync, sign } from 'node:crypto';
import { mkdtempSync, readFileSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { spawnSync } from 'node:child_process';
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { fileURLToPath } from 'node:url';

const schema = Number(readFileSync(fileURLToPath(new URL('../../backend/config/platform.php', import.meta.url)), 'utf8')
  .match(/'schema_version'\s*=>\s*(\d+)\s*,/)[1]);

test('signed releases require matching bytes, identity, schema and trust', () => {
  const directory = mkdtempSync(join(tmpdir(), 'portfolio-release-test-'));
  try {
    const { privateKey, publicKey } = generateKeyPairSync('ed25519');
    const archive = Buffer.from('test archive bytes');
    const digest = createHash('sha256').update(archive).digest('hex');
    const manifest = { repository: 'hamidreza-wq/hamidPortfolio', tag: 'v1.0.0', version: '1.0.0',
      asset: { name: 'portfolio.zip', sha256: digest }, schema_from: schema, schema_to: schema,
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
    const resign = (changes) => {
      const changed = Buffer.from(JSON.stringify({ ...manifest, ...changes }));
      writeFileSync(join(directory, 'portfolio-manifest.json'), changed);
      writeFileSync(join(directory, 'portfolio-manifest.sig'), sign(null, changed, privateKey).toString('base64'));
    };
    writeFileSync(join(directory, 'portfolio-manifest.json'), JSON.stringify({ ...manifest, schema_to: schema + 1 }));
    assert.notEqual(run().status, 0, 'an unsigned edit must fail');
    resign({ schema_to: schema + 1 });
    assert.notEqual(run().status, 0, 'a signed schema-changing release is not one-click deployable');
    resign({ schema_from: schema - 1, schema_to: schema - 1 });
    assert.notEqual(run().status, 0, 'a release for an older schema is refused');
    resign({});
    assert.equal(run().status, 0);
  } finally {
    rmSync(directory, { recursive: true });
  }
});
