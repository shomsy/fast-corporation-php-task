-- SQLite CHECK accepts NULL results. Guard future tracked events without rewriting historical rows.
CREATE TRIGGER activity_require_target_on_insert
    BEFORE INSERT ON activity_events
    WHEN NEW.action IN ('view-page', 'button-click') AND NEW.target IS NULL
BEGIN
    SELECT RAISE(ABORT, 'Tracked activity requires a target.');
END;

CREATE TRIGGER activity_require_target_on_update
    BEFORE UPDATE OF action, target ON activity_events
    WHEN NEW.action IN ('view-page', 'button-click') AND NEW.target IS NULL
BEGIN
    SELECT RAISE(ABORT, 'Tracked activity requires a target.');
END;
