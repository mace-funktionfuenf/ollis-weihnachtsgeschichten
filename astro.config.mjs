import { defineConfig } from 'astro/config';
import sitemap from '@astrojs/sitemap';
import { readFileSync, existsSync } from 'node:fs';

// Legacy-URL inventory is frozen once the full redirect/SEO workstream runs.
// Until then this guard is a no-op rather than a hard build failure, so the
// MVP scaffold can build before that data exists.
function urlIntegrityGuard() {
  return {
    name: 'url-integrity',
    hooks: {
      'astro:build:done': ({ pages, logger }) => {
        const inventoryPath = new URL('./data/legacy-urls.txt', import.meta.url);
        if (!existsSync(inventoryPath)) {
          logger.warn('legacy-urls.txt not present yet — skipping URL-integrity check.');
          return;
        }
        const built = new Set(pages.map((p) => `/${p.pathname}`.replace(/\/+$/, '') + '/'));
        const legacy = readFileSync(inventoryPath, 'utf8').trim().split('\n').filter(Boolean);
        const missing = legacy.filter((u) => !built.has(u));
        if (missing.length) {
          throw new Error(
            `SEO-Regression: ${missing.length} Bestands-URL(s) fehlen im Build:\n${missing.join('\n')}`
          );
        }
      },
    },
  };
}

export default defineConfig({
  site: 'https://www.ollis-weihnachtsgeschichten.de',
  trailingSlash: 'always',
  build: {
    format: 'directory',
  },
  integrations: [sitemap(), urlIntegrityGuard()],
});
