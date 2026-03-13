import { type FormEvent } from 'react';
import { ChevronLeft, Loader2, Pencil, Plus, RefreshCw, Save, Trash2 } from 'lucide-react';

import { useTranslation } from '@/contexts/LanguageContext';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RichTextField } from '@/components/ui/rich-text-field';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';

interface BlogPostSummary {
    id: number;
    site_id: string;
    title: string;
    slug: string;
    excerpt: string | null;
    content: string | null;
    status: 'draft' | 'published';
    cover_media_id: number | null;
    cover_media_url: string | null;
    published_at: string | null;
    created_at: string | null;
    updated_at: string | null;
}

interface BlogPostForm {
    title: string;
    slug: string;
    excerpt: string;
    content: string;
    status: 'draft' | 'published';
    coverMediaId: number | null;
    coverMediaUrl: string;
}

interface CmsBlogTabProps {
    blogPostForm: BlogPostForm;
    blogPostPendingDelete: BlogPostSummary | null;
    blogPosts: BlogPostSummary[];
    editingBlogPostId: number | null;
    isBlogPostDialogOpen: boolean;
    isBlogPostsLoading: boolean;
    isDeletingBlogPost: boolean;
    isSavingBlogPost: boolean;
    modalContentClassName: string;
    modalOverlayClassName: string;
    onBlogPostContentChange: (value: string) => void;
    onBlogPostPendingDeleteChange: (post: BlogPostSummary | null) => void;
    onBlogPostSlugChange: (value: string) => void;
    onBlogPostStatusChange: (status: 'draft' | 'published') => void;
    onBlogPostTitleChange: (value: string) => void;
    onCancelBlogPostDialog: () => void;
    onClearCoverImage: () => void;
    onDeleteBlogPostConfirm: () => void | Promise<void>;
    onEditBlogPost: (post: BlogPostSummary) => void;
    onOpenCoverImagePicker: () => void;
    onOpenCreateBlogPostDialog: () => void;
    onReloadBlogPosts: () => void | Promise<void>;
    onSaveBlogPostSubmit: (event: FormEvent<HTMLFormElement>) => void;
}

function formatDate(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    return date.toLocaleString();
}

