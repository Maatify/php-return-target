# Return Target Package Reference

## Package Purpose

The current package concern is stateless signed internal return-target handling. The package remains `Development / Unpublished`.

## Public Runtime Contract

- `Maatify\\ReturnTarget\\Service\\ReturnTargetServiceInterface` — `accepts(string): bool`, `issue(string): ?string`, and `verify(string): ?VerifiedReturnTargetDTO`.
- `Maatify\\ReturnTarget\\Service\\HmacReturnTargetService` — canonical implementation.
- `Maatify\\ReturnTarget\\Validation\\ReturnTargetRestrictionPolicyInterface` — optional restrict-only Host policy.
- `Maatify\\ReturnTarget\\Config\\ReturnTargetConfig` — immutable validated audience and `1..3600` second TTL.
- `Maatify\\ReturnTarget\\DTO\\VerifiedReturnTargetDTO` — readonly result with exactly `target` and `expiresAt`.
- `Maatify\\ReturnTarget\\Exception\\ReturnTargetExceptionInterface` — package exception marker.
- `Maatify\\ReturnTarget\\Exception\\InvalidReturnTargetConfigurationException` — invalid configuration.
- `Maatify\\ReturnTarget\\Exception\\ReturnTargetCryptoConfigurationException` — classified canonical crypto/key configuration failure.

### Shared Service Behavioral Floor

`ReturnTargetServiceInterface` is a common substitution boundary for the canonical
service and complete Host-supplied implementations. A Host implementation may choose
its own token format, cryptographic mechanism, key management, validation internals,
TTL policy, and storage/persistence internals. It conforms only when `accepts()` reports
current internal-target acceptance, `issue()` returns an opaque token for an accepted
target or null for normal rejection without a fallback target, and `verify()` returns a
currently accepted, non-expired `VerifiedReturnTargetDTO` or null for normal rejection.
`expiresAt` is the authoritative Unix timestamp in seconds.

The guarantees below are canonical-only properties of `HmacReturnTargetService`, not
additional requirements imposed on every Host implementation.

The service constructor is `HmacReturnTargetService(ReturnTargetConfig, KeyProviderInterface, ClockInterface, ?ReturnTargetRestrictionPolicyInterface = null)`. `accepts()` validates the original target and invokes the optional policy exactly once with the single-percent-decoded inspection representation. `issue()` returns null for normal rejection, uses `ClockInterface::now()->getTimestamp()`, and signs the exact original representation. `verify()` passes the Clock timestamp to the internal codec, re-applies current validation and policy, rejects when `now >= exp`, and returns the exact recovered original representation.

The validator rejects unsafe RFC 3986 target forms, original targets larger than 2048
bytes, raw or decoded controls including DEL, whitespace, backslash, `#`, malformed
percent escapes, authority-form exposure, and raw or single-decoded path dot segments.
It performs one percent-decoding inspection pass only, rejects second-stage valid
percent-escape ambiguity, and never trims, normalizes, recursively decodes, or rewrites.
The exact original representation is preserved in canonical token payloads and public
results; the inspection representation is never stored or returned as a replacement.

The service does not catch unknown external/provider or Host-policy throwables. Current
package-owned mappings are `InvalidReturnTargetConfigurationException` to the
`maatify/exceptions` `INVALID_ARGUMENT` contract and
`ReturnTargetCryptoConfigurationException` to `MAATIFY_ERROR`. Classified codec
failures remain governed by the codec contract; unknown failures are not blanket-wrapped
or swallowed. The direct Runtime dependencies are PHP `^8.4`, `ext-hash`, `ext-json`,
`maatify/crypto ^1.0`, `maatify/exceptions ^1.0`, and `maatify/shared-common ^1.0`.

## Public API Inventory

The implemented public API is the following current contract.

### `ReturnTargetServiceInterface`

```php
accepts(string $target): bool
issue(string $target): ?string
verify(string $token): ?VerifiedReturnTargetDTO
```

