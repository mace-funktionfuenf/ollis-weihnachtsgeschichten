// One-time migration: converts the WordPress WXR export into Astro content
// collection files, and downloads/self-hosts every image it references.
// Re-runnable — always overwrites its output directories; image downloads
// are cached (skipped if the destination file already exists), so re-runs
// don't re-hit the network for images already fetched.
//
// Known limitations, deliberately left for the human review pass:
//  - The <br>-soup paragraph-break heuristic (see splitBrSoup) is a best
//    effort, not a guarantee — flagged per-file in the summary when a post
//    has a high <br> count, since that's where it's most likely to need a
//    manual look.
//  - Images are downloaded as-is (original size/format) — no optimization,
//    no WebP conversion, no responsive variants. A follow-up, not blocking.
//  - [wpsleep]-gated content is unwrapped (both branches kept, marked), not
//    resolved — these need a human decision, per the migration plan.
import { readFileSync, writeFileSync, mkdirSync, rmSync, existsSync } from 'node:fs';
import { DOMParser } from 'linkedom';
import TurndownService from 'turndown';
import { gfm } from 'turndown-plugin-gfm';

const WXR_PATH = new URL(
  '../../OS/references/ollisweihnachtsgeschichten.WordPress.2026-08-19.xml',
  import.meta.url
);
const ROOT = new URL('../../', import.meta.url);

const WP_NS = 'http://wordpress.org/export/1.2/';

// --- WXR parsing -----------------------------------------------------------

function childText(item, tagName) {
  const node = Array.from(item.childNodes).find((n) => n.nodeName === tagName);
  return node?.textContent ?? '';
}

function postmeta(item) {
  const out = {};
  for (const pm of Array.from(item.getElementsByTagName('wp:postmeta'))) {
    const key = childText(pm, 'wp:meta_key');
    const value = childText(pm, 'wp:meta_value');
    out[key] = value;
  }
  return out;
}

function parseWxr() {
  const xml = readFileSync(WXR_PATH, 'utf8');
  const doc = new DOMParser().parseFromString(xml, 'text/xml');
  const items = Array.from(doc.getElementsByTagName('item'));

  return items.map((item) => {
    const allTerms = Array.from(item.getElementsByTagName('category'));
    const categories = allTerms
      .filter((c) => c.getAttribute('domain') === 'category')
      .map((c) => c.getAttribute('nicename') || c.textContent);

    // Custom taxonomies used only by the `product` post type (weihnachtsgeschichten,
    // fuer, weihnachtsgeschenke) — kept as a generic domain->terms map rather than
    // named fields, since only the product-extraction step needs these.
    const taxonomies = {};
    for (const term of allTerms) {
      const domain = term.getAttribute('domain');
      if (domain === 'category') continue;
      (taxonomies[domain] ??= []).push(term.getAttribute('nicename') || term.textContent);
    }

    return {
      postType: childText(item, 'wp:post_type'),
      status: childText(item, 'wp:status'),
      title: childText(item, 'title'),
      slug: childText(item, 'wp:post_name'),
      date: childText(item, 'wp:post_date'),
      creator: childText(item, 'dc:creator'),
      content: childText(item, 'content:encoded'),
      categories,
      taxonomies,
      attachmentUrl: childText(item, 'wp:attachment_url'),
      postId: childText(item, 'wp:post_id'),
      meta: postmeta(item),
    };
  });
}

function buildAttachmentIndex(items) {
  const byId = new Map();
  for (const item of items) {
    if (item.postType !== 'attachment') continue;
    byId.set(item.postId, { url: item.attachmentUrl, alt: item.meta._wp_attachment_image_alt || undefined });
  }
  return byId;
}

// --- Image downloading -------------------------------------------------

// One shared queue for every image discovered anywhere (hero images, inline
// body images, product images) — keyed by source URL so the same image
// referenced from multiple posts is only ever downloaded once.
const imageQueue = new Map(); // sourceUrl -> local filesystem URL (file://...)

