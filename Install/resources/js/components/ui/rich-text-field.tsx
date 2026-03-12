import { useEffect, useMemo, useState } from 'react';
import { EditorContent, useEditor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import Link from '@tiptap/extension-link';
import Placeholder from '@tiptap/extension-placeholder';
import TextAlign from '@tiptap/extension-text-align';
import { Button } from '@/components/ui/button';

interface RichTextFieldProps {
    value: string;
    onChange: (value: string) => void;
    minHeightClassName?: string;
    placeholder?: string;
    toolbarPreset?: 'basic' | 'advanced';
    showHtmlToggle?: boolean;
}

function escapeHtml(value: string): string {
    return value
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function toEditorHtml(value: string): string {
    const trimmed = value.trim();
    if (trimmed === '') {
        return '<p></p>';
    }

    const looksLikeHtml = /<([a-z][\w-]*)\b[^>]*>/i.test(value);
    if (looksLikeHtml) {
        return value;
    }

    const lines = value.split(/\r?\n/).map((line) => line.trimEnd());
    return lines
        .map((line) => (line.trim() === '' ? '<p><br></p>' : `<p>${escapeHtml(line)}</p>`))
        .join('');
}

function normalizeEmptyHtml(html: string): string {
    const compact = html.replace(/\s+/g, ' ').trim();
    if (compact === '<p></p>' || compact === '<p><br></p>') {
        return '';
    }

    return html;
}

export function RichTextField({
    value,
    onChange,
    minHeightClassName = 'min-h-[120px]',
    placeholder,
    toolbarPreset = 'basic',
    showHtmlToggle = false,
}: RichTextFieldProps) {
    const [isHtmlMode, setIsHtmlMode] = useState(false);
    const normalizedHtml = useMemo(() => toEditorHtml(value || ''), [value]);
    const plainTextStats = useMemo(() => {
        const text = (value || '')
            .replace(/<[^>]+>/g, ' ')
            .replace(/&nbsp;/gi, ' ')
            .replace(/\s+/g, ' ')
            .trim();

        return {
            chars: text.length,
            words: text === '' ? 0 : text.split(/\s+/).length,
        };
    }, [value]);

    const editor = useEditor({
        extensions: [
            StarterKit.configure({
                heading: { levels: [2, 3] },
            }),
            Link.configure({
                openOnClick: false,
                autolink: true,
                linkOnPaste: true,
                protocols: ['http', 'https', 'mailto', 'tel'],
            }),
            TextAlign.configure({
                types: ['heading', 'paragraph'],
                alignments: ['left', 'center', 'right'],
            }),
            Placeholder.configure({
                placeholder: placeholder ?? '',
            }),
        ],
        content: normalizedHtml,
        immediatelyRender: false,
        editorProps: {
            attributes: {
                class: `${minHeightClassName} rounded-md border bg-background p-3 text-sm leading-6 focus:outline-none focus:ring-2 focus:ring-primary/30 prose prose-sm max-w-none dark:prose-invert`,
            },
        },
        onUpdate: ({ editor: currentEditor }) => {
            const html = normalizeEmptyHtml(currentEditor.getHTML());
            if (html !== (value || '')) {
                onChange(html);
            }
        },
    }, [minHeightClassName, placeholder]);

    useEffect(() => {
        if (!editor || isHtmlMode) {
            return;
        }

        const currentHtml = normalizeEmptyHtml(editor.getHTML());
        const nextHtml = normalizeEmptyHtml(normalizedHtml);
        if (currentHtml !== nextHtml) {
            editor.commands.setContent(normalizedHtml, { emitUpdate: false });
        }
    }, [editor, isHtmlMode, normalizedHtml]);

    const focusEditor = () => {
        editor?.chain().focus().run();
    };

    const applyLink = () => {
        if (!editor) {
            return;
        }

        const currentHref = editor.getAttributes('link').href as string | undefined;
        const nextUrl = window.prompt('URL', currentHref || 'https://') || '';
        if (!nextUrl.trim()) {
            return;
        }

        editor.chain().focus().extendMarkRange('link').setLink({ href: nextUrl.trim() }).run();
    };

    const toolbarButtons = toolbarPreset === 'advanced'
        ? [
            { key: 'undo', label: '↶', action: () => editor?.chain().focus().undo().run() ?? false },
            { key: 'redo', label: '↷', action: () => editor?.chain().focus().redo().run() ?? false },
            { key: 'bold', label: 'B', action: () => editor?.chain().focus().toggleBold().run() ?? false, className: 'font-bold' },
            { key: 'italic', label: 'I', action: () => editor?.chain().focus().toggleItalic().run() ?? false, className: 'italic' },
            { key: 'h2', label: 'H2', action: () => editor?.chain().focus().toggleHeading({ level: 2 }).run() ?? false },
            { key: 'h3', label: 'H3', action: () => editor?.chain().focus().toggleHeading({ level: 3 }).run() ?? false },
            { key: 'p', label: 'P', action: () => editor?.chain().focus().setParagraph().run() ?? false },
            { key: 'quote', label: '❝', action: () => editor?.chain().focus().toggleBlockquote().run() ?? false },
            { key: 'ul', label: '• List', action: () => editor?.chain().focus().toggleBulletList().run() ?? false },
            { key: 'ol', label: '1. List', action: () => editor?.chain().focus().toggleOrderedList().run() ?? false },
            { key: 'left', label: '⇤', action: () => editor?.chain().focus().setTextAlign('left').run() ?? false },
            { key: 'center', label: '≡', action: () => editor?.chain().focus().setTextAlign('center').run() ?? false },
            { key: 'right', label: '⇥', action: () => editor?.chain().focus().setTextAlign('right').run() ?? false },
            { key: 'link', label: 'Link', action: applyLink },
            { key: 'unlink', label: 'Unlink', action: () => editor?.chain().focus().unsetLink().run() ?? false },
            { key: 'hr', label: '─', action: () => editor?.chain().focus().setHorizontalRule().run() ?? false },
            {
                key: 'clear',
                label: 'Clear',
                action: () => editor?.chain().focus().unsetAllMarks().clearNodes().run() ?? false,
            },
        ]
        : [
            { key: 'bold', label: 'B', action: () => editor?.chain().focus().toggleBold().run() ?? false, className: 'font-bold' },
            { key: 'italic', label: 'I', action: () => editor?.chain().focus().toggleItalic().run() ?? false, className: 'italic' },
            { key: 'ul', label: '• List', action: () => editor?.chain().focus().toggleBulletList().run() ?? false },
            { key: 'h3', label: 'H3', action: () => editor?.chain().focus().toggleHeading({ level: 3 }).run() ?? false },
            { key: 'link', label: 'Link', action: applyLink },
            {
                key: 'clear',
                label: 'Clear',
                action: () => editor?.chain().focus().unsetAllMarks().clearNodes().run() ?? false,
            },
        ];

    return (
        <div className="space-y-2">
            <div className="rounded-md border bg-muted/20 p-2">
                <div className="flex flex-wrap items-center gap-2">
                    {toolbarButtons.map((button) => (
                        <Button
                            key={button.key}
                            type="button"
                            variant="outline"
                            size="sm"
                            className={button.className ?? ''}
                            onClick={() => {
                                focusEditor();
                                button.action();
                            }}
                            disabled={!editor}
                        >
                            {button.label}
                        </Button>
                    ))}
                    {showHtmlToggle ? (
                        <Button
                            type="button"
                            variant={isHtmlMode ? 'default' : 'outline'}
                            size="sm"
                            className="ms-auto"
                            onClick={() => setIsHtmlMode((prev) => !prev)}
                        >
                            {isHtmlMode ? 'Visual' : 'HTML'}
                        </Button>
                    ) : null}
                </div>
                <div className="mt-2 text-xs text-muted-foreground">
                    {plainTextStats.words} words · {plainTextStats.chars} chars
                </div>
            </div>

            {isHtmlMode ? (
                <textarea
                    value={value}
                    onChange={(event) => onChange(event.target.value)}
                    placeholder={placeholder ?? ''}
                    className={`${minHeightClassName} w-full rounded-md border bg-background p-3 text-sm leading-6 font-mono focus:outline-none focus:ring-2 focus:ring-primary/30`}
                />
            ) : (
                <div className="[&_.ProseMirror]:outline-none [&_.ProseMirror]:whitespace-pre-wrap [&_.ProseMirror_p.is-editor-empty:first-child::before]:pointer-events-none [&_.ProseMirror_p.is-editor-empty:first-child::before]:float-left [&_.ProseMirror_p.is-editor-empty:first-child::before]:h-0 [&_.ProseMirror_p.is-editor-empty:first-child::before]:text-muted-foreground [&_.ProseMirror_p.is-editor-empty:first-child::before]:content-[attr(data-placeholder)]">
                    <EditorContent editor={editor} />
                </div>
            )}
        </div>
    );
}
