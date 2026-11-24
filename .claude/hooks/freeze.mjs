// Run by a person, never by the agent: node .claude/hooks/freeze.mjs freeze|release|status
import { readdirSync, rmSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { changedFiles, hashOf, readRegistry, registryPath, root } from './lib.mjs';

const files = (dir) =>
    readdirSync(resolve(root, dir), { withFileTypes: true }).flatMap((entry) => {
        const path = `${dir}/${entry.name}`;
        return entry.isDirectory() ? files(path) : [path];
    });

const commands = {
    freeze() {
        const paths = [...files('tests'), 'phpunit.xml'].sort();
        const registry = Object.fromEntries(paths.map((path) => [path, hashOf(path)]));
        writeFileSync(registryPath, JSON.stringify(registry, null, 2) + '\n');
        console.log(`Frozen: ${paths.length} files. Use "git diff --stat HEAD -- tests" to see what is new.`);
        paths.forEach((path) => console.log(`  ${path}`));
    },
    release() {
        rmSync(registryPath, { force: true });
        console.log('Released: no test is frozen and the Stop gate is inactive.');
    },
    status() {
        const registry = readRegistry();
        if (!registry) return console.log('Nothing is frozen. The Stop gate is inactive.');
        const changed = changedFiles(registry);
        console.log(`${Object.keys(registry).length} files frozen, ${changed.length} changed.`);
        changed.forEach((path) => console.log(`  ${path}`));
    },
};

const command = commands[process.argv[2]];
if (!command) {
    console.error('Usage: node .claude/hooks/freeze.mjs freeze|release|status');
    process.exit(1);
}
command();
