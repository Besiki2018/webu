import { useMemo } from 'react';
import { Link } from '@inertiajs/react';
import ApplicationLogo from '@/components/ApplicationLogo';
import { useTranslation } from '@/contexts/LanguageContext';

export function Footer() {
    const { t } = useTranslation();
    const currentYear = new Date().getFullYear();

    const footerLinks = useMemo(() => [
        { label: t('Privacy Policy'), href: '/privacy' },
        { label: t('Terms of Service'), href: '/terms' },
        { label: t('Cookie Policy'), href: '/cookies' },
    ], [t]);

    return (
        <footer className="webu-footer webu-footer--landing">
            <div className="webu-footer__inner webu-footer__inner--landing">
                <div className="flex flex-col items-center text-center space-y-8">
                    {/* Logo */}
                    <Link href="/" className="flex-shrink-0 text-start">
                        <ApplicationLogo showText size="lg" />
                    </Link>

                    {/* Tagline */}
                    <p className="text-muted-foreground text-sm max-w-xl leading-relaxed">
                        {t('Build websites from your ideas in minutes. Professional website builder with no coding required.')}
                    </p>

                    {/* Links */}
                    <nav className="flex flex-wrap items-center justify-center gap-6 sm:gap-8" aria-label="Footer">
                        {footerLinks.map((link) => (
                            <Link
                                key={link.label}
                                href={link.href}
                                className="text-sm text-muted-foreground hover:text-foreground transition-colors"
                            >
                                {link.label}
                            </Link>
                        ))}
                    </nav>

                    {/* Copyright */}
                    <p className="text-xs text-muted-foreground">
                        © {currentYear}. {t('All rights reserved.')}
                    </p>
                </div>
            </div>
        </footer>
    );
}
