// While tests are frozen, the agent cannot finish with changed tests or a failing suite.
import { spawnSync } from 'node:child_process';
import { changedFiles, readRegistry, root } from './lib.mjs';

const block = (reason) => {
    console.log(JSON.stringify({ decision: 'block', reason }));
    process.exit(0);
};

// A crash would not block on its own, so it is turned into a block.
process.on('uncaughtException', (error) => block(`The Stop gate crashed: ${error.message}`));

let registry;
try {
    registry = readRegistry();
} catch {
    block('The registry of frozen tests cannot be read. Ask the user to freeze again.');
}
if (!registry) process.exit(0);

const changed = changedFiles(registry);
if (changed.length) {
    block(`Frozen tests changed: ${changed.join(', ')}. Restore them or ask the user to release them.`);
}

const docker = spawnSync('docker', ['info'], { cwd: root, encoding: 'utf8', timeout: 15_000 });
if (docker.status !== 0) {
    block('Docker is not available, so the suite cannot run. Cause: Docker is down or unreachable, not the code.');
}

// The image build runs vue-tsc, the container runs Pint and PHPUnit.
const run = spawnSync('docker', ['compose', '--progress', 'plain', 'run', '--rm', '-T', 'test'], {
    cwd: root,
    encoding: 'utf8',
    timeout: 240_000,
    maxBuffer: 64 * 1024 * 1024,
});
if (run.error?.code === 'ETIMEDOUT') block('The suite did not finish in 240 seconds.');
if (run.status !== 0) {
    const isNoise = (line) => !line.trim() || /^#\d+ (CACHED|DONE|\[|sha256|resolve|exporting|naming|unpacking|merging|transferring)/.test(line);
    const lines = `${run.stderr ?? ''}\n${run.stdout ?? ''}`.split('\n').filter((line) => !isNoise(line));
    const output = lines.slice(-60).join('\n');
    block(`Tests, Pint or vue-tsc failed:\n${output}`);
}
