# DEC-003 — Stateless Signed Return Target Runtime and Security Boundary

## Decision Metadata

- **Decision ID:** `DEC-003`
- **Title:** Stateless Signed Return Target Runtime and Security Boundary
- **Status:** `PROPOSED`
- **Date:** `2026-09-28`
- **Decision Authority / Deciders:** Project Owner
- **Scope / Concern:** Return-target runtime architecture, Public Contract, security boundary, crypto integration, extensibility boundary, and persistence boundary
- **Supersedes:** None
- **Superseded By:** None
- **Canonical Contract / Current Owner:** None — proposal only; no implementation authority until ACTIVE

> This record is a proposal. It is not Owner approval, a Runtime implementation authority, or an acceptance decision. No implementation may rely on this record until the record is explicitly changed to `ACTIVE` through the applicable decision-governance process.

## Context

`maatify/php-return-target` is currently a `Development / Unpublished` standalone package. It has no Public Runtime API implemented, no materialized `src/` directory, and no persistence or database behavior. `DEC-002` has closed the source topology as `Single Capability`.

Before the first Runtime Work Unit, the material architecture, security boundary, Public Contract, crypto integration boundary, extensibility boundary, and persistence boundary must be recorded. Earlier implementations in Host projects are evidence for discussion only; they are not this package's contract.

The proposal is designed to consume stable reusable capabilities rather than duplicate them:

- `maatify/crypto` stable `v1.0.0`;
- `maatify/shared-common` stable `v1.0.0`; and
- `maatify/exceptions` stable `v1.0.0`.

## Proposed Decision

### 1. Package Capability

`maatify/php-return-target` will be a standalone, framework-agnostic library responsible for this bounded flow:

```text
Untrusted internal return target
→ safety validation
→ signed expiring transport token
→ verification
→ verified internal return target
```

The package will not execute redirects.

### 2. Dual Integration Path

The package will expose two official integration paths.

#### Path A — Canonical Secure Implementation

The package will provide a ready-made implementation named `SecureReturnTargetService` implementing `ReturnTargetServiceInterface`. This is the recommended canonical implementation. Its security semantics are fixed and are not a configurable strategy system.

#### Path B — Host-Supplied Implementation

A Host may provide its own implementation of `ReturnTargetServiceInterface` and replace the canonical implementation without changing the consumer-facing service contract:

```php
final class CustomReturnTargetService implements ReturnTargetServiceInterface
{
}
```

When a Host selects this path, the package does not require the Host implementation to use the canonical crypto or token protocol. The custom implementation must nevertheless honor the interface's behavioral Public Contract when it presents itself as a drop-in implementation.

#### Common Public Behavioral Floor

Every implementation claiming conformity with `ReturnTargetServiceInterface`, whether canonical or Host-supplied, shares the following behavioral floor:

- `isSafe(string $target): bool` returns `true` only when the target is an internal return target currently accepted by that implementation, is not an external-authority redirect target, and any implementation-specific policy currently applied by that implementation passes. It returns `false` for normal target rejection.
- `issue(string $target): ?string` returns an opaque transport token string for an accepted internal target, and `null` for normal target rejection. An implementation must not issue a token for a target it currently considers unacceptable, and the service must not substitute an internal fallback.
- `verify(string $token): ?VerifiedReturnTargetDTO` succeeds only when the token is currently acceptable, its returned target is currently an accepted internal return target, the result is not expired at verification time, and `expiresAt` is the result's Unix expiration instant in seconds. When `current time >= expiresAt`, verification must not succeed. Normal malformed, untrusted, or rejected tokens return `null`.

Every conforming implementation therefore has bounded-lifetime semantics. The exact `1..3600` TTL range remains a policy of `SecureReturnTargetService` only; a custom implementation may choose a different TTL duration while preserving the shared expiry meaning and non-expired verification floor.

### 3. Security Guarantee Boundary

The shared interface guarantees apply to every conforming implementation: successful results remain internal-target-only, normal rejection uses `false` or `null`, successful verification means a currently accepted and non-expired result, `expiresAt` has the shared Unix-expiry meaning, and the service performs no internal fallback substitution.

The following implementation-specific guarantees apply to `SecureReturnTargetService` only: its exact RFC-oriented validator rules, 2048-byte target limit, 4096-byte token limit, `rt1` framing, Base64URL canonicalization, HMAC-SHA256, `maatify/crypto` KeyRotation integration, HKDF, the `return-target:token:v1` context, `kid` semantics, audience format and binding, canonical TTL range `1..3600`, canonical crypto/configuration exception mapping, and restrict-only `ReturnTargetPolicyInterface` behavior.

