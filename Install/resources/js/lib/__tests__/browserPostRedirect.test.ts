import { describe, expect, it, vi, afterEach } from 'vitest';
import { submitBrowserPost } from '../browserPostRedirect';

describe('submitBrowserPost', () => {
    afterEach(() => {
        document.head.innerHTML = '';
        document.body.innerHTML = '';
    });

    it('submits a hidden post form with csrf token and payload fields', () => {
        const meta = document.createElement('meta');
        meta.name = 'csrf-token';
        meta.content = 'csrf-123';
        document.head.appendChild(meta);

        const submitSpy = vi.spyOn(HTMLFormElement.prototype, 'submit').mockImplementation(() => {});

        submitBrowserPost('/projects/generate-website', {
            prompt: 'Create a yoga studio website',
            style: 'modern',
            ignored: null,
        });

        const form = document.body.querySelector('form');
        expect(form).not.toBeNull();
        expect(form?.getAttribute('method')).toBe('POST');
        expect(form?.getAttribute('action')).toBe('/projects/generate-website');

        const values = Object.fromEntries(
            Array.from(form?.querySelectorAll('input') ?? []).map((input) => [input.getAttribute('name'), input.getAttribute('value')])
        );

        expect(values).toEqual({
            _token: 'csrf-123',
            prompt: 'Create a yoga studio website',
            style: 'modern',
        });
        expect(submitSpy).toHaveBeenCalledOnce();

        submitSpy.mockRestore();
    });

    it('normalizes absolute internal URLs to the current origin path', () => {
        const meta = document.createElement('meta');
        meta.name = 'csrf-token';
        meta.content = 'csrf-456';
        document.head.appendChild(meta);

        const submitSpy = vi.spyOn(HTMLFormElement.prototype, 'submit').mockImplementation(() => {});

        submitBrowserPost('http://127.0.0.1:8000/projects/generate-website?draft=1', {
            prompt: 'Create a restaurant website',
        });

        const form = document.body.querySelector('form');
        expect(form?.getAttribute('action')).toBe('/projects/generate-website?draft=1');
        expect(submitSpy).toHaveBeenCalledOnce();

        submitSpy.mockRestore();
    });
});
