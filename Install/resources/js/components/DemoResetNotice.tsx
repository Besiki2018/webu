import { useState } from 'react';
import { usePage } from '@inertiajs/react';
import { PageProps } from '@/types';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Clock } from 'lucide-react';

const STORAGE_KEY = 'demo-reset-notice-dismissed';

export function DemoResetNotice() {
    const { isDemo } = usePage<PageProps>().props;
    const [open, setOpen] = useState(() => {
        if (!isDemo) return false;
        return !localStorage.getItem(STORAGE_KEY);
    });

    const handleDismiss = () => {
        localStorage.setItem(STORAGE_KEY, 'true');
        setOpen(false);
    };

    if (!isDemo) return null;

    return (
        <Dialog open={open} onOpenChange={(value) => {
            if (!value) handleDismiss();
        }}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <div className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-primary/10">
                        <Clock className="h-6 w-6 text-primary" />
                    </div>
                    <DialogTitle className="text-center">
                        Demo Mode
                    </DialogTitle>
                    <DialogDescription className="text-center">
                        This demo environment resets every 3 hours.
                        Any projects, settings, or changes you make
                        will be cleared automatically.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter className="sm:justify-center">
                    <Button onClick={handleDismiss}>
                        Got it
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
