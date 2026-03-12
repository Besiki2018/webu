import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '../dialog';
import {
    AlertDialog,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogHeader,
    AlertDialogTitle,
} from '../alert-dialog';

describe('Dialog layering overrides', () => {
    it('applies overlay and content z-index override classes to DialogContent', () => {
        render(
            <Dialog open>
                <DialogContent
                    overlayClassName="z-[220]"
                    className="z-[221]"
                    showClose={false}
                >
                    <DialogHeader>
                        <DialogTitle>Media Library</DialogTitle>
                        <DialogDescription>Layered dialog test</DialogDescription>
                    </DialogHeader>
                    <div>Dialog body</div>
                </DialogContent>
            </Dialog>
        );

        const overlay = document.querySelector('[data-slot="dialog-overlay"]');
        const content = document.querySelector('[data-slot="dialog-content"]');

        expect(overlay).toBeInTheDocument();
        expect(content).toBeInTheDocument();
        expect(overlay).toHaveClass('z-[220]');
        expect(content).toHaveClass('z-[221]');
        expect(screen.queryByRole('button', { name: /close/i })).not.toBeInTheDocument();
    });

    it('applies overlay z-index override classes to AlertDialogContent', () => {
        render(
            <AlertDialog open>
                <AlertDialogContent overlayClassName="z-[220]" className="z-[221]">
                    <AlertDialogHeader>
                        <AlertDialogTitle>Confirm Delete</AlertDialogTitle>
                        <AlertDialogDescription>Alert layered dialog test</AlertDialogDescription>
                    </AlertDialogHeader>
                </AlertDialogContent>
            </AlertDialog>
        );

        const overlay = document.querySelector('[data-slot="alert-dialog-overlay"]');
        const content = document.querySelector('[data-slot="alert-dialog-content"]');

        expect(overlay).toBeInTheDocument();
        expect(content).toBeInTheDocument();
        expect(overlay).toHaveClass('z-[220]');
        expect(content).toHaveClass('z-[221]');
    });
});