Host-supplied implementations are Host-owned and do not automatically inherit implementation-specific security guarantees of `SecureReturnTargetService`. This boundary must not be described as a security rating of custom implementations; it defines only which implementation owns the stated guarantees.

### 4. No Strategy Explosion Inside the Canonical Implementation

`SecureReturnTargetService` must not expose pluggable strategies for changing its security protocol. It must not introduce any of the following merely to permit customization:

```text
SignerInterface
EncoderInterface
TokenParserInterface
ExpiryStrategyInterface
CryptoStrategyInterface
TokenFormatInterface
GenericValidatorOverrideInterface
```

Changing those semantics requires another `ReturnTargetServiceInterface` implementation rather than configurable internals in the canonical service.

### 5. Safe Extension Inside the Canonical Implementation

The only bounded extension point inside the canonical implementation is an optional Host-specific restriction policy:

```php
interface ReturnTargetPolicyInterface
{
    public function allows(string $target): bool;
}
```

The policy may restrict targets for Host-specific reasons, such as rejecting `/login`, `/logout`, or `/2fa/setup`. It may not redefine generic package safety.

### 6. Restrict-Only Policy Rule

The canonical order is:

```text
Generic Package Safety
→ MUST PASS

Optional Host Policy
→ MUST PASS

Target Accepted
```

An unsafe target such as `//evil.example` remains rejected even if the Host policy returns `true`.

### 7. Policy Application Semantics

For the canonical implementation, `isSafe()` represents effective service acceptance: generic safety and the optional Host policy must both pass.

`issue()` applies the same effective policy before issuing a token. After cryptographic verification, `verify()` re-applies generic safety to the recovered target and then applies the current Host policy to the original target and, when different, its single-decoded inspection view. Every evaluation must allow the target. It returns a DTO only when all checks pass. Consequently, changing the Host policy may make an old token cryptographically valid but currently unacceptable; this is intentional.

### 8. Stateless and Persistence Boundary

The package will be stateless. It will not own or require PDO, SQL, MySQL/MariaDB, Redis, a database, schema, migrations, a Repository, token persistence, nonce persistence, session persistence, or a one-time-token registry. It will not depend on `maatify/persistence`.

A token may be verified more than once while valid. A return-target token is not an authentication or authorization credential. One-time consumption is outside v1.

### 9. Host Ownership

The Host owns the HTTP request and response, router, middleware, session, cookies, authentication, login, 2FA or step-up flow, authorization, actual redirect execution, redirect status, query-parameter name, fallback destination, route existence, Host-specific route restrictions, secret loading, `.env`/vault/KMS integration, construction of the crypto key provider, and selection between canonical and custom `ReturnTargetServiceInterface` implementations.

The package does not know Host route names.

### 10. Canonical Package Ownership

`SecureReturnTargetService` owns the generic internal-target safety floor, canonical token protocol and version, token serialization, HMAC signing, signature verification, expiry semantics, audience binding, `kid` transport semantics, configuration validation, Base64URL canonicalization, resource/input bounds, and revalidation after verification.

### 11. Canonical Crypto Integration

The canonical implementation will depend at Runtime on `maatify/crypto ^1.0` and consume these stable capabilities:

```text
Maatify\Crypto\KeyRotation\KeyRotationService
Maatify\Crypto\HKDF\HKDFService
Maatify\Crypto\HKDF\HKDFContext
```

Issuance uses `KeyRotationService::activeEncryptionKey()` to obtain the active key ID and root material, then HKDF to derive a 32-byte signing key. Verification uses the token `kid` with `KeyRotationService::decryptionKey(kid)` and the same HKDF context. The exact context is:

```text
return-target:token:v1
```

Minimum root-key rules and key lifecycle policy remain owned by `maatify/crypto`.

### 12. HMAC Ownership

Because `maatify/crypto v1.0.0` does not expose a stable generic signing/HMAC Public API, the canonical implementation owns protocol-specific `HMAC-SHA256` signing and uses `hash_equals()` for verification. It must not create a generic local crypto or signing subsystem.

### 13. Direct Runtime Extension

Because the canonical Runtime uses `hash_hmac(...)` directly, a future activation and implementation must declare `ext-hash: *` as a direct Runtime requirement. This proposal does not modify `composer.json`.

### 14. Clock

The canonical implementation will depend on `maatify/shared-common ^1.0` and consume `Maatify\SharedCommon\Contracts\ClockInterface`. It will not create a local Clock abstraction, and canonical expiry behavior will not use `time()` as its source.

