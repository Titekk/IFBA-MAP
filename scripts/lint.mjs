import { execFileSync } from 'node:child_process';
import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join, extname } from 'node:path';

const ROOT = process.cwd();
const PHP_BIN = (() => {
  try {
    execFileSync('/opt/lampp/bin/php', ['-v'], { stdio: 'ignore' });
    return '/opt/lampp/bin/php';
  } catch {
    return 'php';
  }
})();

function walk(dir, files = []) {
  for (const entry of readdirSync(dir)) {
    if (entry === '.git' || entry === 'node_modules' || entry === '.planning') continue;
    const full = join(dir, entry);
    const st = statSync(full);
    if (st.isDirectory()) {
      walk(full, files);
    } else {
      files.push(full);
    }
  }
  return files;
}

const allFiles = walk(ROOT);
let errors = 0;

for (const file of allFiles) {
  const ext = extname(file);
  if (ext === '.js' || ext === '.mjs') {
    try {
      execFileSync(process.execPath, ['--check', file], { stdio: 'pipe' });
    } catch (err) {
      console.error(`[LINT FAIL] JS syntax error in ${file}:\n${err.stderr?.toString() || err.message}`);
      errors++;
    }
  } else if (ext === '.php') {
    try {
      execFileSync(PHP_BIN, ['-l', file], { stdio: 'pipe' });
    } catch (err) {
      console.error(`[LINT FAIL] PHP syntax error in ${file}:\n${err.stderr?.toString() || err.message}`);
      errors++;
    }
  }
}

// Architectural Seam Check: Pure modules must not depend on Three.js or DOM globals
for (const pureRel of ['js/3d/roomConfig.js', 'js/3d/placementEngine.js']) {
  const full = join(ROOT, pureRel);
  try {
    const content = readFileSync(full, 'utf8');
    if (/\bfrom\s+['"]three['"]|\bwindow\.|\bdocument\./.test(content)) {
      console.error(`[LINT FAIL] Architectural Seam Violation: ${pureRel} must remain pure and free of Three.js/DOM globals.`);
      errors++;
    }
  } catch {
    // File not created yet (prior to Stage 4)
  }
}

if (errors > 0) {
  console.error(`\nLint failed with ${errors} error(s).`);
  process.exit(1);
} else {
  console.log('✓ Lint passed (JS syntax, PHP syntax, and architectural seam checks OK).');
}
