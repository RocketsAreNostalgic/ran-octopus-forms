import { readFileSync, writeFileSync } from 'node:fs';
import { spawnSync } from 'node:child_process';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const potPath = resolve(root, 'languages/ran-octopus-forms.pot');
const result = spawnSync(
	'wp',
	[
		'i18n',
		'make-pot',
		'.',
		'languages/ran-octopus-forms.pot',
		'--domain=ran-octopus-forms',
		'--exclude=node_modules,tests',
	],
	{ cwd: root, stdio: 'inherit' }
);

if (0 !== result.status) {
	process.exit(result.status ?? 1);
}

const pot = readFileSync(potPath, 'utf8');
const projectId = /^"Project-Id-Version: RAN Octopus Forms [^\\n]+\\n"$/m;

if (!projectId.test(pot)) {
	throw new Error('Unable to find the POT Project-Id-Version header.');
}

writeFileSync(
	potPath,
	pot.replace(
		projectId,
		'"X-Release-Please-Start: x-release-please-start-version\\n"\n$&\n"X-Release-Please-End: x-release-please-end\\n"'
	)
);