export function CmsBlogTab({
    blogPostForm,
    blogPostPendingDelete,
    blogPosts,
    editingBlogPostId,
    isBlogPostDialogOpen,
    isBlogPostsLoading,
    isDeletingBlogPost,
    isSavingBlogPost,
    modalContentClassName,
    modalOverlayClassName,
    onBlogPostContentChange,
    onBlogPostPendingDeleteChange,
    onBlogPostSlugChange,
    onBlogPostStatusChange,
    onBlogPostTitleChange,
    onCancelBlogPostDialog,
    onClearCoverImage,
    onDeleteBlogPostConfirm,
    onEditBlogPost,
    onOpenCoverImagePicker,
    onOpenCreateBlogPostDialog,
    onReloadBlogPosts,
    onSaveBlogPostSubmit,
}: CmsBlogTabProps) {
    const { t } = useTranslation();

    return (
        <>
            {!isBlogPostDialogOpen ? (
                <Card>
                    <CardHeader className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <CardTitle>{t('Blog Posts')}</CardTitle>
                            <CardDescription>{t('Create, edit, and publish blog content')}</CardDescription>
                        </div>
                        <div className="flex items-center gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={onOpenCreateBlogPostDialog}
                            >
                                <Plus className="h-4 w-4 mr-1.5" />
                                {t('Add New Post')}
                            </Button>
                            <Button
                                variant="outline"
                                size="icon"
                                onClick={() => void onReloadBlogPosts()}
                                disabled={isBlogPostsLoading}
                                aria-label={t('Refresh Blog Posts')}
                            >
                                {isBlogPostsLoading ? <Loader2 className="h-4 w-4 animate-spin" /> : <RefreshCw className="h-4 w-4" />}
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {blogPosts.length === 0 ? (
                            <div className="py-10 text-center text-sm text-muted-foreground">
                                {isBlogPostsLoading ? t('Loading blog posts...') : t('No blog posts yet. Add your first post.')}
                            </div>
                        ) : (
                            <div className="overflow-x-auto rounded-lg border">
                                <table className="w-full min-w-[760px] text-sm">
                                    <thead className="bg-muted/40 text-left">
                                        <tr>
                                            <th className="px-4 py-3 font-medium">{t('Title')}</th>
                                            <th className="px-4 py-3 font-medium">{t('Slug')}</th>
                                            <th className="px-4 py-3 font-medium">{t('Status')}</th>
                                            <th className="px-4 py-3 font-medium">{t('Updated')}</th>
                                            <th className="px-4 py-3 text-right font-medium">{t('Actions')}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {blogPosts.map((post) => (
                                            <tr key={post.id} className="border-t">
                                                <td className="px-4 py-3">
                                                    <button
                                                        type="button"
                                                        className="text-left font-medium hover:underline"
                                                        onClick={() => onEditBlogPost(post)}
                                                    >
                                                        {post.title}
                                                    </button>
                                                </td>
                                                <td className="px-4 py-3 text-muted-foreground">/{post.slug}</td>
                                                <td className="px-4 py-3">
                                                    <Badge variant={post.status === 'published' ? 'default' : 'secondary'}>
                                                        {post.status}
                                                    </Badge>
                                                </td>
                                                <td className="px-4 py-3 text-muted-foreground">{formatDate(post.updated_at)}</td>
                                                <td className="px-4 py-3">
                                                    <div className="flex items-center justify-end gap-1">
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            className="h-8 w-8"
                                                            onClick={() => onEditBlogPost(post)}
                                                            aria-label={t('Edit Post')}
                                                        >
                                                            <Pencil className="h-4 w-4" />
                                                        </Button>
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            className="h-8 w-8 text-destructive"
                                                            onClick={() => onBlogPostPendingDeleteChange(post)}
                                                            aria-label={t('Delete Post')}
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </Button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            ) : (
                <Card>
                    <CardHeader className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div className="space-y-1">
                            <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                <button
                                    type="button"
                                    className="inline-flex items-center gap-1 hover:text-foreground"
                                    onClick={onCancelBlogPostDialog}
                                >
                                    <ChevronLeft className="h-3.5 w-3.5" />
                                    {t('Back to Posts')}
                                </button>
                            </div>
                            <CardTitle>{editingBlogPostId === null ? t('Add New Post') : t('Edit Post')}</CardTitle>
                            <CardDescription>{t('Manage blog post content and publish status')}</CardDescription>
                        </div>
                        <div className="flex items-center gap-2">
                            <Button type="button" variant="outline" onClick={onCancelBlogPostDialog}>
                                {t('Cancel')}
                            </Button>
                            <Button type="submit" form="blog-post-editor-form" disabled={isSavingBlogPost}>
                                {isSavingBlogPost ? <Loader2 className="h-4 w-4 mr-2 animate-spin" /> : <Save className="h-4 w-4 mr-2" />}
                                {editingBlogPostId === null ? t('Create Post') : t('Save Changes')}
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <form id="blog-post-editor-form" className="space-y-4" onSubmit={onSaveBlogPostSubmit}>
                            <div className="grid gap-3 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label>{t('Title')}</Label>
                                    <Input
                                        value={blogPostForm.title}
                                        onChange={(event) => onBlogPostTitleChange(event.target.value)}
                                        placeholder={t('Post title')}
                                        required
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label>{t('Slug')}</Label>
                                    <Input
                                        value={blogPostForm.slug}
                                        onChange={(event) => onBlogPostSlugChange(event.target.value)}
                                        placeholder="my-blog-post"
                                        required
                                    />
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label>{t('Content')}</Label>
                                <RichTextField
                                    value={blogPostForm.content}
                                    onChange={onBlogPostContentChange}
                                    toolbarPreset="advanced"
                                    showHtmlToggle
                                    minHeightClassName="min-h-[420px]"
                                    placeholder={t('Write your blog post content...')}
                                />
                            </div>

                            <div className="grid gap-3 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label>{t('Status')}</Label>
                                    <select
                                        value={blogPostForm.status}
                                        onChange={(event) => onBlogPostStatusChange(event.target.value === 'published' ? 'published' : 'draft')}
                                        className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                                    >
                                        <option value="draft">{t('Draft')}</option>
                                        <option value="published">{t('Published')}</option>
                                    </select>
                                </div>
                                <div className="space-y-2">
                                    <Label>{t('Cover Image')}</Label>
                                    <div className="flex items-center gap-2">
                                        <Button type="button" variant="outline" onClick={onOpenCoverImagePicker}>
                                            {t('Choose Image')}
                                        </Button>
                                        {blogPostForm.coverMediaUrl ? (
                                            <Button type="button" variant="ghost" onClick={onClearCoverImage}>
                                                {t('Remove')}
                                            </Button>
                                        ) : null}
                                    </div>
                                </div>
                            </div>

                            {blogPostForm.coverMediaUrl ? (
                                <div className="rounded-md border bg-muted/20 p-2">
                                    <img src={blogPostForm.coverMediaUrl} alt={t('Cover preview')} className="h-40 w-full rounded object-cover" />
                                </div>
                            ) : null}
                        </form>
                    </CardContent>
                </Card>
            )}

            <AlertDialog
                open={blogPostPendingDelete !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        onBlogPostPendingDeleteChange(null);
                    }
                }}
            >
                <AlertDialogContent overlayClassName={modalOverlayClassName} className={modalContentClassName}>
                    <AlertDialogHeader>
                        <AlertDialogTitle>{t('Delete Post?')}</AlertDialogTitle>
                        <AlertDialogDescription>
                            {t('This action cannot be undone.')}
                            {blogPostPendingDelete ? ` ${t('Post')}: ${blogPostPendingDelete.title}` : ''}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={isDeletingBlogPost}>{t('Cancel')}</AlertDialogCancel>
                        <AlertDialogAction
                            className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                            disabled={isDeletingBlogPost}
                            onClick={(event) => {
                                event.preventDefault();
                                void onDeleteBlogPostConfirm();
                            }}
                        >
                            {isDeletingBlogPost ? <Loader2 className="h-4 w-4 mr-2 animate-spin" /> : <Trash2 className="h-4 w-4 mr-2" />}
                            {t('Delete')}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}
