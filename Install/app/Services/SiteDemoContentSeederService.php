<?php

namespace App\Services;

use App\Cms\Contracts\CmsRepositoryContract;
use App\Ecommerce\Contracts\EcommerceInventoryServiceContract;
use App\Models\BlogPost;
use App\Models\Booking;
use App\Models\BookingAssignment;
use App\Models\BookingEvent;
use App\Models\BookingInvoice;
use App\Models\BookingPayment;
use App\Models\BookingService;
use App\Models\BookingStaffResource;
use App\Models\BookingStaffWorkSchedule;
use App\Models\EcommerceCategory;
use App\Models\EcommerceOrder;
use App\Models\EcommerceOrderPayment;
use App\Models\EcommerceProduct;
use App\Models\EcommerceProductImage;
use App\Models\GlobalSetting;
use App\Models\Media;
use App\Models\Page;
use App\Models\Project;
use App\Models\Site;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SiteDemoContentSeederService
{
    private const SEED_VERSION = 1;

    public function __construct(
        protected CmsRepositoryContract $repository,
        protected EcommerceInventoryServiceContract $inventory
    ) {}

    /**
     * @param  array{skip_ecommerce?: bool}  $options
     */
    public function seedForProject(Site $site, Project $project, array $options = []): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $site = $site->fresh();
        if (! $site || $this->isAlreadySeeded($site)) {
            return;
        }

        if ($this->hasExistingBusinessData($site)) {
            $this->markSeedState($site, [
                'seeded' => false,
                'skipped_reason' => 'existing_content',
            ]);

            return;
        }

        $project->loadMissing(['user', 'template']);
        $skipEcommerce = (bool) ($options['skip_ecommerce'] ?? false);

        DB::transaction(function () use ($site, $project, $skipEcommerce): void {
            $templateDemoContent = $this->loadTemplateDemoContent($project);
            $media = $this->ensureDemoMedia($site, $project);

            $this->ensureGlobalSettings($site, $project, $media);
            $this->ensureDemoSectionContent($site, $project, $media);
            $this->ensureBlogPosts($site, $project, $media, $templateDemoContent);
            if (! $skipEcommerce) {
                $this->ensureEcommerceCatalog($site, $project, $media, $templateDemoContent);
            }
            $this->ensureBookingData($site, $project);

            $templateDemoMeta = [];
            if (is_string($templateDemoContent['source'] ?? null)) {
                $templateDemoMeta['template_demo_content'] = [
                    'source' => $templateDemoContent['source'],
                    'products' => is_array($templateDemoContent['products'] ?? null) ? count($templateDemoContent['products']) : 0,
                    'posts' => is_array($templateDemoContent['posts'] ?? null) ? count($templateDemoContent['posts']) : 0,
                ];
            }

            $this->markSeedState($site, [
                'seeded' => true,
                'stats' => [
                    'media' => Media::query()->where('site_id', $site->id)->count(),
                    'posts' => BlogPost::query()->where('site_id', $site->id)->count(),
                    'products' => EcommerceProduct::query()->where('site_id', $site->id)->count(),
                    'booking_services' => BookingService::query()->where('site_id', $site->id)->count(),
                    'bookings' => Booking::query()->where('site_id', $site->id)->count(),
                ],
                ...$templateDemoMeta,
            ]);
        }, 3);
    }

    private function isEnabled(): bool
    {
        if (! (bool) config('cms.demo_content.enabled', true)) {
            return false;
        }

        if (app()->environment('testing') && ! (bool) config('cms.demo_content.seed_in_testing', false)) {
            return false;
        }

        return true;
    }

    private function isAlreadySeeded(Site $site): bool
    {
        return (bool) data_get($site->theme_settings, 'demo_content.seeded_at');
    }

    private function hasExistingBusinessData(Site $site): bool
    {
        return Media::query()->where('site_id', $site->id)->exists()
            || BlogPost::query()->where('site_id', $site->id)->exists()
            || EcommerceProduct::query()->where('site_id', $site->id)->exists()
            || BookingService::query()->where('site_id', $site->id)->exists()
            || Booking::query()->where('site_id', $site->id)->exists();
    }

    /**
     * @return array<string, Media>
     */
    private function ensureDemoMedia(Site $site, Project $project): array
    {
        $definitions = [
            'logo' => [
                'title' => 'ლოგო',
                'filename' => 'demo-logo',
                'candidates' => ['logo_dark.png', 'logo_light.png'],
            ],
            'hero' => [
                'title' => 'მთავარი ბანერი',
                'filename' => 'demo-hero',
                'candidates' => ['shop_banner_img1.jpg', 'shop_banner.jpg', 'menu_banner1.jpg'],
            ],
            'product_a' => [
                'title' => 'პროდუქტი A',
                'filename' => 'demo-product-a',
                'candidates' => ['shop_banner_img2.jpg', 'menu_banner2.jpg', 'cart_thamb1.jpg'],
            ],
            'product_b' => [
                'title' => 'პროდუქტი B',
                'filename' => 'demo-product-b',
                'candidates' => ['shop_banner_img3.jpg', 'menu_banner3.jpg', 'cart_thamb2.jpg'],
            ],
            'blog' => [
                'title' => 'ბლოგის ქავერი',
                'filename' => 'demo-blog',
                'candidates' => ['blog_img1.jpg', 'blog_img2.jpg', 'shop_banner_img4.jpg'],
            ],
        ];

        $mediaByKey = [];
        foreach ($definitions as $key => $definition) {
            $mediaByKey[$key] = $this->upsertDemoMediaItem(
                $site,
                $project,
                (string) $definition['filename'],
                (string) $definition['title'],
                is_array($definition['candidates'] ?? null) ? $definition['candidates'] : []
            );
        }

        return $mediaByKey;
    }

    private function upsertDemoMediaItem(
        Site $site,
        Project $project,
        string $filename,
        string $title,
        array $candidateFilenames = []
    ): Media {
        $sourcePath = $this->resolveSourceAssetPath($project, $candidateFilenames);

        if ($sourcePath) {
            $extension = Str::lower(pathinfo($sourcePath, PATHINFO_EXTENSION));
            $targetPath = "site-media/{$site->id}/demo/{$filename}.{$extension}";
            $binary = @file_get_contents($sourcePath);
            if ($binary !== false) {
                Storage::disk('public')->put($targetPath, $binary);
                $mime = (string) (mime_content_type($sourcePath) ?: 'application/octet-stream');
                $size = (int) strlen($binary);

                return $this->createOrUpdateMedia($site, $project, $targetPath, $mime, $size, $title);
            }
        }

        $targetPath = "site-media/{$site->id}/demo/{$filename}.svg";
        $svg = $this->buildPlaceholderSvg($title);
        Storage::disk('public')->put($targetPath, $svg);

        return $this->createOrUpdateMedia($site, $project, $targetPath, 'image/svg+xml', strlen($svg), $title);
    }

    private function createOrUpdateMedia(
        Site $site,
        Project $project,
        string $path,
        string $mime,
        int $size,
        string $title
    ): Media {
        $existing = $this->repository->findMediaByPath($site, $path);
        if ($existing) {
            $this->repository->updateMedia($existing, [
                'mime' => $mime,
                'size' => $size,
                'meta_json' => [
                    ...((array) ($existing->meta_json ?? [])),
                    'alt' => $title,
                    'description' => 'სატესტო მედია',
                ],
            ]);

            return $existing->fresh();
        }

        $media = $this->repository->createMedia($site, [
            'path' => $path,
            'mime' => $mime,
            'size' => $size,
            'meta_json' => [
                'alt' => $title,
                'description' => 'სატესტო მედია',
                'seeded' => true,
            ],
        ]);

        if ($size > 0) {
            $project->incrementStorageUsed($size);
        }

        return $media;
    }

    /**
     * @param  array<int, string>  $candidateFilenames
     */
    private function resolveSourceAssetPath(Project $project, array $candidateFilenames): ?string
    {
        if ($candidateFilenames === []) {
            return null;
        }

        $templateSlug = trim((string) ($project->template?->slug ?? ''));
        $root = dirname(base_path());

        $baseDirectories = array_filter([
            $templateSlug !== '' ? public_path("themes/{$templateSlug}/assets/images") : null,
            base_path('public/themes/'.$templateSlug.'/assets/images'),
            public_path('demo/products'),
            public_path('demo/hero'),
            public_path('demo/gallery'),
            public_path('demo/people'),
            public_path('demo/logos'),
            $root.'/Documentation/screenshots',
        ]);

        foreach ($baseDirectories as $baseDirectory) {
            foreach ($candidateFilenames as $candidateFilename) {
                $candidatePath = rtrim($baseDirectory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$candidateFilename;
                if (is_file($candidatePath)) {
                    return $candidatePath;
                }
            }
        }

        return null;
    }

    private function buildPlaceholderSvg(string $title): string
    {
        $safeTitle = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="800" viewBox="0 0 1200 800" role="img" aria-label="{$safeTitle}">
  <defs>
    <linearGradient id="g" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#eef2ff"/>
      <stop offset="100%" stop-color="#fdf2f8"/>
    </linearGradient>
  </defs>
  <rect width="1200" height="800" fill="url(#g)"/>
  <rect x="90" y="90" width="1020" height="620" rx="32" fill="#ffffff" fill-opacity="0.88" stroke="#d4d4d8"/>
  <text x="600" y="380" text-anchor="middle" font-family="Arial, sans-serif" font-size="54" fill="#111827">{$safeTitle}</text>
  <text x="600" y="440" text-anchor="middle" font-family="Arial, sans-serif" font-size="26" fill="#4b5563">სატესტო კონტენტი</text>
</svg>
SVG;
    }

    /**
     * @param  array<string, Media>  $media
     */
    private function ensureGlobalSettings(Site $site, Project $project, array $media): void
    {
        $settings = $this->repository->firstOrCreateGlobalSetting($site, [
            'contact_json' => [
                'email' => null,
                'phone' => null,
                'address' => null,
            ],
            'social_links_json' => [],
            'analytics_ids_json' => [],
        ]);

        $contact = is_array($settings->contact_json) ? $settings->contact_json : [];
        $social = is_array($settings->social_links_json) ? $settings->social_links_json : [];

        $domainSeed = Str::slug($project->name ?: 'demo-site');
        if ($domainSeed === '') {
            $domainSeed = 'demo-site';
        }

        $updates = [];
        $updates['contact_json'] = [
            'email' => (string) ($contact['email'] ?? "info+{$domainSeed}@webu.local"),
            'phone' => (string) ($contact['phone'] ?? '+995 555 00 11 22'),
            'address' => (string) ($contact['address'] ?? 'თბილისი, საქართველო'),
        ];

        if (! is_array($social) || $social === []) {
            $updates['social_links_json'] = [
                ['label' => 'Facebook', 'url' => 'https://facebook.com'],
                ['label' => 'Instagram', 'url' => 'https://instagram.com'],
                ['label' => 'LinkedIn', 'url' => 'https://linkedin.com'],
            ];
        }

        if (! $settings->logo_media_id && isset($media['logo'])) {
            $updates['logo_media_id'] = $media['logo']->id;
        }

        $this->repository->updateGlobalSetting($settings, $updates);
    }

    /**
     * @param  array<string, Media>  $media
     */
    private function ensureDemoSectionContent(Site $site, Project $project, array $media): void
    {
        $pages = $this->repository->listPages($site);
        foreach ($pages as $page) {
            $revision = $this->repository->latestRevision($site, $page);
            if (! $revision) {
                continue;
            }

            $content = is_array($revision->content_json) ? $revision->content_json : [];
            $sections = is_array($content['sections'] ?? null) ? $content['sections'] : [];
            if ($sections === []) {
                continue;
            }

            $changed = false;
            foreach ($sections as $index => $section) {
                if (! is_array($section)) {
                    continue;
                }

                $type = Str::lower(trim((string) ($section['type'] ?? '')));
                if ($type === '') {
                    continue;
                }

                $props = is_array($section['props'] ?? null) ? $section['props'] : [];
                $defaults = $this->defaultPropsForSection($type, $site, $page, $project, $media);
                $nextProps = $this->mergeMissingDefaults($props, $defaults);

                if ($nextProps !== $props) {
                    $sections[$index]['props'] = $nextProps;
                    $changed = true;
                }
            }

            if (! $changed) {
                continue;
            }

            $content['sections'] = array_values($sections);

            $this->repository->updateRevision($revision, [
                'content_json' => $content,
            ]);
        }
    }

    /**
     * @param  array<string, Media>  $media
     * @return array<string, mixed>
     */
    private function defaultPropsForSection(
        string $sectionType,
        Site $site,
        Page $page,
        Project $project,
        array $media
    ): array {
        $heroImage = $this->assetUrl($site, $media['hero'] ?? null);
        $productImage = $this->assetUrl($site, $media['product_a'] ?? null);
        $blogImage = $this->assetUrl($site, $media['blog'] ?? null);

        $name = $project->name !== '' ? $project->name : $site->name;

        if (str_contains($sectionType, 'hero')) {
            return [
                'eyebrow' => 'სატესტო კონტენტი',
                'headline' => "{$name} — სრულად გამზადებული დიზაინი",
                'subtitle' => 'შეცვალეთ ტექსტი, ფოტოები, პროდუქტები და სექციები რეალურ დროში.',
                'image_url' => $heroImage,
                'primary_cta' => [
                    'label' => 'დაიწყე ახლა',
                    'url' => '/contact',
                ],
                'secondary_cta' => [
                    'label' => 'ნახე პროდუქცია',
                    'url' => '/shop',
                ],
            ];
        }

        if (str_contains($sectionType, 'product') || str_contains($sectionType, 'shop') || str_contains($sectionType, 'cart') || str_contains($sectionType, 'checkout')) {
            return [
                'title' => 'სატესტო პროდუქცია',
                'headline' => 'ონლაინ მაღაზიის დემო ბლოკი',
                'subtitle' => 'პროდუქცია, ფასები და მარაგები ავტომატურად მოდის CMS-დან.',
                'image_url' => $productImage,
                'collection' => 'featured',
                'button' => [
                    'label' => 'კალათაში დამატება',
                    'url' => '/cart',
                ],
            ];
        }

        if (str_contains($sectionType, 'booking') || str_contains($sectionType, 'appointment')) {
            return [
                'title' => 'ჯავშნის სექცია',
                'headline' => 'სერვისები და ჯავშნები უკვე დამატებულია',
                'subtitle' => 'ტესტისთვის მზადაა კალენდარი, დროები და სტატუსები.',
                'cta' => [
                    'label' => 'დაჯავშნე ვიზიტი',
                    'url' => '/booking',
                ],
            ];
        }

        if (str_contains($sectionType, 'blog')) {
            return [
                'title' => 'ბლოგის სატესტო პოსტები',
                'headline' => 'კონტენტი უკვე დამატებულია',
                'subtitle' => 'განაახლეთ სტატიები ან დაამატეთ ახალი პოსტები.',
                'image_url' => $blogImage,
            ];
        }

        if (str_contains($sectionType, 'faq')) {
            return [
                'title' => 'ხშირად დასმული კითხვები',
                'items' => [
                    [
                        'q' => 'როგორ შევცვალო დიზაინი?',
                        'a' => 'აირჩიეთ სექცია, შეცვალეთ პარამეტრები და დააჭირეთ გამოქვეყნებას.',
                    ],
                    [
                        'q' => 'სად შევცვალო ფოტოები?',
                        'a' => 'მედია ბიბლიოთეკიდან ატვირთეთ ან აირჩიეთ არსებული ფაილი.',
                    ],
                ],
            ];
        }

        if (str_contains($sectionType, 'contact')) {
            return [
                'title' => 'კონტაქტი',
                'email' => 'support@webu.local',
                'phone' => '+995 555 33 44 55',
                'address' => 'თბილისი, საქართველო',
            ];
        }

        if (str_contains($sectionType, 'text') || str_contains($sectionType, 'rich')) {
            return [
                'title' => $page->title,
                'body' => "{$name}-ის ეს არის ქართული სატესტო კონტენტი. შეგიძლიათ შეცვალოთ ყველა ტექსტი, ფოტო და ბლოკი.",
            ];
        }

        return [
            'title' => $page->title,
            'subtitle' => 'ქართული სატესტო მონაცემები ავტომატურად დამატებულია.',
        ];
    }

    /**
     * @param  array<string, Media>  $media
     * @param  array<string, mixed>  $templateDemoContent
     */
    private function ensureBlogPosts(Site $site, Project $project, array $media, array $templateDemoContent = []): void
    {
        if (BlogPost::query()->where('site_id', $site->id)->exists()) {
            return;
        }

        $posts = $this->buildTemplateSeedBlogPosts($templateDemoContent);
        if ($posts === []) {
            $posts = [
            [
                'title' => 'როგორ დავიწყოთ ონლაინ გაყიდვები მარტივად',
                'slug' => 'rogor-daviwyot-online-gayidvebi',
                'excerpt' => 'მოკლე გზამკვლევი ონლაინ მაღაზიის სწრაფი გაშვებისთვის.',
                'content' => '<p>ეს არის ქართული სატესტო პოსტი. შეცვალეთ ტექსტი, სათაური და ფოტო თქვენი ბიზნესის მიხედვით.</p><p>ბილდერიდან შეგიძლიათ სექციების გადაადგილება და სრული ვიზუალური მართვა.</p>',
            ],
            [
                'title' => 'საუკეთესო პრაქტიკა პროდუქტის გვერდისთვის',
                'slug' => 'sauketeso-praktika-produqtis-gverdistvis',
                'excerpt' => 'როგორ წარმოვადგინოთ პროდუქტი, რომ კონვერსია გაიზარდოს.',
                'content' => '<p>გამოიყენეთ მკაფიო სათაური, კარგი ფოტო და მოკლე უპირატესობების სია.</p><p>ფასები, მარაგი და კატეგორიები სრულად იმართება CMS პანელიდან.</p>',
            ],
            [
                'title' => 'ჯავშნების მენეჯმენტი ერთ სივრცეში',
                'slug' => 'javshnebis-menejmenti-ert-sivrtseshi',
                'excerpt' => 'სერვისები, კალენდარი და კლიენტის სტატუსები ერთ ეკრანზე.',
                'content' => '<p>ბუქინგის მოდულში შეგიძლიათ დაამატოთ სერვისები, პერსონალი და სამუშაო დროები.</p><p>ჯავშნების სტატუსები და დროები რეალურ დროში განახლდება.</p>',
            ],
            ];
        }

        foreach ($posts as $index => $post) {
            $coverMedia = $this->resolveSeedBlogCoverMedia($site, $project, $media, $post, $index);

            BlogPost::query()->updateOrCreate(
                [
                    'site_id' => $site->id,
                    'slug' => $post['slug'],
                ],
                [
                    'title' => $post['title'],
                    'excerpt' => $post['excerpt'],
                    'content' => $post['content'],
                    'status' => 'published',
                    'cover_media_id' => $coverMedia?->id,
                    'published_at' => now()->subDays(max(1, $index + 1)),
                    'created_by' => $project->user_id,
                    'updated_by' => $project->user_id,
                ]
            );
        }
    }

    /**
     * @param  array<string, Media>  $media
     * @param  array<string, mixed>  $templateDemoContent
     */
    private function ensureEcommerceCatalog(Site $site, Project $project, array $media, array $templateDemoContent = []): void
    {
        if (EcommerceProduct::query()->where('site_id', $site->id)->exists()) {
            return;
        }

        $categories = [
            ['slug' => 'akhali-produqcia', 'name' => 'ახალი პროდუქცია', 'sort_order' => 1],
            ['slug' => 'popularuli', 'name' => 'პოპულარული', 'sort_order' => 2],
            ['slug' => 'sale', 'name' => 'აქციები', 'sort_order' => 3],
            ['slug' => 'recommended', 'name' => 'რეკომენდებული', 'sort_order' => 4],
        ];

        $categoryIds = [];
        foreach ($categories as $category) {
            $entry = EcommerceCategory::query()->updateOrCreate(
                [
                    'site_id' => $site->id,
                    'slug' => $category['slug'],
                ],
                [
                    'name' => $category['name'],
                    'description' => 'ქართული სატესტო კატეგორია.',
                    'status' => 'active',
                    'sort_order' => $category['sort_order'],
                    'meta_json' => ['seeded' => true],
                ]
            );

            $categoryIds[$category['slug']] = $entry->id;
        }

        $products = $this->buildTemplateSeedProducts($templateDemoContent, array_keys($categoryIds));
        if ($products === []) {
            $products = [
            [
                'slug' => 'smart-watch-pro',
                'name' => 'ჭკვიანი საათი პრო',
                'sku' => 'DEMO-SWP-001',
                'price' => '249.00',
                'compare_at_price' => '289.00',
                'category' => 'popularuli',
                'stock_quantity' => 18,
                'image' => 'product_a',
            ],
            [
                'slug' => 'wireless-headphones-x',
                'name' => 'უკაბელო ყურსასმენი X',
                'sku' => 'DEMO-WHX-002',
                'price' => '179.00',
                'compare_at_price' => '209.00',
                'category' => 'akhali-produqcia',
                'stock_quantity' => 24,
                'image' => 'product_b',
            ],
            [
                'slug' => 'home-aroma-set',
                'name' => 'სახლის არომა ნაკრები',
                'sku' => 'DEMO-HAS-003',
                'price' => '89.00',
                'compare_at_price' => '109.00',
                'category' => 'sale',
                'stock_quantity' => 31,
                'image' => 'hero',
            ],
            [
                'slug' => 'business-backpack-lite',
                'name' => 'ბიზნეს ზურგჩანთა ლაითი',
                'sku' => 'DEMO-BBL-004',
                'price' => '129.00',
                'compare_at_price' => null,
                'category' => 'recommended',
                'stock_quantity' => 16,
                'image' => 'product_a',
            ],
            ];
        }

        $seededProducts = [];

        foreach ($products as $index => $productData) {
            $product = EcommerceProduct::withTrashed()
                ->where('site_id', $site->id)
                ->where('slug', $productData['slug'])
                ->first();

            if (! $product) {
                $product = new EcommerceProduct;
                $product->site_id = $site->id;
                $product->slug = $productData['slug'];
            }

            if ($product->trashed()) {
                $product->restore();
            }

            $product->fill([
                'category_id' => $categoryIds[$productData['category']] ?? null,
                'name' => $productData['name'],
                'sku' => $productData['sku'],
                'short_description' => (string) ($productData['short_description'] ?? 'ქართული სატესტო აღწერა პროდუქტის სწრაფი შევსებისთვის.'),
                'description' => (string) ($productData['description'] ?? 'ეს არის დემო პროდუქტი. შეცვალეთ სათაური, ფასი, ფოტო და დეტალები თქვენი სურვილის მიხედვით.'),
                'price' => $productData['price'],
                'compare_at_price' => $productData['compare_at_price'],
                'currency' => (string) ($productData['currency'] ?? 'GEL'),
                'status' => 'active',
                'stock_tracking' => true,
                'stock_quantity' => $productData['stock_quantity'],
                'allow_backorder' => false,
                'is_digital' => false,
                'weight_grams' => (int) ($productData['weight_grams'] ?? 350),
                'attributes_json' => is_array($productData['attributes_json'] ?? null)
                    ? $productData['attributes_json']
                    : [
                        'color' => ['შავი', 'თეთრი'],
                        'size' => ['S', 'M', 'L'],
                    ],
                'seo_title' => $productData['name'].' | '.$site->name,
                'seo_description' => (string) ($productData['seo_description'] ?? 'სატესტო SEO აღწერა დემო პროდუქტისთვის.'),
                'published_at' => now()->subDays(2),
            ]);
            $product->save();

            $seededProducts[] = $product->fresh();

            $mediaEntry = $this->resolveSeedProductMedia($site, $project, $media, $productData, $index);
            if ($mediaEntry) {
                EcommerceProductImage::query()->updateOrCreate(
                    [
                        'site_id' => $site->id,
                        'product_id' => $product->id,
                        'sort_order' => 0,
                    ],
                    [
                        'media_id' => $mediaEntry->id,
                        'path' => $mediaEntry->path,
                        'alt_text' => $productData['name'],
                        'is_primary' => true,
                        'meta_json' => ['seeded' => true],
                    ]
                );
            }

            $this->inventory->syncInventorySnapshotForProduct($site, $product, reason: 'demo_seed');
        }

        if ($seededProducts !== []) {
            $this->ensureDemoOrder($site, $seededProducts);
        }
    }

    /**
     * @param  array<int, EcommerceProduct>  $products
     */
    private function ensureDemoOrder(Site $site, array $products): void
    {
        if (EcommerceOrder::query()->where('site_id', $site->id)->exists()) {
            return;
        }

        $lineItems = array_values(array_slice($products, 0, 2));
        $subtotal = collect($lineItems)->sum(fn (EcommerceProduct $product): float => (float) $product->price);
        $shipping = 9.90;
        $grandTotal = $subtotal + $shipping;

        $order = EcommerceOrder::query()->create([
            'site_id' => $site->id,
            'order_number' => 'DEMO-1001',
            'status' => 'processing',
            'payment_status' => 'paid',
            'fulfillment_status' => 'unfulfilled',
            'currency' => 'GEL',
            'customer_email' => 'demo.customer@webu.local',
            'customer_phone' => '+995 555 12 34 56',
            'customer_name' => 'დემო მომხმარებელი',
            'billing_address_json' => [
                'country_code' => 'GE',
                'city' => 'თბილისი',
                'address' => 'რუსთაველის გამზ. 1',
            ],
            'shipping_address_json' => [
                'country_code' => 'GE',
                'city' => 'თბილისი',
                'address' => 'ჭავჭავაძის გამზ. 7',
            ],
            'subtotal' => number_format($subtotal, 2, '.', ''),
            'tax_total' => '0.00',
            'shipping_total' => number_format($shipping, 2, '.', ''),
            'discount_total' => '0.00',
            'grand_total' => number_format($grandTotal, 2, '.', ''),
            'paid_total' => number_format($grandTotal, 2, '.', ''),
            'outstanding_total' => '0.00',
            'placed_at' => now()->subDay(),
            'paid_at' => now()->subDay()->addMinutes(12),
            'notes' => 'სატესტო შეკვეთა',
            'meta_json' => ['seeded' => true],
        ]);

        foreach ($lineItems as $item) {
            $unitPrice = (float) $item->price;

            $order->items()->create([
                'site_id' => $site->id,
                'product_id' => $item->id,
                'variant_id' => null,
                'name' => $item->name,
                'sku' => $item->sku,
                'quantity' => 1,
                'unit_price' => number_format($unitPrice, 2, '.', ''),
                'tax_amount' => '0.00',
                'discount_amount' => '0.00',
                'line_total' => number_format($unitPrice, 2, '.', ''),
                'options_json' => null,
                'meta_json' => ['seeded' => true],
            ]);
        }

        EcommerceOrderPayment::query()->create([
            'site_id' => $site->id,
            'order_id' => $order->id,
            'provider' => 'manual',
            'status' => 'captured',
            'method' => 'bank_transfer',
            'transaction_reference' => 'DEMO-PAY-1001',
            'amount' => number_format($grandTotal, 2, '.', ''),
            'currency' => 'GEL',
            'is_installment' => false,
            'installment_plan_json' => null,
            'raw_payload_json' => ['seeded' => true],
            'processed_at' => now()->subDay()->addMinutes(15),
        ]);
    }

    private function ensureBookingData(Site $site, Project $project): void
    {
        if (BookingService::query()->where('site_id', $site->id)->exists()) {
            return;
        }

        $staffEntries = [];
        $staffSeed = [
            ['slug' => 'mariam-consultant', 'name' => 'მარიამი — კონსულტანტი'],
            ['slug' => 'giorgi-specialist', 'name' => 'გიორგი — სპეციალისტი'],
        ];

        foreach ($staffSeed as $staffData) {
            $staff = BookingStaffResource::query()->updateOrCreate(
                [
                    'site_id' => $site->id,
                    'slug' => $staffData['slug'],
                ],
                [
                    'name' => $staffData['name'],
                    'type' => BookingStaffResource::TYPE_STAFF,
                    'status' => BookingStaffResource::STATUS_ACTIVE,
                    'email' => Str::slug($staffData['slug']).'@webu.local',
                    'phone' => '+995 555 45 67 89',
                    'timezone' => 'Asia/Tbilisi',
                    'max_parallel_bookings' => 2,
                    'buffer_minutes' => 10,
                    'meta_json' => ['seeded' => true],
                ]
            );

            for ($day = 1; $day <= 5; $day++) {
                BookingStaffWorkSchedule::query()->updateOrCreate(
                    [
                        'site_id' => $site->id,
                        'staff_resource_id' => $staff->id,
                        'day_of_week' => $day,
                        'start_time' => '10:00:00',
                        'end_time' => '18:00:00',
                    ],
                    [
                        'is_available' => true,
                        'timezone' => 'Asia/Tbilisi',
                        'effective_from' => null,
                        'effective_to' => null,
                        'meta_json' => ['seeded' => true],
                    ]
                );
            }

            $staffEntries[] = $staff;
        }

        $services = [];
        $serviceSeed = [
            ['slug' => 'initial-consultation', 'name' => 'საწყისი კონსულტაცია', 'price' => '70.00', 'duration' => 60],
            ['slug' => 'premium-session', 'name' => 'პრემიუმ სესია', 'price' => '120.00', 'duration' => 90],
            ['slug' => 'follow-up-visit', 'name' => 'შემდგომი ვიზიტი', 'price' => '50.00', 'duration' => 45],
        ];

        foreach ($serviceSeed as $seed) {
            $service = BookingService::query()->updateOrCreate(
                [
                    'site_id' => $site->id,
                    'slug' => $seed['slug'],
                ],
                [
                    'name' => $seed['name'],
                    'status' => BookingService::STATUS_ACTIVE,
                    'description' => 'ქართული სატესტო სერვისი.',
                    'duration_minutes' => $seed['duration'],
                    'buffer_before_minutes' => 10,
                    'buffer_after_minutes' => 10,
                    'slot_step_minutes' => 15,
                    'max_parallel_bookings' => 2,
                    'requires_staff' => true,
                    'allow_online_payment' => true,
                    'price' => $seed['price'],
                    'currency' => 'GEL',
                    'meta_json' => ['seeded' => true],
                ]
            );

            $services[] = $service;
        }

        $this->ensureDemoBookings($site, $project, $services, $staffEntries);
    }

    /**
     * @param  array<int, BookingService>  $services
     * @param  array<int, BookingStaffResource>  $staffEntries
     */
    private function ensureDemoBookings(Site $site, Project $project, array $services, array $staffEntries): void
    {
        if ($services === [] || $staffEntries === [] || Booking::query()->where('site_id', $site->id)->exists()) {
            return;
        }

        $seedBookings = [
            [
                'number' => 'DEMO-BKG-001',
                'status' => Booking::STATUS_CONFIRMED,
                'service' => $services[0],
                'staff' => $staffEntries[0],
                'starts_at' => CarbonImmutable::now('Asia/Tbilisi')->addDay()->setTime(11, 0),
                'customer_name' => 'დემო კლიენტი 1',
                'customer_email' => 'booking1@webu.local',
            ],
            [
                'number' => 'DEMO-BKG-002',
                'status' => Booking::STATUS_PENDING,
                'service' => $services[1],
                'staff' => $staffEntries[1],
                'starts_at' => CarbonImmutable::now('Asia/Tbilisi')->addDays(2)->setTime(14, 30),
                'customer_name' => 'დემო კლიენტი 2',
                'customer_email' => 'booking2@webu.local',
            ],
            [
                'number' => 'DEMO-BKG-003',
                'status' => Booking::STATUS_COMPLETED,
                'service' => $services[2],
                'staff' => $staffEntries[0],
                'starts_at' => CarbonImmutable::now('Asia/Tbilisi')->subDays(1)->setTime(12, 15),
                'customer_name' => 'დემო კლიენტი 3',
                'customer_email' => 'booking3@webu.local',
            ],
        ];

        foreach ($seedBookings as $seed) {
            $service = $seed['service'];
            $staff = $seed['staff'];
            $startsAt = $seed['starts_at'];
            $duration = max(15, (int) $service->duration_minutes);
            $endsAt = $startsAt->addMinutes($duration);
            $fee = number_format((float) $service->price, 2, '.', '');

            $booking = Booking::query()->updateOrCreate(
                [
                    'site_id' => $site->id,
                    'booking_number' => $seed['number'],
                ],
                [
                    'service_id' => $service->id,
                    'staff_resource_id' => $staff->id,
                    'status' => $seed['status'],
                    'source' => 'panel',
                    'customer_name' => $seed['customer_name'],
                    'customer_email' => $seed['customer_email'],
                    'customer_phone' => '+995 599 00 00 00',
                    'customer_notes' => 'სატესტო ჯავშანი',
                    'internal_notes' => 'დემო ჩანაწერი',
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'collision_starts_at' => $startsAt->subMinutes((int) $service->buffer_before_minutes),
                    'collision_ends_at' => $endsAt->addMinutes((int) $service->buffer_after_minutes),
                    'duration_minutes' => $duration,
                    'buffer_before_minutes' => (int) $service->buffer_before_minutes,
                    'buffer_after_minutes' => (int) $service->buffer_after_minutes,
                    'timezone' => 'Asia/Tbilisi',
                    'service_fee' => $fee,
                    'discount_total' => '0.00',
                    'tax_total' => '0.00',
                    'grand_total' => $fee,
                    'paid_total' => $seed['status'] === Booking::STATUS_COMPLETED ? $fee : '0.00',
                    'outstanding_total' => $seed['status'] === Booking::STATUS_COMPLETED ? '0.00' : $fee,
                    'currency' => 'GEL',
                    'confirmed_at' => in_array($seed['status'], [Booking::STATUS_CONFIRMED, Booking::STATUS_COMPLETED], true) ? now() : null,
                    'completed_at' => $seed['status'] === Booking::STATUS_COMPLETED ? now()->subDay() : null,
                    'meta_json' => ['seeded' => true],
                    'created_by' => $project->user_id,
                    'updated_by' => $project->user_id,
                ]
            );

            BookingAssignment::query()->updateOrCreate(
                [
                    'site_id' => $site->id,
                    'booking_id' => $booking->id,
                    'staff_resource_id' => $staff->id,
                ],
                [
                    'assignment_type' => 'primary',
                    'status' => 'assigned',
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'meta_json' => ['seeded' => true],
                    'created_by' => $project->user_id,
                ]
            );

            BookingEvent::query()->updateOrCreate(
                [
                    'site_id' => $site->id,
                    'event_key' => 'demo:'.$booking->booking_number,
                ],
                [
                    'booking_id' => $booking->id,
                    'event_type' => 'created',
                    'payload_json' => [
                        'seeded' => true,
                        'booking_number' => $booking->booking_number,
                        'status' => $booking->status,
                    ],
                    'occurred_at' => $startsAt->subHours(2),
                    'created_by' => $project->user_id,
                ]
            );

            $this->ensureDemoBookingInvoiceAndPayment($site, $booking, $project->user_id);
        }
    }

    private function ensureDemoBookingInvoiceAndPayment(Site $site, Booking $booking, ?int $actorId): void
    {
        $invoiceNumber = 'DEMO-INV-'.$booking->booking_number;

        $invoice = BookingInvoice::query()->updateOrCreate(
            [
                'site_id' => $site->id,
                'invoice_number' => $invoiceNumber,
            ],
            [
                'booking_id' => $booking->id,
                'status' => $booking->status === Booking::STATUS_COMPLETED ? 'paid' : 'issued',
                'currency' => $booking->currency,
                'subtotal' => $booking->service_fee,
                'tax_total' => '0.00',
                'discount_total' => '0.00',
                'grand_total' => $booking->grand_total,
                'paid_total' => $booking->paid_total,
                'outstanding_total' => $booking->outstanding_total,
                'issued_at' => now()->subDay(),
                'due_at' => now()->addDays(7),
                'paid_at' => $booking->status === Booking::STATUS_COMPLETED ? now()->subDay() : null,
                'meta_json' => ['seeded' => true],
                'created_by' => $actorId,
            ]
        );

        if ((float) $booking->paid_total <= 0) {
            return;
        }

        BookingPayment::query()->updateOrCreate(
            [
                'site_id' => $site->id,
                'transaction_reference' => 'DEMO-BPAY-'.$booking->booking_number,
            ],
            [
                'booking_id' => $booking->id,
                'invoice_id' => $invoice->id,
                'provider' => 'manual',
                'status' => 'captured',
                'method' => 'cash',
                'amount' => $booking->paid_total,
                'currency' => $booking->currency,
                'is_prepayment' => true,
                'processed_at' => now()->subDay(),
                'raw_payload_json' => ['seeded' => true],
                'meta_json' => ['seeded' => true],
                'created_by' => $actorId,
            ]
        );
    }

    /**
     * Load template-level demo-content JSON files, if present.
     *
     * @return array<string, mixed>
     */
    private function loadTemplateDemoContent(Project $project): array
    {
        $directory = $this->resolveTemplateDemoContentDirectory($project);
        if (! $directory) {
            return [];
        }

        $manifest = $this->readTemplateDemoJsonFile($directory.'/content.json');
        $datasets = (is_array($manifest) && $this->isAssociative($manifest) && is_array($manifest['datasets'] ?? null))
            ? $manifest['datasets']
            : [];

        $productsFile = $this->normalizeTemplateDemoDatasetFileName($datasets['products_file'] ?? null, 'products.json');
        $postsFile = $this->normalizeTemplateDemoDatasetFileName($datasets['posts_file'] ?? null, 'posts.json');

        $products = $this->readTemplateDemoJsonFile($directory.'/'.$productsFile);
        $posts = $this->readTemplateDemoJsonFile($directory.'/'.$postsFile);

        return [
            'source' => $directory,
            'manifest' => $manifest,
            'products' => is_array($products) ? $products : [],
            'posts' => is_array($posts) ? $posts : [],
        ];
    }

    private function resolveTemplateDemoContentDirectory(Project $project): ?string
    {
        $templateSlug = trim((string) ($project->template?->slug ?? ''));
        if ($templateSlug === '') {
            return null;
        }

        $repoRoot = dirname(base_path());
        $baseSlug = preg_replace('/-\d+$/', '', $templateSlug);
        if (! is_string($baseSlug) || $baseSlug === '') {
            $baseSlug = $templateSlug;
        }

        $candidates = array_values(array_unique(array_filter([
            base_path('templates/'.$templateSlug.'/demo-content'),
            base_path('templates/'.$baseSlug.'/demo-content'),
            $repoRoot.'/themeplate/'.$templateSlug.'/demo-content',
            $repoRoot.'/themeplate/'.$baseSlug.'/demo-content',
        ])));

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && is_dir($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @return array<int|string, mixed>|null
     */
    private function readTemplateDemoJsonFile(string $path): ?array
    {
        if (! is_file($path)) {
            return null;
        }

        $raw = @file_get_contents($path);
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function normalizeTemplateDemoDatasetFileName(mixed $value, string $fallback): string
    {
        if (! is_string($value)) {
            return $fallback;
        }

        $candidate = trim(str_replace('\\', '/', $value));
        if ($candidate === '') {
            return $fallback;
        }

        $candidate = basename($candidate);
        if ($candidate === '' || preg_match('/^[a-z0-9._-]+$/i', $candidate) !== 1) {
            return $fallback;
        }

        return $candidate;
    }

    private function normalizeTemplateDemoImageReference(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $reference = trim($value);
        if ($reference === '') {
            return null;
        }

        $path = parse_url($reference, PHP_URL_PATH);
        if (is_string($path) && $path !== '') {
            $reference = $path;
        }

        $basename = basename(str_replace('\\', '/', $reference));
        if ($basename === '') {
            return null;
        }

        if (preg_match('/\.(png|jpe?g|gif|webp|svg|avif)$/i', $basename) !== 1) {
            return null;
        }

        return $reference;
    }

    private function templateDemoImageCandidateFilename(?string $reference): ?string
    {
        if (! is_string($reference) || trim($reference) === '') {
            return null;
        }

        $path = parse_url($reference, PHP_URL_PATH);
        $source = is_string($path) && $path !== '' ? $path : $reference;
        $basename = basename(str_replace('\\', '/', $source));

        if ($basename === '' || preg_match('/\.(png|jpe?g|gif|webp|svg|avif)$/i', $basename) !== 1) {
            return null;
        }

        return $basename;
    }

    private function seedMediaFromTemplateReference(
        Site $site,
        Project $project,
        ?string $reference,
        string $filenameStem,
        string $title
    ): ?Media {
        $candidateFilename = $this->templateDemoImageCandidateFilename($reference);
        if (! $candidateFilename) {
            return null;
        }

        $safeStem = Str::slug($filenameStem);
        if ($safeStem === '') {
            $safeStem = 'demo-media';
        }

        return $this->upsertDemoMediaItem(
            $site,
            $project,
            $safeStem,
            $title,
            [$candidateFilename]
        );
    }

    /**
     * @param  array<string, Media>  $fallbackMedia
     * @param  array<string, mixed>  $post
     */
    private function resolveSeedBlogCoverMedia(
        Site $site,
        Project $project,
        array $fallbackMedia,
        array $post,
        int $index
    ): ?Media {
        $reference = is_string($post['image_reference'] ?? null) ? $post['image_reference'] : null;
        $title = (string) ($post['title'] ?? 'დემო პოსტი');
        $slug = (string) ($post['slug'] ?? ('post-'.($index + 1)));

        $media = $this->seedMediaFromTemplateReference(
            $site,
            $project,
            $reference,
            'demo-post-cover-'.$slug,
            $title
        );

        if ($media) {
            return $media;
        }

        $fallbackKey = $index % 2 === 0 ? 'blog' : 'hero';

        return Arr::get($fallbackMedia, $fallbackKey);
    }

    /**
     * @param  array<string, Media>  $fallbackMedia
     * @param  array<string, mixed>  $productData
     */
    private function resolveSeedProductMedia(
        Site $site,
        Project $project,
        array $fallbackMedia,
        array $productData,
        int $index
    ): ?Media {
        $reference = is_string($productData['image_reference'] ?? null) ? $productData['image_reference'] : null;
        $name = (string) ($productData['name'] ?? 'დემო პროდუქტი');
        $slug = (string) ($productData['slug'] ?? ('product-'.($index + 1)));

        $media = $this->seedMediaFromTemplateReference(
            $site,
            $project,
            $reference,
            'demo-product-'.$slug,
            $name
        );

        if ($media) {
            return $media;
        }

        $imageKey = trim((string) ($productData['image'] ?? ''));
        if ($imageKey !== '' && isset($fallbackMedia[$imageKey]) && $fallbackMedia[$imageKey] instanceof Media) {
            return $fallbackMedia[$imageKey];
        }

        $cycle = ['product_a', 'product_b', 'hero'];
        $fallbackKey = $cycle[$index % count($cycle)] ?? 'product_a';

        return Arr::get($fallbackMedia, $fallbackKey);
    }

    /**
     * @param  array<string, mixed>  $templateDemoContent
     * @return array<int, array{title:string,slug:string,excerpt:string,content:string}>
     */
    private function buildTemplateSeedBlogPosts(array $templateDemoContent): array
    {
        $rows = Arr::get($templateDemoContent, 'posts');
        if (! is_array($rows) || $rows === []) {
            return [];
        }

        $posts = [];
        $usedSlugs = [];

        foreach (array_values($rows) as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $title = trim((string) ($row['title'] ?? $row['name'] ?? ''));
            if ($title === '') {
                continue;
            }

            $slugBase = trim((string) ($row['slug'] ?? ''));
            $slugBase = $slugBase !== '' ? Str::slug($slugBase) : Str::slug($title);
            if ($slugBase === '') {
                $slugBase = 'demo-post-'.($index + 1);
            }

            $slug = $slugBase;
            $suffix = 2;
            while (isset($usedSlugs[$slug])) {
                $slug = $slugBase.'-'.$suffix;
                $suffix++;
            }
            $usedSlugs[$slug] = true;

            $contentText = trim((string) ($row['content'] ?? ''));
            if ($contentText === '') {
                $contentText = 'სატესტო ბლოგ პოსტი template demo-content JSON-დან.';
            }

            $content = $contentText;
            if (strip_tags($content) === $content) {
                $content = '<p>'.nl2br(htmlspecialchars($content, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')).'</p>';
            }

            $excerpt = trim((string) ($row['excerpt'] ?? ''));
            if ($excerpt === '') {
                $excerpt = Str::limit(trim(strip_tags($contentText)), 160, '...');
            }

            $posts[] = [
                'title' => $title,
                'slug' => $slug,
                'excerpt' => $excerpt !== '' ? $excerpt : 'სატესტო პოსტი',
                'content' => $content,
                'image_reference' => $this->normalizeTemplateDemoImageReference($row['image'] ?? null),
            ];
        }

        return $posts;
    }

    /**
     * @param  array<string, mixed>  $templateDemoContent
     * @param  array<int, string>  $categoryKeys
     * @return array<int, array<string, mixed>>
     */
    private function buildTemplateSeedProducts(array $templateDemoContent, array $categoryKeys): array
    {
        $rows = Arr::get($templateDemoContent, 'products');
        if (! is_array($rows) || $rows === [] || $categoryKeys === []) {
            return [];
        }

        $products = [];
        $usedSlugs = [];
        $imageKeys = ['product_a', 'product_b', 'hero'];

        foreach (array_values($rows) as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $name = trim((string) ($row['name'] ?? $row['title'] ?? ''));
            if ($name === '') {
                continue;
            }

            $rawPrice = $row['price'] ?? null;
            if (! is_numeric($rawPrice)) {
                continue;
            }

            $price = round((float) $rawPrice, 2);
            if ($price <= 0) {
                continue;
            }

            $slugBase = trim((string) ($row['slug'] ?? ''));
            $slugBase = $slugBase !== '' ? Str::slug($slugBase) : Str::slug($name);
            if ($slugBase === '') {
                $slugBase = 'demo-product-'.($index + 1);
            }

            $slug = $slugBase;
            $suffix = 2;
            while (isset($usedSlugs[$slug])) {
                $slug = $slugBase.'-'.$suffix;
                $suffix++;
            }
            $usedSlugs[$slug] = true;

            $compareAt = null;
            if (array_key_exists('compare_at_price', $row) && is_numeric($row['compare_at_price'])) {
                $compareCandidate = round((float) $row['compare_at_price'], 2);
                if ($compareCandidate > $price) {
                    $compareAt = number_format($compareCandidate, 2, '.', '');
                }
            } else {
                $compareCandidate = round($price * 1.15, 2);
                if ($compareCandidate > $price) {
                    $compareAt = number_format($compareCandidate, 2, '.', '');
                }
            }

            $sku = trim((string) ($row['sku'] ?? ''));
            if ($sku === '') {
                $sku = sprintf('DEMO-TPL-%03d', $index + 1);
            }

            $shortDescription = trim((string) ($row['short_description'] ?? $row['excerpt'] ?? ''));
            if ($shortDescription === '') {
                $shortDescription = 'სატესტო აღწერა template demo-content JSON-დან.';
            }

            $description = trim((string) ($row['description'] ?? $row['content'] ?? ''));
            if ($description === '') {
                $description = 'ეს არის template-specific დემო პროდუქტი. შეცვალეთ ინფორმაცია CMS პანელიდან.';
            }

            $imageField = trim((string) ($row['image'] ?? ''));
            $imageReference = $this->normalizeTemplateDemoImageReference($imageField !== '' ? $imageField : null);
            $imageKey = $imageKeys[$index % count($imageKeys)];
            if ($imageReference === null && $imageField !== '' && preg_match('/^[a-z0-9_-]+$/i', $imageField) === 1) {
                $imageKey = $imageField;
            }

            $products[] = [
                'slug' => $slug,
                'name' => $name,
                'sku' => $sku,
                'price' => number_format($price, 2, '.', ''),
                'compare_at_price' => $compareAt,
                'category' => $categoryKeys[$index % count($categoryKeys)],
                'stock_quantity' => max(5, 12 + ($index * 3)),
                'image' => $imageKey,
                'image_reference' => $imageReference,
                'short_description' => $shortDescription,
                'description' => $description,
                'currency' => 'GEL',
            ];
        }

        return $products;
    }

    private function assetUrl(Site $site, ?Media $media): ?string
    {
        if (! $media?->path) {
            return null;
        }

        return route('public.sites.assets', [
            'site' => $site->id,
            'path' => $media->path,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function markSeedState(Site $site, array $payload): void
    {
        $themeSettings = is_array($site->theme_settings) ? $site->theme_settings : [];
        $themeSettings['demo_content'] = [
            ...(is_array($themeSettings['demo_content'] ?? null) ? $themeSettings['demo_content'] : []),
            ...$payload,
            'seeded_at' => now()->toIso8601String(),
            'seed_version' => self::SEED_VERSION,
        ];

        $site->update([
            'theme_settings' => $themeSettings,
        ]);
    }

    /**
     * @param  array<string, mixed>  $props
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    private function mergeMissingDefaults(array $props, array $defaults): array
    {
        foreach ($defaults as $key => $value) {
            if (! array_key_exists($key, $props)) {
                $props[$key] = $value;

                continue;
            }

            if (is_array($value) && is_array($props[$key]) && $this->isAssociative($value) && $this->isAssociative($props[$key])) {
                $props[$key] = $this->mergeMissingDefaults($props[$key], $value);
            }
        }

        return $props;
    }

    /**
     * @param  array<int|string, mixed>  $value
     */
    private function isAssociative(array $value): bool
    {
        if ($value === []) {
            return false;
        }

        return array_keys($value) !== range(0, count($value) - 1);
    }
}
