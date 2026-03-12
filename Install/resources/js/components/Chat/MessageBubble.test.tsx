import { act, render, screen } from '@testing-library/react';
import { describe, it, expect, vi, afterEach } from 'vitest';
import { MessageBubble, PendingAssistantBubble } from './MessageBubble';
import { ChatMessage } from '@/types/chat';
import type { User } from '@/types';
import type { BuildProgress } from '@/hooks/useBuilderChat';

const currentUser: User = {
    id: 1,
    name: 'Giorgi Suladze',
    email: 'giorgi@example.com',
    avatar: null,
    role: 'user',
};

afterEach(() => {
    vi.useRealTimers();
});

describe('MessageBubble', () => {
    it('renders user message content', () => {
        const message: ChatMessage = {
            id: '1',
            type: 'user',
            content: 'Hello, AI!',
            timestamp: new Date(),
        };

        render(<MessageBubble message={message} currentUser={currentUser} />);

        expect(screen.getByText('Hello, AI!')).toBeInTheDocument();
    });

    it('renders assistant message content', () => {
        const message: ChatMessage = {
            id: '2',
            type: 'assistant',
            content: 'Hello! How can I help you?',
            timestamp: new Date(),
        };

        render(<MessageBubble message={message} currentUser={currentUser} />);

        expect(screen.getByText('Hello! How can I help you?')).toBeInTheDocument();
    });

    it('shows current user name for user messages', () => {
        const message: ChatMessage = {
            id: '1',
            type: 'user',
            content: 'Test message',
            timestamp: new Date(),
        };

        render(<MessageBubble message={message} currentUser={currentUser} />);

        expect(screen.getByText('Giorgi Suladze')).toBeInTheDocument();
    });

    it('does not show current user name for assistant messages', () => {
        const message: ChatMessage = {
            id: '2',
            type: 'assistant',
            content: 'Test response',
            timestamp: new Date(),
        };

        render(<MessageBubble message={message} currentUser={currentUser} />);

        expect(screen.queryByText('Giorgi Suladze')).not.toBeInTheDocument();
    });

    it('renders a centered timestamp row', () => {
        const message: ChatMessage = {
            id: '1',
            type: 'user',
            content: 'Line 1',
            timestamp: new Date('2026-02-10T05:43:00'),
        };

        render(<MessageBubble message={message} currentUser={currentUser} />);

        expect(screen.getByText('Feb 10')).toBeInTheDocument();
        expect(screen.getByText(/5:43 AM/)).toBeInTheDocument();
    });

    describe('Markdown Support', () => {
        it('renders markdown in assistant messages', () => {
            const message: ChatMessage = {
                id: '1',
                type: 'assistant',
                content: '## Header\nThis is **bold** text',
                timestamp: new Date(),
            };
            render(<MessageBubble message={message} currentUser={currentUser} />);
            expect(screen.getByText('Header')).toBeInTheDocument();
            expect(screen.getByText('bold')).toBeInTheDocument();
        });

        it('renders markdown in user messages', () => {
            const message: ChatMessage = {
                id: '2',
                type: 'user',
                content: '**Bold message**',
                timestamp: new Date(),
            };
            render(<MessageBubble message={message} currentUser={currentUser} />);
            expect(screen.getByText('Bold message')).toBeInTheDocument();
        });

        it('renders plain text without markdown as-is', () => {
            const message: ChatMessage = {
                id: '3',
                type: 'assistant',
                content: 'Just plain text, no markdown',
                timestamp: new Date(),
            };
            render(<MessageBubble message={message} currentUser={currentUser} />);
            expect(screen.getByText('Just plain text, no markdown')).toBeInTheDocument();
        });

        it('types assistant replies progressively when enabled', () => {
            vi.useFakeTimers();

            const message: ChatMessage = {
                id: '4',
                type: 'assistant',
                content: 'Typed response',
                timestamp: new Date(),
            };

            render(<MessageBubble message={message} currentUser={currentUser} shouldType />);

            expect(screen.queryByText('Typed response')).not.toBeInTheDocument();

            act(() => {
                vi.advanceTimersByTime(1000);
            });

            expect(screen.getByText('Typed response')).toBeInTheDocument();
        });
    });

    it('renders live assistant progress details while a request is running', () => {
        const progress: BuildProgress = {
            status: 'running',
            iterations: 1,
            tokensUsed: 0,
            hasFileChanges: false,
            statusMessage: 'Creating product grid',
            messages: [],
            actions: [
                {
                    action: 'Updating',
                    target: 'Hero title',
                    details: '',
                    category: 'modifying',
                },
            ],
            toolCalls: [
                {
                    id: 'call-1',
                    tool: 'updateComponentProps',
                    params: { component: 'HeroSection' },
                },
            ],
            toolResults: [
                {
                    id: 'result-1',
                    tool: 'updateComponentProps',
                    success: true,
                    output: 'ok',
                },
            ],
            thinkingContent: null,
            thinkingStartTime: null,
            error: null,
            previewUrl: null,
        };

        render(<PendingAssistantBubble progress={progress} label="Creating the online store layout" />);

        expect(screen.getByText('Webu is working')).toBeInTheDocument();
        expect(screen.getAllByText('Creating the online store layout')).toHaveLength(2);
        expect(screen.getByText('Updating Hero title')).toBeInTheDocument();
        expect(screen.getByText('Tool: updateComponentProps')).toBeInTheDocument();
        expect(screen.getByText('updateComponentProps: ok')).toBeInTheDocument();
    });
});
