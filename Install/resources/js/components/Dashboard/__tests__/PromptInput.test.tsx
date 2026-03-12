import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { PromptInput } from '../PromptInput';

vi.mock('@/contexts/LanguageContext', () => ({
    useTranslation: () => ({
        t: (value: string) => value,
    }),
}));

vi.mock('@/components/Chat/ChatInputWithMentions', () => ({
    ChatInputWithMentions: ({
        value,
        onChange,
        onSubmit,
        disabled,
        placeholder,
    }: {
        value: string;
        onChange: (nextValue: string) => void;
        onSubmit: (event?: React.FormEvent) => void;
        disabled?: boolean;
        placeholder?: string;
    }) => (
        <form onSubmit={onSubmit}>
            <input
                aria-label="Prompt"
                value={value}
                onChange={(event) => onChange(event.target.value)}
                disabled={disabled}
                placeholder={placeholder}
            />
            <button type="submit">Send</button>
        </form>
    ),
}));

describe('PromptInput', () => {
    it('prefers quick generate when the create page provides it', async () => {
        const user = userEvent.setup();
        const onSubmit = vi.fn();
        const onQuickGenerate = vi.fn();

        render(
            <PromptInput
                onSubmit={onSubmit}
                onQuickGenerate={onQuickGenerate}
            />
        );

        await user.type(screen.getByLabelText('Prompt'), 'Create a yoga studio website');
        await user.click(screen.getByRole('button', { name: 'Send' }));

        expect(onQuickGenerate).toHaveBeenCalledWith('Create a yoga studio website', null);
        expect(onSubmit).not.toHaveBeenCalled();
    });

    it('falls back to the legacy AI project creation handler when quick generate is unavailable', async () => {
        const user = userEvent.setup();
        const onSubmit = vi.fn();

        render(<PromptInput onSubmit={onSubmit} />);

        await user.type(screen.getByLabelText('Prompt'), 'Create a consulting landing page');
        await user.click(screen.getByRole('button', { name: 'Send' }));

        expect(onSubmit).toHaveBeenCalledWith('Create a consulting landing page', null, null, 'ai');
    });
});
