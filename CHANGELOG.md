# Changelog

All notable changes to this project will be documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 0.1.1

### Added
- Symfony bundle: expose the configured API key as the `abuse_ip_db.api_key` container parameter, so consumer services that need the raw key can wire it explicitly (via `#[Autowire('%abuse_ip_db.api_key%')]` or `bind:`).
- Symfony bundle: created and moved the Flex recipe to the `symfony/recipes-contrib` repository.

### Documentation
- README: added a Symfony troubleshooting note explaining why a bare `string $abuseIpDbKey` constructor type-hint can never autowire (a general Symfony limitation), and documenting the recommended `AbuseIpDbClient` autowiring path plus the explicit workaround.

## 0.1

### Initial Release

