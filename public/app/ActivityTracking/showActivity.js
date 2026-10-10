import { drawChart } from './drawChart.js';

const actions = [
  ['registration', 'Registration'],
  ['login', 'Sign in'],
  ['logout', 'Sign out'],
  ['view-page', 'Page view'],
  ['button-click', 'Button click'],
];
const actionLabels = Object.fromEntries(actions);
const targetLabels = {
  'page-a': 'Page A', 'page-b': 'Page B',
  'buy-cow': 'Buy a cow', download: 'Download',
};

function link(page, href, text) {
  const node = page.createElement('a');
  node.href = href;
  node.textContent = text;
  return node;
}

function filters(page, path, params, names) {
  const form = page.createElement('form');
  form.action = path;
  form.method = 'get';
  for (const [name, caption] of names) {
    const label = page.createElement('label');
    label.textContent = caption;
    const control = page.createElement(name === 'action' ? 'select' : 'input');
    control.name = name;
    if (name === 'action') {
      for (const [value, display] of [['', 'All actions'], ...actions]) {
        const option = page.createElement('option');
        option.value = value;
        option.textContent = display;
        if (params.get(name) === value) option.selected = true;
        control.append(option);
      }
    } else {
      control.value = params.get(name) || '';
      control.maxLength = name === 'date' || name === 'from' || name === 'to' ? 10 : 128;
      control.placeholder = name === 'user' ? 'Username (exact match)' : 'YYYY-MM-DD';
    }
    label.append(control);
    form.append(label);
  }
  const submit = page.createElement('button');
  submit.type = 'submit';
  submit.textContent = 'Apply filters';
  form.append(submit, link(page, path, 'Clear filters'));
  return form;
}

function table(page, rows, columns) {
  const wrap = page.createElement('div');
  wrap.className = 'table-wrap';
  const grid = page.createElement('table');
  const head = page.createElement('thead');
  const top = page.createElement('tr');
  for (const [, title] of columns) {
    const th = page.createElement('th');
    th.scope = 'col';
    th.textContent = title;
    top.append(th);
  }
  head.append(top);
  const body = page.createElement('tbody');
  for (const record of rows) {
    const tr = page.createElement('tr');
    for (const [key] of columns) {
      const td = page.createElement('td');
      const value = record[key];
      td.textContent = value === null || value === undefined ? '—'
        : key === 'action' ? (actionLabels[value] || String(value))
        : key === 'target' ? (targetLabels[value] || String(value))
        : String(value);
      tr.append(td);
    }
    body.append(tr);
  }
  grid.append(head, body);
  wrap.append(grid);
  return wrap;
}

function endpointFor(screen, params) {
  const allowed = screen.path === '/app/statistics'
    ? ['date', 'user', 'action', 'cursor'] : ['from', 'to'];
  const sanitized = new URLSearchParams();
  for (const key of allowed) if (params.has(key)) sanitized.set(key, params.get(key));
  const endpoint = screen.path === '/app/statistics'
    ? '/api/activity/statistics' : '/api/activity/reports';
  return endpoint + (sanitized.size ? '?' + sanitized : '');
}

function cursorLink(page, params, cursor, label) {
  const target = new URLSearchParams(params);
  target.delete('page');
  target.set('cursor', cursor);
  return link(page, '/app/statistics?' + target, label);
}

/**
 * Admin ActivityTracking view: server owns authorization, filters, cursor and report totals.
 * Native GET form navigation keeps the URL bookmarkable without a second router/state store.
 */
export function showActivity(screen, { page, outlet, signal }, { client, query = '', navigate }) {
  const isStatistics = screen.path === '/app/statistics';
  const params = new URLSearchParams(query);
  const section = page.createElement('section');
  const heading = page.createElement('h2');
  heading.textContent = isStatistics ? 'Activity statistics' : 'Daily activity reports';
  heading.tabIndex = -1;
  const status = page.createElement('p');
  status.setAttribute('role', 'status');
  status.setAttribute('aria-live', 'polite');
  status.tabIndex = -1;
  status.textContent = 'Loading…';
  section.append(heading, status);
  outlet.replaceChildren(section);
  heading.focus?.();

  const fields = isStatistics ? [['date', 'Date (UTC)'], ['user', 'User'], ['action', 'Action']]
    : [['from', 'From (UTC)'], ['to', 'To (UTC)']];
  section.append(filters(page, screen.path, params, fields));

  async function load() {
    try {
      const result = await client.request(endpointFor(screen, params), { signal });
      if (signal.aborted) return;
      const rows = isStatistics ? result?.events : result?.days;
      if (!Array.isArray(rows)) throw new Error('We could not display this activity. Please try again.');
      status.textContent = rows.length ? '' : isStatistics
        ? 'No activity matches these filters.' : 'No activity to report yet.';
      if (isStatistics) {
        section.append(table(page, rows, [
          ['occurred_at', 'UTC time'], ['actor_label', 'User'],
          ['action', 'Action'], ['target', 'Target'],
        ]));
        const nav = page.createElement('nav');
        nav.setAttribute('aria-label', 'Activity history pages');
        if (typeof result.previousCursor === 'string')
          nav.append(cursorLink(page, params, result.previousCursor, '← Previous'));
        if (typeof result.nextCursor === 'string')
          nav.append(cursorLink(page, params, result.nextCursor, 'Next →'));
        section.append(nav);
      } else if (rows.length) {
        const note = page.createElement('p');
        note.textContent = 'Only dates with activity appear in the chart. Days without activity are shown as gaps. See the table for exact totals.';
        section.append(note, drawChart(page, rows), table(page, rows, [
          ['date', 'UTC date'], ['pageAViews', 'Page A views'],
          ['pageBViews', 'Page B views'], ['buyCowClicks', 'Buy cow clicks'],
          ['downloadClicks', 'Download clicks'],
        ]));
      }
    } catch (error) {
      if (signal.aborted || error?.name === 'AbortError') return;
      if (error?.status === 401) {
        navigate('/app/login');
        return;
      }
      status.setAttribute('role', 'alert');
      status.textContent = error?.status === 403 ? 'You do not have permission to view this information.' :
        error?.message || 'We could not load the activity. Please try again.';
      status.focus?.();
    }
  }
  void load();
}
