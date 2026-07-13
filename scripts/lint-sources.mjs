import { readdirSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const type = process.argv[2];
const extension = 'js' === type ? '.js' : '.css';
const command = 'win32' === process.platform ? 'wp-scripts.cmd' : 'wp-scripts';
const directoriesToIgnore = new Set(['.git', 'dist', 'node_modules', 'vendor']);

function collectFiles(directory) {
	return readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
		const path = resolve(directory, entry.name);

		if (entry.isDirectory()) {
			return directoriesToIgnore.has(entry.name)
				? []
				: collectFiles(path);
		}

		return entry.isFile() && entry.name.endsWith(extension) ? [path] : [];
	});
}

const files = collectFiles(root);

if (0 === files.length) {
	console.log(`No ${type} source files to lint.`);
	process.exit(0);
}

const result = spawnSync(
	command,
	['js' === type ? 'lint-js' : 'lint-style', ...files],
	{
		cwd: root,
		stdio: 'inherit',
		shell: 'win32' === process.platform,
	}
);

process.exit(result.status ?? 1);
