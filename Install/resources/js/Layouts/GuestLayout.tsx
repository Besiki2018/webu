import { Link } from '@inertiajs/react';
import { PropsWithChildren } from 'react';
import { Card, CardContent } from '@/components/ui/card';
import ApplicationLogo from '@/components/ApplicationLogo';
import { ThemeToggle } from '@/components/ThemeToggle';
import { LanguageSelector } from '@/components/LanguageSelector';
import { Toaster } from '@/components/ui/sonner';

export default function Guest({ children }: PropsWithChildren) {
    return (
        <div className="auth-shell relative min-h-screen bg-white flex flex-col items-center justify-center p-4">

            {/* Language and Theme Toggle */}
            <div className="absolute top-4 end-4 z-50 flex items-center gap-2">
                <LanguageSelector />
                <ThemeToggle />
            </div>

            <div className="auth-shell__container relative z-10 w-full max-w-md">
                {/* Logo */}
                <Link href="/" className="auth-shell__logo flex items-center justify-center mb-8">
                    <ApplicationLogo showText size="lg" />
                </Link>

                {/* Card */}
                <Card className="auth-shell__card">
                    <CardContent>
                        {children}
                    </CardContent>
                </Card>
            </div>

            <Toaster />
        </div>
    );
}
