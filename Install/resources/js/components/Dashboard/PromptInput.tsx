import { useState } from 'react';
import { useTranslation } from '@/contexts/LanguageContext';
import { ChatInputWithMentions } from '@/components/Chat/ChatInputWithMentions';

interface Template {
    id: number;
    name: string;
    description: string | null;
    thumbnail: string | null;
    is_system: boolean;
}

interface PromptInputProps {
    onSubmit: (
        value: string,
        templateId: number | null,
        themePreset: string | null,
        mode: 'ai' | 'manual'
    ) => void;
    onQuickGenerate?: (value: string, themePreset: string | null) => void;
    disabled?: boolean;
    suggestions?: string[];
    typingPrompts?: string[];
    isLoadingSuggestions?: boolean;
    templates?: Template[];
}

const DEFAULT_TYPING_PROMPTS = [
    'Create a task management app with drag and drop...',
    'Design a landing page for my SaaS startup...',
    'Build a blog platform with markdown support...',
    'Create a dashboard for tracking analytics...',
    'Create a customer support knowledge base...',
    'Build a personal brand landing page...',
    'Build a social media feed with infinite scroll...',
];

export function PromptInput({
    onSubmit,
    onQuickGenerate,
    disabled = false,
    suggestions: _suggestions,
    typingPrompts: _typingPrompts = DEFAULT_TYPING_PROMPTS,
    templates: _templates = [],
}: PromptInputProps) {
    const { t } = useTranslation();
    const [prompt, setPrompt] = useState('');

    const handleSubmit = (e?: React.FormEvent) => {
        e?.preventDefault();
        if (prompt.trim() && !disabled) {
            if (onQuickGenerate) {
                onQuickGenerate(prompt.trim(), null);
            } else {
                onSubmit(prompt.trim(), null, null, 'ai');
            }
            setPrompt('');
        }
    };

    const aiPlaceholder = t('Write to Webu');

    return (
        <div className="mx-auto w-full max-w-[56rem]">
            <ChatInputWithMentions
                value={prompt}
                onChange={setPrompt}
                onSubmit={handleSubmit}
                disabled={disabled}
                selectedElement={null}
                onClearElement={() => {}}
                placeholder={aiPlaceholder}
                variant="workspace"
            />
        </div>
    );
}
