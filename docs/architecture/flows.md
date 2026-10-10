# Flow Views

Flows are explanatory/testability views, not required Flow classes.

| Flow             | Behavior    | Owner            | Uses                                           |
|------------------|-------------|------------------|------------------------------------------------|
| Get access       | Register    | Accounts         | Accounts + ActivityTracking registration event |
| Get access       | Login       | Accounts         | Accounts + ActivityTracking login event        |
| Get access       | Logout      | Accounts         | ActivityTracking logout event + session revoke |
| Use Page A       | View Page A | CowOffer         | ActivityTracking page view                     |
| Use Page A       | Buy Cow     | CowOffer         | ActivityTracking button click                  |
| Use Page B       | View Page B | Downloads        | ActivityTracking page view                     |
| Use Page B       | Download    | Downloads        | ActivityTracking button click + fixed artifact |
| Analyse activity | Statistics  | ActivityTracking | event log                                      |
| Analyse activity | Reports     | ActivityTracking | daily aggregation                              |

## Knowledge / Capability / Contract

| Owner            | Knowledge                                                       | Capabilities                                   | Contract                              |
|------------------|-----------------------------------------------------------------|------------------------------------------------|---------------------------------------|
| Accounts         | account, credential, role, authenticated identity/access policy | register, authenticate, sign in/out, authorize | identity/label surface where required |
| ActivityTracking | event vocabulary/history, filter/report semantics               | record, filter, aggregate                      | narrow recording vocabulary/surface   |
| CowOffer         | Page A and Buy Cow behavior                                     | view Page A, accept Buy Cow                    | entry points only                     |
| Downloads        | Page B and artifact delivery behavior                           | view Page B, download artifact                 | entry points only                     |

These are semantic views, not directory layers.

## Accounts responsibility groups

`Access/SessionIdentity` owns session establishment and revocation; `Access/CurrentAccount` and the access guards
project or require authoritative identity. `Credentials/AccountCredentials` holds one username/password rule set,
while `Credentials/BcryptPasswords` realizes its hash mechanism. `Register` and `Login` call these owner-internal
behaviors; `AccountLifecycle` coordinates accepted activity. The groups describe existing knowledge and do not add
mandatory execution stages, public interfaces or separate owners.
