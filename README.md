# AbuseIPDB PHP Client

A framework-agnostic PHP client for the [AbuseIPDB APIv2](https://docs.abuseipdb.com), built on PSR-18/PSR-17, with an optional Symfony bundle. You can even use this with vanilla PHP projects with no formal framework!

- Covers all 7 endpoints: `check`, `reports`, `blacklist`, `report`, `check-block`, `bulk-report`, `clear-address`
- Typed parameter and result objects, typed exceptions, rate-limit metadata
- PHP 7.4+

## Installation

```bash
composer require reddingwebdev/abuseipdb
```

If you already have a PHP Framework (Laravel, Symfony, etc) **you're all done**. 

_Otherwise, if you don't already have a PSR-18 HTTP client, you'll also need:_

```bash
composer require symfony/http-client nyholm/psr7
```

## Standalone usage - simple setup
_(Symfony Instructions and Advanced Standalone examples are later on)_
```php
use AbuseIpDb\AbuseIpDbClient;

$client = new AbuseIpDbClient('YOUR_API_KEY');
```

### check

Look up a single IP address and retrieve its abuse confidence score and, optionally, recent reports.

```php
$result = $client->check('8.8.8.8', 90, true); // ip, maxAgeInDays, verbose
$result->getAbuseConfidenceScore();
$result->getIsp();
$result->getCountryCode();
$result->getReports(); // populated when verbose
```


### reports

Retrieve the reports associated with an IP address, with pagination and an optional report-age window.

```php
$page = $client->reports('118.25.6.39', 30, 1, 25); // ip, maxAgeInDays, page, perPage
$page->getTotal();
$page->hasNextPage();
foreach ($page->getResults() as $report) { /* AbuseIpDb\Result\Report */ }
```

### blacklist

Download the current AbuseIPDB blacklist as structured entries or as a plaintext IP list.

```php
use AbuseIpDb\Request\BlacklistParameters;

// confidenceMinimum, limit, onlyCountries, exceptCountries, ipVersion, plaintext
$list = $client->blacklist(new BlacklistParameters(90, 10000, ['US'], [], 4));
foreach ($list as $entry) {
    echo $entry->getIpAddress(), ' ', $entry->getAbuseConfidenceScore(), PHP_EOL;
}

// Plaintext mode returns the raw newline-separated string
$text = $client->blacklist(new BlacklistParameters(null, null, [], [], null, true));
```

### report

Submit an abuse report for an IP address using one or more documented report categories.

```php
use AbuseIpDb\Enum\ReportCategory;

$result = $client->report(
    '127.0.0.1',
    [ReportCategory::SSH, ReportCategory::BRUTE_FORCE],
    'SSH login attempts with user root.',
    new \DateTimeImmutable()
);
$result->getAbuseConfidenceScore();
```

### check-block

Check an IP network in CIDR notation and retrieve reported-address counts within that block.

```php
$block = $client->checkBlock('127.0.0.1/24', 15);
foreach ($block->getReportedAddresses() as $address) {
    echo $address->getIpAddress(), ': ', $address->getNumReports(), PHP_EOL;
}
```

### bulk-report

Submit multiple abuse reports at once by uploading a CSV file or passing CSV contents.

```php
$result = $client->bulkReport('/path/to/report.csv'); // file path or raw CSV contents
$result->getSavedReports();
foreach ($result->getInvalidReports() as $error) {
    echo $error->getRowNumber(), ': ', $error->getError(), PHP_EOL;
}
```

### clear-address

Remove your reports for an IP address and retrieve the number of reports deleted.

```php
$result = $client->clearAddress('127.0.0.1');
$result->getIpAddress();
$result->getNumReportsDeleted();
```

## Error handling

Every exception thrown by the library implements `AbuseIpDb\Exception\AbuseIpDbException`.

| Situation | Exception |
|---|---|
| HTTP 401 | `AuthenticationException` |
| HTTP 402 | `PaymentRequiredException` |
| HTTP 422 | `ValidationException` (`getFieldErrors()`) |
| HTTP 429 | `RateLimitExceededException` (`getRetryAfter()`) |
| HTTP 5xx | `ServerException` |
| Transport failure | `NetworkException` |
| Malformed body | `UnexpectedResponseException` |

Invalid input (bad IP, empty categories, out-of-range values) is rejected before any HTTP call.

```php
use AbuseIpDb\Exception\AbuseIpDbException;
use AbuseIpDb\Exception\RateLimitExceededException;

try {
    $result = $client->check($ip);
} catch (RateLimitExceededException $e) {
    sleep($e->getRetryAfter() ?? 60);
} catch (AbuseIpDbException $e) {
    // log and fail open/closed
}
```

## Rate limits

```php
$client->check($ip);
$rateLimit = $client->getLastRateLimit(); // null before the first call
$rateLimit->getLimit();
$rateLimit->getRemaining();
$rateLimit->getResetAt(); // ?DateTimeImmutable
```

All fields are nullable when the headers are absent. API exceptions also expose `getRateLimit()`.

Responses are not cached; caching `check` results is left to your application.

## Standalone usage - more options

```php
use AbuseIpDb\AbuseIpDbClient;

$client = new AbuseIpDbClient('YOUR_API_KEY', $psr18Client, $requestFactory, $streamFactory, [
    'logger' => $psr3Logger,           // optional PSR-3 logger
    'default_max_age_in_days' => 30,   // used by check/reports/checkBlock when maxAgeInDays is omitted
    'base_uri' => 'https://api.abuseipdb.com/api/v2/',
]);
```

## Symfony

The bundle is optional and inert unless you register it (requires `symfony/framework-bundle` or `symfony/http-kernel` + `symfony/dependency-injection`).

The Symfony Flex recipe source is maintained in this repository (see [recipes/README.md](recipes/README.md)) and is intended for submission to [`symfony/recipes-contrib`](https://github.com/symfony/recipes-contrib). Flex does not automatically consume recipe files from a package repository; the recipe becomes available to normal Flex installs once accepted and published in the contrib repository (with `composer config extra.symfony.allow-contrib true`). Until then, or if not using Flex, register the bundle and create the configuration manually as shown below.

```php
// config/bundles.php
return [
    // ...
    AbuseIpDb\Bridge\Symfony\AbuseIpDbBundle::class => ['all' => true],
];
```

```yaml
# config/packages/abuse_ip_db.yaml
abuse_ip_db:
    api_key: '%env(ABUSEIPDB_API_KEY)%'
    default_max_age_in_days: 30   # optional
    http_client: ~                # optional PSR-18 service id, auto-discovered when null
    logger: ~                     # optional PSR-3 service id, e.g. 'logger'
    # base_uri: 'https://api.abuseipdb.com/api/v2/'
```

### Storing the API key

`ABUSEIPDB_API_KEY` is just an environment variable, so you can set it however your deployment normally handles secrets.

Flex writes a placeholder `ABUSEIPDB_API_KEY=` line into `.env` on install. For local development, put the real value in `.env.local` (git-ignored), or in an environment-specific `.env.<env>.local`:

```env
# .env.local (not committed to VCS)
ABUSEIPDB_API_KEY=your-real-api-key-here
```

For staging/production, prefer Symfony's encrypted secrets vault instead of plaintext env values:

```bash
php bin/console secrets:set ABUSEIPDB_API_KEY
# paste the key value when prompted
```

No configuration change is needed for this — Symfony exposes vault secrets as environment variables at runtime, so the existing `%env(ABUSEIPDB_API_KEY)%` in `abuse_ip_db.yaml` picks it up automatically. Use `.env.local` for local development convenience, and `secrets:set` for anything deployed, since it keeps plaintext keys out of your environment/CI configuration.

Then autowire the client:

```php
use AbuseIpDb\AbuseIpDbClient;

final class IpGuard
{
    public function __construct(private AbuseIpDbClient $abuseIpDb) {}
}
```

The service is also available as `abuse_ip_db.client`.

### Troubleshooting: "Cannot autowire ... is type-hinted \"string\""

If one of your own services or commands type-hints the raw API key directly, e.g.:

```php
final class AbuseReportCommand
{
    public function __construct(private string $abuseIpDbKey) {}
}
```

you will get an error like:

```
Cannot autowire service "App\Command\AbuseReportCommand": argument "$abuseIpDbKey"
of method "__construct()" is type-hinted "string", you should configure its value explicitly.
```

This is a general Symfony limitation, not specific to this bundle: [autowiring only works for object arguments](https://symfony.com/doc/current/service_container/autowiring.html#fixing-non-autowireable-arguments), so a bare scalar type-hint (`string`, `int`, `array`, etc.) can never be autowired, no matter how the value is exposed.

- **Recommended:** type-hint `AbuseIpDbClient $abuseIpDb` instead (shown above) — this already autowires correctly and covers most use cases.
- **If you truly need the raw key value** (the string API key is primarily meant for standalone, non-Symfony usage), the bundle exposes it as the `abuse_ip_db.api_key` container parameter, and you must wire it explicitly in your own service, either with the `#[Autowire]` attribute:

  ```php
  use Symfony\Component\DependencyInjection\Attribute\Autowire;

  final class AbuseReportCommand
  {
      public function __construct(
          #[Autowire('%abuse_ip_db.api_key%')] private string $abuseIpDbKey
      ) {}
  }
  ```

  or with `bind:` in your `config/services.yaml`:

  ```yaml
  services:
      _defaults:
          bind:
              string $abuseIpDbKey: '%abuse_ip_db.api_key%'
  ```

## Development

```bash
composer test      # PHPUnit (mocked HTTP, no network)
composer phpstan
composer cs
```

## Versioning & publishing

This package is published on [Packagist as `reddingwebdev/abuseipdb`](https://packagist.org/packages/reddingwebdev/abuseipdb) from the canonical [GitHub repository](https://github.com/reddingwebpro/abuseIpDb). This package follows [Semantic Versioning](https://semver.org).

## License

MIT — see [LICENSE](LICENSE).