### 15. Exceptions

Package-owned exceptions will expose:

```php
interface ReturnTargetExceptionInterface extends \Throwable
{
}
```

The proposed package-owned hierarchy includes `InvalidReturnTargetConfigurationException` using the appropriate Validation hierarchy from `maatify/exceptions`, and `ReturnTargetCryptoConfigurationException` using the appropriate System hierarchy.

The following are normal failure returns, not exceptions: unsafe targets, Host-policy rejection, malformed tokens, unsupported versions, malformed or non-canonical Base64URL, malformed payloads, signature mismatch, wrong audience, expired tokens, unknown `kid`, rejected verification keys, and invalid targets recovered from payloads.

Invalid audience or TTL configuration, missing or invalid active crypto keys, broken key-rotation invariants, invalid root-key material, and HKDF configuration/material failures are exceptional configuration or runtime-setup failures. When converting a known crypto failure, the original exception must be preserved as `previous`; blanket `catch (\Throwable)` wrapping is forbidden, and unknown external throwables must not be swallowed.

### 16. Proposed Public Service Contract

The common integration boundary is:

```php
interface ReturnTargetServiceInterface
{
    public function isSafe(string $target): bool;

    public function issue(string $target): ?string;

    public function verify(string $token): ?VerifiedReturnTargetDTO;
}
```

This boundary is shared by the canonical secure implementation and Host-supplied implementations.

### 17. Verified Result DTO

The canonical verified result is:

```php
final readonly class VerifiedReturnTargetDTO
{
    public function __construct(
        public string $target,
        public int $expiresAt,
    ) {
    }
}
```

No additional fields are proposed at this time.

### 18. Canonical Configuration DTO

The canonical implementation will use:

```php
final readonly class ReturnTargetConfigDTO
{
    public function __construct(
        public string $audience,
        public int $ttlSeconds,
    ) {
    }
}
```

It must not contain a mode, driver, algorithm, signer, encoder, secret, root key, active key, verification keys, rotation state, or custom validator. The DTO describes policy configuration and does not choose architecture. A custom Host implementation is not required to use it.

### 19. Canonical Service Dependencies

The proposed constructor boundary is:

```text
SecureReturnTargetService(
    ReturnTargetConfigDTO $config,
    KeyRotationService $keyRotation,
    HKDFService $hkdf,
    ClockInterface $clock,
    ?ReturnTargetPolicyInterface $policy = null,
)
```

No class is implemented by this proposal.

### 20. Public Failure Semantics

For the canonical implementation:

- `isSafe()` returns `true` only when generic safety and the optional Host policy pass; otherwise it returns `false`.
- `issue()` returns a signed token for an accepted target, `null` for generic or Host-policy rejection, and a typed exception for configuration or crypto setup failure.
- `verify()` returns `VerifiedReturnTargetDTO` for a valid, currently accepted token, `null` for normal untrusted-input or token rejection, and a typed exception for configuration or crypto setup failure.

There is no internal fallback behavior.

### 21. Behavioral Requirements for Host-Supplied Implementations

A custom `ReturnTargetServiceInterface` implementation may change its token format, cryptographic mechanism, key-management mechanism, storage model, internal architecture, stricter validation policy, and TTL duration policy. A custom implementation that claims drop-in compatibility must nevertheless preserve the common behavioral floor:

```text
isSafe()
→ boolean acceptance result

issue()
→ token/string on accepted target
→ null on normal target rejection

verify()
→ VerifiedReturnTargetDTO on accepted valid token
→ null on normal untrusted-token rejection
```

In particular, a successful target remains internal, normal target rejection is `false` or `null`, normal token rejection is `null`, successful verification returns a currently accepted non-expired result, and `expiresAt` is the actual Unix expiration instant in seconds. A Host implementation that changes these meanings is not a drop-in implementation of the same Public Contract. Its protocol, crypto, and storage internals remain Host-owned. Expected malformed or untrusted input must not be used as exception-driven control flow when the implementation claims full interface behavioral compatibility.

### 22. Canonical Generic Target Safety Contract

The canonical implementation accepts only an absolute-path reference with an optional query. Examples include `/`, `/orders`, `/orders/15`, and `/orders/15?tab=payment`.

The original target must first pass raw validation. It must be non-empty, no more than 2048 bytes, begin with exactly one `/`, not begin with `//`, contain no raw backslash, raw fragment marker (`#`), ASCII control character, NUL, DEL, or raw whitespace, and contain only percent escapes where `%` is followed by exactly two hexadecimal digits. It must satisfy valid RFC 3986 absolute-path plus optional-query syntax.

