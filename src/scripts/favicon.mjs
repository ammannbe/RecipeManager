#!/usr/bin/env node
// Generate a full favicon set with RealFaviconGenerator (npx realfavicon) and
// copy it into a project's public web root.
//
// Usage:
//   node generate-favicon.mjs [options]
//
// Options:
//   --source <file>        Master icon, SVG preferred (default: src/favicon.svg)
//   --project <dir>        Project root (default: current directory)
//   --web <dir>            Public web root relative to project (default: auto: web | public)
//   --name <string>        Manifest name (default: existing site.webmanifest)
//   --short-name <string>  Manifest short_name (default: existing site.webmanifest)
//   --app-title <string>   apple-mobile-web-app-title (default: name)
//   --theme <hex>          Manifest theme_color (default: existing site.webmanifest)
//   --bg <hex>             Manifest background_color (default: existing site.webmanifest)
//   --padding-bg <hex>     Background for touch + manifest icons (default: manifest background_color)
//   --padding-scale <n>    Inner image scale for touch + manifest icons (default: 0.8)
//   --no-padding           Use the icon as is for touch + manifest icons
//   --radius <n>           Corner radius of the desktop favicon, 0 = square, 1 = circle (default: 0.3)
//   --dry-run             Generate into a temp dir only, don't copy
//   --stage                git add the generated files afterwards

import { execFileSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';

const REALFAVICON_VERSION = '0.9.3';

const parseArgs = (argv) => {
  const args = {};
  for (let i = 0; i < argv.length; i++) {
    const key = argv[i];
    if (!key.startsWith('--')) throw new Error(`Unexpected argument: ${key}`);
    const name = key.slice(2);
    if (['dry-run', 'stage', 'no-padding', 'no-inject', 'clean'].includes(name)) {
      args[name] = true;
    } else {
      args[name] = argv[++i];
      if (args[name] === undefined) throw new Error(`Missing value for ${key}`);
    }
  }
  return args;
};

const fail = (message) => {
  console.error(`✖ ${message}`);
  process.exit(1);
};

const transformation = (type = 'none', backgroundColor = '#ffffff', imageScale = 1, backgroundRadius = 0) => ({
  type,
  backgroundRadius,
  backgroundColor,
  imageScale,
  brightness: 1,
});

const args = parseArgs(process.argv.slice(2));
const project = path.resolve(args.project ?? '.');
const source = path.resolve(project, args.source ?? 'src/favicon.svg');
if (!fs.existsSync(source)) fail(`Source not found: ${source}`);
if (!fs.existsSync(project)) fail(`Project not found: ${project}`);

const webDirName = args.web ?? ['web', 'public'].find((dir) => fs.existsSync(path.join(project, dir)));
if (!webDirName) fail('No web root found (web/ or public/). Pass --web.');
const webDir = path.join(project, webDirName);

const manifestPath = path.join(webDir, 'site.webmanifest');
const existing = fs.existsSync(manifestPath) ? JSON.parse(fs.readFileSync(manifestPath, 'utf8')) : {};

const name = args.name || existing.name;
const shortName = args['short-name'] || existing.short_name || name;
const themeColor = args.theme || existing.theme_color;
const backgroundColor = args.bg || existing.background_color;
const missing = Object.entries({ name, theme: themeColor, bg: backgroundColor })
  .filter(([, value]) => !value)
  .map(([key]) => `--${key}`);
if (missing.length) fail(`No existing site.webmanifest values, pass: ${missing.join(' ')}`);

// Touch and maskable manifest icons get cropped by the OS, so give them breathing room
const paddedTransformation = args['no-padding']
  ? transformation()
  : transformation('background', args['padding-bg'] || backgroundColor, Number(args['padding-scale'] ?? 0.8));

// Browser tabs show the favicon as is, so round its corners. Touch and manifest
// icons stay square: iOS and Android apply their own masks.
const radius = Number(args.radius ?? 0.3);
const desktopTransformation = radius > 0
  ? transformation('background', args['padding-bg'] || backgroundColor, 1, radius)
  : transformation();

const settings = {
  icon: {
    desktop: {
      regularIconTransformation: desktopTransformation,
      darkIconType: 'none',
      darkIconTransformation: transformation(),
    },
    touch: {
      transformation: paddedTransformation,
      appTitle: args['app-title'] ?? name,
    },
    webAppManifest: {
      transformation: paddedTransformation,
      backgroundColor,
      themeColor,
      name,
      shortName,
    },
  },
  path: '/',
};

const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'favicon-'));
const outDir = path.join(tmp, 'out');
fs.copyFileSync(source, path.join(tmp, `source${path.extname(source)}`));
fs.writeFileSync(path.join(tmp, 'settings.json'), JSON.stringify(settings, null, 2));

console.log(`→ Generating with realfavicon@${REALFAVICON_VERSION} in ${tmp}`);
execFileSync(
  'npx',
  ['-y', `realfavicon@${REALFAVICON_VERSION}`, 'generate', `source${path.extname(source)}`, 'settings.json', 'markups.json', 'out'],
  { cwd: tmp, stdio: ['ignore', 'inherit', 'ignore'] },
);

