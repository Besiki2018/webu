import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { getTranslatedPersonas, getIconComponent } from './data';
import { useTranslation } from '@/contexts/LanguageContext';

interface PersonaItem {
    title: string;
    description: string;
    icon: string;
}

interface UseCasesProps {
    content?: Record<string, unknown>;
    items?: PersonaItem[];
    settings?: Record<string, unknown>;
}

export function UseCases({ content, items, settings: _settings }: UseCasesProps = {}) {
    const { t } = useTranslation();

    // Use database items if provided, otherwise fall back to translated defaults
    const personas = items?.length
        ? items.map((item, index) => ({
              id: `persona-${index}`,
              title: item.title,
              description: item.description,
              icon: getIconComponent(item.icon),
          }))
        : getTranslatedPersonas(t);

    // Get content with defaults - DB content takes priority
    const title = (content?.title as string) || t('Built for everyone');
    const subtitle = (content?.subtitle as string) || t("Whether you're a developer, designer, or entrepreneur, our platform helps you build faster and smarter.");

    return (
        <section id="use-cases" className="webu-use-cases py-16 lg:py-20 border-t border-border/60">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                {/* Section Header */}
                <div className="text-center mb-12">
                    <h2 className="webu-use-cases__title text-3xl md:text-4xl font-semibold tracking-tight mb-4">
                        {title}
                    </h2>
                    <p className="text-base md:text-lg text-muted-foreground max-w-2xl mx-auto leading-relaxed">
                        {subtitle}
                    </p>
                </div>

                {/* Persona Grid */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    {personas.map((persona) => {
                        const Icon = persona.icon;
                        return (
                            <Card
                                key={persona.id}
                                className="group border border-border shadow-none hover:bg-accent/40 transition-colors text-center"
                            >
                                <CardHeader className="pb-2">
                                    <div className="w-14 h-14 rounded-2xl bg-muted flex items-center justify-center mx-auto mb-4 group-hover:bg-muted/80 transition-colors">
                                        <Icon className="w-7 h-7 text-primary" />
                                    </div>
                                    <CardTitle className="text-xl">
                                        {persona.title}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <CardDescription className="text-sm">
                                        {persona.description}
                                    </CardDescription>
                                </CardContent>
                            </Card>
                        );
                    })}
                </div>
            </div>
        </section>
    );
}
