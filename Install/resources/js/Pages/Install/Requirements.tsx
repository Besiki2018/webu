import { Head } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import InstallerLayout from '@/Layouts/InstallerLayout';
import { CheckCircle2, XCircle, AlertCircle } from 'lucide-react';
import { Alert, AlertDescription } from '@/components/ui/alert';

interface Props {
    dependencies: Record<string, boolean>;
    dependencyDetails?: {
        id: string;
        name: string;
        required: boolean;
        status: boolean;
        hint: string | null;
    }[];
}

type DependencyItem = {
    id: string;
    name: string;
    required: boolean;
    status: boolean;
    hint: string | null;
};

export default function Requirements({ dependencies, dependencyDetails = [] }: Props) {
    const items: DependencyItem[] = dependencyDetails.length > 0
        ? dependencyDetails
        : Object.entries(dependencies).map(([name, status], index) => ({
            id: `legacy-${index}`,
            name: name.replace(' (Optional)', ''),
            required: !name.includes('(Optional)'),
            status,
            hint: null,
        }));

    const requiredEntries = items.filter((item) => item.required);
    const optionalEntries = items.filter((item) => !item.required);

    const allRequiredPassed = requiredEntries.every((item) => item.status);
    const passedCount = items.filter((item) => item.status).length;

    return (
        <InstallerLayout currentStep={1} title="Server Requirements">
            <Head title="Requirements Check" />

            <p className="text-center text-muted-foreground mb-6">
                Checking if your server meets the requirements to run the application.
            </p>

            <div className="space-y-2 mb-6">
                {requiredEntries.map((item) => (
                    <div
                        key={item.id}
                        className="flex items-center justify-between p-3 rounded-lg bg-muted/50"
                    >
                        <div className="pr-3">
                            <p className="text-sm">{item.name}</p>
                            {!item.status && item.hint && (
                                <p className="text-xs text-muted-foreground mt-1">{item.hint}</p>
                            )}
                        </div>
                        {item.status ? (
                            <CheckCircle2 className="w-5 h-5 text-success" />
                        ) : (
                            <XCircle className="w-5 h-5 text-destructive" />
                        )}
                    </div>
                ))}

                {optionalEntries.length > 0 && (
                    <>
                        <div className="pt-2 pb-1">
                            <span className="text-xs font-medium text-muted-foreground uppercase tracking-wide">
                                Optional
                            </span>
                        </div>
                        {optionalEntries.map((item) => (
                            <div
                                key={item.id}
                                className="flex items-center justify-between p-3 rounded-lg bg-muted/30"
                            >
                                <div className="pr-3">
                                    <p className="text-sm text-muted-foreground">{item.name}</p>
                                    {!item.status && item.hint && (
                                        <p className="text-xs text-muted-foreground mt-1">{item.hint}</p>
                                    )}
                                </div>
                                {item.status ? (
                                    <CheckCircle2 className="w-5 h-5 text-success" />
                                ) : (
                                    <AlertCircle className="w-5 h-5 text-warning" />
                                )}
                            </div>
                        ))}
                    </>
                )}
            </div>

            <div className="flex items-center justify-between mb-6 p-3 rounded-lg bg-muted/50">
                <span className="text-sm font-medium">Status</span>
                <span className={`text-sm font-medium ${allRequiredPassed ? 'text-success' : 'text-destructive'}`}>
                    {passedCount} / {items.length} passed
                </span>
            </div>

            {!allRequiredPassed && (
                <Alert variant="destructive" className="mb-6">
                    <AlertDescription>
                        Please resolve failed required checks before continuing. Each failed check now includes an actionable hint.
                    </AlertDescription>
                </Alert>
            )}

            <div className="flex gap-3">
                <a href={route('install')} className="flex-1">
                    <Button variant="outline" className="w-full">
                        Back
                    </Button>
                </a>
                {allRequiredPassed ? (
                    <a href={route('install.permissions')} className="flex-1">
                        <Button className="w-full">Continue</Button>
                    </a>
                ) : (
                    <Button className="flex-1" disabled>
                        Continue
                    </Button>
                )}
            </div>
        </InstallerLayout>
    );
}
