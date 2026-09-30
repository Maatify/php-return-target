<div align="center">

# PHP Return Target

![Maatify.dev](https://www.maatify.dev/assets/img/img/maatify_logo_white.svg)

[![Status](https://img.shields.io/badge/Status-Pre--Release-blue)](CHANGELOG.md)
[![Version](https://img.shields.io/badge/Version-dev--main-blue)](https://packagist.org/packages/maatify/php-return-target)
[![PHP](https://img.shields.io/badge/PHP-8.4-8892BF)](composer.json)
[![License](https://img.shields.io/badge/License-Proprietary-green)](LICENSE)
[![PHPStan](https://img.shields.io/badge/PHPStan-max-blue)](phpstan.neon)

[![Packagist](https://img.shields.io/badge/Packagist-maatify%2Fphp--return--target-blue)](https://packagist.org/packages/maatify/php-return-target)
[![Monthly Downloads](https://img.shields.io/packagist/dm/maatify/php-return-target)](https://packagist.org/packages/maatify/php-return-target)
[![Total Downloads](https://img.shields.io/packagist/dt/maatify/php-return-target)](https://packagist.org/packages/maatify/php-return-target)
[![Maatify Ecosystem](https://img.shields.io/badge/Maatify-Ecosystem-blueviolet)](https://github.com/Maatify)
[![Install](https://img.shields.io/badge/Install-dev--main-brightgreen)](https://packagist.org/packages/maatify/php-return-target)

[![Usage Guide](https://img.shields.io/badge/Usage%20Guide-current-blue)](docs/guides/USAGE_GUIDE.md)
[![Examples](https://img.shields.io/badge/Examples-maintained-blue)](examples/)
[![Package Reference](https://img.shields.io/badge/Package%20Reference-canonical-blue)](RETURN_TARGET_PACKAGE_REFERENCE.md)
[![Changelog](https://img.shields.io/badge/Changelog-Unreleased-lightgrey)](CHANGELOG.md)
[![Security Policy](https://img.shields.io/badge/Security%20Policy-current-blue)](SECURITY.md)
[![Contributing Guide](https://img.shields.io/badge/Contributing%20Guide-current-blue)](CONTRIBUTING.md)

Stateless signed internal return-target handling for framework-agnostic PHP applications.

</div>

---

## Status

**Pre-Release**

Packagist currently exposes the development version `dev-main` and development branch versions for consumer verification. The target `v1.0.0-rc.1` is not yet published; these development versions are not the SemVer RC.

## Key Features

- Validate internal return targets without rewriting their original representation.
- Issue opaque, expiring `rt1` HMAC tokens and verify them through a public service.
- Re-apply current target restrictions and expiry rules during verification.
- Keep HTTP, routing, authentication, redirect execution, and key loading under Host ownership.

## Requirements

- PHP `^8.4` (CI covers PHP `8.4` and `8.5`)
- `ext-hash`
- `ext-json`
- `maatify/crypto` `^1.0`
- `maatify/exceptions` `^1.0`
- `maatify/shared-common` `^1.0`

## Installation

The currently published development/pre-release distribution is available through Packagist:

```shell
composer require maatify/php-return-target:dev-main
```

This installs the externally resolvable development version, not the target `v1.0.0-rc.1` RC.

## Quick Usage

```php
$service = new HmacReturnTargetService(
    new ReturnTargetConfig('admin-auth', 60),
    $hostKeyProvider,
    $hostClock,
    $optionalHostRestrictionPolicy,
);

$token = $service->issue('/orders/15');
$verified = $token === null ? null : $service->verify($token);
```

The Host supplies the `KeyProviderInterface`, `ClockInterface`, and optional restrict-only policy. See the [Usage Guide](docs/guides/USAGE_GUIDE.md) and [maintained example](examples/basic_usage.php) for a complete runnable construction.

## Public Runtime API

The public substitution boundary is `ReturnTargetServiceInterface`. The canonical implementation is `HmacReturnTargetService`; its public collaborators are `ReturnTargetConfig`, `ReturnTargetRestrictionPolicyInterface`, `KeyProviderInterface`, and `ClockInterface`. Successful verification returns `VerifiedReturnTargetDTO`; normal target, token, policy, or expiry rejection returns `false`/`null` according to the operation. The [Package Reference](RETURN_TARGET_PACKAGE_REFERENCE.md) is the complete canonical contract and API inventory.

## Boundaries

The package does not execute redirects and does not own HTTP, routing, sessions, authentication, authorization, persistence, databases, or token consumption state. The Host owns those concerns and the construction of key material and time policy.

The canonical `rt1` token is signed, not encrypted, and must not carry confidential information or secrets.

Normal target, token, policy, or expiry rejection returns `false` or `null`. Invalid canonical configuration throws `InvalidReturnTargetConfigurationException`; classified canonical crypto/key configuration failures throw `ReturnTargetCryptoConfigurationException`; unknown provider, external, or Host-policy throwables propagate unchanged.

## Documentation

- [Usage Guide](docs/guides/USAGE_GUIDE.md) — consumer fit, boundaries, and workflows.
- [Examples](examples/) — maintained Public API examples.
- [Package Reference](RETURN_TARGET_PACKAGE_REFERENCE.md) — canonical public/runtime/behavioral contract.
- [Changelog](CHANGELOG.md) — factual project history.
- [Security Policy](SECURITY.md) — private vulnerability reporting route and package security ownership.
- [Contributing Guide](CONTRIBUTING.md) — contribution paths, local verification, and repository workflow.
- [Code of Conduct](CODE_OF_CONDUCT.md) — community collaboration and conduct rules.

## Quality Status

The repository defines local and CI gates for Composer validation, Composer 2.10 dependency policy audit, latest/lowest dependency resolution, PHP 8.4/8.5 tests, PHPStan max, formatting, syntax, whitespace, examples, Consumer Verification, and workflow lint. GitHub `Final Gate` is the stable aggregate CI check; the actual status for a commit is reported by its GitHub Actions run.

## Development and Testing

Latest-compatible local sequence:

```text
composer validate --strict
composer update --no-interaction --prefer-dist --no-progress
composer check-platform-reqs
composer audit --no-interaction --abandoned=fail
composer check:syntax
composer check:whitespace
composer format:check
composer analyse
composer test:unit
composer test:system
composer check:examples
composer verify:consumer
composer check:workflows
```

Lowest-supported dependency sequence begins with:

```text
composer update --prefer-lowest --prefer-stable --no-interaction --prefer-dist --no-progress
composer check-platform-reqs
composer audit --no-interaction --abandoned=fail
composer check:syntax
composer format:check
composer analyse
composer test:unit
composer test:system
composer check:examples
```

Restore latest-compatible dependencies and rerun the final applicable local sequence after the lowest check. The Consumer Verification Harness performs two independent clean Composer consumer resolutions and removes generated consumer state after each run. CI tests PHP `8.4` and `8.5`; its `Final Gate` aggregates `quality`, `tests`, `lowest`, `consumer-verification`, and `workflow-lint`.

## License

This package is proprietary software owned by Maatify. See [LICENSE](LICENSE) for the applicable terms.

## Author

Engineered by **Mohamed Abdulalim** ([@megyptm](https://github.com/megyptm))<br>
Backend Lead & Technical Architect<br>
[https://www.maatify.dev](https://www.maatify.dev)

---

<div align="center">

[Built with ❤️ by Maatify.dev — Unified Ecosystem for Modern PHP Libraries](https://www.maatify.dev)

</div>
