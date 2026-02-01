# Lean SEO

Lightweight SEO for WordPress. No bloat, no upsells, just what you need.

## Features

- **Meta Tags** - Title, description, Open Graph, Twitter Cards
- **XML Sitemaps** - Auto-generated, paginated for large sites
- **Schema/JSON-LD** - WebSite, Organization, Article, BreadcrumbList
- **Canonical URLs** - Proper canonicalization
- **Per-Post SEO** - Custom title and description fields with live preview
- **Developer Friendly** - Clean code, hooks for customization

## Installation

1. Upload the `lean-seo` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu
3. That's it! No configuration needed.

## Sitemaps

Your sitemap is available at:
- `/sitemap.xml` - Index
- `/sitemap-posts.xml` - Posts
- `/sitemap-pages.xml` - Pages
- `/sitemap-categories.xml` - Categories
- `/sitemap-tags.xml` - Tags

For sites with 1000+ posts, posts are automatically paginated:
- `/sitemap-posts-1.xml`, `/sitemap-posts-2.xml`, etc.

## Hooks

### Filters

**`lean_seo_custom_description`**
Provide a custom description for special pages/routes.

```php
add_filter('lean_seo_custom_description', function($desc) {
    if (is_page('special-page')) {
        return 'My custom description';
    }
    return $desc;
});
```

**`lean_seo_default_image`**
Set a default fallback OG image.

```php
add_filter('lean_seo_default_image', function($url) {
    return 'https://example.com/default-og-image.jpg';
});
```

**`lean_seo_meta_box_post_types`**
Control which post types show the SEO meta box.

```php
add_filter('lean_seo_meta_box_post_types', function($types) {
    $types[] = 'product';
    return $types;
});
```

### Actions

**`lean_seo_sitemap_index`**
Add custom sitemaps to the index.

```php
add_action('lean_seo_sitemap_index', function() {
    echo '  <sitemap>' . "\n";
    echo '    <loc>' . home_url('/my-custom-sitemap.xml') . '</loc>' . "\n";
    echo '  </sitemap>' . "\n";
});
```

## Why Lean SEO?

Yoast and similar plugins are bloated. They:
- Slow down your admin
- Add megabytes of unnecessary code
- Constantly upsell premium features
- Do way more than most sites need

Lean SEO does exactly what you need for good SEO:
- ~500 lines of focused PHP
- No admin settings page (it just works)
- No tracking, no upsells, no bloat

## Requirements

- WordPress 6.0+
- PHP 7.4+

## License

GPL-2.0+ (same as WordPress)

## Author

Built by [Sarai Chinwag](https://saraichinwag.com) for [Extra Chill](https://extrachill.com).
