/**
 * Packages the plugin as an installable ZIP: dist/licensesender.zip (top folder "licensesender/").
 *
 * Run through `npm run build:zip`, which builds the admin bundles first. Files listed in
 * .distignore are left out. When Composer is available, an optimised classmap autoloader is
 * generated into the package's vendor/; without it the plugin falls back to src/autoload.php.
 */
import { spawnSync } from 'child_process';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import zlib from 'zlib';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const slug = 'licensesender';
const distDir = path.join(root, 'dist');
const stageDir = path.join(distDir, slug);
const zipPath = path.join(distDir, `${slug}.zip`);

function fail(message) {
  console.error(`✖ ${message}`);
  process.exit(1);
}

// 1. The header version and LICENSESENDER_VERSION must agree, or WordPress and the updater disagree.
const main = fs.readFileSync(path.join(root, `${slug}.php`), 'utf8');
const headerVersion = main.match(/^\s*\*\s*Version:\s*(\S+)/m)?.[1];
const constVersion = main.match(/define\(\s*'LICENSESENDER_VERSION',\s*'([^']+)'/)?.[1];
if (!headerVersion || headerVersion !== constVersion) {
  fail(`Version mismatch: header ${headerVersion} vs LICENSESENDER_VERSION ${constVersion}.`);
}
if (/define\(\s*'LICENSESENDER_API_BASE_URL',\s*'http:\/\/(127\.0\.0\.1|localhost)/.test(main)) {
  fail('licensesender.php points at a local API. Define LICENSESENDER_API_BASE_URL in wp-config.php instead.');
}
for (const required of ['build/admin/licenses.js', 'build/admin/assets/style.css', 'build/public/support.js', 'build/public/my-keys.js']) {
  if (!fs.existsSync(path.join(root, required))) fail(`Missing ${required}. Run npm run build first.`);
}

// 2. Copy everything not in .distignore into dist/licensesender/.
const rules = fs
  .readFileSync(path.join(root, '.distignore'), 'utf8')
  .split(/\r?\n/)
  .map((line) => line.trim())
  .filter((line) => line && !line.startsWith('#'))
  .map((line) => {
    const anchored = line.startsWith('/');
    const pattern = line.replace(/^\//, '').replace(/\/$/, '');
    const source = pattern.replace(/[.+^${}()|[\]\\]/g, '\\$&').replace(/\*/g, '[^/]*').replace(/\?/g, '[^/]');
    return new RegExp(anchored ? `^${source}$` : `(^|/)${source}$`);
  });
const ignored = (rel) => rules.some((rule) => rule.test(rel));

fs.rmSync(distDir, { recursive: true, force: true });

function copyTree(rel) {
  const abs = path.join(root, rel);
  for (const entry of fs.readdirSync(abs, { withFileTypes: true })) {
    const childRel = rel ? `${rel}/${entry.name}` : entry.name;
    if (ignored(childRel)) continue;
    if (entry.isDirectory()) {
      copyTree(childRel);
    } else if (entry.isFile()) {
      const target = path.join(stageDir, childRel);
      fs.mkdirSync(path.dirname(target), { recursive: true });
      fs.copyFileSync(path.join(root, childRel), target);
    }
  }
}
copyTree('');

// 3. Production autoloader (optional).
fs.copyFileSync(path.join(root, 'composer.json'), path.join(stageDir, 'composer.json'));
const composer = spawnSync('composer', ['dump-autoload', '--optimize', '--no-dev', '--classmap-authoritative', '--no-interaction', '--quiet'], {
  cwd: stageDir,
  shell: process.platform === 'win32',
  stdio: 'inherit',
});
fs.rmSync(path.join(stageDir, 'composer.json'));
if (composer.status === 0) {
  console.log('✔ Composer classmap autoloader generated');
} else {
  fs.rmSync(path.join(stageDir, 'vendor'), { recursive: true, force: true });
  console.warn('! Composer not found: the package uses the built-in PSR-4 loader (src/autoload.php).');
}

// 4. Zip it (store + deflate, no dependencies).
const crcTable = new Uint32Array(256).map((_, n) => {
  let c = n;
  for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
  return c >>> 0;
});
function crc32(buf) {
  let c = 0xffffffff;
  for (let i = 0; i < buf.length; i++) c = crcTable[(c ^ buf[i]) & 0xff] ^ (c >>> 8);
  return (c ^ 0xffffffff) >>> 0;
}
function dosTime(date) {
  const time = (date.getHours() << 11) | (date.getMinutes() << 5) | (date.getSeconds() >> 1);
  const day = ((date.getFullYear() - 1980) << 9) | ((date.getMonth() + 1) << 5) | date.getDate();
  return { time, day };
}

function listFiles(dir, rel = '') {
  return fs.readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
    const childRel = rel ? `${rel}/${entry.name}` : entry.name;
    return entry.isDirectory() ? listFiles(path.join(dir, entry.name), childRel) : [childRel];
  });
}

const chunks = [];
const central = [];
let offset = 0;
const files = listFiles(stageDir).sort();
for (const rel of files) {
  const data = fs.readFileSync(path.join(stageDir, rel));
  const deflated = zlib.deflateRawSync(data, { level: 9 });
  const useDeflate = deflated.length < data.length;
  const body = useDeflate ? deflated : data;
  const name = Buffer.from(`${slug}/${rel}`, 'utf8');
  const { time, day } = dosTime(fs.statSync(path.join(stageDir, rel)).mtime);
  const crc = crc32(data);

  const local = Buffer.alloc(30);
  local.writeUInt32LE(0x04034b50, 0);
  local.writeUInt16LE(20, 4);
  local.writeUInt16LE(0x0800, 6); // UTF-8 names
  local.writeUInt16LE(useDeflate ? 8 : 0, 8);
  local.writeUInt16LE(time, 10);
  local.writeUInt16LE(day, 12);
  local.writeUInt32LE(crc, 14);
  local.writeUInt32LE(body.length, 18);
  local.writeUInt32LE(data.length, 22);
  local.writeUInt16LE(name.length, 26);
  chunks.push(local, name, body);

  const entry = Buffer.alloc(46);
  entry.writeUInt32LE(0x02014b50, 0);
  entry.writeUInt16LE(20, 4);
  entry.writeUInt16LE(20, 6);
  entry.writeUInt16LE(0x0800, 8);
  entry.writeUInt16LE(useDeflate ? 8 : 0, 10);
  entry.writeUInt16LE(time, 12);
  entry.writeUInt16LE(day, 14);
  entry.writeUInt32LE(crc, 16);
  entry.writeUInt32LE(body.length, 20);
  entry.writeUInt32LE(data.length, 24);
  entry.writeUInt16LE(name.length, 28);
  entry.writeUInt32LE(offset, 42);
  central.push(entry, name);

  offset += local.length + name.length + body.length;
}
const centralSize = central.reduce((sum, b) => sum + b.length, 0);
const end = Buffer.alloc(22);
end.writeUInt32LE(0x06054b50, 0);
end.writeUInt16LE(files.length, 8);
end.writeUInt16LE(files.length, 10);
end.writeUInt32LE(centralSize, 12);
end.writeUInt32LE(offset, 16);

fs.writeFileSync(zipPath, Buffer.concat([...chunks, ...central, end]));
fs.rmSync(stageDir, { recursive: true, force: true });

const size = (fs.statSync(zipPath).size / 1024 / 1024).toFixed(2);
console.log(`✔ ${path.relative(root, zipPath)}  v${headerVersion}  ${files.length} files  ${size} MB`);
console.log('  Attach it to the GitHub release v' + headerVersion + ' so the update checker installs it.');
