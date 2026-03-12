import { useTranslation } from '@/contexts/LanguageContext';

interface SocialProofProps {
    statistics: {
        usersCount: number;
        projectsCount: number;
    };
    content?: Record<string, unknown>;
}

function formatCount(count: number): string {
    if (count < 100) {
        return '100+';
    }
    if (count >= 1_000_000) {
        return `${(count / 1_000_000).toFixed(1).replace(/\.0$/, '')}M+`;
    }
    if (count >= 1_000) {
        return `${(count / 1_000).toFixed(1).replace(/\.0$/, '')}K+`;
    }
    return `${count.toLocaleString()}+`;
}

export function SocialProof({ statistics, content }: SocialProofProps) {
    const { t } = useTranslation();

    // Extract content with defaults - DB content takes priority
    const usersLabel = (content?.users_label as string) || t('Happy Users');
    const projectsLabel = (content?.projects_label as string) || t('Projects Created');
    const uptimeLabel = (content?.uptime_label as string) || t('Availability');
    const uptimeValue = (content?.uptime_value as string) || t('High');

    const stats = [
        { value: formatCount(statistics.projectsCount), label: projectsLabel },
        { value: formatCount(statistics.usersCount), label: usersLabel },
        { value: uptimeValue, label: uptimeLabel },
    ];

    return (
        <section className="webu-social-proof py-14 lg:py-16 border-t border-border/60">
            <div className="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
                {/* Stats */}
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4">
                    {stats.map((stat) => (
                        <div key={stat.label} className="text-center rounded-xl border border-border bg-card p-6">
                            <div className="text-2xl md:text-3xl font-semibold text-foreground">
                                {stat.value}
                            </div>
                            <div className="text-sm text-muted-foreground mt-1.5">
                                {stat.label}
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </section>
    );
}
