import ReactMarkdown from 'react-markdown';
import rehypeRaw from 'rehype-raw';
import rehypeSanitize from 'rehype-sanitize';
import { cn } from '@/lib/utils';
import { containsMarkdown } from '@/lib/markdown';

interface MarkdownRendererProps {
    content: string;
    className?: string;
}

/**
 * Renders markdown or plain text with auto-detection
 * - Auto-detects markdown syntax
 * - Sanitizes HTML for XSS protection
 * - Uses shadcn/ui design tokens via prose classes
 */
export function MarkdownRenderer({ content, className }: MarkdownRendererProps) {
    // If no markdown detected, render as plain text
    if (!containsMarkdown(content)) {
        return (
            <p className={cn('text-sm whitespace-pre-wrap break-words', className)}>
                {content}
            </p>
        );
    }

    return (
        <div className={cn(
            'chat-markdown prose prose-sm dark:prose-invert max-w-none break-words',
            'prose-headings:font-semibold prose-headings:text-foreground prose-headings:tracking-[-0.02em]',
            'prose-p:text-muted-foreground prose-p:leading-7 prose-p:my-0',
            'prose-strong:text-foreground prose-strong:font-semibold',
            'prose-code:bg-muted prose-code:text-foreground prose-code:border prose-code:border-border prose-code:px-1.5 prose-code:py-0.5 prose-code:rounded-md prose-code:text-[0.92em] prose-code:font-medium prose-code:before:content-none prose-code:after:content-none',
            'prose-pre:bg-muted prose-pre:border prose-pre:border-border prose-pre:overflow-x-auto prose-pre:rounded-2xl prose-pre:px-4 prose-pre:py-3',
            'prose-pre:[&_code]:border-0 prose-pre:[&_code]:bg-transparent prose-pre:[&_code]:p-0 prose-pre:[&_code]:font-normal',
            'prose-table:block prose-table:overflow-x-auto',
            'prose-a:text-primary prose-a:no-underline hover:prose-a:underline',
            'prose-ul:text-muted-foreground prose-ol:text-muted-foreground prose-ul:my-4 prose-ol:my-4',
            'prose-li:marker:text-muted-foreground prose-li:my-1',
            'prose-blockquote:my-4 prose-blockquote:border-l prose-blockquote:border-border prose-blockquote:pl-4 prose-blockquote:italic',
            'prose-hr:my-6',
            className
        )}>
            <ReactMarkdown rehypePlugins={[rehypeRaw, rehypeSanitize]}>
                {content}
            </ReactMarkdown>
        </div>
    );
}
