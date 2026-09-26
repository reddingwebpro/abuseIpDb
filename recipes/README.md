# Symfony Flex Recipe for AbuseIPDB

This directory contains the official Symfony Flex recipe for `reddingwebdev/abuseipdb`.

## Recipe Structure

```
recipes/
└── reddingwebdev/
    └── abuseipdb/
        └── 1.0/
            ├── config/
            │   └── packages/
            │       └── abuse_ip_db.yaml    # Default package configuration
            ├── manifest.json               # Flex instructions (bundles, env, copy)
            └── post-install.txt            # Console output displayed after recipe installation
```

### Manifest Overview

The `manifest.json` file configures:
1. **Bundle Registration**: Automatically registers `AbuseIpDb\Bridge\Symfony\AbuseIpDbBundle` in `config/bundles.php` for all environments.
2. **Configuration Files**: Copies `config/packages/abuse_ip_db.yaml` to `%CONFIG_DIR%/packages/abuse_ip_db.yaml`.
3. **Environment Variables**: Adds `ABUSEIPDB_API_KEY=` placeholder to `.env`.
4. **Post-install Instructions**: Prompts the developer to configure their API key using `.env.local` or Symfony's encrypted secrets vault (`php bin/console secrets:set ABUSEIPDB_API_KEY`).

---

## Local Testing of the Recipe

Before submitting to `symfony/recipes-contrib`, you can test the recipe locally in a Symfony project.

### Option 1: Automated Unit & Container Tests

This repository includes automated tests in `tests/Unit/Bridge/Symfony/RecipeTest.php` that validate:
- JSON structure and syntax of `manifest.json`.
- File existence for all paths referenced in `copy-from-recipe`.
- Parsing of `abuse_ip_db.yaml`.
- Symfony DI ContainerBuilder compilation using the recipe's configuration.

Run tests via:
```bash
vendor/bin/phpunit --filter RecipeTest
```

### Option 2: Testing with a Local Symfony Project via Flex Endpoint

To test installation in a real Symfony application using Flex:

1. In your target Symfony application, allow contrib recipes:
   ```bash
   composer config extra.symfony.allow-contrib true
   ```
2. Configure Flex to load recipes from a custom endpoint or fork if desired:
   ```bash
   composer config extra.symfony.endpoint 'https://api.github.com/repos/<username>/recipes-contrib/contents/index.json'
   ```
3. Alternatively, copy the `recipes/reddingwebdev/abuseipdb/1.0/` contents directly to test bundle registration and configuration parsing in a test kernel.

---

## Upstream Submission to `symfony/recipes-contrib`

Because this package is maintained outside of the core Symfony organization, its Flex recipe belongs in the community-driven [`symfony/recipes-contrib`](https://github.com/symfony/recipes-contrib) repository.

### Submission Steps

1. **Fork and Clone `symfony/recipes-contrib`**:
   ```bash
   git clone https://github.com/<your-username>/recipes-contrib.git
   cd recipes-contrib
   git checkout -b recipe/reddingwebdev-abuseipdb-1.0
   ```

2. **Copy the Recipe Directory**:
   Copy the recipe tree from this repository to `recipes-contrib`:
   ```bash
   mkdir -p reddingwebdev/abuseipdb/1.0
   cp -r /path/to/abuseipdb/recipes/reddingwebdev/abuseipdb/1.0/* reddingwebdev/abuseipdb/1.0/
   ```

3. **Verify Guidelines**:
   Ensure compliance with the [Symfony Recipes Contribution Guidelines](https://github.com/symfony/recipes-contrib/blob/master/README.md):
   - Package name is lowercased: `reddingwebdev/abuseipdb`.
   - Version directory matches minimum package version: `1.0`.
   - `manifest.json` is formatted with 4-space indentation.
   - Configuration files only define necessary defaults and keep optional settings commented out.

4. **Submit Pull Request**:
   - Push your branch to GitHub.
   - Open a Pull Request against `symfony/recipes-contrib` target branch `main` (or `master`).
   - Title: `Add recipe for reddingwebdev/abuseipdb 1.0`.
   - Once merged by the Symfony core/recipes team, `composer require reddingwebdev/abuseipdb` will automatically execute the recipe in all Symfony applications with `extra.symfony.allow-contrib: true`.
