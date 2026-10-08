-- Indices justified by EXPLAIN QUERY PLAN on bounded activity queries.
CREATE INDEX IF NOT EXISTS idx_activity_recent
    ON activity_events (occurred_at DESC, id DESC);
CREATE INDEX IF NOT EXISTS idx_activity_day
    ON activity_events (substr(occurred_at, 1, 10) DESC);
