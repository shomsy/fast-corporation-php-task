-- Measured rare-actor access path; combined date filters keep using idx_activity_day.
CREATE INDEX IF NOT EXISTS idx_activity_actor_recent
    ON activity_events (actor_label COLLATE NOCASE, occurred_at DESC, id DESC);
