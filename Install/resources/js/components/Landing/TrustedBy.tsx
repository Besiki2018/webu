import { useMemo } from 'react';
import { useTranslation } from '@/contexts/LanguageContext';
import { cn } from '@/lib/utils';

const defaultCompanies = [
    { name: 'TechFlow', initial: 'T', color: 'bg-blue-500' },
    { name: 'BuildCorp', initial: 'B', color: 'bg-green-500' },
    { name: 'DataSync', initial: 'D', color: 'bg-purple-500' },
    { name: 'CloudBase', initial: 'C', color: 'bg-orange-500' },
    { name: 'DevStack', initial: 'S', color: 'bg-pink-500' },
];

interface LogoItem {
    name: string;
    initial: string;
    color: string;
    image_url?: string | null;
}

interface TrustedByProps {
    content?: Record<string, unknown>;
    items?: LogoItem[];
    settings?: Record<string, unknown>;
}

function CompanyBadge({ company }: { company: LogoItem }) {
    return (
        <div className="webu-trusted-by__logo" aria-label={company.name} title={company.name}>
            {company.image_url ? (
                <img
                    src={company.image_url}
                    alt={company.name}
                    className="webu-trusted-by__logo-image"
                />
            ) : (
                <span className="webu-trusted-by__logo-wordmark">
                    {company.name}
                </span>
            )}
        </div>
    );
}

export function TrustedBy({ content, items, settings: _settings }: TrustedByProps = {}) {
    const { t, isRtl } = useTranslation();

    // Use database items if provided, otherwise fall back to static data
    const companies = items?.length ? items : defaultCompanies;
    const marqueeItems = useMemo(
        () => (companies.length > 1 ? [...companies, ...companies] : companies),
        [companies]
    );

    // Get content with defaults - DB content takes priority
    const title = (content?.title as string) || t('Trusted by teams at');
    const marqueeClass = isRtl ? 'animate-marquee-rtl-slow' : 'animate-marquee-slow';

    return (
        <div className="webu-trusted-by">
            <p className="webu-trusted-by__title">
                {title}
            </p>
            <div className="webu-trusted-by__viewport">
                <div
                    className={cn(
                        'webu-trusted-by__track',
                        companies.length > 1 && `${marqueeClass} hover:[animation-play-state:paused]`
                    )}
                >
                    {marqueeItems.map((company, index) => (
                        <CompanyBadge key={`${company.name}-${index}`} company={company} />
                    ))}
                </div>
            </div>
        </div>
    );
}
