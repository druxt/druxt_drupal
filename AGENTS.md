# AGENTS.md

This file provides guidance to AI agents when working with code in this repository.

## Overview

DruxtJS is a Drupal module that bridges Drupal's JSON:API backend with a
[Nuxt.js](https://nuxtjs.org) frontend. It provides a permission for
read-only access to all JSON:API resources required by the
[DruxtJS](https://druxtjs.org) frontend framework, plus route translation
support for Views, Contact forms, and wildcard paths via the Decoupled Router
module.

## Development Commands

**HARD RULE - use the provided command wrappers, never the tool binaries directly.** When `make` or `ahoy` exposes a command for a task, use that command. Do not call the underlying binary directly. Each wrapper `chdir`s into `build/` and runs the tool with the config, plugins, and environment that CI uses, so a raw invocation from the repository root silently diverges from CI - it can pass locally while CI fails (or vice versa), or crash outright when a relative path resolves against the wrong directory. If no wrapped command covers what you need, extend the `make` / `ahoy` target rather than making a one-off raw call. If that is not feasible, stop and ask.

Run each tool through its `make` wrapper, never the binary directly:

- **PHPCS / PHPCBF**: `make lint` / `make lint-fix` - never `vendor/bin/phpcs` or `vendor/bin/phpcbf`.
- **PHPStan**: `make lint` - never `vendor/bin/phpstan`.
- **Rector**: `make lint` (dry-run) / `make lint-fix` - never `vendor/bin/rector`.
- **ESLint / Stylelint**: `make lint` / `make lint-fix` - never `npx eslint` or `npx stylelint`.
- **CSpell**: `make lint` - never `npx cspell`.
- **PHPUnit**: `make test` / `make test-unit` / `make test-kernel` / `make test-functional` - never `vendor/bin/phpunit`.
- **Jest**: `make test-js` - never `npx jest`.
- **Drush**: `make drush <command>` - never `build/vendor/bin/drush` directly.


### Build and Environment Management

**Using Make (default):**
- `make build` - Complete build (stop → assemble → start → provision)
- `make assemble` - Assemble codebase with dependencies
- `make start` - Start PHP development server
- `make stop` - Stop development server
- `make provision` - Install/provision Drupal site
- `make reset` - Clean build directory and logs (aliases: `make delete`, `make destroy`)


### Code Quality

**Linting:**
- `make lint` - Run all linting tools
- `make lint-fix` - Auto-fix coding standards violations

**Testing:**
- `make test` - Run all tests
- `make test-unit` - Run unit tests only
- `make test-kernel` - Run kernel tests only
- `make test-functional` - Run functional tests only
- `make test-functional-javascript` - Run FunctionalJavascript tests (requires Selenium)
- `make test-js` - Run JavaScript unit tests (Jest)
- `make selenium-start` - Start Selenium container
- `make selenium-stop` - Stop Selenium container

### Drupal Commands

- `make drush <command>` - Run Drush commands
- `make login` - Get one-time login link

### Diagnostics

- `make info` - Print a read-only summary of PHP/Drupal/Composer/Drush/Node versions, webserver host/port (with source), XDebug state, build directory, database path, and active profile. (alias: `make describe`)

## Project Structure

**Key Files:**
- `druxt.module` - Hook implementations (permissions, CORS)
- `druxt.install` - Install/uninstall hooks (CORS defaults)
- `druxt.services.yml` - Service definitions (event subscribers)
- `src/DruxtServiceProvider.php` - Service provider overrides
- `src/EventSubscriber/ViewsPathTranslatorSubscriber.php` - Views route translation
- `src/EventSubscriber/WildcardPathTranslatorSubscriber.php` - Wildcard route translation
- `src/EventSubscriber/ContactPathTranslatorSubscriber.php` - Contact form route translation
- `src/Plugin/Condition/DruxtRequestPath.php` - Condition plugin for Druxt requests
- `tests/src/Functional/` - Functional tests (condition bypass, CORS, permissions, EntityViewDisplay)
- `build/` - Assembled Drupal codebase (symlinked extension, gitignored)
- `.devtools/` - Build and deployment scripts used by CI
- `scripts/` - Custom post-start (`start-*.sh`), post-provision (`provision-*.sh`), and pre-stop (`stop-*.sh`) hooks. Run automatically at the end of each phase in lexicographic order. A non-zero exit aborts the parent. Excluded from distribution archives via `.gitattributes`

## Architecture

- **Route translation**: Event subscribers integrate with the Decoupled Router
  module to translate Views, Contact form, and wildcard paths for the DruxtJS
  frontend.
- **Resource access**: The `access druxt resources` permission gates
  read-only access to all JSON:API resources the frontend needs.
- **Condition bypass**: Block condition plugins are bypassed for Druxt requests
  so all blocks are available to the frontend.
- **CORS**: Cross-Origin Resource Sharing is enabled with sensible defaults
  for frontend consumption.
- **EntityViewDisplay**: Ensures configuration is available for the
  DruxtSchema module.

## Environment Variables

- `DRUPAL_VERSION` - Target Drupal version (e.g., `10`, `11`, `11@alpha`)
- `WEBSERVER_HOST` - Development server host (default: localhost)
- `WEBSERVER_PORT` - Development server port. Auto-discovered from range 8000-8099 and written to `.env` if not already set
- `GITHUB_TOKEN` - GitHub API token to avoid rate limits

## Development Workflow

1. Build environment: `DRUPAL_VERSION=11 make build` or `ahoy build`
2. Develop DruxtJS code in `src/`
3. Check standards: `make lint` or `ahoy lint`
4. Run tests: `make test` or `ahoy test`
5. Access site at http://localhost:8000

## Code Quality Tools

- **CSpell**: Spell checking across the codebase (config at `.cspell.json`)
- **PHPCS**: Drupal and DrupalPractice standards
- **PHPStan**: Static analysis with Drupal extensions
- **Rector**: Automated refactoring and deprecation fixes

## CI/CD Support

- **GitHub Actions**: `.github/workflows/test.yml` (lint + matrix test)
- **GitLab CI**: `.gitlab-ci.yml` for Drupal.org CI
- **Matrix testing**: PHP 8.2-8.5, Drupal 10-11

## Important Notes

- The `build/` directory contains the assembled Drupal site
- Extension files are symlinked from root into `build/web/modules/custom/` (module) or `build/web/themes/custom/` (theme)
- SQLite database created in `/tmp/site_druxt.sqlite`
- All quality tools run from within `build/` directory

## Updating the scaffold

When the user asks to update this project's scaffold (e.g. "update scaffold"), fetch the update skill from GitHub into the local `.claude/skills/` directory, then invoke it:

1. Create the target directory if it does not exist:

   ```bash
   mkdir -p .claude/skills/update-consumer-drupal-extension-scaffold
   ```

2. Download the skill:

   ```bash
   curl -sSL https://raw.githubusercontent.com/AlexSkrypnyk/drupal_extension_scaffold/1.x/.scaffold/skills/update-consumer-drupal-extension-scaffold/SKILL.md -o .claude/skills/update-consumer-drupal-extension-scaffold/SKILL.md
   ```

3. Invoke the `update-consumer-drupal-extension-scaffold` skill and follow its steps.

The skill directory is git-ignored - it is fetched on demand and not committed to the project.