function wpUploadsLocalUrl(pathOrUrl) {
  const path = pathOrUrl.replace(SITE_ORIGIN, '');
  const match = path.match(/\/wp-content\/uploads\/(.+)$/);
  if (!match) return null;
  return new URL(`public/wp-content/uploads/${match[1]}`, ROOT);
}

function queueWpImage(pathOrUrl) {
  if (!pathOrUrl || !pathOrUrl.includes('/wp-content/uploads/')) return null;
  const dest = wpUploadsLocalUrl(pathOrUrl);
  if (!dest) return null;
  const sourceUrl = pathOrUrl.startsWith('http')
    ? pathOrUrl
    : `https://www.ollis-weihnachtsgeschichten.de${pathOrUrl}`;
  imageQueue.set(sourceUrl, dest);
  return `/wp-content/uploads/${dest.pathname.split('/wp-content/uploads/')[1]}`;
}

function queueProductImage(sourceUrl, slug) {
  const ext = (sourceUrl.match(/\.(jpe?g|png|webp|gif)(?:[?#]|$)/i)?.[1] ?? 'jpg').toLowerCase();
  const dest = new URL(`public/images/products/${slug}.${ext}`, ROOT);
  imageQueue.set(sourceUrl, dest);
  return `/images/products/${slug}.${ext}`;
}

async function downloadQueuedImages({ warnings }) {
  const entries = [...imageQueue.entries()].filter(([, dest]) => !existsSync(dest));
  console.log(`\nDownloading ${entries.length} image(s) (${imageQueue.size - entries.length} already cached)...`);

  const CONCURRENCY = 6;
  let cursor = 0;
  let done = 0;
  let failed = 0;

  async function worker() {
    while (cursor < entries.length) {
      const [sourceUrl, dest] = entries[cursor++];
      try {
        const res = await fetch(sourceUrl);
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        mkdirSync(new URL('.', dest), { recursive: true });
        writeFileSync(dest, Buffer.from(await res.arrayBuffer()));
        done++;
      } catch (err) {
        failed++;
        warnings.push(`image download failed (${sourceUrl}): ${err.message}`);
      }
    }
  }

  await Promise.all(Array.from({ length: Math.min(CONCURRENCY, entries.length) }, worker));
  console.log(`Downloaded ${done}, failed ${failed}, ${imageQueue.size - entries.length} already cached.`);
}

// --- HTML preprocessing + Markdown conversion ------------------------------

const turndown = new TurndownService({ headingStyle: 'atx', bulletListMarker: '-' });
turndown.use(gfm);

// AIOSEO wraps h2 text in <span id="..."> for its table-of-contents; strip
// the wrapper but there is no attempt (yet) to replicate the exact id
// algorithm — anchor-link parity is a follow-up, not part of this pass.
turndown.addRule('unwrapHeadingAnchor', {
  filter: (node) => node.nodeName === 'SPAN' && node.parentNode?.nodeName?.match(/^H[1-6]$/),
  replacement: (content) => content,
});

function splitBrSoup(html) {
  // Runs of 2+ <br> are almost always a paragraph break in this content
  // (verified on sample posts); a single <br> is left as a soft break.
  return html.replace(/(<br\s*\/?>\s*){2,}/gi, '</p><p>');
}

const WPSLEEP_MARK = '**[REDAKTIONELL PRÜFEN — zeitgesteuerter Inhalt, siehe Migrationsplan]**';
const NO_STATIC_CONTENT_MARK =
  '**[RECHTSTEXT FEHLT — dieser Abschnitt wurde bisher von einem Plugin dynamisch erzeugt (kein Text im Export enthalten) und muss neu geschrieben werden]**';

function stripKnownShortcodes(html) {
  let out = html.replace(/\[\/?ASA\]/g, '');
  // HTML comments don't survive turndown, so the human-review flag has to be
  // plain visible text or it silently disappears from the output file.
  out = out.replace(/\[wpsleep[^\]]*\]/gi, `\n\n${WPSLEEP_MARK}\n\n`);
  out = out.replace(/\[\/wpsleep\]/gi, `\n\n${WPSLEEP_MARK}\n\n`);
  // eRecht24 (Impressum/Datenschutz text) and Borlabs Cookie (consent button)
  // both render dynamically — the WXR export never contained real text for
  // either, so there's nothing to convert. Flag rather than fabricate: never
  // invent placeholder legal text here, that's a liability, not a fix.
  out = out.replace(/\[erecht24[^\]]*\]/gi, `\n\n${NO_STATIC_CONTENT_MARK}\n\n`);
  out = out.replace(/\[borlabs-cookie[^\]]*\]/gi, ''); // dropped intentionally, see migration plan (no consent banner)
  return out;
}

function plainTextTeaser(text, fallback, minLen = 40, maxLen = 280) {
  const clean = text.replace(/\s+/g, ' ').trim();
  if (clean.length < minLen) return fallback;
  if (clean.length <= maxLen) return clean;
  const cut = clean.slice(0, maxLen);
  return cut.slice(0, cut.lastIndexOf(' ')) + '…';
}

// WordPress content links back to its own site with the full absolute URL
// rather than a relative path — harmless once this domain is the live site
// again post-cutover, but during dev/staging it yanks the visitor off to
// the still-live WordPress site, and it's just worse practice regardless.
// Covers http/https and the www/non-www variants seen historically; strips
// the origin and keeps everything else (path, query, fragment) untouched —
// including links that don't resolve to a page yet (uploads, archives not
// built), since a relative path there is still strictly better: it starts
// working the moment that content exists, instead of needing a second pass.
const SITE_ORIGIN = /^https?:\/\/(www\.)?ollis-weihnachtsgeschichten\.de/i;

function rewriteSelfLinksToRelative(root) {
  for (const el of Array.from(root.querySelectorAll('[href], [src]'))) {
    for (const attr of ['href', 'src']) {
      const value = el.getAttribute(attr);
      if (value && SITE_ORIGIN.test(value)) {
        el.setAttribute(attr, value.replace(SITE_ORIGIN, '') || '/');
      }
    }
  }
}

function convertContent(rawHtml, { warnings, label, title, products = [] }) {
  const { html: withoutProdukte, productSlugs } = resolveProduktShortcodes(rawHtml, {
    products,
    warnings,
    label,
  });
  let html = stripKnownShortcodes(withoutProdukte);
  html = splitBrSoup(html);
  if (html.includes(WPSLEEP_MARK)) {
    warnings.push(`${label}: contains [wpsleep] date-gated content — needs a human decision`);
  }
  if (html.includes(NO_STATIC_CONTENT_MARK)) {
    warnings.push(`${label}: had a plugin-rendered [erecht24] block with no static text — needs real legal copy written`);
  }

  const doc = new DOMParser().parseFromString(`<div>${html}</div>`, 'text/html');
  const root = doc.documentElement.querySelector('div') ?? doc.documentElement;

  rewriteSelfLinksToRelative(root);

  for (const script of Array.from(root.querySelectorAll('script, noscript'))) {
    warnings.push(`${label}: stripped an inline <script> (third-party widget) from content`);
    script.remove();
  }

  for (const img of Array.from(root.querySelectorAll('img'))) {
    const src = img.getAttribute('src') ?? '';
    if (/awin1\.com\/cshow\.php/i.test(src)) {
      // Not a content image — an Awin affiliate impression-tracking beacon.
      // Self-hosting it wouldn't fix anything (it needs to hit Awin's own
      // server to register), so it's removed outright rather than queued.
      warnings.push(`${label}: removed an Awin tracking-pixel <img> — see migration plan (no third-party requests)`);
      img.remove();
      continue;
    }
    queueWpImage(src);
  }

  // The theme repeats the post title as the first <h2> — drop it (the H1
  // comes from frontmatter) before it can leak into the teaser too.
  const firstHeading = root.querySelector('h2, h3');
  if (firstHeading && root.firstElementChild === firstHeading) {
    firstHeading.remove();
  }

  const brCount = (rawHtml.match(/<br/gi) ?? []).length;
  if (brCount > 20) {
    warnings.push(`${label}: ${brCount} <br> tags — paragraph-break heuristic needs a manual check`);
  }

  let markdown = turndown.turndown(root.innerHTML).trim();
  const fallbackTeaser = productSlugs.length
    ? `${title} — eine Auswahl passender Empfehlungen.`
    : title;
  const teaser = plainTextTeaser(root.textContent ?? '', fallbackTeaser);

  // A post whose entire body was a [produkte] shortcode has no prose left
  // once it's resolved to structured data — leaving the page textually
  // empty would be worse than the original shortcode. The frontmatter
  // `products` reference (set by the caller) renders the actual grid; this
  // is just enough narrative that the page isn't a blank header.
  if (!markdown && productSlugs.length) {
    markdown = 'Hier findest du eine Auswahl passender Empfehlungen:';
    warnings.push(`${label}: body was [produkte]-only — replaced with a short placeholder intro (needs a real one)`);
  }

  return { markdown, teaser, productSlugs };
}

// --- Product extraction (from the `product` custom post type) -------------

// Maps the WordPress custom-taxonomy terms used to tag products onto the
// same FORMATS/AUDIENCES enums used for stories (src/content.config.ts),
// so a product's tags and a story's tags are directly comparable.
const WEIHNACHTSGESCHICHTEN_TAXONOMY_TO_FORMAT = {
  buecher: 'buch',
  hoerbuecher: 'hoerbuch',
  hoerspiele: 'hoerspiel',
  'lokale-weihnachtsgeschichten': 'lokal',
  plattdeutsch: 'plattdeutsch',
  weihnachtsfilme: 'dvd',
};
const FUER_TAXONOMY_TO_AUDIENCE = { erwachsene: 'erwachsene', familie: 'familie', kinder: 'kinder' };
const WEIHNACHTSGESCHENKE_TAXONOMY_TO_AUDIENCE = {
  erwachsene: 'erwachsene',
  'fuer-die-familie': 'familie',
  'fuer-kinder': 'kinder',
};

function productFormatsAndAudience(taxonomies) {
  const formats = new Set(
    (taxonomies.weihnachtsgeschichten ?? [])
      .map((t) => WEIHNACHTSGESCHICHTEN_TAXONOMY_TO_FORMAT[t])
      .filter(Boolean)
  );
  const audience = new Set([
    ...(taxonomies.fuer ?? []).map((t) => FUER_TAXONOMY_TO_AUDIENCE[t]).filter(Boolean),
    ...(taxonomies.weihnachtsgeschenke ?? [])
      .map((t) => WEIHNACHTSGESCHENKE_TAXONOMY_TO_AUDIENCE[t])
      .filter(Boolean),
  ]);
  return { formats: [...formats], audience: [...audience] };
}

// Prefer the tagged affiliate link (carries tag=ollisweichnac-21) over the
// bare `link` meta field, which is untagged.
function productAffiliateUrl(meta) {
  return meta.product_shops_0_link || meta.link || null;
}

function extractProducts(items, { collectionsDir, warnings }) {
  const products = [];
  for (const item of items) {
    if (item.postType !== 'product' || item.status !== 'publish') continue;
    const url = productAffiliateUrl(item.meta);
    if (!url) {
      warnings.push(`product /${item.slug}/ (id ${item.postId}): no affiliate link found, skipped`);
      continue;
    }
    const { formats, audience } = productFormatsAndAudience(item.taxonomies);
    const asin = item.meta.product_shops_0_amazon_asin || item.meta.amazon_produkt_id || undefined;

    // Products reference their thumbnail by external URL (Amazon's CDN),
    // not a WordPress attachment — self-host it like everything else rather
    // than hotlinking Amazon on every page load (rules/gdpr.md).
    const extImageUrl = item.meta._thumbnail_ext_url || undefined;
    const image = extImageUrl ? queueProductImage(extImageUrl, item.slug) : undefined;
    const imageAlt = image ? `${item.title} — Produktbild` : undefined;
    if (!image) {
      warnings.push(`product /${item.slug}/ (id ${item.postId}): no product image found`);
    }

    const fm = frontmatter({
      title: item.title,
      url,
      network: item.meta.product_shops_0_portal === 'amazon' ? 'amazon' : 'amazon',
      asin,
      image,
      imageAlt,
      formats,
      audience,
      active: true,
      legacyId: Number(item.postId),
    });
    writeFileSync(new URL(`${item.slug}.md`, collectionsDir('products')), fm + '\n');
    products.push({ id: Number(item.postId), slug: item.slug, formats, audience });
  }
  return products;
}

// --- [produkte ...] shortcode resolution ------------------------------------
//
// The `affiliatetheme-amazon` plugin's [produkte] shortcode renders a live,
// filtered product grid. There's no static content to convert — but the
// filter itself (either explicit post IDs via include=, or taxonomy-term
// attributes) is real data we can resolve against the products extracted
// above, via reference() rather than leaving the raw shortcode as text.
function parseShortcodeAttrs(tag) {
  const attrs = {};
  for (const m of tag.matchAll(/(\w[\w-]*)="([^"]*)"/g)) attrs[m[1]] = m[2];
  return attrs;
}

function matchProducts(attrs, products) {
  if (attrs.include) {
    const ids = attrs.include.split(',').map((s) => Number(s.trim()));
    const bySlug = new Map(products.map((p) => [p.id, p.slug]));
    return ids.map((id) => bySlug.get(id)).filter(Boolean);
  }
  const wantFormat = WEIHNACHTSGESCHICHTEN_TAXONOMY_TO_FORMAT[attrs.weihnachtsgeschichten];
  const wantAudience =
    FUER_TAXONOMY_TO_AUDIENCE[attrs.fuer] ??
    WEIHNACHTSGESCHENKE_TAXONOMY_TO_AUDIENCE[attrs.weihnachtsgeschenke];
  return products
    .filter((p) => (!wantFormat || p.formats.includes(wantFormat)))
    .filter((p) => (!wantAudience || p.audience.includes(wantAudience)))
    .map((p) => p.slug);
}

function resolveProduktShortcodes(html, { products, warnings, label }) {
  const matchedSlugs = new Set();
  const withoutMaps = html.replace(/\[mapsmarker[^\]]*\]/gi, () => {
    warnings.push(`${label}: removed a [mapsmarker] embed — no static content to recover`);
    return '';
  });
  const resolved = withoutMaps.replace(/\[produkte([^\]]*)\]/gi, (_match, attrString) => {
    const attrs = parseShortcodeAttrs(attrString);
    const slugs = matchProducts(attrs, products);
    if (!slugs.length) {
      warnings.push(`${label}: [produkte] shortcode matched no products — needs a manual look`);
      return '';
    }
    slugs.forEach((s) => matchedSlugs.add(s));
    return '';
  });
  return { html: resolved, productSlugs: [...matchedSlugs] };
}

