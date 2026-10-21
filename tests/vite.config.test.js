import assert from 'node:assert/strict';
import { test } from 'node:test';
import { resolveConfig } from 'vite';

for (const docker of ['0', '1']) {
    test(`Vite restricts CORS while preserving development origins (Docker=${docker})`, async (context) => {
        const previousEnvironment = { ...process.env };
        context.after(() => {
            process.env = previousEnvironment;
        });
        process.env.DOCKER = docker;
        process.env.APP_URL = 'https://app.example.com/application';
        process.env.APP_URL_UNRELATED = 'https://untrusted.example.com';
        process.env.LARAVEL_BYPASS_ENV_CHECK = '1';

        const config = await resolveConfig({}, 'serve');
        const allowedOrigins = config.server.cors.origin;
        assert.ok(Array.isArray(allowedOrigins));

        const allowsOrigin = (origin) => allowedOrigins.some((allowed) => (
            allowed instanceof RegExp ? allowed.test(origin) : allowed === origin
        ));

        for (const origin of [
            'http://localhost:8080',
            'http://localhost:5174',
            'http://127.0.0.1:8080',
            'http://[::1]:8080',
            'https://onesiforo.test',
            'http://onesiforo.test:8080',
            'http://onesiforo.localhost:8080',
            'https://app.example.com',
        ]) {
            assert.equal(allowsOrigin(origin), true, `${origin} should be allowed`);
        }

        for (const origin of [
            'https://attacker.example',
            'https://onesiforo.test.attacker.example',
            'https://localhost.attacker.example',
            'https://untrusted.example.com',
            'null',
        ]) {
            assert.equal(allowsOrigin(origin), false, `${origin} should be denied`);
        }

        if (docker === '1') {
            assert.equal(config.server.host, '0.0.0.0');
            assert.equal(config.server.port, 5173);
            assert.equal(config.server.strictPort, true);
            assert.equal(config.server.origin, 'http://localhost:5174');
            assert.equal(config.server.hmr.host, 'localhost');
            assert.equal(config.server.hmr.port, 5174);
            assert.equal(config.server.watch.usePolling, true);
        }
    });
}
