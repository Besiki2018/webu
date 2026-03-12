import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MessageBubble } from '../MessageBubble';

vi.mock('@/contexts/LanguageContext', () => ({
    useTranslation: () => ({
        t: (key: string) => key,
        locale: 'en',
    }),
}));

vi.mock('sonner', () => ({
    toast: { success: vi.fn(), error: vi.fn() },
}));

describe('MessageBubble diagnostic_log rendering', () => {
  it('assistant message with diagnosticLog shows Debug log block and every line', () => {
    render(
      <MessageBubble
        message={{
          id: 'msg-1',
          type: 'assistant',
          content: 'I could not apply changes.',
          timestamp: new Date(),
          diagnosticLog: [
            '[Step 1] Resolved section: hero-1',
            '[Step 2] Field title not found in schema',
            '[Step 3] Skipped update',
          ],
        }}
      />
    );
    expect(screen.getByText('Debug log')).toBeInTheDocument();
    expect(screen.getByText('[Step 1] Resolved section: hero-1')).toBeInTheDocument();
    expect(screen.getByText('[Step 2] Field title not found in schema')).toBeInTheDocument();
    expect(screen.getByText('[Step 3] Skipped update')).toBeInTheDocument();
  });

  it('assistant message without diagnosticLog does not render Debug log block', () => {
    render(
      <MessageBubble
        message={{
          id: 'msg-2',
          type: 'assistant',
          content: 'I applied your changes.',
          timestamp: new Date(),
        }}
      />
    );
    expect(screen.queryByText('Debug log')).not.toBeInTheDocument();
  });

  it('assistant message with empty diagnosticLog array does not render Debug log block', () => {
    render(
      <MessageBubble
        message={{
          id: 'msg-3',
          type: 'assistant',
          content: 'Done.',
          timestamp: new Date(),
          diagnosticLog: [],
        }}
      />
    );
    expect(screen.queryByText('Debug log')).not.toBeInTheDocument();
  });
});
