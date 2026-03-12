import { describe, expect, it } from 'vitest';
import { getClarificationPrompt } from './chatClarification';

describe('getClarificationPrompt', () => {
    it('asks for clarification on vague header changes', () => {
        expect(getClarificationPrompt('ჰედერის შეცვლა მინდა', 'ka')).toContain('ჰედერში');
        expect(getClarificationPrompt('Change the header', 'en')).toContain('header');
    });

    it('does not ask for clarification when header details are present', () => {
        expect(getClarificationPrompt('ჰედერის ფონი შავი გახადე', 'ka')).toBeNull();
        expect(getClarificationPrompt('Change the header background to black', 'en')).toBeNull();
    });

    it('asks for clarification on generic hero changes', () => {
        expect(getClarificationPrompt('hero section change', 'en')).toContain('hero');
        expect(getClarificationPrompt('Change hero text', 'en')).toContain('hero');
    });

    it('redirects chat-ui requests out of scope', () => {
        expect(getClarificationPrompt('მიმოწერის დიზაინი გაასწორე', 'ka')).toContain('ჩატის ინტერფეისს');
    });

    it('does not interrupt broad site-generation requests with generic clarification', () => {
        expect(getClarificationPrompt('შექმენი საიტი ონლაინ მაღაზიისთვის', 'ka')).toBeNull();
        expect(getClarificationPrompt('Build a website for a dental clinic', 'en')).toBeNull();
    });
});
