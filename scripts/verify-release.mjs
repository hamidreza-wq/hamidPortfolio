import { createHash, createPublicKey, verify } from 'node:crypto';
import { readFileSync } from 'node:fs';

const [directory, tag, digest, installed] = process.argv.slice(2);
if (!/^v(?:0|[1-9]\d*)\.(?:0|[1-9]\d*)\.(?:0|[1-9]\d*)$/.test(tag)
    || !/^[a-f0-9]{64}$/.test(digest)) {
  throw new Error('Invalid release identity');
}
const raw = readFileSync(`${directory}/portfolio-manifest.json`);
const signature = Buffer.from(readFileSync(`${directory}/portfolio-manifest.sig`, 'utf8').trim(), 'base64');
const publicBytes = Buffer.from(process.env.RELEASE_PUBLIC_KEY ?? '', 'base64');
if (raw.length > 65536 || signature.length !== 64 || publicBytes.length !== 32) {
  throw new Error('Missing or invalid release signature/trust key');
}
const key = createPublicKey({ key: Buffer.concat([Buffer.from('302a300506032b6570032100', 'hex'), publicBytes]), format: 'der', type: 'spki' });
if (!verify(null, raw, key, signature)) throw new Error('Release signature failed');
const manifest = JSON.parse(raw);
if (manifest.repository !== 'hamidreza-wq/hamidPortfolio' || manifest.tag !== tag
    || manifest.version !== tag.slice(1) || manifest.asset?.sha256 !== digest
    || manifest.asset?.name !== 'portfolio.zip'
    || manifest.schema_from !== 3 || manifest.schema_to !== 3
    || typeof manifest.minimum_php !== 'string'
    || !Array.isArray(manifest.required_extensions)
    || !['mysql', 'mariadb'].includes(manifest.database_engine)) {
  throw new Error('Signed release identity or schema compatibility failed');
}
const tuple = value => value.split(/[.-]/).slice(0, 3).map(Number);
const newer = tuple(manifest.version).some((value, index, values) =>
  values.slice(0, index).every((part, previous) => part === tuple(installed)[previous])
  && value > tuple(installed)[index]);
if (!newer) throw new Error('Release must be newer than the installed version');
const archive = readFileSync(`${directory}/portfolio.zip`);
if (archive.length > 50 * 1024 * 1024 || createHash('sha256').update(archive).digest('hex') !== digest) {
  throw new Error('Release archive checksum or size failed');
}
console.log('Independent release signature, identity and archive digest verified.');
