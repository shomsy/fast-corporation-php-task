/**
 * Page A owns only transient browser state; CowOffer PHP owns every accepted event.
 */
export function showPageA({ page, outlet, signal, onDispose }, { client, navigate }) {
  const section = page.createElement('section');
  const heading = page.createElement('h2');
  heading.textContent = 'Page A';
  heading.tabIndex = -1;
  const feedback = page.createElement('p');
  feedback.setAttribute('role', 'status');
  feedback.setAttribute('aria-live', 'polite');
  feedback.textContent = 'Loading Page A…';
  section.append(heading, feedback);
  outlet.replaceChildren(section);
  heading.focus?.();

  async function mount() {
    try {
      await client.request('/api/cow-offer/view', { method: 'POST', signal });
      if (signal.aborted) return;
      feedback.textContent = '';
      const button = page.createElement('button');
      button.type = 'button';
      button.textContent = 'Buy a cow';
      section.append(button);
      async function buy() {
        if (signal.aborted || button.disabled || button.hidden) return;
        button.disabled = true;
        try {
          const result = await client.request('/api/cow-offer/buy', { method: 'POST', signal });
          if (signal.aborted) return;
          if (result?.outcome !== 'thankYou') throw new Error('Unexpected response.');
          button.hidden = true;
          feedback.textContent = 'thankYou';
          feedback.focus?.();
        } catch (error) {
          if (!signal.aborted && error?.name !== 'AbortError') {
            feedback.textContent = error?.message || 'Unable to record the action.';
            button.disabled = false;
          }
        }
      }
      button.addEventListener('click', buy);
      onDispose(() => button.removeEventListener('click', buy));
    } catch (error) {
      if (signal.aborted || error?.name === 'AbortError') return;
      if (error?.status === 401) {
        navigate('/app/login');
        return;
      }
      feedback.textContent = error?.message || 'Unable to show Page A.';
    }
  }
  void mount();
}