After raw syntax and percent validation succeeds, the canonical implementation creates one security-inspection view by percent-decoding the original representation exactly once. This view exists only for security inspection; it is not a replacement representation and is never recursively decoded. If the single-decoded view contains a newly formed valid percent escape that could require a second decoding pass to reveal URI structure, the target is rejected. For example, a class of inputs such as `%25xx` is rejected when the first decoding produces a second-stage escape ambiguity.

The single-decoded inspection view must be rejected if it contains NUL, an ASCII control character, DEL, raw backslash, raw whitespace, or a fragment marker; no longer represents an internal absolute-path plus optional-query structure; begins with `//`; becomes an external authority or scheme form; or otherwise violates the generic internal-target safety floor. The package rejects these cases rather than normalizing them.

The path is also rejected when either the raw path or the single-decoded inspection path contains a complete segment exactly equal to `.` or `..` after splitting on `/`. This includes encoded forms that become `.` or `..` after the single decoding. The package does not perform dot-segment normalization.

Validation performs no trimming, mutation, normalization, or recursive decoding. The optional Host policy is applied to the original target and, when the single-decoded inspection view differs, to that decoded view as well; every evaluation must allow the target. This prevents a Host route restriction from being bypassed by percent encoding while preserving the restrict-only rule. The canonical service contains no Host-route denylist.

Successful issuance and verification preserve the original representation exactly: the exact original target is issued, stored in the token payload, recovered, and returned. The decoded inspection view is never stored instead of the original and is never returned to the consumer.

After successful `verify()`, the Host must treat the returned target as the validated representation. If the Host percent-decodes, normalizes, resolves, rewrites, or otherwise transforms it before redirect execution, the Host owns re-validation of the transformed value before using it as a redirect target.

### 23. Token Confidentiality Boundary

The canonical token is signed, not encrypted. Its purpose is integrity, authenticity, bounded lifetime, and context isolation—not confidentiality. A return-target token must not carry passwords, credentials, session secrets, API secrets, confidential tokens, sensitive PII, or other confidential information.

### 24. Canonical Token Protocol v1

The exact token form is:

```text
rt1.{kidSegment}.{payloadSegment}.{signatureSegment}
```

There are exactly four non-empty dot-separated segments.

The version is `rt1`; any other version causes `verify()` to return `null`. `kidSegment` is the exact Crypto key ID encoded as unpadded Base64URL. `payloadSegment` is unpadded Base64URL for a JSON payload with exactly these keys and no extras:

```json
{
  "aud": "admin-auth",
  "t": "/orders/15?tab=payment",
  "exp": 1790000000
}
```

The signature input is `rt1.{kidSegment}.{payloadSegment}`. The signature is raw `HMAC-SHA256` output using the 32-byte HKDF-derived signing key, encoded as unpadded Base64URL.

### 25. Base64URL Contract

The canonical implementation uses the RFC 4648 URL-safe alphabet without `=` padding. Verification rejects invalid alphabets, empty required segments, invalid decoding, and non-canonical representations. For each segment, decoding followed by package encoding must reproduce the exact received segment.

### 26. Token Size

The maximum canonical token input is 4096 bytes. A larger input causes `verify()` to return `null`.

### 27. Audience Contract

Audience is mandatory and has length 1–64 bytes. Allowed characters are `A-Z`, `a-z`, `0-9`, `.`, `_`, and `-`. There is no hidden default. A wrong audience causes `verify()` to return `null`. Audience isolation does not make the package an authentication system.

### 28. TTL and Expiry

`ttlSeconds` is mandatory and must be in the range 1–3600 seconds. There is no hidden default. Issuance sets `exp = clock.now.timestamp + ttlSeconds`. Verification rejects when `now >= exp`; there is no grace period or hidden clock skew.

### 29. Canonical Key Rotation Semantics

New tokens use `KeyRotationService::activeEncryptionKey()`. Verification uses `KeyRotationService::decryptionKey(kid)`. The canonical service does not rebuild key-status policy; active/inactive/decryption eligibility remains owned by `maatify/crypto`.

An unknown or non-verifiable key in untrusted input is a normal verification rejection and returns `null`. A broken Host crypto configuration or invariant remains exceptional.

### 30. No Secret Ownership in `ReturnTargetConfigDTO`

`ReturnTargetConfigDTO` must not own secrets, root keys, active keys, verification keys, key status, key stores, or rotation state. These remain owned by the Host and the Crypto boundary.

