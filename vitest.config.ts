import path from 'node:path';
import { defineConfig } from 'vitest/config';

/**
 * Vitest config - JS-side unit tests for pure logic that's too central
 * to leave uncovered (auto-layout geometry, future date math, etc.).
 *
 * Run with `npm run test:js`. Vite's main config doesn't need a
 * `test:` block because nothing else here imports vitest; keeping the
 * configs separate avoids the dev/build pipeline pulling in test deps.
 */
export default defineConfig({
    resolve: {
        alias: {
            '@': path.resolve(__dirname, './resources/js'),
        },
    },
    test: {
        include: ['tests/js/**/*.test.ts'],
        environment: 'node',
    },
});
