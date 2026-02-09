# Lean SEO

Lightweight SEO for WordPress — no bloat, no upsells, just what you need.

## What It Does

Essential SEO in ~500 lines of PHP:

- **Meta tags** — Title, description, Open Graph, Twitter Cards
- **XML sitemaps** — Auto-generated, paginated for large sites
- **Schema/JSON-LD** — WebSite, Organization, Article, BreadcrumbList
- **Canonical URLs** — Proper canonicalization
- **Per-post SEO** — Custom title/description with live preview

## How It Works

```
┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│   CONTENT   │ ──▶ │  LEAN SEO   │ ──▶ │   OUTPUT    │
│             │     │             │     │             │
│  Posts      │     │  Meta Tags  │     │  <head>     │
│  Pages      │     │  Schema     │     │  Sitemaps   │
│  Terms      │     │  Sitemaps   │     │  JSON-LD    │
└─────────────┘     └─────────────┘     └─────────────┘
```

Zero configuration. Activate and it works.

## Features

| Feature | Description |
|---------|-------------|
| **Meta Tags** | Title, description, OG, Twitter Cards |
| **Sitemaps** | Auto-paginated for 1000+ posts |
| **Schema** | WebSite, Organization, Article, Breadcrumbs |
| **Per-Post** | Custom SEO fields with preview |

## Sitemaps

| URL | Content |
|-----|---------|
| `/sitemap.xml` | Index |
| `/sitemap-posts.xml` | Posts (auto-paginates) |
| `/sitemap-pages.xml` | Pages |
| `/sitemap-categories.xml` | Categories |
| `/sitemap-tags.xml` | Tags |

## Why Not Yoast?

| | Lean SEO | Yoast |
|-|----------|-------|
| Code size | ~500 lines | Megabytes |
| Config needed | None | Extensive |
| Upsells | None | Constant |
| Admin bloat | None | Heavy |

## Developer Hooks

```php
// Custom description for special pages
add_filter('lean_seo_custom_description', function($desc) {
    if (is_page('special')) return 'Custom description';
    return $desc;
});

// Default OG image fallback
add_filter('lean_seo_default_image', function($url) {
    return 'https://example.com/default.jpg';
});

// Add post types to SEO meta box
add_filter('lean_seo_meta_box_post_types', function($types) {
    $types[] = 'product';
    return $types;
});

// Add custom sitemaps to index
add_action('lean_seo_sitemap_index', function() {
    echo '<sitemap><loc>' . home_url('/custom.xml') . '</loc></sitemap>';
});
```

## Requirements

- WordPress 6.0+
- PHP 7.4+

## Installation

```bash
# Upload to plugins directory
cp -r lean-seo /wp-content/plugins/

# Activate - no configuration needed
wp plugin activate lean-seo
```

## License

GPL-2.0+ (same as WordPress)

---

**Author**: [Sarai Chinwag](https://saraichinwag.com) for [Extra Chill](https://extrachill.com)
