#!/usr/bin/env python3
"""Fail closed when canonical packet IDs and durable progress counts drift."""
from collections import Counter
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
PLAN = ROOT / 'docs/roadmap/CANONICAL-REMEDIATION-PLAN.md'
LEDGER = ROOT / 'docs/roadmap/EXECUTION-PROGRESS.md'

def main():
    plan = PLAN.read_text(encoding='utf-8')
    ledger = LEDGER.read_text(encoding='utf-8')
    planned = re.findall(r'^### (C-\d{2})\b', plan, flags=re.MULTILINE)
    packets = re.findall(r'^- \[([ x])\] \*\*(C-\d{2})\*\*', ledger, flags=re.MULTILINE)
    expected = {f'C-{number:02d}' for number in range(1, 49)}
    plan_counts = Counter(planned)
    ledger_counts = Counter(item for _, item in packets)
    if set(plan_counts) != expected or any(count != 1 for count in plan_counts.values()):
        raise AssertionError('Plan does not contain exactly C-01 through C-48 once')
    if set(ledger_counts) != expected or any(count != 1 for count in ledger_counts.values()):
        raise AssertionError('Ledger does not contain exactly C-01 through C-48 once')
    done = sum(mark == 'x' for mark, _ in packets)
    open_count = len(packets) - done
    header = re.search(r'^## Canonical packet status — (\d+) DONE / (\d+) TOTAL \((\d+) OPEN\)', ledger, flags=re.MULTILINE)
    if not header or tuple(map(int, header.groups())) != (done, 48, open_count):
        raise AssertionError('Ledger status header and checkboxes disagree')
    if f'{done}/48 complete.' not in ledger:
        raise AssertionError('Ledger overview and checkboxes disagree')
    print(f'EXECUTION LEDGER: PASS; {done}/48 DONE, {open_count} OPEN, all IDs unique')

if __name__ == '__main__':
    main()
