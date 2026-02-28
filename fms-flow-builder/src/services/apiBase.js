function detectAppRoot() {
  const fromQuery = new URLSearchParams(window.location.search).get('appRoot');
  if (fromQuery) return fromQuery.startsWith('/') ? fromQuery : `/${fromQuery}`;
  if (window.__FMS_APP_ROOT) return window.__FMS_APP_ROOT;
  const path = window.location.pathname || '/';
  const parts = path.split('/').filter(Boolean);
  return parts.length > 0 ? '/' + parts[0] : '';
}

export function buildHandlerUrl(handlerFile, action, params) {
  let base;
  const appRoot = detectAppRoot();

  if (window.__FMS_PHP_BASE && handlerFile === 'fms_flow_handler.php') {
    base = window.location.origin + window.__FMS_PHP_BASE;
  } else if (window.location.port === '5173') {
    base = `${window.location.origin.replace(':5173', '')}${appRoot}/ajax/${handlerFile}`;
  } else {
    base = `${window.location.origin}${appRoot}/ajax/${handlerFile}`;
  }

  const url = new URL(base);
  if (action) url.searchParams.set('action', action);
  if (params) {
    Object.entries(params).forEach(([k, v]) => url.searchParams.set(k, String(v)));
  }
  return url.toString();
}

export async function apiRequest(handlerFile, action, opts = {}) {
  const url = buildHandlerUrl(handlerFile, action, opts.params);
  const fetchOpts = { credentials: 'include' };

  if (opts.body) {
    fetchOpts.method = 'POST';
    fetchOpts.headers = { 'Content-Type': 'application/json' };
    fetchOpts.body = JSON.stringify(opts.body);
  }

  let res;
  try {
    res = await fetch(url, fetchOpts);
  } catch (_) {
    throw new Error('Network error — backend may be offline');
  }

  const text = await res.text();
  if (!text) throw new Error('Empty response from server (HTTP ' + res.status + ')');

  let json;
  try {
    json = JSON.parse(text);
  } catch (_) {
    throw new Error('Server returned non-JSON (HTTP ' + res.status + ')');
  }

  if (json.status !== 'ok') throw new Error(json.message || 'Unknown API error');
  return json;
}

export async function apiRequestWithPayloadFallback(handlerFile, action, body, params) {
  try {
    return await apiRequest(handlerFile, action, { body, params });
  } catch (err) {
    const msg = String(err?.message || '');
    const shouldRetry =
      body &&
      (msg.toLowerCase().includes('invalid request body') || msg.toLowerCase().includes('non-json'));

    if (!shouldRetry) {
      throw err;
    }

    const url = buildHandlerUrl(handlerFile, action, params);
    const form = new URLSearchParams();
    form.set('payload', JSON.stringify(body));

    const res = await fetch(url, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: form.toString(),
    });

    const text = await res.text();
    if (!text) throw new Error('Empty response from server (HTTP ' + res.status + ')');

    let json;
    try {
      json = JSON.parse(text);
    } catch (_) {
      throw new Error('Server returned non-JSON (HTTP ' + res.status + ')');
    }
    if (json.status !== 'ok') throw new Error(json.message || 'Unknown API error');
    return json;
  }
}

