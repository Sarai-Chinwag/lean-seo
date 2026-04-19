# Changelog

## [1.6.0] - 2026-04-19

### Added
- **Site Identity** settings section under Settings → Lean SEO. Configure
  who the site represents (Person or Organization) without writing code:
  - Identity type radio (Person | Organization)
  - Name
  - Description / bio
  - Logo / photo (media picker)
  - Default OG image (media picker, fallback for posts without featured image)
  - Twitter @handle (normalized; leading @ stored stripped)
  - Social profile URLs for Twitter/X, GitHub, Facebook, LinkedIn,
    Instagram, YouTube, Mastodon (emitted as `sameAs` in schema)
- `Lean_SEO_Identity_Applier` automatically wires settings into the
  filters added in 1.5.0:
  - `lean_seo_twitter_handle` ← Twitter handle setting
  - `lean_seo_default_image` ← default OG image setting
  - `lean_seo_person_schema` ← built when type is 'person'
  - `lean_seo_organization_schema` ← enriched with description, logo, sameAs
- Media uploader JS enqueued only on the Lean SEO settings page.

### Changed
- No behavior changes when identity settings are empty — the plugin
  continues to output the same minimal schema it always has. The UI
  is purely additive.

## [1.5.0] - 2026-04-19

### Added
- New filters for title, description, and social meta:
  - `lean_seo_title` — override the resolved SEO title
  - `lean_seo_document_title` — short-circuit the `<title>` tag (works on the homepage)
  - `lean_seo_og_title` — override the `og:title` value
  - `lean_seo_description` — override the resolved description (context-aware)
  - `lean_seo_og_site_name` — override `og:site_name`
  - `lean_seo_og_locale` — override `og:locale`
  - `lean_seo_twitter_handle` — set `@handle` for `twitter:site`
  - `lean_seo_title_separator` — customize the title separator (default `|`)
- New filters for schema enrichment:
  - `lean_seo_website_schema` — modify the WebSite node
  - `lean_seo_organization_schema` — modify the Organization node (add `sameAs`, etc.)
  - `lean_seo_person_schema` — opt-in Person node for personal sites
  - `lean_seo_webpage_schema` — modify the WebPage node
  - `lean_seo_breadcrumb_schema` — modify the BreadcrumbList node
  - `lean_seo_schema_graph` — modify the complete `@graph` before output
- `Lean_SEO_Meta::get_context()` helper exposing the current page context
  (`home` | `single` | `archive` | `taxonomy` | `search` | `author` | `date` | `404` | `other`).

### Changed
- `Lean_SEO_Meta::get_description()` now applies `lean_seo_description` after
  the default fallback chain so themes/plugins can refine the resolved value
  without rebuilding it from scratch. The legacy `lean_seo_custom_description`
  filter still short-circuits the chain for backwards compatibility.
- `pre_get_document_title` is now hooked so `lean_seo_document_title` can
  override the homepage `<title>` (which `document_title_parts` cannot).

### Fixed
- BreadcrumbList no longer emits a duplicate `Home → Home` entry on the
  homepage. The current-page crumb is now skipped when on the front page.

## [1.2.0] - 2026-02-23

### Added
- IndexNow integration for automatic search engine URL submission on publish

## [1.1.0] - 2026-02-23

### Added
- FAQPage schema auto-detection from post content

## [1.0.1] - 2026-02-01

### Changed
- Add CHANGELOG.md via homeboy
- Initial commit: Lean SEO v1.0.0

### Fixed
- Fix sitemap pagination + add robots.txt filter

## [1.0.0] - 2026-02-01
- Initial release
