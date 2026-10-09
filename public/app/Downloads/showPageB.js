/**
 * Page B owns native form presentation; PHP owns fixed artifact and activity audit.
 * The executable is never fetched into a JavaScript blob or stored in client memory.
 */
export function showPageB({ page, outlet, signal }, { client, navigate }) {
  const section = page.createElement('section');
  const heading = page.createElement('h2');
  heading.textContent = 'Page B';
  heading.tabIndex = -1;
  const feedback = page.createElement('p');
  feedback.setAttribute('role', 'status');
  feedback.setAttribute('aria-live', 'polite');
  feedback.textContent = 'Preparing download…';
  section.append(heading, feedback);
  outlet.replaceChildren(section);
  heading.focus?.();

  async function load() {
    try {
      await client.request('/api/downloads/page/view', { method: 'POST', signal });
      if (signal.aborted) return;
      const token = await client.getCsrf({ signal });
      if (signal.aborted) return;

      const form = page.createElement('form');
      form.method = 'post';
      form.action = '/api/downloads/sample';
      form.enctype = 'application/x-www-form-urlencoded';
      const field = page.createElement('input');
      field.type = 'hidden';
      field.name = 'csrf_token';
      field.value = token;
      const button = page.createElement('button');
      button.type = 'submit';
      button.textContent = 'Download';
      form.append(field, button);
      section.append(form);
      feedback.textContent = 'Ready to download the sample executable.';
    } catch (error) {
      if (signal.aborted || error?.name === 'AbortError') return;
      if (error?.status === 401) {
        navigate('/app/login');
        return;
      }
      feedback.textContent = error?.message || 'Download is temporarily unavailable.';
    }
  }
  void load();
}
