export function submitBrowserPost(action: string, fields: Record<string, string | undefined | null>): void {
    if (typeof document === 'undefined') {
        throw new Error('submitBrowserPost requires a browser document');
    }

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = normalizeBrowserPostAction(action);
    form.style.display = 'none';

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (csrfToken) {
        appendHiddenField(form, '_token', csrfToken);
    }

    Object.entries(fields).forEach(([name, value]) => {
        if (value == null) {
            return;
        }

        appendHiddenField(form, name, value);
    });

    document.body.appendChild(form);
    form.submit();
}

function normalizeBrowserPostAction(action: string): string {
    if (typeof window === 'undefined') {
        return action;
    }

    try {
        const url = new URL(action, window.location.href);

        // Use a relative path for internal browser POST redirects so the form
        // always targets the current origin. This avoids localhost/127.0.0.1
        // or port mismatches that can drop the session cookie and trigger 419.
        return `${url.pathname}${url.search}${url.hash}`;
    } catch {
        return action;
    }
}

function appendHiddenField(form: HTMLFormElement, name: string, value: string): void {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = name;
    input.value = value;
    form.appendChild(input);
}
