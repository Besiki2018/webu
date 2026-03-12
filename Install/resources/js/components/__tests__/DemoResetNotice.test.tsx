import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { usePage } from '@inertiajs/react';
import type { PageProps } from '@/types';
import { DemoResetNotice } from '../DemoResetNotice';

function mockPageProps(props: Partial<PageProps> = {}) {
    vi.mocked(usePage).mockReturnValue({
        props: props as PageProps,
    } as ReturnType<typeof usePage>);
}

vi.mock('@inertiajs/react', () => ({
    usePage: vi.fn(),
}));

describe('DemoResetNotice', () => {
    beforeEach(() => {
        vi.mocked(localStorage.getItem).mockReset();
        vi.mocked(localStorage.setItem).mockReset();
    });

    it('renders nothing when not in demo mode', () => {
        mockPageProps({ isDemo: false });
        const { container } = render(<DemoResetNotice />);
        expect(container.innerHTML).toBe('');
    });

    it('renders nothing when isDemo is undefined', () => {
        mockPageProps();
        const { container } = render(<DemoResetNotice />);
        expect(container.innerHTML).toBe('');
    });

    it('shows dialog when in demo mode and not previously dismissed', () => {
        mockPageProps({ isDemo: true });
        vi.mocked(localStorage.getItem).mockReturnValue(null);

        render(<DemoResetNotice />);

        expect(screen.getByRole('dialog')).toBeInTheDocument();
        expect(screen.getByText('Demo Mode')).toBeInTheDocument();
        expect(screen.getByText(/resets every 3 hours/i)).toBeInTheDocument();
    });

    it('does not show dialog when previously dismissed', () => {
        mockPageProps({ isDemo: true });
        vi.mocked(localStorage.getItem).mockReturnValue('true');

        render(<DemoResetNotice />);

        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });

    it('checks localStorage with the correct key', () => {
        mockPageProps({ isDemo: true });
        vi.mocked(localStorage.getItem).mockReturnValue(null);

        render(<DemoResetNotice />);

        expect(localStorage.getItem).toHaveBeenCalledWith('demo-reset-notice-dismissed');
    });

    it('dismisses dialog and saves to localStorage when button is clicked', async () => {
        const user = userEvent.setup();
        mockPageProps({ isDemo: true });
        vi.mocked(localStorage.getItem).mockReturnValue(null);

        render(<DemoResetNotice />);

        const dismissButton = screen.getByRole('button', { name: /got it/i });
        await user.click(dismissButton);

        expect(localStorage.setItem).toHaveBeenCalledWith(
            'demo-reset-notice-dismissed',
            'true'
        );
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });

    it('dismisses when the close button (X) is clicked', async () => {
        const user = userEvent.setup();
        mockPageProps({ isDemo: true });
        vi.mocked(localStorage.getItem).mockReturnValue(null);

        render(<DemoResetNotice />);

        const closeButton = screen.getByRole('button', { name: /close/i });
        await user.click(closeButton);

        expect(localStorage.setItem).toHaveBeenCalledWith(
            'demo-reset-notice-dismissed',
            'true'
        );
    });
});
