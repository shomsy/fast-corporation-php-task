# Complexity Ledger

Initial state: no consequential non-baseline mechanism has been accepted.

| Mechanism | Pressure | Evidence | Simpler option considered | Recurring cost | Validation | Removal trigger |
|---|---|---|---|---|---|---|
| Clock seam | Deterministic event timestamps/report tests | Every tracked event is time-based | Direct wall-clock calls | One tiny contract + implementation | Fixed-clock tests | Time no longer affects behavior/tests |
| Deptrac | Objective owner dependency rules should remain true | Two explicit business owners | Manual review only | CI/tool config | Forbidden-dependency fixture/CI | Equivalent check replaces it or boundary disappears |
| Table ownership check | Prevent cross-owner storage coupling | Two owners with separate authoritative facts | Manual SQL review | Small check script/test | CI | One owner remains or stronger DB isolation replaces it |
