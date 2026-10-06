# Boundary Register

| Boundary | Type | Protects | Owner | Contract surface | Enforced by | Evidence | Revisit |
|---|---|---|---|---|---|---|---|
| Accounts -> Activity | Logical | Identity/security knowledge remains private while accepted account actions are recorded | Accounts / Activity | Stable activity-recording behavior and actor/action data | Planned Deptrac + tests + no cross-table reads | NOT VERIFIED | Contract becomes broad/chatty or semantics move across it |
| Business -> HTTP runtime | Logical technical edge | Transport/session/response mechanics stay out of owner knowledge | Runtime edge | Route registration and small runtime types | Planned dependency rules | NOT VERIFIED | HTTP edge becomes materially complex or framework volatility appears |
| Owner -> own authoritative tables | Logical data ownership | One authority for identity facts and activity facts | Accounts / Activity | Owner-local persistence | Planned table ownership check | NOT VERIFIED | Shared writes become necessary or storage ownership changes |
