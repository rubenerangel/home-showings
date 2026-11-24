import { createHash } from 'node:crypto';
import { existsSync, readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

export const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
export const registryName = 'frozen-tests.json';
export const registryPath = resolve(root, '.claude', registryName);

export const hashOf = (path) =>
    createHash('sha256').update(readFileSync(resolve(root, path))).digest('hex');

// Map of relative path to SHA-256, or null when nothing is frozen.
export const readRegistry = () =>
    existsSync(registryPath) ? JSON.parse(readFileSync(registryPath, 'utf8')) : null;

export const changedFiles = (registry) =>
    Object.keys(registry).filter(
        (path) => !existsSync(resolve(root, path)) || hashOf(path) !== registry[path],
    );

export const readInput = () => JSON.parse(readFileSync(0, 'utf8'));