### 31. Proposed Canonical Runtime Dependencies

If this decision becomes `ACTIVE` and the canonical implementation is built, the proposed Runtime requirements are:

```text
php ^8.4
ext-hash *
maatify/crypto ^1.0
maatify/exceptions ^1.0
maatify/shared-common ^1.0
```

`maatify/crypto v1.0.0` itself requires `ext-openssl` and `ext-sodium`; those must not be declared as direct Return Target requirements unless the Return Target source uses them directly.

### 32. Explicit Non-Goals

The canonical package implementation does not own HTTP, PSR-7, framework integration, middleware, controllers, routers, sessions, cookies, authentication, authorization, 2FA, redirect responses, fallback selection, route-existence checks, database, cache, persistence, one-time-token consumption, OAuth state replacement, POST/body replay, confidential-payload encryption, secret loading, or a generic crypto/signing framework.

### 33. Source Topology

This proposal does not change `DEC-002`. If activated, the package remains `Source Topology: Single Capability`. Future responsibilities such as `Config/`, `DTO/`, `Exception/`, `Service/`, and `Validation/` may be materialized only when supported by real Runtime responsibilities. This Work Unit creates no source files.

### 34. Alternatives Considered

#### A. Host owns all signing

Not preferred as the canonical path because protocol and integrity logic would be repeated across Hosts. It remains permitted only through a custom `ReturnTargetServiceInterface` implementation.

#### B. Pluggable crypto strategies inside `SecureReturnTargetService`

Rejected because guarantees would become conditional and the canonical implementation would suffer abstraction explosion.

#### C. Return Target owns raw key arrays

Rejected because it duplicates key lifecycle management owned by `maatify/crypto`.

#### D. Local HKDF and local key rotation

Rejected because it duplicates stable Maatify shared capabilities.

#### E. Encrypt the canonical token

Not selected for v1 because confidentiality is not an established requirement.

#### F. Database-backed one-time tokens

Not selected because it introduces state and persistence without an established domain need.

#### G. Host route denylist inside the generic validator

Rejected because it couples the generic package to Host-specific routes.

#### H. Fully closed library with no custom implementation path

Not selected because interface-level replacement supports Hosts with materially different requirements without weakening the canonical secure implementation.

## Rationale

The package remains standalone and framework-agnostic so its return-target capability can be reused without coupling it to a Host application's HTTP, routing, authentication, or session model. A canonical implementation is necessary to prevent each Host from duplicating security-sensitive return-target logic. Stable Maatify capabilities—`maatify/crypto`, `maatify/shared-common`, and `maatify/exceptions`—are reused rather than duplicated.

The canonical security protocol remains locked so its guarantees stay understandable and testable. Extensibility is placed at the service boundary instead of turning the canonical implementation into a strategy or plugin framework. A restrict-only Host policy provides Host-specific routing restrictions without weakening generic package safety.

The stateless design is appropriate because a return-target token is not an authentication or authorization credential and there is no established domain need for one-time persistence. The signed, non-encrypted design is intentional because the requirement is integrity, authenticity, and bounded lifetime, not confidentiality. Host ownership of HTTP, authentication, sessions, redirects, and secrets prevents framework and application coupling. Finally, a shared behavioral floor gives `ReturnTargetServiceInterface` one consistent meaning when a Host replaces the implementation.

### 35. Consequences

- The package has a recommended canonical implementation with fixed security semantics.
- The package does not become a strategy or plugin framework.
- Host-specific restrictions are possible through a restrict-only policy.
- Hosts with materially different requirements can replace the service implementation entirely.
- Canonical security guarantees do not automatically extend to custom implementations.
- The first Runtime source must be a real implementation, not a placeholder.
- `FILE-05` may be closed only with real Runtime code and PHPStan configuration.
- Composer dependencies remain unchanged until this decision is activated and Runtime implementation begins.
- The Package Reference becomes the canonical current Public Contract after approval and implementation.
- README, usage examples, CI, and testing must follow the actual implemented behavior.
- Any material boundary change after activation requires formal reopen or supersession.

## Decision Index Relationship

This record must be indexed under `Proposed Decisions`, not `Active Decisions`. `DEC-001` and `DEC-002` remain unchanged. This proposal does not supersede either active decision.

## Activation Boundary

Until an explicit Owner decision changes this record to `ACTIVE`, it does not authorize Runtime implementation, Composer changes, Public Contract publication, or any dependent architecture. Activation requires the corresponding Record and Index status updates together and must follow the repository's Decision Governance Standard.
