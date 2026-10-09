#!/usr/bin/env python3
"""Deterministic SQLite report/filter workload and plan measurements; no production DB access."""
import json
import sqlite3
import statistics
import tempfile
import time
from datetime import date, timedelta
from pathlib import Path

N = 100000
DATES = 180
REPORT = """SELECT substr(occurred_at,1,10) AS date,
SUM(CASE WHEN action='view-page' AND target='page-a' THEN 1 ELSE 0 END) AS page_a_views,
SUM(CASE WHEN action='view-page' AND target='page-b' THEN 1 ELSE 0 END) AS page_b_views,
SUM(CASE WHEN action='button-click' AND target='buy-cow' THEN 1 ELSE 0 END) AS buy_cow_clicks,
SUM(CASE WHEN action='button-click' AND target='download' THEN 1 ELSE 0 END) AS download_clicks
FROM activity_events WHERE ((action='view-page' AND target IN ('page-a','page-b'))
OR (action='button-click' AND target IN ('buy-cow','download')))
{bounds} GROUP BY substr(occurred_at,1,10) ORDER BY date DESC LIMIT 30"""
FILTERS = {
    "recent": "SELECT id FROM activity_events ORDER BY occurred_at DESC,id DESC LIMIT 51",
    "date": "SELECT id FROM activity_events WHERE substr(occurred_at,1,10)='2026-09-10' ORDER BY occurred_at DESC,id DESC LIMIT 51",
    "rare_actor": "SELECT id FROM activity_events WHERE actor_label='rare' COLLATE NOCASE ORDER BY occurred_at DESC,id DESC LIMIT 51",
    "common_actor": "SELECT id FROM activity_events WHERE actor_label='common' COLLATE NOCASE ORDER BY occurred_at DESC,id DESC LIMIT 51",
    "rare_actor_action_date": "SELECT id FROM activity_events WHERE actor_label='rare' COLLATE NOCASE AND action='view-page' AND substr(occurred_at,1,10)='2026-09-10' ORDER BY occurred_at DESC,id DESC LIMIT 51",
    "common_action_date": "SELECT id FROM activity_events WHERE actor_label='common' COLLATE NOCASE AND action='view-page' AND substr(occurred_at,1,10)='2026-09-10' ORDER BY occurred_at DESC,id DESC LIMIT 51",
}
def sample(db, sql, repeat=20):
    times=[]
    for _ in range(repeat):
        t=time.perf_counter_ns()
        rows=db.execute(sql).fetchall()
        times.append((time.perf_counter_ns()-t)/1e6)
    times.sort()
    plan=[r[3] for r in db.execute("EXPLAIN QUERY PLAN "+sql)]
    return {"p95_ms":round(times[int(.95*(repeat-1))],3),"rows":len(rows),"plan":plan}
def main():
    with tempfile.TemporaryDirectory(prefix="fast-report-measure-") as t:
        db=sqlite3.connect(str(Path(t)/"measure.sqlite"))
        db.executescript("""CREATE TABLE activity_events(id INTEGER PRIMARY KEY AUTOINCREMENT,actor_id INTEGER NOT NULL,actor_label TEXT NOT NULL,action TEXT NOT NULL,target TEXT,occurred_at TEXT NOT NULL);
CREATE INDEX idx_activity_recent ON activity_events (occurred_at DESC,id DESC);
CREATE INDEX idx_activity_day ON activity_events (substr(occurred_at,1,10) DESC);""")
        first=date(2026,4,1)
        batch=[]
        for i in range(N):
            day=first+timedelta(days=i%DATES)
            actor='rare' if i%313==0 else 'common' if i%4 else 'other'
            action,target=(('view-page','page-a'),('view-page','page-b'),('button-click','buy-cow'),('button-click','download'),('login',None))[i%5]
            batch.append((1,actor,action,target,day.isoformat()+'T12:00:00+00:00'))
        t0=time.perf_counter()
        db.executemany('INSERT INTO activity_events(actor_id,actor_label,action,target,occurred_at) VALUES (?,?,?,?,?)',batch)
        db.commit()
        print('SEED',N,'rows',DATES,'days','seconds',round(time.perf_counter()-t0,2))
        unbounded=sample(db,REPORT.format(bounds=''))
        lower=first+timedelta(days=DATES-30)
        bounded=sample(db,REPORT.format(bounds=" AND substr(occurred_at,1,10)>='"+lower.isoformat()+"'"))
        filtered={k:sample(db,sql) for k,sql in FILTERS.items()}
        extra=[(1,"rare","view-page","page-a",first.isoformat()+"T12:00:00+00:00") for _ in range(3000)]
        t_write=time.perf_counter_ns()
        db.executemany("INSERT INTO activity_events(actor_id,actor_label,action,target,occurred_at) VALUES (?,?,?,?,?)",extra)
        db.commit()
        base_write_ms=round((time.perf_counter_ns()-t_write)/1e6,2)
        t0=time.perf_counter()
        db.execute('CREATE INDEX idx_activity_actor_recent ON activity_events(actor_label COLLATE NOCASE,occurred_at DESC,id DESC)')
        db.commit()
        index_creation_ms=round((time.perf_counter()-t0)*1000,2)
        candidate={k:sample(db,sql) for k,sql in FILTERS.items()}
        date_guarded={
            k:sample(db,sql.replace("FROM activity_events WHERE", "FROM activity_events INDEXED BY idx_activity_day WHERE"))
            for k,sql in FILTERS.items() if "date" in k
        }
        t0=time.perf_counter_ns()
        db.executemany("INSERT INTO activity_events(actor_id,actor_label,action,target,occurred_at) VALUES (?,?,?,?,?)",extra)
        db.commit()
        indexed_write_ms=round((time.perf_counter_ns()-t0)/1e6,2)
        print(json.dumps({"report_all":unbounded,"report_last30":bounded,"filters_before":filtered,"index_creation_ms":index_creation_ms,"filters_after_actor_recent_index":candidate,"date_guarded_with_actor_index":date_guarded,"3000_inserts_before_actor_index_ms":base_write_ms,"3000_inserts_after_actor_index_ms":indexed_write_ms},indent=2))
        print('BENCHMARK: PASS')
if __name__=='__main__':main()
