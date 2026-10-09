# PHP Hammer 2026.1.2 - all 87 findings

Source: 2026-10-09 user PhpStorm Inspect Code export, classified by plugin ID net.rentalhost.plugins.php.hammer. This XML is a baseline and has NOT been regenerated after the changes.

FIXED means source adjusted and tests passed, not that a new plugin scan is clean. RETAIN means individually reviewed, deliberate unchanged behavior. MOI is the original authoritative formatter. No global inspections were disabled.

| # | Inspection | Severity | Source | Disposition | Concrete reasoning |
|---:|---|---|---|---|---|
| 1 | ClassnameLiteralInspection | WEAK WARNING | tests/Architecture/PublicSurfaceTest.php:171 | RETAIN - fixture | Literal class-name strings are explicit fixtures (some retired). A ::class rewrite is stylistic, provides no correctness benefit, and adds class syntax to a test of name exports. |
| 2 | ClassnameLiteralInspection | WEAK WARNING | tests/Architecture/PublicSurfaceTest.php:172 | RETAIN - fixture | Literal class-name strings are explicit fixtures (some retired). A ::class rewrite is stylistic, provides no correctness benefit, and adds class syntax to a test of name exports. |
| 3 | ClassnameLiteralInspection | WEAK WARNING | tests/Architecture/PublicSurfaceTest.php:174 | RETAIN - fixture | Literal class-name strings are explicit fixtures (some retired). A ::class rewrite is stylistic, provides no correctness benefit, and adds class syntax to a test of name exports. |
| 4 | ClassnameLiteralInspection | WEAK WARNING | tests/Architecture/PublicSurfaceTest.php:175 | RETAIN - fixture | Literal class-name strings are explicit fixtures (some retired). A ::class rewrite is stylistic, provides no correctness benefit, and adds class syntax to a test of name exports. |
| 5 | ClassnameLiteralInspection | WEAK WARNING | tests/Architecture/PublicSurfaceTest.php:230 | RETAIN - fixture | Literal class-name strings are explicit fixtures (some retired). A ::class rewrite is stylistic, provides no correctness benefit, and adds class syntax to a test of name exports. |
| 6 | ClassnameLiteralInspection | WEAK WARNING | tests/Architecture/PublicSurfaceTest.php:231 | RETAIN - fixture | Literal class-name strings are explicit fixtures (some retired). A ::class rewrite is stylistic, provides no correctness benefit, and adds class syntax to a test of name exports. |
| 7 | CompactReplacementInspection | WEAK WARNING | bootstrap/Http/AccountApi.php:111 | RETAIN - explicit keys | Explicit keys preserve API/SQL/fixture field names separately from local variable names; compact couples naming to schema. |
| 8 | CompactReplacementInspection | WEAK WARNING | bootstrap/Http/AccountApi.php:124 | RETAIN - explicit keys | Explicit keys preserve API/SQL/fixture field names separately from local variable names; compact couples naming to schema. |
| 9 | CompactReplacementInspection | WEAK WARNING | bootstrap/Http/ActivityApi.php:111 | RETAIN - explicit keys | Explicit keys preserve API/SQL/fixture field names separately from local variable names; compact couples naming to schema. |
| 10 | CompactReplacementInspection | WEAK WARNING | bootstrap/Http/ActivityApi.php:145 | RETAIN - explicit keys | Explicit keys preserve API/SQL/fixture field names separately from local variable names; compact couples naming to schema. |
| 11 | CompactReplacementInspection | WEAK WARNING | bootstrap/Http/CowOfferApi.php:52 | RETAIN - explicit keys | Explicit keys preserve API/SQL/fixture field names separately from local variable names; compact couples naming to schema. |
| 12 | CompactReplacementInspection | WEAK WARNING | src/Accounts/Accounts.php:165 | RETAIN - explicit keys | Explicit keys preserve API/SQL/fixture field names separately from local variable names; compact couples naming to schema. |
| 13 | CompactReplacementInspection | WEAK WARNING | src/Accounts/Accounts.php:236 | RETAIN - explicit keys | Explicit keys preserve API/SQL/fixture field names separately from local variable names; compact couples naming to schema. |
| 14 | CompactReplacementInspection | WEAK WARNING | src/ActivityTracking/DailyReports.php:50 | RETAIN - explicit keys | Explicit keys preserve API/SQL/fixture field names separately from local variable names; compact couples naming to schema. |
| 15 | CompactReplacementInspection | WEAK WARNING | src/ActivityTracking/Statistics.php:152 | RETAIN - explicit keys | Explicit keys preserve API/SQL/fixture field names separately from local variable names; compact couples naming to schema. |
| 16 | CompactReplacementInspection | WEAK WARNING | tests/Flow/HttpFlowTest.php:295 | RETAIN - explicit keys | Explicit keys preserve API/SQL/fixture field names separately from local variable names; compact couples naming to schema. |
| 17 | DebugFunctionUsageInspection | WARNING | infrastructure/Diagnostics/SecurityEvents.php:53 | RETAIN - audit | Allowlisted error_log records minimal structured server security incidents, not ad-hoc debug output or client input. |
| 18 | IfSimplificationOrInspection | WEAK WARNING | src/ActivityTracking/Statistics.php:260 | RETAIN - safety guard | Cursor syntax and length validation must precede HMAC validation using the matched groups. |
| 19 | NullableArgumentPassedInspection | WARNING | src/Accounts/UsernameTaken.php:25 | RETAIN - signature | DomainException::__construct named previous is ?Throwable; regression proves null and preserved exception. |
| 20 | NullableArgumentPassedInspection | WARNING | src/Downloads/DownloadFile.php:91 | FIXED - digest | Local nullable SHA-256 digest is narrowed before hash_equals; both pin-match and pin-reject regression tested. |
| 21 | NullableTypeFormatInspection | WEAK WARNING | bootstrap/Http/ActivityApi.php:122 | MOI - style | PHP ?T is valid and is the owners authoritative MOI format; T\|null is a competing plugin preference. |
| 22 | NullableTypeFormatInspection | WEAK WARNING | bootstrap/Http/ActivityPages.php:119 | MOI - style | PHP ?T is valid and is the owners authoritative MOI format; T\|null is a competing plugin preference. |
| 23 | NullableTypeFormatInspection | WEAK WARNING | bootstrap/Http/ActivityPages.php:138 | MOI - style | PHP ?T is valid and is the owners authoritative MOI format; T\|null is a competing plugin preference. |
| 24 | NullableTypeFormatInspection | WEAK WARNING | bootstrap/Http/ActivityPages.php:157 | MOI - style | PHP ?T is valid and is the owners authoritative MOI format; T\|null is a competing plugin preference. |
| 25 | NullableTypeFormatInspection | WEAK WARNING | bootstrap/Http/CowOfferPages.php:44 | MOI - style | PHP ?T is valid and is the owners authoritative MOI format; T\|null is a competing plugin preference. |
| 26 | NullableTypeFormatInspection | WEAK WARNING | bootstrap/Http/DownloadPages.php:45 | MOI - style | PHP ?T is valid and is the owners authoritative MOI format; T\|null is a competing plugin preference. |
| 27 | NullableTypeFormatInspection | WEAK WARNING | bootstrap/Http/HealthProbes.php:30 | MOI - style | PHP ?T is valid and is the owners authoritative MOI format; T\|null is a competing plugin preference. |
| 28 | NullableTypeFormatInspection | WEAK WARNING | bootstrap/routes.php:81 | MOI - style | PHP ?T is valid and is the owners authoritative MOI format; T\|null is a competing plugin preference. |
| 29 | NullableTypeFormatInspection | WEAK WARNING | infrastructure/Diagnostics/SecurityEvents.php:26 | MOI - style | PHP ?T is valid and is the owners authoritative MOI format; T\|null is a competing plugin preference. |
| 30 | NullableTypeFormatInspection | WEAK WARNING | infrastructure/Session/PhpSession.php:26 | MOI - style | PHP ?T is valid and is the owners authoritative MOI format; T\|null is a competing plugin preference. |
| 31 | NullableTypeFormatInspection | WEAK WARNING | runtime/Http/Request.php:35 | MOI - style | PHP ?T is valid and is the owners authoritative MOI format; T\|null is a competing plugin preference. |
| 32 | NullableTypeFormatInspection | WEAK WARNING | runtime/Http/Router.php:31 | MOI - style | PHP ?T is valid and is the owners authoritative MOI format; T\|null is a competing plugin preference. |
| 33 | NullableTypeFormatInspection | WEAK WARNING | src/Accounts/Accounts.php:156 | MOI - style | PHP ?T is valid and is the owners authoritative MOI format; T\|null is a competing plugin preference. |
| 34 | NullableTypeFormatInspection | WEAK WARNING | src/Accounts/Accounts.php:231 | MOI - style | PHP ?T is valid and is the owners authoritative MOI format; T\|null is a competing plugin preference. |
| 35 | NullableTypeFormatInspection | WEAK WARNING | src/Accounts/CurrentAccount.php:27 | MOI - style | PHP ?T is valid and is the owners authoritative MOI format; T\|null is a competing plugin preference. |
| 36 | NullableTypeFormatInspection | WEAK WARNING | src/Accounts/SessionIdentity.php:60 | MOI - style | PHP ?T is valid and is the owners authoritative MOI format; T\|null is a competing plugin preference. |
| 37 | NullableTypeFormatInspection | WEAK WARNING | src/Accounts/SessionIdentity.php:97 | MOI - style | PHP ?T is valid and is the owners authoritative MOI format; T\|null is a competing plugin preference. |
| 38 | NullableTypeFormatInspection | WEAK WARNING | src/Accounts/UsernameTaken.php:23 | MOI - style | PHP ?T is valid and is the owners authoritative MOI format; T\|null is a competing plugin preference. |
| 39 | NullableTypeFormatInspection | WEAK WARNING | src/ActivityTracking/ActivityLog.php:61 | MOI - style | PHP ?T is valid and is the owners authoritative MOI format; T\|null is a competing plugin preference. |
| 40 | NullableTypeFormatInspection | WEAK WARNING | src/ActivityTracking/DailyReports.php:47 | MOI - style | PHP ?T is valid and is the owners authoritative MOI format; T\|null is a competing plugin preference. |
| 41 | NullableTypeFormatInspection | WEAK WARNING | src/ActivityTracking/DailyReports.php:47 | MOI - style | PHP ?T is valid and is the owners authoritative MOI format; T\|null is a competing plugin preference. |
| 42 | NullableTypeFormatInspection | WEAK WARNING | src/ActivityTracking/Statistics.php:49 | MOI - style | PHP ?T is valid and is the owners authoritative MOI format; T\|null is a competing plugin preference. |
| 43 | NullableTypeFormatInspection | WEAK WARNING | src/ActivityTracking/Statistics.php:49 | MOI - style | PHP ?T is valid and is the owners authoritative MOI format; T\|null is a competing plugin preference. |
| 44 | NullableTypeFormatInspection | WEAK WARNING | src/ActivityTracking/Statistics.php:49 | MOI - style | PHP ?T is valid and is the owners authoritative MOI format; T\|null is a competing plugin preference. |
| 45 | NullableTypeFormatInspection | WEAK WARNING | src/ActivityTracking/Statistics.php:66 | MOI - style | PHP ?T is valid and is the owners authoritative MOI format; T\|null is a competing plugin preference. |
| 46 | NullableTypeFormatInspection | WEAK WARNING | src/ActivityTracking/Statistics.php:66 | MOI - style | PHP ?T is valid and is the owners authoritative MOI format; T\|null is a competing plugin preference. |
| 47 | NullableTypeFormatInspection | WEAK WARNING | src/ActivityTracking/Statistics.php:66 | MOI - style | PHP ?T is valid and is the owners authoritative MOI format; T\|null is a competing plugin preference. |
| 48 | NullableTypeFormatInspection | WEAK WARNING | src/ActivityTracking/Statistics.php:116 | MOI - style | PHP ?T is valid and is the owners authoritative MOI format; T\|null is a competing plugin preference. |
| 49 | NullableTypeFormatInspection | WEAK WARNING | src/ActivityTracking/Statistics.php:116 | MOI - style | PHP ?T is valid and is the owners authoritative MOI format; T\|null is a competing plugin preference. |
| 50 | NullableTypeFormatInspection | WEAK WARNING | src/ActivityTracking/Statistics.php:116 | MOI - style | PHP ?T is valid and is the owners authoritative MOI format; T\|null is a competing plugin preference. |
| 51 | NullableTypeFormatInspection | WEAK WARNING | src/ActivityTracking/Statistics.php:166 | MOI - style | PHP ?T is valid and is the owners authoritative MOI format; T\|null is a competing plugin preference. |
| 52 | NullableTypeFormatInspection | WEAK WARNING | src/ActivityTracking/Statistics.php:166 | MOI - style | PHP ?T is valid and is the owners authoritative MOI format; T\|null is a competing plugin preference. |
| 53 | NullableTypeFormatInspection | WEAK WARNING | src/ActivityTracking/Statistics.php:166 | MOI - style | PHP ?T is valid and is the owners authoritative MOI format; T\|null is a competing plugin preference. |
| 54 | NullableTypeFormatInspection | WEAK WARNING | src/ActivityTracking/Statistics.php:166 | MOI - style | PHP ?T is valid and is the owners authoritative MOI format; T\|null is a competing plugin preference. |
| 55 | NullableTypeFormatInspection | WEAK WARNING | src/Downloads/DownloadFile.php:32 | MOI - style | PHP ?T is valid and is the owners authoritative MOI format; T\|null is a competing plugin preference. |
| 56 | NullableTypeFormatInspection | WEAK WARNING | tests/Runtime/HttpProtocolTest.php:68 | MOI - style | PHP ?T is valid and is the owners authoritative MOI format; T\|null is a competing plugin preference. |
| 57 | NullableTypeFormatInspection | WEAK WARNING | tests/Runtime/RouterTest.php:122 | MOI - style | PHP ?T is valid and is the owners authoritative MOI format; T\|null is a competing plugin preference. |
| 58 | NullableTypeFormatInspection | WEAK WARNING | tools/check-phpdocs.php:51 | MOI - style | PHP ?T is valid and is the owners authoritative MOI format; T\|null is a competing plugin preference. |
| 59 | OverrideMissingInspection | WEAK WARNING | tests/Accounts/SessionCompensationTest.php:54 | FIXED - Override | Added PHP 8.3 Override attribute to inherited method; targeted regression checks pass. |
| 60 | OverrideMissingInspection | WEAK WARNING | tests/Infrastructure/MigratorIntegrityTest.php:33 | FIXED - Override | Added PHP 8.3 Override attribute to inherited method; targeted regression checks pass. |
| 61 | OverrideMissingInspection | WEAK WARNING | tests/Infrastructure/MigratorIntegrityTest.php:45 | FIXED - Override | Added PHP 8.3 Override attribute to inherited method; targeted regression checks pass. |
| 62 | OverrideMissingInspection | WEAK WARNING | tests/Infrastructure/TransactionFailureTest.php:62 | FIXED - Override | Added PHP 8.3 Override attribute to inherited method; targeted regression checks pass. |
| 63 | OverrideMissingInspection | WEAK WARNING | tests/Infrastructure/TransactionFailureTest.php:107 | FIXED - Override | Added PHP 8.3 Override attribute to inherited method; targeted regression checks pass. |
| 64 | OverrideMissingInspection | WEAK WARNING | tests/Infrastructure/TransactionFailureTest.php:117 | FIXED - Override | Added PHP 8.3 Override attribute to inherited method; targeted regression checks pass. |
| 65 | ParameterDefaultsNullInspection | WEAK WARNING | src/ActivityTracking/Statistics.php:166 | RETAIN - fail closed | Nonnullable cursor-signing key defaults to empty and fails closed; changing to nullable weakens the public contract. |
| 66 | ReturnTernaryReplacementInspection | WEAK WARNING | bootstrap/Http/ActivityPages.php:126 | RETAIN - readability | Simple ternary expresses two short return branches; expanding to if does not improve correctness. |
| 67 | ReturnTernaryReplacementInspection | WEAK WARNING | bootstrap/routes.php:86 | RETAIN - readability | Simple ternary expresses two short return branches; expanding to if does not improve correctness. |
| 68 | ReturnTernaryReplacementInspection | WEAK WARNING | runtime/Http/Router.php:112 | RETAIN - readability | Simple ternary expresses two short return branches; expanding to if does not improve correctness. |
| 69 | ReturnTernaryReplacementInspection | WEAK WARNING | runtime/Http/Router.php:118 | RETAIN - readability | Simple ternary expresses two short return branches; expanding to if does not improve correctness. |
| 70 | ReturnTernaryReplacementInspection | WEAK WARNING | src/Accounts/Accounts.php:240 | RETAIN - readability | Simple ternary expresses two short return branches; expanding to if does not improve correctness. |
| 71 | SensitiveParameterInspection | WARNING | bootstrap/Http/AccountApi.php:122 | RETAIN - nonsecret | code parameter is a public HTTP JSON error identifier, not a secret; SensitiveParameter would mislabel it. |
| 72 | SensitiveParameterInspection | WARNING | bootstrap/Http/ActivityApi.php:143 | RETAIN - nonsecret | code parameter is a public HTTP JSON error identifier, not a secret; SensitiveParameter would mislabel it. |
| 73 | UnaryOperatorFormatInspection | WEAK WARNING | bootstrap/Http/HealthProbes.php:129 | MOI - style | Prefix increment is valid PHP and established MOI practice; postfix adds no value. |
| 74 | UnaryOperatorFormatInspection | WEAK WARNING | tests/ActivityTracking/ActivityApiTest.php:57 | MOI - style | Prefix increment is valid PHP and established MOI practice; postfix adds no value. |
| 75 | UnaryOperatorFormatInspection | WEAK WARNING | tests/ActivityTracking/StablePaginationTest.php:106 | MOI - style | Prefix increment is valid PHP and established MOI practice; postfix adds no value. |
| 76 | UnaryOperatorFormatInspection | WEAK WARNING | tests/ActivityTracking/StatisticsReportsTest.php:219 | MOI - style | Prefix increment is valid PHP and established MOI practice; postfix adds no value. |
| 77 | UnaryOperatorFormatInspection | WEAK WARNING | tests/ActivityTracking/StatisticsReportsTest.php:257 | MOI - style | Prefix increment is valid PHP and established MOI practice; postfix adds no value. |
| 78 | UnaryOperatorFormatInspection | WEAK WARNING | tests/ActivityTracking/StatisticsReportsTest.php:308 | MOI - style | Prefix increment is valid PHP and established MOI practice; postfix adds no value. |
| 79 | UnaryOperatorFormatInspection | WEAK WARNING | tests/ActivityTracking/StatisticsReportsTest.php:329 | MOI - style | Prefix increment is valid PHP and established MOI practice; postfix adds no value. |
| 80 | UnaryOperatorFormatInspection | WEAK WARNING | tests/CowOffer/CowOfferApiTest.php:54 | MOI - style | Prefix increment is valid PHP and established MOI practice; postfix adds no value. |
| 81 | UnaryOperatorFormatInspection | WEAK WARNING | tests/Flow/HttpFlowTest.php:178 | MOI - style | Prefix increment is valid PHP and established MOI practice; postfix adds no value. |
| 82 | UnaryOperatorFormatInspection | WEAK WARNING | tests/Flow/HttpFlowTest.php:189 | MOI - style | Prefix increment is valid PHP and established MOI practice; postfix adds no value. |
| 83 | UnaryOperatorFormatInspection | WEAK WARNING | tests/Flow/HttpFlowTest.php:221 | MOI - style | Prefix increment is valid PHP and established MOI practice; postfix adds no value. |
| 84 | UnaryOperatorFormatInspection | WEAK WARNING | tests/Flow/HttpFlowTest.php:249 | MOI - style | Prefix increment is valid PHP and established MOI practice; postfix adds no value. |
| 85 | UnaryOperatorFormatInspection | WEAK WARNING | tests/Flow/HttpFlowTest.php:282 | MOI - style | Prefix increment is valid PHP and established MOI practice; postfix adds no value. |
| 86 | UnaryOperatorFormatInspection | WEAK WARNING | tests/Runtime/HttpProtocolTest.php:63 | MOI - style | Prefix increment is valid PHP and established MOI practice; postfix adds no value. |
| 87 | UnaryOperatorFormatInspection | WEAK WARNING | tests/Runtime/HttpProtocolTest.php:91 | MOI - style | Prefix increment is valid PHP and established MOI practice; postfix adds no value. |

## Totals

87 findings from 11 populated PHP Hammer inspections (of 57 defined).
- 7 source-level addressed (6 overrides; one nullable hash narrowing).
- 53 purely MOI formatting suggestions (?T and ++var).
- 27 reviewed and intentionally retained (including one tested DomainException signature).

## Verification

Tests and architecture gates are recorded separately after this document was generated. The original export is not rewritten. Re-run full PhpStorm Inspect Code with the same scope to measure current plugin warnings; IDE per-file calls that time out do not establish zero findings.
Public JSON error identifiers, named-argument contracts, security logging and MOI remain unchanged.
