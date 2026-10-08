# PHP Developer Technical Task

## Brief

Build a simple web application that tracks user activity.

Required features:

- registration
- login
- logout
- Page A with a **Buy a cow** button; after a successful click the button disappears in the current UI state and the UI
  displays `thankYou`
- Page B with a **Download** button; clicking it starts a download of an executable file
- Statistics page
- Reports page

Roles:

- **user**: authenticate and view/use the normal pages
- **admin**: user rights plus access to activity statistics

Statistics must show an event table with filters for date, user and action.

Tracked actions: `registration`, `login`, `logout`, `view-page`, `button-click`.

Reports must show both a graph and a table with daily counts for Page A views, Page B views, Buy a cow clicks and
Download clicks.

## Recruiter clarification

The implementation approach is intentionally left to the candidate. Vanilla PHP is recommended and no PHP version was
mandated in the task.

## Working assumptions

- Use modern Vanilla PHP, Composer and PDO.
- SQLite is the default database for zero-friction reviewer setup.
- Reports are admin-only because they expose the same activity analytics domain as Statistics.
- `view-page` is recorded for Page A and Page B.
- Only successful registration/login/logout actions are recorded.
- Registration does not automatically log the user in.
- Buy-a-cow state is UI-local; reloading Page A shows the button again because persistent purchase state is not required
  by the task.
- The downloadable executable is a harmless placeholder file supplied by the project.
- Timestamps are stored in UTC and formatted for display at the edge.
