/**
 * Administrator account directory embedded in Home. Security decisions belong to PHP.
 * Bounded keyset pagination; every grant requires a second, explicit click.
 */
export function showManageUsers({ page, section, signal }, { client }) {
  const panel = page.createElement('section');
  panel.className = 'manage-users';
  const title = page.createElement('h3');
  title.textContent = 'Manage users';
  const explanation = page.createElement('p');
  explanation.textContent = 'View accounts and grant administrator access to a standard user.';
  const status = page.createElement('p');
  status.setAttribute('role', 'status');
  status.setAttribute('aria-live', 'polite');
  status.textContent = 'Loading users…';
  const list = page.createElement('ul');
  list.className = 'user-list';
  panel.append(title, explanation, status, list);
  section.append(panel);

  let nextAfter = null;
  let loading = false;
  let moreButton = null;

  function announce(message, isError = false) {
    status.textContent = message;
    status.setAttribute('role', isError ? 'alert' : 'status');
    if (isError) {
      status.tabIndex = -1;
      status.focus?.();
    }
  }

  function userRow(user) {
    const item = page.createElement('li');
    item.className = 'user-row';
    const label = page.createElement('span');
    label.className = 'user-name';
    label.textContent = user.username;
    const role = page.createElement('span');
    role.className = 'user-role';
    role.textContent = user.role === 'admin' ? 'Administrator' : 'User';
    item.append(label, role);

    if (user.role !== 'user') return item;

    const actions = page.createElement('div');
    actions.className = 'user-actions';
    const promote = page.createElement('button');
    promote.type = 'button';
    promote.textContent = 'Make admin';
    promote.setAttribute('aria-label', 'Make ' + user.username + ' an administrator');
    actions.append(promote);
    item.append(actions);

    promote.addEventListener('click', () => {
      if (signal.aborted) return;
      actions.replaceChildren();
      const question = page.createElement('p');
      question.textContent = 'Give ' + user.username + ' full administrator access?';
      const confirm = page.createElement('button');
      confirm.type = 'button';
      confirm.textContent = 'Confirm access';
      const cancel = page.createElement('button');
      cancel.type = 'button';
      cancel.className = 'secondary-button';
      cancel.textContent = 'Cancel';
      actions.append(question, confirm, cancel);

      cancel.addEventListener('click', () => {
        if (!signal.aborted) {
          actions.replaceChildren(promote);
          promote.focus?.();
        }
      });
      confirm.addEventListener('click', async () => {
        if (confirm.disabled || signal.aborted) return;
        confirm.disabled = true;
        cancel.disabled = true;
        announce('Updating account…');
        try {
          const response = await client.request('/api/accounts/users/promote', {
            method: 'POST', body: { userId: user.id }, signal,
          });
          if (signal.aborted) return;
          if (response?.user?.id !== user.id || response.user.role !== 'admin') {
            throw new Error('We could not confirm the new permissions. Refresh the page.');
          }
          role.textContent = 'Administrator';
          actions.replaceChildren();
          announce(user.username + ' now has administrator access.');
        } catch (error) {
          if (signal.aborted || error?.name === 'AbortError') return;
          announce(error?.message || 'We could not update this account. Refresh and check its role.', true);
          actions.replaceChildren(promote);
        }
      });
      confirm.focus?.();
    });
    return item;
  }

  async function load(after = null) {
    if (loading || signal.aborted) return;
    loading = true;
    if (moreButton) moreButton.disabled = true;
    announce('Loading users…');
    try {
      const path = after === null ? '/api/accounts/users'
        : '/api/accounts/users?after=' + encodeURIComponent(String(after));
      const result = await client.request(path, { signal });
      if (signal.aborted) return;
      if (!Array.isArray(result?.users)) throw new Error('We could not display users. Please refresh the page.');
      if (after !== null && result.nextAfter !== null && result.nextAfter <= after) {
        throw new Error('We could not load the next page. Please refresh the page.');
      }
      if (moreButton) moreButton.remove();
      moreButton = null;
      for (const user of result.users) list.append(userRow(user));
      nextAfter = Number.isSafeInteger(result.nextAfter) && result.nextAfter > 0 ? result.nextAfter : null;
      if (nextAfter !== null) {
        moreButton = page.createElement('button');
        moreButton.type = 'button';
        moreButton.className = 'secondary-button';
        moreButton.textContent = 'Load more users';
        panel.append(moreButton);
        moreButton.addEventListener('click', () => { void load(nextAfter); });
      }
      announce(list.children.length ? '' : 'No accounts found.');
    } catch (error) {
      if (!signal.aborted && error?.name !== 'AbortError') {
        announce(error?.message || 'We could not load users. Please refresh the page.', true);
      }
    } finally {
      loading = false;
      if (!signal.aborted && moreButton) moreButton.disabled = false;
    }
  }

  void load();
}
