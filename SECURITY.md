# Security Policy

[![Maatify PHP Return Target](https://img.shields.io/badge/Maatify-PHP%20Return%20Target-blue?style=for-the-badge)](https://github.com/Maatify/php-return-target)
[![Maatify Ecosystem](https://img.shields.io/badge/Maatify-Ecosystem-9C27B0?style=for-the-badge)](https://github.com/Maatify)

## Current Support State

This package is currently in **Pre-Release**. Packagist exposes development versions, but the target `v1.0.0-rc.1` has not yet been published.

There is currently no published Stable release, no supported Stable release
line, and no published SemVer Release Candidate (RC). Development/pre-release
distribution does not create Stable support. This policy does not
present `1.x` or `1.0` as a supported release line.

## Reporting a Vulnerability

Please report suspected vulnerabilities privately to `support@maatify.dev`.
Do not disclose vulnerabilities through public GitHub Issues. Do not include
production secrets or unrelated personal or customer data in a report.

Where known, a report may include:

- the affected commit or version;
- the security impact;
- reproduction steps or a proof of concept; and
- relevant environment information.

This policy does not promise a response or resolution service-level agreement.

## Scope

This policy covers security concerns in the package-owned:

- return-target acceptance boundary;
- canonical token issue and verification behavior; and
- cryptographic or key-integration behavior owned by this package, including
  dependency interactions insofar as they affect this package.

The Host owns HTTP, routing, sessions, authentication, authorization, actual
redirect execution, and key storage or loading policy. Those Host concerns are
outside this package's security ownership unless a report demonstrates a
security impact in a package-owned boundary.