All conforming implementations provide the shared behavioral floor above: normal target
or token rejection returns `false` or `null` as applicable, issuance never substitutes
a fallback target, and successful verification returns a currently accepted,
non-expired result.

### `HmacReturnTargetService`

This `final` class is the canonical implementation of the interface.

```php
HmacReturnTargetService(
    ReturnTargetConfig $config,
    KeyProviderInterface $keyProvider,
    ClockInterface $clock,
    ?ReturnTargetRestrictionPolicyInterface $restrictionPolicy = null,
)
```

`KeyProviderInterface` and `ClockInterface` are public collaborator contracts supplied
by dependencies; they are not package-owned interfaces. The optional
`ReturnTargetRestrictionPolicyInterface` is the only Host extension inside this
canonical implementation.

### `ReturnTargetRestrictionPolicyInterface`

```php
allows(string $inspectionTarget): bool
```

This is a restrict-only policy. It is called once per acceptance evaluation, after
canonical validation, with the validated single-decoded inspection representation.
It cannot widen the package-owned safety boundary.

### `ReturnTargetConfig`

```php
new ReturnTargetConfig(string $audience, int $ttlSeconds)
```

The readonly public values are `audience` and `ttlSeconds`. Canonical constraints are:

- `audience` is `1..64` bytes and contains only `A-Z`, `a-z`, `0-9`, `.`, `_`, or `-`.
- `ttlSeconds` is `1..3600` seconds.

### `VerifiedReturnTargetDTO`

```php
new VerifiedReturnTargetDTO(string $target, int $expiresAt)
```

The readonly public properties are `target` and `expiresAt`. `expiresAt` is the
authoritative Unix timestamp in seconds. The DTO implements `JsonSerializable` and its
exact serialization shape is:

```php
[
    'target' => string,
    'expiresAt' => int,
]
```

No additional fields are part of this result contract.

### Package-owned exceptions

The current package-owned exception contracts are `ReturnTargetExceptionInterface`,
`InvalidReturnTargetConfigurationException`, and
`ReturnTargetCryptoConfigurationException`. Their stable semantics are:

- `InvalidReturnTargetConfigurationException` maps to the `maatify/exceptions`
  `INVALID_ARGUMENT` contract.
- `ReturnTargetCryptoConfigurationException` maps to `MAATIFY_ERROR`.

Not every throwable is package-owned: unknown provider, infrastructure, and Host-policy
throwables propagate unchanged.

## Canonical HMAC Security and Configuration Contract

### Token semantics

The canonical token family is `rt1`. Tokens are opaque to consumers; consumers must not
parse or depend on internal framing. The canonical token is signed, not encrypted. It
provides integrity, authenticity, bounded lifetime, and context/audience isolation, but
does not provide confidentiality.

Return targets and tokens must not carry passwords, credentials, session secrets, API
secrets, confidential tokens, sensitive PII, or other confidential data. Wire-format
internals remain an implementation detail rather than a consumer contract.

### Resource and key bounds

- The maximum target length is `2048` bytes.
- The maximum token length is `4096` bytes.
- Configuration uses the audience and TTL constraints stated above.

The canonical HMAC implementation requires exactly one ACTIVE key, a non-empty active
key ID, and key material supplied by the Host through the public `KeyProviderInterface`.
The key material must satisfy the consumed crypto/HKDF contract; this reference does
not add a separate minimum key length.

### Verification failure semantics

- Unknown `kid`, decryption-disallowed key, expired, malformed, or untrusted token:
  normal rejection returning `null`.
- No active key, multiple active keys, classified HKDF or key-configuration failure,
  or inconsistent post-lookup key state: `ReturnTargetCryptoConfigurationException`.
- Unknown provider or infrastructure throwables: propagate unchanged.

### Exact target and redirect boundary

