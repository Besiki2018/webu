<?php

namespace App\Http\Controllers\Cms;

use App\Cms\Contracts\CmsPanelBlogPostServiceContract;
use App\Cms\Exceptions\CmsDomainException;
use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PanelBlogPostController extends Controller
{
    public function __construct(
        protected CmsPanelBlogPostServiceContract $posts
    ) {}

    public function index(Site $site): JsonResponse
    {
        Gate::authorize('view', $site->project);

        return response()->json($this->posts->listPosts($site));
    }

    public function show(Site $site, BlogPost $blogPost): JsonResponse
    {
        Gate::authorize('view', $site->project);

        return response()->json($this->posts->getPost($site, $blogPost));
    }

    public function store(Request $request, Site $site): JsonResponse
    {
        Gate::authorize('update', $site->project);
        $actorId = $request->user()?->id ?? auth()->id();

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('blog_posts')->where(fn ($query) => $query->where('site_id', $site->id)),
            ],
            'excerpt' => ['nullable', 'string', 'max:5000'],
            'content' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in(['draft', 'published'])],
            'cover_media_id' => ['nullable', 'integer'],
        ]);

        try {
            $post = $this->posts->createPost($site, $validated, $actorId);
        } catch (CmsDomainException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return response()->json([
            'message' => 'Blog post created successfully.',
            'post' => $this->posts->getPost($site, $post)['post'],
        ], 201);
    }

    public function update(Request $request, Site $site, BlogPost $blogPost): JsonResponse
    {
        Gate::authorize('update', $site->project);
        $actorId = $request->user()?->id ?? auth()->id();

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('blog_posts')
                    ->ignore($blogPost->id)
                    ->where(fn ($query) => $query->where('site_id', $site->id)),
            ],
            'excerpt' => ['nullable', 'string', 'max:5000'],
            'content' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in(['draft', 'published'])],
            'cover_media_id' => ['nullable', 'integer'],
        ]);

        try {
            $post = $this->posts->updatePost($site, $blogPost, $validated, $actorId);
        } catch (CmsDomainException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return response()->json([
            'message' => 'Blog post updated successfully.',
            'post' => $this->posts->getPost($site, $post)['post'],
        ]);
    }

    public function destroy(Site $site, BlogPost $blogPost): JsonResponse
    {
        Gate::authorize('update', $site->project);

        $this->posts->deletePost($site, $blogPost);

        return response()->json([
            'message' => 'Blog post deleted successfully.',
        ]);
    }
}

