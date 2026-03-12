import { describe, expect, it } from 'vitest';
import { detectReplyLanguage, resolveAiCommandLocale } from '../chatLocale';

describe('chatLocale', () => {
    it('detects Georgian script and qartulad-style hints as Georgian', () => {
        expect(detectReplyLanguage('ჰედერის დიზიანი შემიცვალე')).toBe('ka');
        expect(detectReplyLanguage('qartulad damiwere online magazia', 'en')).toBe('ka');
    });

    it('keeps explicit English messages in English', () => {
        expect(detectReplyLanguage('Change the header layout', 'ka')).toBe('en');
        expect(resolveAiCommandLocale('Update the hero title', 'ka')).toBe('en');
    });

    it('falls back to Georgian when the signal is ambiguous', () => {
        expect(detectReplyLanguage('', undefined)).toBe('ka');
        expect(resolveAiCommandLocale('12345', undefined)).toBe('ka');
    });

    describe('Georgian colloquial and builder terms', () => {
        it.each([
            ['აქ დამიწერე', 'ka'],
            ['ეს გადამიტანე', 'ka'],
            ['მაღაზიის გვერდზე გადააგდე', 'ka'],
            ['ჰედერში ეს ჩაასწორე', 'ka'],
            ['ფუტერში მარტო ეს დატოვე', 'ka'],
            ['ქარტულად', 'ka'],
            ['დიზიანი', 'ka'],
            ['ერტი', 'ka'],
            ['kartulad shecvale', 'ka'],
            ['qartuli magazia', 'ka'],
        ])('detects %s as Georgian', (msg, expected) => {
            expect(detectReplyLanguage(msg)).toBe(expected);
        });

        it('handles mixed Georgian-English prompts as Georgian when Georgian script present', () => {
            expect(detectReplyLanguage('Change ჰედერის დიზაინი')).toBe('ka');
        });

        it('handles romanized Georgian hints (qartulad, kartulad) as Georgian', () => {
            expect(detectReplyLanguage('qartulad diziani shemitsvale')).toBe('ka');
            expect(detectReplyLanguage('kartulad ertad shecvale')).toBe('ka');
        });

        it('builder terms in Georgian resolve to ka', () => {
            expect(detectReplyLanguage('სექცია დაამატე')).toBe('ka');
            expect(detectReplyLanguage('ჰედერი შეცვალე')).toBe('ka');
        });

        it('ecommerce terms in Georgian resolve to ka', () => {
            expect(detectReplyLanguage('მაღაზიის გვერდზე')).toBe('ka');
            expect(detectReplyLanguage('პროდუქტი დაამატე')).toBe('ka');
        });
    });
});
