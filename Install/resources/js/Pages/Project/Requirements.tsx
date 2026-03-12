import { useState, useRef, useEffect, useCallback } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ScrollArea } from '@/components/ui/scroll-area';
import { useTranslation } from '@/contexts/LanguageContext';
import AdminLayout from '@/Layouts/AdminLayout';
import { Loader2, Sparkles } from 'lucide-react';
import { toast } from 'sonner';
import { Toaster } from '@/components/ui/sonner';
import axios from 'axios';
import { route } from 'ziggy-js';
import type { PageProps } from '@/types';

interface Project {
    id: string;
    name: string;
    requirement_config: Record<string, unknown> | null;
    requirement_collection_state: string | null;
    conversation_history: Array<{ role: string; content: string }>;
}

interface QuestionnaireQuestion {
    key: string;
    label: string;
    placeholder?: string;
    type: string;
    options?: Array<{ value: string; label: string }>;
    required?: boolean;
    skip?: boolean;
}

interface RequirementsPageProps {
    project: Project;
}

export default function Requirements({ project }: RequirementsPageProps) {
    const { t } = useTranslation();
    const { auth } = usePage<PageProps>().props;
    const [messages, setMessages] = useState<Array<{ role: string; content: string }>>(
        Array.isArray(project.conversation_history) ? project.conversation_history : []
    );
    const [input, setInput] = useState('');
    const [loading, setLoading] = useState(false);
    const [configReady, setConfigReady] = useState(
        project.requirement_collection_state === 'complete' && project.requirement_config != null
    );
    const [generating, setGenerating] = useState(false);
    const scrollRef = useRef<HTMLDivElement>(null);

    const [questionnaireMode, setQuestionnaireMode] = useState<boolean | null>(null);
    const [questionnaireQuestion, setQuestionnaireQuestion] = useState<QuestionnaireQuestion | null>(null);
    const [questionnaireAnswers, setQuestionnaireAnswers] = useState<Record<string, unknown>>({});
    const [questionnaireLoading, setQuestionnaireLoading] = useState(false);

    const fetchQuestionnaireState = useCallback(async () => {
        try {
            const { data } = await axios.get<{
                completed: boolean;
                next_question: QuestionnaireQuestion | null;
                answers: Record<string, unknown>;
            }>(route('panel.projects.questionnaire.state', { project: project.id }));
            setQuestionnaireAnswers(data.answers ?? {});
            if (data.completed) {
                setConfigReady(true);
                setQuestionnaireQuestion(null);
            } else {
                setQuestionnaireQuestion(data.next_question ?? null);
            }
        } catch {
            setQuestionnaireMode(false);
        }
    }, [project.id]);

    useEffect(() => {
        if (configReady || project.requirement_collection_state === 'complete') return;
        axios.get(route('panel.projects.questionnaire.state', { project: project.id }))
            .then((res) => {
                const data = res.data as { completed: boolean; next_question: QuestionnaireQuestion | null; answers?: Record<string, unknown> };
                setQuestionnaireMode(true);
                setQuestionnaireAnswers(data.answers ?? {});
                if (data.completed) {
                    setConfigReady(true);
                } else {
                    setQuestionnaireQuestion(data.next_question ?? null);
                }
            })
            .catch(() => setQuestionnaireMode(false));
    }, [project.id, project.requirement_collection_state, configReady]);

    useEffect(() => {
        scrollRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages, questionnaireQuestion]);

    const handleQuestionnaireAnswer = async (value: unknown, skip?: boolean) => {
        if (!questionnaireQuestion || questionnaireLoading) return;
        setQuestionnaireLoading(true);
        try {
            const { data } = await axios.post<{
                completed: boolean;
                next_question: QuestionnaireQuestion | null;
                answers: Record<string, unknown>;
            }>(route('panel.projects.questionnaire.answer', { project: project.id }), {
                question_key: questionnaireQuestion.key,
                value: skip ? null : value,
            });
            setQuestionnaireAnswers(data.answers ?? {});
            if (data.completed) {
                setConfigReady(true);
                setQuestionnaireQuestion(null);
                toast.success(t('All set! Click "Generate my site" below.'));
            } else {
                setQuestionnaireQuestion(data.next_question ?? null);
            }
        } catch (e) {
            const msg = axios.isAxiosError(e) && e.response?.data?.error ? e.response.data.error : t('Something went wrong');
            toast.error(msg);
        } finally {
            setQuestionnaireLoading(false);
        }
    };

    const [multiChoiceSelected, setMultiChoiceSelected] = useState<string[]>([]);
    useEffect(() => {
        setMultiChoiceSelected([]);
    }, [questionnaireQuestion?.key]);
    const submitMultiChoice = () => {
        if (multiChoiceSelected.length > 0) {
            handleQuestionnaireAnswer(multiChoiceSelected);
            setMultiChoiceSelected([]);
        }
    };

    const handleSend = async () => {
        const msg = input.trim();
        if (!msg || loading) return;

        setInput('');
        setMessages((prev) => [...prev, { role: 'user', content: msg }]);
        setLoading(true);

        try {
            const { data } = await axios.post<{ type: string; text?: string; config?: Record<string, unknown> }>(
                route('panel.projects.requirement-step', { project: project.id }),
                { message: msg }
            );

            if (data.type === 'question' && data.text) {
                setMessages((prev) => [...prev, { role: 'assistant', content: data.text! }]);
            } else if (data.type === 'config' && data.config) {
                setMessages((prev) => [
                    ...prev,
                    { role: 'assistant', content: t("I have everything I need. Click \"Generate my site\" to create your store.") },
                ]);
                setConfigReady(true);
            }
        } catch (e) {
            const message = axios.isAxiosError(e) && e.response?.data?.message ? e.response.data.message : t('Something went wrong');
            toast.error(message);
            setMessages((prev) => prev.slice(0, -1));
        } finally {
            setLoading(false);
        }
    };

    const handleGenerate = async () => {
        if (generating || !configReady) return;
        setGenerating(true);
        try {
            const { data } = await axios.post<{ success?: boolean }>(
                route('panel.projects.generate-from-config', { project: project.id })
            );
            if (data.success) {
                toast.success(t('Site generated! Opening editor. You can open the live preview from the editor.'));
                router.visit(route('project.cms', { project: project.id }));
                return;
            }
        } catch (e) {
            const message = axios.isAxiosError(e) && e.response?.data?.error ? e.response.data.error : t('Failed to generate site');
            toast.error(message);
        } finally {
            setGenerating(false);
        }
    };

    const showInitialPrompt = messages.length === 0;

    return (
        <AdminLayout user={auth.user!} title={t('Build your store')}>
            <Head title={t('Build your store')} />
            <Toaster />
            <div className="container max-w-2xl mx-auto py-6 px-4">
                <div className="flex items-center gap-2 mb-6">
                    <Link
                        href={route('projects.index')}
                        className="text-sm text-muted-foreground hover:text-foreground"
                    >
                        {t('Projects')}
                    </Link>
                    <span className="text-muted-foreground">/</span>
                    <span className="font-medium">{project.name}</span>
                </div>

                <h1 className="text-2xl font-bold mb-2 flex items-center gap-2">
                    <Sparkles className="h-6 w-6 text-primary" />
                    {t('Build your store')}
                </h1>
                <p className="text-muted-foreground mb-6">
                    {t('Answer a few questions and we\'ll generate your online store.')}
                </p>

                <div className="rounded-lg border bg-card overflow-hidden flex flex-col min-h-[400px]">
                    <ScrollArea className="flex-1 p-4 min-h-[320px] max-h-[50vh]">
                        {questionnaireMode === true && questionnaireQuestion && (
                            <div className="space-y-4">
                                <p className="font-medium text-foreground">{questionnaireQuestion.label}</p>
                                {questionnaireQuestion.options && questionnaireQuestion.type === 'choice' && (
                                    <div className="flex flex-wrap gap-2">
                                        {questionnaireQuestion.options.map((opt) => (
                                            <Button
                                                key={opt.value}
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                disabled={questionnaireLoading}
                                                onClick={() => handleQuestionnaireAnswer(opt.value)}
                                            >
                                                {opt.label}
                                            </Button>
                                        ))}
                                    </div>
                                )}
                                {questionnaireQuestion.options && questionnaireQuestion.type === 'multi_choice' && (
                                    <div className="space-y-2">
                                        <div className="flex flex-wrap gap-2">
                                            {questionnaireQuestion.options.map((opt) => (
                                                <Button
                                                    key={opt.value}
                                                    type="button"
                                                    variant={multiChoiceSelected.includes(opt.value) ? 'default' : 'outline'}
                                                    size="sm"
                                                    disabled={questionnaireLoading}
                                                    onClick={() => setMultiChoiceSelected((prev) =>
                                                        prev.includes(opt.value) ? prev.filter((v) => v !== opt.value) : [...prev, opt.value]
                                                    )}
                                                >
                                                    {opt.label}
                                                </Button>
                                            ))}
                                        </div>
                                        <Button onClick={submitMultiChoice} disabled={questionnaireLoading || multiChoiceSelected.length === 0}>
                                            {t('Next')}
                                        </Button>
                                    </div>
                                )}
                                {(questionnaireQuestion.type === 'text' || !questionnaireQuestion.options) && questionnaireQuestion.type !== 'upload_or_skip' && questionnaireQuestion.type !== 'colors_or_skip' && (
                                    <form
                                        onSubmit={(e) => {
                                            e.preventDefault();
                                            const form = e.currentTarget;
                                            const inputField = form.querySelector<HTMLInputElement>('input');
                                            const v = inputField?.value?.trim();
                                            if (v) handleQuestionnaireAnswer(v);
                                        }}
                                        className="flex gap-2"
                                    >
                                        <Input
                                            placeholder={questionnaireQuestion.placeholder ?? t('Type your answer…')}
                                            disabled={questionnaireLoading}
                                            className="flex-1"
                                        />
                                        <Button type="submit" disabled={questionnaireLoading}>
                                            {t('Next')}
                                        </Button>
                                    </form>
                                )}
                                {(questionnaireQuestion.type === 'upload_or_skip' || questionnaireQuestion.type === 'colors_or_skip') && (
                                    <div className="flex gap-2">
                                        <Button variant="outline" onClick={() => handleQuestionnaireAnswer(null, true)} disabled={questionnaireLoading}>
                                            {t('Skip for now')}
                                        </Button>
                                    </div>
                                )}
                                {questionnaireQuestion.skip && (
                                    <Button variant="ghost" size="sm" onClick={() => handleQuestionnaireAnswer(null, true)} disabled={questionnaireLoading}>
                                        {t('Skip')}
                                    </Button>
                                )}
                            </div>
                        )}
                        {questionnaireMode === false && showInitialPrompt && (
                            <div className="rounded-lg bg-muted/50 p-4 text-sm text-muted-foreground">
                                {t('Example: "I want an online store for fashion" or "I need a store that sells electronics". Type your answer below and the AI will ask a few short questions.')}
                            </div>
                        )}
                        {questionnaireMode === false && messages.map((m, i) => (
                            <div
                                key={i}
                                className={`mt-3 flex ${m.role === 'user' ? 'justify-end' : 'justify-start'}`}
                            >
                                <div
                                    className={`max-w-[85%] rounded-lg px-4 py-2 text-sm ${
                                        m.role === 'user'
                                            ? 'bg-primary text-primary-foreground'
                                            : 'bg-muted'
                                    }`}
                                >
                                    {m.content}
                                </div>
                            </div>
                        ))}
                        {questionnaireMode === null && !configReady && (
                            <div className="flex items-center justify-center py-8">
                                <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
                            </div>
                        )}
                        {loading && (
                            <div className="mt-3 flex justify-start">
                                <div className="rounded-lg bg-muted px-4 py-2 flex items-center gap-2">
                                    <Loader2 className="h-4 w-4 animate-spin" />
                                    <span className="text-sm">{t('Thinking…')}</span>
                                </div>
                            </div>
                        )}
                        <div ref={scrollRef} />
                    </ScrollArea>

                    <div className="p-4 border-t bg-background">
                        {configReady ? (
                            <div className="flex flex-col gap-2">
                                <Button
                                    onClick={handleGenerate}
                                    disabled={generating}
                                    className="w-full"
                                >
                                    {generating ? (
                                        <>
                                            <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                                            {t('Generating…')}
                                        </>
                                    ) : (
                                        t('Generate my site')
                                    )}
                                </Button>
                                <p className="text-xs text-center text-muted-foreground">
                                    {t('Your store will be created with demo products. You can edit everything in the visual builder or return here to refine the design with chat.')}
                                </p>
                            </div>
                        ) : questionnaireMode === true && questionnaireQuestion ? null : questionnaireMode === false ? (
                            <form
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    handleSend();
                                }}
                                className="flex gap-2"
                            >
                                <Input
                                    value={input}
                                    onChange={(e) => setInput(e.target.value)}
                                    placeholder={t('Type your answer…')}
                                    disabled={loading}
                                    className="flex-1"
                                />
                                <Button type="submit" disabled={loading || !input.trim()}>
                                    {loading ? <Loader2 className="h-4 w-4 animate-spin" /> : t('Send')}
                                </Button>
                            </form>
                        ) : null}
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
