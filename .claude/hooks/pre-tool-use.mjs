// Denies edits to frozen tests, to the registry and, while something is frozen, to the gate itself.
// The shell rule matches names, so it can be worked around (a glob, for instance).
// It prevents accidents: the guarantee is the hash check in stop.mjs.
import { basename, relative, resolve } from 'node:path';
import { readInput, readRegistry, registryName, root } from './lib.mjs';

const { tool_name: tool, tool_input: input, cwd } = readInput();
const registry = readRegistry();
const registryFile = `.claude/${registryName}`;
const frozen = registry ? Object.keys(registry) : [];
const isGate = (path) => path.startsWith('.claude/hooks/') || path.startsWith('.claude/settings');

const deny = (reason) => {
    console.log(
        JSON.stringify({
            hookSpecificOutput: {
                hookEventName: 'PreToolUse',
                permissionDecision: 'deny',
                permissionDecisionReason: reason,
            },
        }),
    );
    process.exit(0);
};

if (tool === 'Bash') {
    const command = input.command ?? '';
    const generic = new Set(['.gitkeep', 'TestCase.php', 'phpunit.xml']);
    const names = [registryFile, registryName, 'freeze.mjs'];
    if (registry) {
        names.push('.claude/hooks', '.claude/settings', ...frozen);
        names.push(...frozen.map((path) => basename(path)).filter((name) => !generic.has(name)));
    }
    const hit = names.find((name) => command.includes(name));
    if (hit) deny(`The command names "${hit}", which is frozen or part of the gate. Only the user can change it.`);
} else {
    const path = relative(root, resolve(cwd ?? root, input.file_path ?? input.notebook_path ?? ''));
    if (path === registryFile) deny('The registry of frozen tests is written only by the user.');
    if (frozen.includes(path)) deny(`${path} is frozen. Ask the user to release it.`);
    if (registry && isGate(path)) deny(`${path} is part of the gate, locked while tests are frozen.`);
}
