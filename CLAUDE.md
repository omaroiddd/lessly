# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Lessly** is a WordPress theme built on top of the [_tw](https://github.com/retlehs/_tw) Tailwind CSS starter theme. It uses modern frontend tooling — Tailwind CSS v4, PostCSS, esbuild — and follows WordPress coding standards for its PHP code.

## Tech Stack

- **PHP**: WordPress theme (PHP 7.4+ recommended)
- **CSS**: Tailwind CSS v4 via `@tailwindcss/postcss`, with PostCSS plugins for nesting, variables, and globbed imports
- **JS**: Bundled with esbuild (ESM, `--target=esnext`)
- **Testing**: PHPUnit 10 + Brain Monkey (WordPress function mocking)
- **Linting**: ESLint + Prettier (JS/CSS), PHPCS + WPCS (PHP)
- **Packaging**: Custom Node script using `adm-zip`

## Development Commands

### Build (one-shot)

```bash
npm run dev               # Build everything once
```

This compiles:
- `tailwind.css` → `lessly/style.css` (frontend stylesheet)
- `tailwind.css` → `lessly/style-editor.css` (block editor stylesheet)
- `tailwind.css` → `tailwind-intellisense.css` (for VS Code Tailwind IntelliSense)
- `javascript/script.js` → `lessly/js/script.min.js`
- `javascript/block-editor.js` → `lessly/js/block-editor.min.js`

### Watch (rebuild on save)

```bash
npm run watch             # All watchers in parallel
```

### Production Build & Packaging

```bash
npm run prod              # Production build (minified, cssnano enabled)
npm run zip               # Package theme as lessly.zip
npm run bundle            # Production build + zip (use this to ship)
```

### Linting & Formatting (JS/CSS)

```bash
npm run lint              # ESLint + Prettier check
npm run lint-fix          # Auto-fix everything possible
```

### PHP Code Quality

```bash
composer php:lint              # Run PHPCS with progress + sniff codes
composer php:lint:errors       # Same, but exits 0 on warnings (CI mode)
composer php:lint:autofix      # Run PHPCBF to auto-fix violations
composer php:lint:changed      # Lint only files changed in git
```

### PHP Tests

```bash
composer test                          # Run all PHPUnit tests
composer test:unit                     # Run unit suite only
composer test:coverage                 # Generate HTML coverage report at tests/coverage/
composer test:filter test_method_name  # Run one test by name
vendor/bin/phpunit tests/unit/Foo.php  # Run a specific test file directly
```

**Run `composer test` before every commit that touches PHP.** Tests are fast (no WordPress DB needed) — typical suite runs in under a second.

### Translations

```bash
composer make-pot         # Extract translatable strings to lessly/languages/lessly.pot
```

## Architecture

### Directory Structure

```
tailwind.css              # Tailwind entry point
javascript/
├── script.js             # Frontend JS entry
└── block-editor.js       # Block editor JS entry
lessly/                    # Compiled output + theme PHP files (this is what ships)
├── style.css             # Compiled frontend stylesheet
├── style-editor.css      # Compiled block editor stylesheet
├── js/
│   ├── script.min.js
│   └── block-editor.min.js
└── languages/
    └── lessly.pot        # Translation template
inc/                      # PHP source code (theme functions, classes, modules)
functions.php             # Theme entry point
tests/
├── bootstrap.php         # PHPUnit bootstrap (loads Composer, defines WP constants)
├── unit/
│   ├── BaseTestCase.php  # Base class all tests extend (sets up Brain Monkey)
│   └── *Test.php         # Test files (one per class/feature)
└── coverage/             # Generated coverage report (gitignored)
node_scripts/
└── zip.js                # Custom packaging script
postcss.config.js         # PostCSS plugin pipeline
phpunit.xml.dist          # PHPUnit configuration
phpcs.xml                 # PHPCS ruleset
package.json              # Node tooling
composer.json             # PHP tooling
```

### Build Pipeline (CSS)

`tailwind.css` is processed by PostCSS with this plugin chain:

1. `postcss-import-ext-glob` — glob support in `@import`
2. `postcss-advanced-variables` — Sass-like variables/loops
3. `postcss-nesting` — native CSS nesting → flat
4. `@tailwindcss/postcss` — Tailwind v4 engine
5. `postcss-header` — injects WordPress theme header into `style.css`
6. `cssnano` — minification (production only, when `_TW_ENV=production`)

The same entry compiles three times with different `_TW_TARGET` values: `frontend`, `editor`, `intellisense`.

### Build Pipeline (JS)

esbuild bundles two entry points to `lessly/js/`. Both target `esnext`, minified only in production.

## Testing Conventions

### Stack

- **PHPUnit 10** — test runner
- **Brain Monkey** — mocks WordPress functions, actions, filters
- **Mockery** — object mocking (Brain Monkey depends on it)

Tests run in pure PHP — no WordPress installation, no database, no HTTP. Brain Monkey replaces every `wp_*` / `__()` / `apply_filters()` call with a mock. This keeps tests fast and deterministic.

### Writing a New Test

1. Create `tests/unit/SomethingTest.php`
2. Namespace it `Lessly\Tests`
3. Extend `BaseTestCase` (this auto-handles Brain Monkey setup/teardown)
4. Name test methods `test_what_it_does`

Skeleton:

```php
<?php
declare( strict_types=1 );

namespace Lessly\Tests;

use Brain\Monkey\Functions;

final class MyFeatureTest extends BaseTestCase {

    public function test_does_the_thing(): void {
        Functions\when( 'get_option' )->justReturn( 'expected_value' );

        $result = lessly_my_function();

        $this->assertSame( 'expected_value', $result );
    }
}
```

