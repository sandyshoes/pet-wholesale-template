(() => {
  const original = window.fetch.bind(window);
  window.fetch = (resource, options = {}) => {
    const url = new URL(typeof resource === 'string' ? resource : resource.url, location.href);
    if (url.origin === location.origin && !['GET','HEAD'].includes((options.method || 'GET').toUpperCase())) {
      const token = document.querySelector('meta[name="csrf-token"]')?.content;
      options.headers = new Headers(options.headers);
      if (token) options.headers.set('X-CSRF-Token', token);
    }
    return original(resource, options);
  };
  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form').forEach(form => {
      if (form.querySelector('[name="csrf_token"]')) return;
      const input = document.createElement('input'); input.type='hidden'; input.name='csrf_token';
      input.value=document.querySelector('meta[name="csrf-token"]')?.content || ''; form.append(input);
    });
  });
})();
