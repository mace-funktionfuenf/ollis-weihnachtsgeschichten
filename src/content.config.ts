import { defineCollection, reference } from 'astro:content';
import { glob } from 'astro/loaders';
import { z } from 'astro/zod';

// Filenames are frozen legacy WordPress slugs (some contain underscores, one
// is pluralized irregularly) — disable the default github-slugger rewrite so
// the id is always exactly the filename, never a derived slug.
const rawId = ({ entry }: { entry: string }) => entry.replace(/\.md$/, '');

const FORMATS = [
  'vorlesen',
  'hoerbuch',
  'hoerspiel',
  'dvd',
  'buch',
  'plattdeutsch',
  'lokal',
] as const;
const AUDIENCES = ['kinder', 'familie', 'erwachsene'] as const;
const TOPICS = [
  'schnee',
  'wetter',
  'familie-staude',
  'humor',
  'mundart',
  'rezepte',
  'geschenke',
  'film',
  'tradition',
  'recht',
] as const;

const seo = z.object({
  metaTitle: z.string().max(70).optional(),
  metaDescription: z.string().min(80).max(165).optional(),
  noindex: z.boolean().default(false),
});
const optionalSeo = () => seo.optional();

const stories = defineCollection({
  loader: glob({ pattern: '*.md', base: './src/content/stories', generateId: rawId }),
  schema: z.object({
    title: z.string(),
    kind: z.enum(['jahresgeschichte', 'adventskalendergeschichte']),
    year: z.number().int().min(2000).max(2100),
    pubDate: z.coerce.date(),
    updatedDate: z.coerce.date().optional(),
    author: reference('authors'),
    teaser: z.string().min(40).max(300),
    // Self-hosted path — either a migrated legacy image (/wp-content/uploads/...)
    // or a newly-uploaded one (/images/uploads/..., per public/admin/config.yml).
    // No prefix constraint: enumerating every valid location here would just
    // drift out of sync with reality; the migration script and Decap's media
    // config are what actually enforce self-hosting.
    heroImage: z.string().optional(),
    heroImageAlt: z.string().optional(),
    formats: z.array(z.enum(FORMATS)).default([]),
    audience: z.array(z.enum(AUDIENCES)).default([]),
    topics: z.array(z.enum(TOPICS)).default([]),
    relatedManual: z.array(reference('stories')).max(3).default([]),
    products: z.array(reference('products')).default([]),
    draft: z.boolean().default(false),
    seo: optionalSeo(),
    legacyId: z.number().int().optional(),
  }),
});

const posts = defineCollection({
  loader: glob({ pattern: '*.md', base: './src/content/posts', generateId: rawId }),
  schema: z.object({
    title: z.string(),
    pubDate: z.coerce.date(),
    updatedDate: z.coerce.date().optional(),
    author: reference('authors'),
    teaser: z.string().min(40).max(300),
    heroImage: z.string().optional(),
    heroImageAlt: z.string().optional(),
    categories: z.array(z.string()).min(1),
    formats: z.array(z.enum(FORMATS)).default([]),
    audience: z.array(z.enum(AUDIENCES)).default([]),
    topics: z.array(z.enum(TOPICS)).default([]),
    relatedManual: z.array(reference('posts')).max(3).default([]),
    products: z.array(reference('products')).default([]),
    draft: z.boolean().default(false),
    seo: optionalSeo(),
    legacyId: z.number().int().optional(),
  }),
});

const products = defineCollection({
  loader: glob({ pattern: '*.md', base: './src/content/products', generateId: rawId }),
  schema: z.object({
    title: z.string(),
    brand: z.string().optional(),
    // No image pipeline yet — nothing has been downloaded/self-hosted, so
    // this can't require a local path yet. Never point this at a hotlinked
    // third-party (e.g. Amazon CDN) URL even as a stopgap — that fires a
    // third-party request on load, which rules/gdpr.md rules out.
    image: z.string().startsWith('/images/products/').optional(),
    imageAlt: z.string().optional(),
    blurb: z.string().max(220).optional(),
    url: z.url(),
    network: z.enum(['amazon', 'awin', 'direkt']).default('amazon'),
    asin: z.string().optional(),
    // Reuses the same enums as stories — these come directly from the two
    // WordPress product taxonomies (`weihnachtsgeschichten`, `fuer` /
    // `weihnachtsgeschenke`), so a product's tags and a story's tags are
    // directly comparable without a separate classification system.
    formats: z.array(z.enum(FORMATS)).default([]),
    audience: z.array(z.enum(AUDIENCES)).default([]),
    active: z.boolean().default(true),
    legacyId: z.number().int().optional(),
  }),
});

const authors = defineCollection({
  loader: glob({ pattern: '*.md', base: './src/content/authors', generateId: rawId }),
  schema: z.object({
    name: z.string(),
    displayName: z.string(),
    bio: z.string().optional(),
    image: z.string().startsWith('/images/').optional(),
  }),
});

const pages = defineCollection({
  loader: glob({ pattern: '*.md', base: './src/content/pages', generateId: rawId }),
  schema: z.object({
    title: z.string(),
    heroImage: z.string().optional(),
    heroImageAlt: z.string().optional(),
    seo: optionalSeo(),
    products: z.array(reference('products')).default([]),
  }),
});

export const collections = { stories, posts, products, authors, pages };
