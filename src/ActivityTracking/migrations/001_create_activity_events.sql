CREATE TABLE activity_events
(
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    actor_id    INTEGER NOT NULL,
    actor_label TEXT    NOT NULL,
    action      TEXT    NOT NULL,
    target      TEXT NULL,
    occurred_at TEXT    NOT NULL,
    CHECK (
        (action IN ('registration', 'login', 'logout') AND target IS NULL)
            OR (action = 'view-page' AND target IN ('page-a', 'page-b'))
            OR (action = 'button-click' AND target IN ('buy-cow', 'download'))
        )
);