### Common Brain Monkey Patterns

```php
// "When this WP function is called, return this."
Functions\when( 'get_bloginfo' )->justReturn( 'Site Name' );

// Pass through (useful for esc_html, __, etc.):
Functions\when( 'esc_html' )->returnArg();
Functions\when( '__' )->returnArg();

// Assert a filter was applied:
Filters\expectApplied( 'lessly_my_filter' )
    ->once()
    ->with( 'input' )
    ->andReturn( 'output' );

// Assert an action fired:
Actions\expectDone( 'lessly_my_action' )->once();
```

See `tests/unit/ExampleTest.php` for a runnable reference. Delete it once you have real tests.

### What to Test

Focus tests on logic, not on WordPress itself:

- Sanitization/validation functions
- Custom queries and data transformations
- Functions that compute or format strings
- Conditional logic in template helpers
- Filter/action wiring (does this function add the right hook?)

Skip:

- "Does WordPress's `add_action` work?" — that's WordPress's job
- Visual rendering — use the browser for that

### Test Naming

- File: `tests/unit/{Subject}Test.php` (e.g. `MenuFallbackTest.php`)
- Class: `final class {Subject}Test extends BaseTestCase`
- Methods: `test_{behavior_being_tested}`

## Code Style

### PHP Standards
- **Prefix**: All theme functions, classes, constants, and globals must be prefixed with `lessly_` (functions) or `Lessly_` (classes). Enforced via PHPCS.
- **Text domain**: Always `'lessly'` for translation functions.
- **Indentation**: Tabs.
- **Strict types**: Use `declare(strict_types=1);` in new files where practical.
- **Variable analysis**: `phpcs-variable-analysis` flags undefined/unused variables.

### WordPress Conventions
- **Escape on output**: `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()`.
- **Sanitize on input**: `sanitize_text_field()`, `absint()`, `sanitize_email()`.
- **Nonces** for all forms and AJAX.
- **Capability checks**: `current_user_can()` before privileged actions.
- **Translations**: Always with `'lessly'` text domain.
- **Hook naming**: `lessly_action_name` / `lessly_filter_name`.

### JavaScript / CSS Conventions
- **Module syntax**: ES Modules (`"type": "module"`).
- **Tailwind class order**: Auto-sorted by `prettier-plugin-tailwindcss`.
- **Tailwind class validity**: `eslint-plugin-tailwindcss` flags unknown utilities.

## Typical Workflows

### Starting a coding session
```bash
npm run watch
```

### Before committing PHP changes
```bash
composer test                  # Tests must pass
composer php:lint:autofix      # Fix PHP automatically
composer php:lint:changed      # Verify your changes are clean
```

### Before committing frontend changes
```bash
npm run lint-fix
```

### Adding a new feature (TDD-friendly flow)
1. Write the test first (`tests/unit/NewFeatureTest.php`)
2. Run it — watch it fail
3. Implement the feature in `inc/`
4. Run `composer test` — watch it pass
5. Run `composer php:lint:changed` — fix any style issues
6. Commit

### Releasing a new version
```bash
composer test && npm run bundle
```

## Common Pitfalls

- **Never edit `lessly/style.css` directly** — compiled output. Edit `tailwind.css` and its imports.
- **Never edit `lessly/js/*.min.js` directly** — edit `javascript/` sources.
- **The WordPress theme header is injected by `postcss-header`**, not written into `style.css` manually.
- **`cross-env` is required** for env vars — don't use `VAR=value` syntax in scripts (breaks on Windows).
- **Tests don't run inside WordPress** — every WP function must be mocked via Brain Monkey or it will throw an undefined-function error.
- **Always extend `BaseTestCase`** — extending raw `TestCase` skips Brain Monkey setup and mocks won't work.
- **`Mockery::close()` is automatic** via `BaseTestCase::tearDown()` — don't call it yourself.
- **Text domain must be `'lessly'`** — PHPCS will fail otherwise.

## Quick Reference

| Task | Command |
|------|---------|
| Start dev (watch mode) | `npm run watch` |
| One-off dev build | `npm run dev` |
| Production build | `npm run prod` |
| Package theme as zip | `npm run bundle` |
| Run all PHP tests | `composer test` |
| Run one PHP test | `composer test:filter test_name` |
| Coverage report | `composer test:coverage` |
| Lint JS/CSS | `npm run lint` |
| Auto-fix JS/CSS | `npm run lint-fix` |
| Lint PHP | `composer php:lint` |
| Auto-fix PHP | `composer php:lint:autofix` |
| Lint changed PHP only | `composer php:lint:changed` |
| Update translation template | `composer make-pot` |

## Notes for Claude

- **Always run `composer test` after modifying PHP** in `inc/` or `functions.php`. If tests fail, fix them before considering the task done.
- **When adding new PHP functions**, write a corresponding test in `tests/unit/`. Use `ExampleTest.php` as a reference for Brain Monkey patterns.
- **When refactoring**, run tests first to confirm baseline passes, then again after changes.
- This theme is based on `_tw`, which is intentionally minimal — no pre-built components, no opinionated PHP architecture. Build structure as needed.
- Prefer Tailwind utilities over custom CSS. Custom CSS goes in `tailwind.css` (or imports) inside `@layer` blocks.
- New JS goes in `script.js` (frontend) or `block-editor.js` (Gutenberg editor) — they bundle separately and load in different contexts.
- Don't introduce new build tools (Webpack, Vite, Gulp) without strong justification.
- This project uses Tailwind v4 — config lives in CSS via `@theme`, not in `tailwind.config.js`.