const files = fs.readdirSync(outDir).sort();
// Cache busting: version all favicon URLs with a hash of the generated icons,
// so the version only changes when the icons actually change
const hash = createHash('sha256');
files.filter((file) => file !== 'site.webmanifest').forEach((file) => hash.update(fs.readFileSync(path.join(outDir, file))));
const version = hash.digest('hex').slice(0, 8);
const withVersion = (url) => `${url}?v=${version}`;

const manifestFile = path.join(outDir, 'site.webmanifest');
const manifest = JSON.parse(fs.readFileSync(manifestFile, 'utf8'));
manifest.icons.forEach((icon) => (icon.src = withVersion(icon.src)));
// Keep fields the generator doesn't know (id, start_url, scope, lang, …), generated values win
fs.writeFileSync(manifestFile, JSON.stringify({ ...existing, ...manifest }, null, 2) + '\n');

const markups = JSON.parse(fs.readFileSync(path.join(tmp, 'markups.json'), 'utf8'))
  .markups.map((markup) => markup.replace(/href="([^"]+)"/, (match, url) => `href="${withVersion(url)}"`));
const dryRun = Boolean(args['dry-run']);
const toStage = files.map((file) => path.join(webDirName, file));

if (dryRun) {
  console.log(`✔ Dry run, files left in ${outDir}`);
} else {
  files.forEach((file) => fs.copyFileSync(path.join(outDir, file), path.join(webDir, file)));
  console.log(`✔ Copied ${files.length} files to ${webDirName}/`);
}
files.forEach((file) => console.log(`  - ${file}`));

// Replace the favicon block in the head template. The block starts at the
// line containing "realfavicongenerator.net" and spans the following
// favicon-related <link>/<meta> lines.
const FAVICON_LINE = /^\s*<(link\b[^>]*rel="(icon|shortcut icon|apple-touch-icon|manifest|mask-icon)"|meta\b[^>]*name="(apple-mobile-web-app-title|msapplication-\w+|theme-color)")/;

const templateDirs = ['templates', 'resources/views'].map((dir) => path.join(project, dir)).filter(fs.existsSync);
const templateFiles = templateDirs.flatMap((dir) =>
  fs.readdirSync(dir, { recursive: true })
    .map((file) => path.join(dir, file))
    .filter((file) => /\.(twig|php|html)$/.test(file)),
);

console.log('\nHead markup:');
markups.forEach((markup) => console.log(`  ${markup}`));

const headTemplate = args['no-inject'] ? null : templateFiles.find((file) => fs.readFileSync(file, 'utf8').includes('realfavicongenerator.net'));
if (headTemplate) {
  const content = fs.readFileSync(headTemplate, 'utf8');
  const eol = content.includes('\r\n') ? '\r\n' : '\n';
  const lines = content.split(eol);
  const start = lines.findIndex((line) => line.includes('realfavicongenerator.net'));
  let end = start + 1;
  while (end < lines.length && FAVICON_LINE.test(lines[end])) end++;
  const indent = lines[start].match(/^\s*/)[0];
  const updated = [...lines.slice(0, start + 1), ...markups.map((markup) => indent + markup), ...lines.slice(end)].join(eol);
  const relative = path.relative(project, headTemplate);

  if (updated === content) {
    console.log(`\n✔ ${relative} already up to date.`);
  } else if (dryRun) {
    console.log(`\n→ Would update favicon block in ${relative}.`);
  } else {
    fs.writeFileSync(headTemplate, updated);
    toStage.push(relative);
    console.log(`\n✔ Updated favicon block in ${relative}.`);
  }
} else if (!args['no-inject']) {
  console.log('\n⚠ No "realfavicongenerator.net" marker found in templates. Add this to <head>:');
  console.log(`  {# https://realfavicongenerator.net #}\n${markups.map((markup) => `  ${markup}`).join('\n')}`);
}

// Favicon files from older RealFaviconGenerator versions that are no longer generated
const OBSOLETE = /^(favicon-\d+x\d+\.png|android-chrome-.+\.png|mstile-.+\.png|apple-touch-icon-.+\.png|safari-pinned-tab\.svg|browserconfig\.xml)$/;
const obsolete = fs.readdirSync(webDir).filter((file) => OBSOLETE.test(file) && !files.includes(file));
if (obsolete.length) {
  if (args.clean && !dryRun) {
    obsolete.forEach((file) => fs.rmSync(path.join(webDir, file)));
    toStage.push(...obsolete.map((file) => path.join(webDirName, file)));
    console.log(`\n✔ Removed obsolete files: ${obsolete.join(', ')}`);
  } else {
    console.log(`\n⚠ Obsolete favicon files (remove with --clean): ${obsolete.join(', ')}`);
  }
}

if (args.stage && !dryRun) {
  execFileSync('git', ['add', '-A', '--', ...toStage], { cwd: project, stdio: 'inherit' });
  console.log('✔ Staged favicon changes.');
}