function extractYear(slug, dateStr) {
  const match = slug.match(/20\d{2}/);
  if (match) return Number(match[0]);
  return new Date(dateStr.replace(' ', 'T')).getFullYear();
}

function toIsoDate(wpDate) {
  return wpDate.replace(' ', 'T') + '+01:00';
}

function resolveHeroImage(meta, attachmentsById) {
  const thumbId = meta._thumbnail_id;
  if (!thumbId || thumbId === 'by_url') return {}; // 'by_url' is the products sentinel, handled separately
  const attachment = attachmentsById.get(thumbId);
  if (!attachment?.url) return {};
  const heroImage = queueWpImage(attachment.url);
  if (!heroImage) return {};
  return { heroImage, heroImageAlt: attachment.alt };
}

function frontmatter(fields) {
  const lines = Object.entries(fields)
    .filter(([, v]) => v !== undefined)
    .map(([k, v]) => `${k}: ${JSON.stringify(v)}`);
  return `---\n${lines.join('\n')}\n---\n`;
}

// --- Main --------------------------------------------------------------

async function main() {
  const items = parseWxr();
  const attachmentsById = buildAttachmentIndex(items);
  const warnings = [];
  const legacyUrls = [];

  const collectionsDir = (name) => new URL(`src/content/${name}/`, ROOT);
  for (const name of ['stories', 'posts', 'pages', 'products']) {
    rmSync(collectionsDir(name), { recursive: true, force: true });
    mkdirSync(collectionsDir(name), { recursive: true });
  }

  // Products first — [produkte] shortcode resolution below needs the full
  // catalogue (with each product's formats/audience tags) to match against.
  const products = extractProducts(items, { collectionsDir, warnings });

  const STORY_CATEGORIES = new Set(['weihnachtsgeschichten', 'adventskalendergeschichten']);
  const SKIP_PAGE_SLUGS = new Set(['startseite', 'weihnachtsgeschenke']);

  let storyCount = 0;
  let postCount = 0;
  let pageCount = 0;
  const authorCounts = {};

  for (const item of items) {
    if (item.status !== 'publish') continue;
    authorCounts[item.creator] = (authorCounts[item.creator] ?? 0) + 1;

    if (item.postType === 'post') {
      const isStory = item.categories.some((c) => STORY_CATEGORIES.has(c.toLowerCase()));
      const label = `${isStory ? 'story' : 'post'} /${item.slug}/`;
      const { markdown: body, teaser, productSlugs } = convertContent(item.content, {
        warnings,
        label,
        title: item.title,
        products,
      });

      const hero = resolveHeroImage(item.meta, attachmentsById);

      if (isStory) {
        const kind = item.categories.some((c) => c.toLowerCase() === 'adventskalendergeschichten')
          ? 'adventskalendergeschichte'
          : 'jahresgeschichte';
        const fm = frontmatter({
          title: item.title,
          kind,
          year: extractYear(item.slug, item.date),
          pubDate: toIsoDate(item.date),
          author: 'olaf',
          teaser,
          heroImage: hero.heroImage,
          heroImageAlt: hero.heroImageAlt,
          products: productSlugs.length ? productSlugs : undefined,
          legacyId: Number(item.postId),
        });
        writeFileSync(new URL(`${item.slug}.md`, collectionsDir('stories')), fm + '\n' + body + '\n');
        storyCount++;
      } else {
        const fm = frontmatter({
          title: item.title,
          pubDate: toIsoDate(item.date),
          author: 'olaf',
          teaser,
          heroImage: hero.heroImage,
          heroImageAlt: hero.heroImageAlt,
          categories: item.categories,
          products: productSlugs.length ? productSlugs : undefined,
          legacyId: Number(item.postId),
        });
        writeFileSync(new URL(`${item.slug}.md`, collectionsDir('posts')), fm + '\n' + body + '\n');
        postCount++;
      }
      legacyUrls.push(`/${item.slug}/`);
    }

    if (item.postType === 'page') {
      if (SKIP_PAGE_SLUGS.has(item.slug)) {
        warnings.push(`page /${item.slug}/: skipped — see migration plan for why (not a plain copy job)`);
        continue;
      }
      const label = `page /${item.slug}/`;
      const { markdown: body } = convertContent(item.content, {
        warnings,
        label,
        title: item.title,
        products,
      });
      const hero = resolveHeroImage(item.meta, attachmentsById);
      const fm = frontmatter({ title: item.title, heroImage: hero.heroImage, heroImageAlt: hero.heroImageAlt });
      writeFileSync(new URL(`${item.slug}.md`, collectionsDir('pages')), fm + '\n' + body + '\n');
      legacyUrls.push(`/${item.slug}/`);
      pageCount++;
    }
  }

  legacyUrls.push('/');
  mkdirSync(new URL('data/', ROOT), { recursive: true });
  writeFileSync(new URL('data/legacy-urls.txt', ROOT), legacyUrls.sort().join('\n') + '\n');

  await downloadQueuedImages({ warnings });

  console.log(
    `\nMigrated: ${storyCount} stories, ${postCount} posts, ${pageCount} pages, ${products.length} products.`
  );
  console.log(`Author distribution in source export:`, authorCounts);
  console.log(`All migrated content re-attributed to "olaf" per the migration plan.\n`);
  if (warnings.length) {
    console.log(`${warnings.length} item(s) flagged for human review:`);
    for (const w of warnings) console.log(`  - ${w}`);
  }
}

main();