The original target representation is preserved exactly. The decoded target is for
inspection only, and the package does not perform redirects. If a Host decodes,
normalizes, resolves, rewrites, or otherwise transforms the verified returned target
before redirecting, the Host owns re-validation of the transformed value.

## Verification Architecture

- **Unit:** protects isolated canonical logic and edge cases.
- **System:** protects public library workflows from the Public API to observable
  results across the real in-process implementation chain.
- **Consumer Verification Harness:** uses a separate Composer root, production
  autoload, clean-state execution, two independent runs, and a Public API workflow.
  It complements, and does not replace, Unit/System verification or Real Host
  Validation.
- **Integration applicability:** the package currently owns no persistence, database,
  or external-service Runtime boundary. A database/service Integration suite is not
  applicable to the current contract.

## Current Boundary

- The package currently has no persistence, database, SQL, or PDO behavior.
- The package does not own framework, HTTP, router, session, or controller behavior.
- The package does not contain Host-specific authentication flows.
- The Host owns HTTP, router, session, authentication, authorization, actual redirect execution, fallback destinations, and Host-specific route policy.
- The internal token/crypto codec and `VerifiedTokenPayloadDTO` are not Public APIs and are not Host-replaceable.

## Source Topology

**Source Topology: Single Capability**, as recorded in the Owner-approved [DEC-002 — Single Capability Source Topology](docs/decisions/DEC-002-SINGLE-CAPABILITY-SOURCE-TOPOLOGY.md).

The `Adapter/`, `Config/`, `DTO/`, `Exception/`, `Service/`, and `Validation/` responsibilities are materialized for the implemented Runtime.

## Composer Ownership

Composer identity, requirements, dependencies, autoloading, configuration, stability, and distribution metadata are owned by `composer.json`.

The current direct Runtime dependencies are `php ^8.4`, `ext-hash *`, `ext-json *`, `maatify/crypto ^1.0`, `maatify/exceptions ^1.0`, and `maatify/shared-common ^1.0`.

## Technical Consumer Workflow

The canonical consumer workflow is:

```text
Host input
→ ReturnTargetServiceInterface / canonical HmacReturnTargetService
→ canonical acceptance / optional Host restriction
→ token issue or verify
→ VerifiedReturnTargetDTO / normal null rejection
→ Host-owned redirect decision and execution
```

The Host constructs and supplies the public `KeyProviderInterface`, the `ClockInterface` implementation, and the optional `ReturnTargetRestrictionPolicyInterface`. The Host also owns key loading, HTTP, router, session, authentication, authorization, fallback destinations, and redirect execution. The canonical package implementation owns target validation and token verification; it never executes a redirect.

See the [Usage Guide](docs/guides/USAGE_GUIDE.md) for integration guidance and [examples/](examples/) for maintained Public API examples. Those artifacts explain and demonstrate this contract; they are not alternative contract sources.

## Extension Guide

### Complete Host Implementation

A Host may implement `ReturnTargetServiceInterface` as a complete replacement
implementation. It is bound only by the Shared Behavioral Floor described in
this reference. It may differ in token format, cryptography, key management,
validation internals, TTL policy, and storage or persistence internals, as
specified by DEC-003 and the Public Contract.

### Canonical HMAC Restriction Extension

With `HmacReturnTargetService`, the only Host extension point inside the
canonical implementation is `ReturnTargetRestrictionPolicyInterface`. It is a
restrict-only policy and does not expand canonical safety guarantees.

### Non-Extension Internals

The following are not extension surfaces:

- `HmacReturnTargetTokenCodec`;
- `CanonicalReturnTargetValidator`; and
- `VerifiedTokenPayloadDTO`.

No additional interface is created for these internals.

## Persistence / Operational Read Classification: Out of Scope

The package owns no persisted state, package tables, or schema, and no
operational reporting surface is applicable to the current contract. This is a
current-state classification and does not generalize the package's future
scope.
