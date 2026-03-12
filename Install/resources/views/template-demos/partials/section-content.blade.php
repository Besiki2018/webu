@php
    $sharedDemoQuery = $sharedDemoQuery ?? [];
    $data = is_array($section['data'] ?? null) ? $section['data'] : [];
    $component = $section['component'] ?? 'generic';
    $normalizedKey = strtolower(trim((string) ($section['key'] ?? '')));
    $renderGeneric = ! ($isEcommerceTemplate ?? false);
    $products = is_array($data['products'] ?? null) ? $data['products'] : [];
    $categories = is_array($data['categories'] ?? null) ? $data['categories'] : [];
    $faqItems = is_array($data['items'] ?? null) ? $data['items'] : [];
@endphp
                @if($isEcommerceTemplate)
                    @switch($normalizedKey)
                        @case('hero')
                        @case('webu_general_heading_01')
                            @php
                                $heroVariant = strtolower(trim((string) ($data['layout_variant'] ?? $data['hero_variant'] ?? 'hero-1')));
                                if (! preg_match('/^hero-\d+$/', $heroVariant)) { $heroVariant = 'hero-1'; }
                                $heroHeadline = trim((string) ($data['headline'] ?? 'Headline'));
                                $heroSubheading = trim((string) ($data['subheading'] ?? 'Subheading text'));
                                $heroCtaLabel = trim((string) ($data['hero_cta_label'] ?? $data['cta_label'] ?? ''));
                                $heroCtaUrl = trim((string) ($data['hero_cta_url'] ?? $data['cta_url'] ?? '/shop'));
                                $heroCtaPath = trim($heroCtaUrl, '/');
                                $heroCtaQuery = array_filter([
                                    'site' => $siteId !== '' ? $siteId : null,
                                    'draft' => $draft !== '' ? $draft : null,
                                    'locale' => $locale !== '' ? $locale : null,
                                    'slug' => $heroCtaPath !== '' ? $heroCtaPath : 'home',
                                ], static fn ($value) => $value !== null);
                                $heroCtaHref = (str_starts_with($heroCtaUrl, 'http://') || str_starts_with($heroCtaUrl, 'https://'))
                                    ? $heroCtaUrl
                                    : route('template-demos.show', ['templateSlug' => $templateSlug, 'path' => $heroCtaPath !== '' ? $heroCtaPath : null] + $heroCtaQuery);
                                $heroCta2Label = trim((string) ($data['hero_cta_secondary_label'] ?? $data['cta_secondary_label'] ?? ''));
                                $heroCta2Url = trim((string) ($data['hero_cta_secondary_url'] ?? $data['cta_secondary_url'] ?? ''));
                                $heroCta2Path = trim($heroCta2Url, '/');
                                $heroCta2Query = array_filter([
                                    'site' => $siteId !== '' ? $siteId : null,
                                    'draft' => $draft !== '' ? $draft : null,
                                    'locale' => $locale !== '' ? $locale : null,
                                    'slug' => $heroCta2Path !== '' ? $heroCta2Path : 'home',
                                ], static fn ($value) => $value !== null);
                                $heroCta2Href = $heroCta2Url === '' ? '' : ((str_starts_with($heroCta2Url, 'http://') || str_starts_with($heroCta2Url, 'https://'))
                                    ? $heroCta2Url
                                    : route('template-demos.show', ['templateSlug' => $templateSlug, 'path' => $heroCta2Path !== '' ? $heroCta2Path : null] + $heroCta2Query));
                                $heroImageUrl = trim((string) ($data['hero_image_url'] ?? $data['image_url'] ?? $thumb ?? ''));
                                $heroImageAlt = trim((string) ($data['hero_image_alt'] ?? $data['image_alt'] ?? 'Hero'));
                                $heroOverlayImageUrl = trim((string) ($data['hero_overlay_image_url'] ?? $data['overlay_image_url'] ?? ''));
                                $heroOverlayImageAlt = trim((string) ($data['hero_overlay_image_alt'] ?? $data['overlay_image_alt'] ?? ''));
                                $heroStatValue = trim((string) ($data['hero_stat_value'] ?? $data['stat_value'] ?? ''));
                                $heroStatUnit = trim((string) ($data['hero_stat_unit'] ?? $data['stat_unit'] ?? ''));
                                $heroStatLabel = trim((string) ($data['hero_stat_label'] ?? $data['stat_label'] ?? ''));
                                $heroStatAvatars = is_array($data['hero_stat_avatars'] ?? null) ? $data['hero_stat_avatars'] : (is_array($data['stat_avatars'] ?? null) ? $data['stat_avatars'] : []);
                                $heroStatAvatars = array_values(array_filter(array_map(static function ($avatar): array {
                                    $item = is_array($avatar) ? $avatar : [];

                                    return [
                                        'url' => trim((string) ($item['url'] ?? '')),
                                        'alt' => trim((string) ($item['alt'] ?? '')),
                                    ];
                                }, $heroStatAvatars), static fn (array $avatar): bool => $avatar['url'] !== ''));
                                $heroHasStatCard = $heroStatValue !== '' || $heroStatUnit !== '' || $heroStatLabel !== '' || count($heroStatAvatars) > 0;
                                $heroSplitLeftBg = trim((string) ($data['left_background_color'] ?? '#f2eeee'));
                                $heroSplitRightBg = trim((string) ($data['right_background_color'] ?? '#f2eeee'));
                                $heroEyebrow = trim((string) ($data['eyebrow'] ?? ''));
                                $heroBadge = trim((string) ($data['badge_text'] ?? ''));
                                $heroSharedQuery = array_filter(['site' => $siteId !== '' ? $siteId : null, 'draft' => $draft !== '' ? $draft : null, 'locale' => $locale !== '' ? $locale : null, 'slug' => 'home'], static fn ($v) => $v !== null);
                            @endphp
                            @if($heroVariant === 'hero-1')
                                <section class="webu-hero-split-image webu-hero-split-image--hero-1">
                                    <div class="webu-hero-split-image__grid">
                                        <div class="webu-hero-split-image__left" style="background-color:{{ $heroSplitLeftBg }};">
                                            <div class="webu-hero-split-image__slider">
                                                <div class="webu-hero-split-image__slide webu-hero-split-image__slide--active" data-slide-index="0">
                                                    <div class="webu-hero-split-image__content">
                                                        @if($heroEyebrow !== '' || $heroBadge !== '')
                                                            <div class="webu-hero-split-image__top">
                                                                @if($heroBadge !== '')<span class="webu-hero-split-image__badge" data-webu-field="badge_text">{{ $heroBadge }}</span>@endif
                                                                @if($heroEyebrow !== '')<span class="webu-hero-split-image__eyebrow" data-webu-field="eyebrow">{{ $heroEyebrow }}</span>@endif
                                                            </div>
                                                        @endif
                                                        @if($heroHeadline !== '')<h1 class="webu-hero-split-image__headline" data-webu-field="headline">{{ $heroHeadline }}</h1>@endif
                                                        @if($heroSubheading !== '')<p class="webu-hero-split-image__description" data-webu-field="subheading">{{ $heroSubheading }}</p>@endif
                                                        @if(($heroCtaLabel !== '' && $heroCtaHref !== '') || ($heroCta2Label !== '' && $heroCta2Href !== ''))
                                                            <div class="webu-hero-split-image__actions">
                                                                @if($heroCtaLabel !== '' && $heroCtaHref !== '')
                                                                    <a href="{{ $heroCtaHref }}" class="webu-hero-split-image__cta" data-webu-field="hero_cta_url"><span data-webu-field="hero_cta_label">{{ $heroCtaLabel }}</span></a>
                                                                @endif
                                                                @if($heroCta2Label !== '' && $heroCta2Href !== '')
                                                                    <a href="{{ $heroCta2Href }}" class="webu-hero-split-image__cta webu-hero-split-image__cta--secondary" data-webu-field="hero_cta_secondary_url"><span data-webu-field="hero_cta_secondary_label">{{ $heroCta2Label }}</span></a>
                                                                @endif
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="webu-hero-split-image__right" style="background-color:{{ $heroSplitRightBg }};">
                                            @if($heroImageUrl !== '')
                                                <img src="{{ $heroImageUrl }}" alt="{{ $heroImageAlt }}" class="webu-hero-split-image__static-img" loading="lazy" data-webu-field="hero_image_url">
                                            @endif
                                        </div>
                                    </div>
                                    <div class="webu-hero-split-image__indicators webu-hero-split-image__indicators--static" aria-hidden="true">
                                        <span class="webu-hero-split-image__dot webu-hero-split-image__dot--active"></span>
                                        <span class="webu-hero-split-image__dot"></span>
                                        <span class="webu-hero-split-image__dot"></span>
                                    </div>
                                </section>
                            @else
                                {{-- hero-2: centered, image below --}}
                                @if($heroVariant === 'hero-2')
                                <section class="webu-hero webu-hero--hero-2">
                                    <div class="webu-hero__inner webu-hero__inner--centered">
                                        <div class="webu-hero__content">
                                            <h1 class="webu-hero__title" data-webu-field="headline">{{ $heroHeadline }}</h1>
                                            <p class="webu-hero__subtitle" data-webu-field="subheading">{{ $heroSubheading }}</p>
                                            <div class="webu-hero__ctas">
                                                @if($heroCtaLabel !== '' && $heroCtaHref !== '')<a href="{{ $heroCtaHref }}" class="webu-hero__cta webu-hero__cta--primary" data-webu-field="hero_cta_url"><span data-webu-field="hero_cta_label">{{ $heroCtaLabel }}</span></a>@endif
                                                @if($heroCta2Label !== '' && $heroCta2Href !== '')<a href="{{ $heroCta2Href }}" class="webu-hero__cta webu-hero__cta--secondary" data-webu-field="hero_cta_secondary_url"><span data-webu-field="hero_cta_secondary_label">{{ $heroCta2Label }}</span></a>@endif
                                            </div>
                                        </div>
                                        @if($heroImageUrl !== '')<div class="webu-hero__media webu-hero__media--below"><img src="{{ $heroImageUrl }}" alt="{{ $heroImageAlt }}" class="webu-hero__image" loading="lazy" data-webu-field="hero_image_url"></div>@endif
                                    </div>
                                </section>
                                {{-- hero-3: split reversed (image left, content right) --}}
                                @elseif($heroVariant === 'hero-3')
                                <section class="webu-hero webu-hero--hero-3">
                                    <div class="webu-hero__inner webu-hero__inner--split">
                                        @if($heroImageUrl !== '')<div class="webu-hero__media"><img src="{{ $heroImageUrl }}" alt="{{ $heroImageAlt }}" class="webu-hero__image" loading="lazy" data-webu-field="hero_image_url"></div>@endif
                                        <div class="webu-hero__content">
                                            <h1 class="webu-hero__title" data-webu-field="headline">{{ $heroHeadline }}</h1>
                                            <p class="webu-hero__subtitle" data-webu-field="subheading">{{ $heroSubheading }}</p>
                                            <div class="webu-hero__ctas">
                                                @if($heroCtaLabel !== '' && $heroCtaHref !== '')<a href="{{ $heroCtaHref }}" class="webu-hero__cta webu-hero__cta--primary" data-webu-field="hero_cta_url"><span data-webu-field="hero_cta_label">{{ $heroCtaLabel }}</span></a>@endif
                                                @if($heroCta2Label !== '' && $heroCta2Href !== '')<a href="{{ $heroCta2Href }}" class="webu-hero__cta webu-hero__cta--secondary" data-webu-field="hero_cta_secondary_url"><span data-webu-field="hero_cta_secondary_label">{{ $heroCta2Label }}</span></a>@endif
                                            </div>
                                        </div>
                                    </div>
                                </section>
                                {{-- hero-4: full-width image background, overlay text --}}
                                @elseif($heroVariant === 'hero-4')
                                <section class="webu-hero webu-hero--hero-4" @if($heroImageUrl !== '') style="background-image:url({{ $heroImageUrl }});" @endif data-webu-field-bg="hero_image_url">
                                    <div class="webu-hero__overlay"></div>
                                    <div class="webu-hero__inner webu-hero__inner--centered webu-hero__inner--overlay">
                                        <div class="webu-hero__content">
                                            <h1 class="webu-hero__title" data-webu-field="headline">{{ $heroHeadline }}</h1>
                                            <p class="webu-hero__subtitle" data-webu-field="subheading">{{ $heroSubheading }}</p>
                                            <div class="webu-hero__ctas">
                                                @if($heroCtaLabel !== '' && $heroCtaHref !== '')<a href="{{ $heroCtaHref }}" class="webu-hero__cta webu-hero__cta--primary" data-webu-field="hero_cta_url"><span data-webu-field="hero_cta_label">{{ $heroCtaLabel }}</span></a>@endif
                                                @if($heroCta2Label !== '' && $heroCta2Href !== '')<a href="{{ $heroCta2Href }}" class="webu-hero__cta webu-hero__cta--secondary" data-webu-field="hero_cta_secondary_url"><span data-webu-field="hero_cta_secondary_label">{{ $heroCta2Label }}</span></a>@endif
                                            </div>
                                        </div>
                                    </div>
                                </section>
                                {{-- hero-5: minimal, compact row --}}
                                @elseif($heroVariant === 'hero-5')
                                <section class="webu-hero webu-hero--hero-5">
                                    <div class="webu-hero__inner webu-hero__inner--minimal">
                                        <div class="webu-hero__content">
                                            <h1 class="webu-hero__title webu-hero__title--sm">{{ $heroHeadline }}</h1>
                                            <p class="webu-hero__subtitle webu-hero__subtitle--sm">{{ $heroSubheading }}</p>
                                            <div class="webu-hero__ctas">
                                                @if($heroCtaLabel !== '' && $heroCtaHref !== '')<a href="{{ $heroCtaHref }}" class="webu-hero__cta webu-hero__cta--primary">{{ $heroCtaLabel }}</a>@endif
                                                @if($heroCta2Label !== '' && $heroCta2Href !== '')<a href="{{ $heroCta2Href }}" class="webu-hero__cta webu-hero__cta--secondary">{{ $heroCta2Label }}</a>@endif
                                            </div>
                                        </div>
                                        @if($heroImageUrl !== '')<div class="webu-hero__media webu-hero__media--compact"><img src="{{ $heroImageUrl }}" alt="{{ $heroImageAlt }}" class="webu-hero__image" loading="lazy"></div>@endif
                                    </div>
                                </section>
                                {{-- hero-6: full bleed image, bottom-aligned text --}}
                                @elseif($heroVariant === 'hero-6')
                                <section class="webu-hero webu-hero--hero-6" @if($heroImageUrl !== '') style="background-image:url({{ $heroImageUrl }});" @endif>
                                    <div class="webu-hero__overlay webu-hero__overlay--strong"></div>
                                    <div class="webu-hero__inner webu-hero__inner--bottom webu-hero__inner--overlay">
                                        <div class="webu-hero__content">
                                            <h1 class="webu-hero__title">{{ $heroHeadline }}</h1>
                                            <p class="webu-hero__subtitle">{{ $heroSubheading }}</p>
                                            <div class="webu-hero__ctas">
                                                @if($heroCtaLabel !== '' && $heroCtaHref !== '')<a href="{{ $heroCtaHref }}" class="webu-hero__cta webu-hero__cta--primary">{{ $heroCtaLabel }}</a>@endif
                                                @if($heroCta2Label !== '' && $heroCta2Href !== '')<a href="{{ $heroCta2Href }}" class="webu-hero__cta webu-hero__cta--secondary">{{ $heroCta2Label }}</a>@endif
                                            </div>
                                        </div>
                                    </div>
                                </section>
                                @else
                                <section class="webu-hero webu-hero--hero-7">
                                    <div class="webu-hero__inner webu-hero__inner--finwave">
                                        <div class="webu-hero__content webu-hero__content--finwave">
                                            @if($heroEyebrow !== '')<span class="webu-hero__eyebrow webu-hero__eyebrow--finwave">{{ $heroEyebrow }}</span>@endif
                                            @if($heroHeadline !== '')<h1 class="webu-hero__title webu-hero__title--finwave">{{ $heroHeadline }}</h1>@endif
                                            @if($heroSubheading !== '')<p class="webu-hero__subtitle webu-hero__subtitle--finwave">{{ $heroSubheading }}</p>@endif
                                            @if(($heroCtaLabel !== '' && $heroCtaHref !== '') || $heroHasStatCard)
                                                <div class="webu-hero__cta-row webu-hero__cta-row--finwave">
                                                    @if($heroCtaLabel !== '' && $heroCtaHref !== '')<a href="{{ $heroCtaHref }}" class="webu-hero__cta webu-hero__cta--finwave">{{ $heroCtaLabel }}</a>@endif
                                                    @if($heroHasStatCard)
                                                        <div class="webu-hero__stat-card">
                                                            @if($heroStatAvatars !== [])
                                                                <div class="webu-hero__stat-avatars" aria-hidden="true">
                                                                    @foreach(array_slice($heroStatAvatars, 0, 4) as $avatar)
                                                                        <span class="webu-hero__stat-avatar">
                                                                            <img src="{{ $avatar['url'] }}" alt="{{ $avatar['alt'] }}" loading="lazy">
                                                                        </span>
                                                                    @endforeach
                                                                </div>
                                                            @endif
                                                            <div class="webu-hero__stat-copy">
                                                                @if($heroStatValue !== '' || $heroStatUnit !== '')
                                                                    <span class="webu-hero__stat-value">{{ $heroStatValue }}@if($heroStatUnit !== '')<span class="webu-hero__stat-unit">{{ $heroStatUnit }}</span>@endif</span>
                                                                @endif
                                                                @if($heroStatLabel !== '')<span class="webu-hero__stat-label">{{ $heroStatLabel }}</span>@endif
                                                            </div>
                                                        </div>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                        <div class="webu-hero__media webu-hero__media--finwave">
                                            @if($heroImageUrl !== '')<img src="{{ $heroImageUrl }}" alt="{{ $heroImageAlt }}" class="webu-hero__image webu-hero__image--finwave" loading="lazy">@endif
                                            @if($heroOverlayImageUrl !== '')<div class="webu-hero__floating-card"><img src="{{ $heroOverlayImageUrl }}" alt="{{ $heroOverlayImageAlt }}" loading="lazy"></div>@endif
                                        </div>
                                    </div>
                                </section>
                                @endif
                            @endif
                            @break

                        @case('hero_split_image')
                            @php
                                $heroSplitVariant = strtolower(trim((string) ($data['layout_variant'] ?? $data['hero_variant'] ?? 'hero-1')));
                                if (! preg_match('/^hero-\d+$/', $heroSplitVariant)) { $heroSplitVariant = 'hero-1'; }
                                $heroSplitSlides = is_array($data['slides'] ?? null) ? $data['slides'] : [];
                                $heroSplitLeftBg = trim((string) ($data['left_background_color'] ?? '#f2eeee'));
                                $heroSplitRightBg = trim((string) ($data['right_background_color'] ?? '#f2eeee'));
                                $heroSplitRightUrl = trim((string) ($data['right_image_url'] ?? ''));
                                $heroSplitRightAlt = trim((string) ($data['right_image_alt'] ?? ''));
                                if ($heroSplitSlides === []) {
                                    $heroSplitSlides = [['eyebrow' => 'Exclusive Offer', 'badge_text' => '-20% Off', 'headline' => 'Super Fast Performance', 'description' => 'We have prepared special discounts for you on electronic products. Don\'t miss these opportunities.', 'cta_label' => 'Shop Now', 'cta_url' => '/shop', 'image_url' => '', 'image_alt' => '']];
                                }
                                $heroSplitFirst = is_array($heroSplitSlides[0] ?? null) ? $heroSplitSlides[0] : [];
                                $heroSplitInitialRightUrl = trim((string) ($heroSplitFirst['image_url'] ?? ''));
                                if ($heroSplitInitialRightUrl === '') { $heroSplitInitialRightUrl = $heroSplitRightUrl; }
                                $heroSplitInitialRightAlt = trim((string) ($heroSplitFirst['image_alt'] ?? ''));
                                if ($heroSplitInitialRightAlt === '') { $heroSplitInitialRightAlt = $heroSplitRightAlt; }
                                $heroSplitSharedQuery = array_filter(['site' => $siteId !== '' ? $siteId : null, 'draft' => $draft !== '' ? $draft : null, 'locale' => $locale !== '' ? $locale : null, 'slug' => 'home'], static fn ($v) => $v !== null);
                            @endphp
                            @if($heroSplitVariant === 'hero-1')
                            {{-- Variant hero-1: editorial split hero slider --}}
                            <section class="webu-hero-split-image webu-hero-split-image--hero-1" data-hero-split-slider>
                                <div class="webu-hero-split-image__grid">
                                    <div class="webu-hero-split-image__left" style="background-color:{{ $heroSplitLeftBg }};">
                                        <div class="webu-hero-split-image__slider">
                                            @foreach($heroSplitSlides as $sIdx => $slide)
                                                @php
                                                    $s = is_array($slide) ? $slide : [];
                                                    $eyebrow = trim((string) ($s['eyebrow'] ?? ''));
                                                    $badge = trim((string) ($s['badge_text'] ?? ''));
                                                    $headline = trim((string) ($s['headline'] ?? ''));
                                                    $desc = trim((string) ($s['description'] ?? ''));
                                                    $ctaLabel = trim((string) ($s['cta_label'] ?? ''));
                                                    $ctaUrl = trim((string) ($s['cta_url'] ?? '/shop'));
                                                    $ctaPath = trim($ctaUrl, '/');
                                                    $ctaHref = (str_starts_with($ctaUrl, 'http') ? $ctaUrl : route('template-demos.show', ['templateSlug' => $templateSlug, 'path' => $ctaPath ?: null] + $heroSplitSharedQuery + ['slug' => $ctaPath ?: 'home']));
                                                    $slideImg = trim((string) ($s['image_url'] ?? ''));
                                                    $slideImgAlt = trim((string) ($s['image_alt'] ?? ''));
                                                    $slideRightImage = $slideImg !== '' ? $slideImg : $heroSplitRightUrl;
                                                    $slideRightAlt = $slideImgAlt !== '' ? $slideImgAlt : ($heroSplitRightAlt !== '' ? $heroSplitRightAlt : ($headline !== '' ? $headline : 'Hero slide'));
                                                @endphp
                                                <div class="webu-hero-split-image__slide {{ $sIdx === 0 ? 'webu-hero-split-image__slide--active' : '' }}" data-slide-index="{{ $sIdx }}" data-right-image="{{ $slideRightImage }}" data-right-alt="{{ $slideRightAlt }}">
                                                    <div class="webu-hero-split-image__content">
                                                        @if($eyebrow !== '' || $badge !== '')
                                                            <div class="webu-hero-split-image__top">
                                                                @if($badge !== '')<span class="webu-hero-split-image__badge">{{ $badge }}</span>@endif
                                                                @if($eyebrow !== '')<span class="webu-hero-split-image__eyebrow">{{ $eyebrow }}</span>@endif
                                                            </div>
                                                        @endif
                                                        @if($headline !== '')<h2 class="webu-hero-split-image__headline">{{ $headline }}</h2>@endif
                                                        @if($desc !== '')<p class="webu-hero-split-image__description">{{ $desc }}</p>@endif
                                                        @if($ctaLabel !== '' && $ctaHref !== '')
                                                            <div class="webu-hero-split-image__actions">
                                                                <a href="{{ $ctaHref }}" class="webu-hero-split-image__cta">{{ $ctaLabel }}</a>
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                    <div class="webu-hero-split-image__right" style="background-color:{{ $heroSplitRightBg }};">
                                        @if($heroSplitInitialRightUrl !== '')
                                            <img src="{{ $heroSplitInitialRightUrl }}" alt="{{ $heroSplitInitialRightAlt !== '' ? $heroSplitInitialRightAlt : 'Hero slide' }}" class="webu-hero-split-image__static-img" loading="lazy" data-hero-split-static-image>
                                        @endif
                                    </div>
                                </div>
                                @if(count($heroSplitSlides) > 1)
                                    <div class="webu-hero-split-image__indicators">
                                        @foreach($heroSplitSlides as $i => $sl)
                                            <button type="button" class="webu-hero-split-image__dot {{ $i === 0 ? 'webu-hero-split-image__dot--active' : '' }}" aria-label="{{ __('Slide') }} {{ $i + 1 }}" data-slide-to="{{ $i }}"></button>
                                        @endforeach
                                    </div>
                                @endif
                                <script>
                                (function(){
                                    var sliders = document.querySelectorAll('[data-hero-split-slider]');
                                    sliders.forEach(function(el){
                                        var slides = Array.prototype.slice.call(el.querySelectorAll('.webu-hero-split-image__slide'));
                                        var dots = Array.prototype.slice.call(el.querySelectorAll('.webu-hero-split-image__dot'));
                                        var staticImage = el.querySelector('[data-hero-split-static-image]');
                                        var rightPanel = el.querySelector('.webu-hero-split-image__right');
                                        function syncImage(slide) {
                                            if (!slide) return;
                                            var nextSrc = slide.getAttribute('data-right-image') || '';
                                            var nextAlt = slide.getAttribute('data-right-alt') || '';
                                            if (!staticImage && nextSrc && rightPanel) {
                                                staticImage = document.createElement('img');
                                                staticImage.className = 'webu-hero-split-image__static-img';
                                                staticImage.setAttribute('loading', 'lazy');
                                                staticImage.setAttribute('data-hero-split-static-image', 'true');
                                                rightPanel.appendChild(staticImage);
                                            }
                                            if (!staticImage) return;
                                            if (nextSrc) {
                                                staticImage.hidden = false;
                                                staticImage.setAttribute('src', nextSrc);
                                                staticImage.setAttribute('alt', nextAlt);
                                            } else {
                                                staticImage.hidden = true;
                                            }
                                        }
                                        function goTo(i) {
                                            slides.forEach(function(slide, index){ slide.classList.toggle('webu-hero-split-image__slide--active', index === i); });
                                            dots.forEach(function(dot, index){ dot.classList.toggle('webu-hero-split-image__dot--active', index === i); });
                                            syncImage(slides[i] || null);
                                        }
                                        dots.forEach(function(dot, i){ dot.addEventListener('click', function(){ goTo(i); }); });
                                        goTo(0);
                                    });
                                })();
                                </script>
                            </section>
                            @else
                            @php
                                $b1 = $heroSplitFirst;
                                $bHeadline = trim((string) ($b1['headline'] ?? 'Super Fast Performance'));
                                $bDesc = trim((string) ($b1['description'] ?? 'We have prepared special discounts for you.'));
                                $bCtaLabel = trim((string) ($b1['cta_label'] ?? 'Shop Now'));
                                $bCtaUrl = trim((string) ($b1['cta_url'] ?? '/shop'));
                                $bCtaPath = trim($bCtaUrl, '/');
                                $bCtaHref = (str_starts_with($bCtaUrl, 'http') ? $bCtaUrl : route('template-demos.show', ['templateSlug' => $templateSlug, 'path' => $bCtaPath ?: null] + $heroSplitSharedQuery + ['slug' => $bCtaPath ?: 'home']));
                                $bEyebrow = trim((string) ($b1['eyebrow'] ?? ''));
                                $bBadge = trim((string) ($b1['badge_text'] ?? ''));
                            @endphp
                            {{-- hero-2: banner stacked — content on top, image below --}}
                            @if($heroSplitVariant === 'hero-2')
                            <section class="webu-hero-split-image webu-hero-split-image--hero-2">
                                <div class="webu-hero-split-image__banner-stack">
                                    <div class="webu-hero-split-image__banner-content" style="background-color:{{ $heroSplitLeftBg }};">
                                        @if($bEyebrow !== '' || $bBadge !== '')
                                            <div class="webu-hero-split-image__top">
                                                @if($bEyebrow !== '')<span class="webu-hero-split-image__eyebrow">{{ $bEyebrow }}</span>@endif
                                                @if($bBadge !== '')<span class="webu-hero-split-image__badge">{{ $bBadge }}</span>@endif
                                            </div>
                                        @endif
                                        @if($bHeadline !== '')<h2 class="webu-hero-split-image__headline">{{ $bHeadline }}</h2>@endif
                                        @if($bDesc !== '')<p class="webu-hero-split-image__description">{{ $bDesc }}</p>@endif
                                        @if($bCtaLabel !== '' && $bCtaHref !== '')<a href="{{ $bCtaHref }}" class="webu-hero-split-image__cta">{{ $bCtaLabel }} →</a>@endif
                                    </div>
                                    @if($heroSplitRightUrl !== '')<div class="webu-hero-split-image__banner-media"><img src="{{ $heroSplitRightUrl }}" alt="{{ $heroSplitRightAlt }}" loading="lazy"></div>@endif
                                </div>
                            </section>
                            {{-- hero-3: split reversed — image left, content right --}}
                            @elseif($heroSplitVariant === 'hero-3')
                            <section class="webu-hero-split-image webu-hero-split-image--hero-3">
                                <div class="webu-hero-split-image__grid webu-hero-split-image__grid--reversed">
                                    <div class="webu-hero-split-image__right" style="background-color:{{ $heroSplitRightBg }};">
                                        @if($heroSplitRightUrl !== '')<img src="{{ $heroSplitRightUrl }}" alt="{{ $heroSplitRightAlt }}" class="webu-hero-split-image__static-img" loading="lazy">@endif
                                    </div>
                                    <div class="webu-hero-split-image__left" style="background-color:{{ $heroSplitLeftBg }};">
                                        <div class="webu-hero-split-image__content">
                                            @if($bEyebrow !== '' || $bBadge !== '')<div class="webu-hero-split-image__top">@if($bEyebrow !== '')<span class="webu-hero-split-image__eyebrow">{{ $bEyebrow }}</span>@endif @if($bBadge !== '')<span class="webu-hero-split-image__badge">{{ $bBadge }}</span>@endif</div>@endif
                                            @if($bHeadline !== '')<h2 class="webu-hero-split-image__headline">{{ $bHeadline }}</h2>@endif
                                            @if($bDesc !== '')<p class="webu-hero-split-image__description">{{ $bDesc }}</p>@endif
                                            @if($bCtaLabel !== '' && $bCtaHref !== '')<a href="{{ $bCtaHref }}" class="webu-hero-split-image__cta">{{ $bCtaLabel }} →</a>@endif
                                        </div>
                                    </div>
                                </div>
                            </section>
                            {{-- hero-4: full-width image background, overlay content --}}
                            @elseif($heroSplitVariant === 'hero-4')
                            <section class="webu-hero-split-image webu-hero-split-image--hero-4" @if($heroSplitRightUrl !== '') style="background-image:url({{ $heroSplitRightUrl }});" @endif>
                                <div class="webu-hero-split-image__overlay"></div>
                                <div class="webu-hero-split-image__overlay-content">
                                    @if($bEyebrow !== '' || $bBadge !== '')<div class="webu-hero-split-image__top">@if($bEyebrow !== '')<span class="webu-hero-split-image__eyebrow">{{ $bEyebrow }}</span>@endif @if($bBadge !== '')<span class="webu-hero-split-image__badge">{{ $bBadge }}</span>@endif</div>@endif
                                    @if($bHeadline !== '')<h2 class="webu-hero-split-image__headline">{{ $bHeadline }}</h2>@endif
                                    @if($bDesc !== '')<p class="webu-hero-split-image__description">{{ $bDesc }}</p>@endif
                                    @if($bCtaLabel !== '' && $bCtaHref !== '')<a href="{{ $bCtaHref }}" class="webu-hero-split-image__cta">{{ $bCtaLabel }} →</a>@endif
                                </div>
                            </section>
                            {{-- hero-5: minimal strip — compact row --}}
                            @elseif($heroSplitVariant === 'hero-5')
                            <section class="webu-hero-split-image webu-hero-split-image--hero-5">
                                <div class="webu-hero-split-image__minimal-row" style="background-color:{{ $heroSplitLeftBg }};">
                                    <div class="webu-hero-split-image__minimal-content">
                                        @if($bHeadline !== '')<h2 class="webu-hero-split-image__headline webu-hero-split-image__headline--sm">{{ $bHeadline }}</h2>@endif
                                        @if($bCtaLabel !== '' && $bCtaHref !== '')<a href="{{ $bCtaHref }}" class="webu-hero-split-image__cta">{{ $bCtaLabel }} →</a>@endif
                                    </div>
                                    @if($heroSplitRightUrl !== '')<div class="webu-hero-split-image__minimal-media"><img src="{{ $heroSplitRightUrl }}" alt="{{ $heroSplitRightAlt }}" loading="lazy"></div>@endif
                                </div>
                            </section>
                            {{-- hero-6: full bleed image, content at bottom --}}
                            @else
                            <section class="webu-hero-split-image webu-hero-split-image--hero-6" @if($heroSplitRightUrl !== '') style="background-image:url({{ $heroSplitRightUrl }});" @endif>
                                <div class="webu-hero-split-image__overlay webu-hero-split-image__overlay--strong"></div>
                                <div class="webu-hero-split-image__bottom-content">
                                    @if($bEyebrow !== '' || $bBadge !== '')<div class="webu-hero-split-image__top">@if($bEyebrow !== '')<span class="webu-hero-split-image__eyebrow">{{ $bEyebrow }}</span>@endif @if($bBadge !== '')<span class="webu-hero-split-image__badge">{{ $bBadge }}</span>@endif</div>@endif
                                    @if($bHeadline !== '')<h2 class="webu-hero-split-image__headline">{{ $bHeadline }}</h2>@endif
                                    @if($bDesc !== '')<p class="webu-hero-split-image__description">{{ $bDesc }}</p>@endif
                                    @if($bCtaLabel !== '' && $bCtaHref !== '')<a href="{{ $bCtaHref }}" class="webu-hero-split-image__cta">{{ $bCtaLabel }} →</a>@endif
                                </div>
                            </section>
                            @endif
                            @endif
                            @break

                        @case('banner')
                            @php
                                $bannerTitle = trim((string) ($data['title'] ?? $data['headline'] ?? ''));
                                $bannerSubtitle = trim((string) ($data['subtitle'] ?? $data['subheading'] ?? ''));
                                $bannerCtaLabel = trim((string) ($data['cta_label'] ?? ''));
                                $bannerCtaUrl = trim((string) ($data['cta_url'] ?? '/'));
                                $bannerCtaPath = trim($bannerCtaUrl, '/');
                                $bannerCtaQuery = array_filter([
                                    'site' => $siteId !== '' ? $siteId : null,
                                    'draft' => $draft !== '' ? $draft : null,
                                    'locale' => $locale !== '' ? $locale : null,
                                    'slug' => $bannerCtaPath !== '' ? $bannerCtaPath : 'home',
                                ], static fn ($value) => $value !== null);
                                $bannerCtaHref = (str_starts_with($bannerCtaUrl, 'http') ? $bannerCtaUrl : route('template-demos.show', ['templateSlug' => $templateSlug, 'path' => $bannerCtaPath !== '' ? $bannerCtaPath : null] + $bannerCtaQuery));
                            @endphp
                            <section class="webu-banner webu-banner--banner-1">
                                <div class="webu-banner__inner">
                                    @if($bannerTitle !== '')<h2 class="webu-banner__title">{{ $bannerTitle }}</h2>@endif
                                    @if($bannerSubtitle !== '')<p class="webu-banner__subtitle">{{ $bannerSubtitle }}</p>@endif
                                    @if($bannerCtaLabel !== '' && $bannerCtaHref !== '')
                                        <a href="{{ $bannerCtaHref }}" class="webu-banner__cta">{{ $bannerCtaLabel }}</a>
                                    @endif
                                </div>
                            </section>
                            @break

                        @case('webu_general_newsletter_01')
                            @php
                                $newsletterTitle = trim((string) ($data['title'] ?? 'Stay updated'));
                                $newsletterText = trim((string) ($data['text'] ?? $data['subtitle'] ?? 'Subscribe for offers and news.'));
                                $newsletterPlaceholder = trim((string) ($data['placeholder'] ?? 'Your email'));
                                $newsletterButtonLabel = trim((string) ($data['button_label'] ?? 'Subscribe'));
                            @endphp
                            <section class="webu-newsletter webu-newsletter--newsletter-1">
                                <div class="webu-newsletter__inner">
                                    <h3 class="webu-newsletter__title">{{ $newsletterTitle }}</h3>
                                    <p class="webu-newsletter__text">{{ $newsletterText }}</p>
                                    <form class="webu-newsletter__form" action="#" method="get" onsubmit="return false;">
                                        <input type="email" class="webu-newsletter__input" placeholder="{{ $newsletterPlaceholder }}" aria-label="{{ $newsletterPlaceholder }}">
                                        <button type="submit" class="webu-newsletter__button">{{ $newsletterButtonLabel }}</button>
                                    </form>
                                </div>
                            </section>
                            @break

                        @case('webu_general_text_01')
                            <p style="line-height:1.7;">{{ $data['body'] ?? '' }}</p>
                            @break

                        @case('webu_general_spacer_01')
                            @php $spacerHeight = (int) ($data['height'] ?? 24); @endphp
                            <div class="section-spacer" style="min-height: {{ max(0, $spacerHeight) }}px;" aria-hidden="true"></div>
                            @break

                        @case('webu_ecom_product_search_01')
                            <div data-webby-ecommerce-search>
                                <div class="split">
                                    <div>
                                        <label class="field-label">{{ $data['search_label'] ?? '' }}</label>
                                        <input class="field" type="text" value="{{ $data['query_preview'] ?? '' }}" readonly>
                                    </div>
                                    <div>
                                        <label class="field-label">{{ $data['scope_label'] ?? '' }}</label>
                                        <select class="field" disabled>
                                            @foreach((is_array($data['scope_options'] ?? null) ? $data['scope_options'] : []) as $scopeOption)
                                                <option>{{ $scopeOption }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="chip-row">
                                    @foreach((is_array($data['trending'] ?? null) ? $data['trending'] : []) as $trending)
                                        <span class="chip">{{ $trending }}</span>
                                    @endforeach
                                </div>
                            </div>
                            @break

                        @case('webu_ecom_category_list_01')
                            <div data-webby-ecommerce-categories>
                                <div class="chip-row">
                                    @foreach($categories as $category)
                                        <span class="chip">{{ $category['name'] ?? $category['slug'] ?? 'Category' }}</span>
                                    @endforeach
                                </div>
                            </div>
                            @break

                        @case('webu_ecom_product_grid_01')
                            @php
                                $productsCount = count($products);
                                $ctaLabel = trim((string) ($data['add_to_cart_label'] ?? 'Add to cart'));
                                $ctaLabel = $ctaLabel !== '' ? $ctaLabel : 'Add to cart';
                            @endphp
                            <div data-webby-ecommerce-products>
                                <div class="grid" data-webu-role="ecom-grid">
                                    @foreach($products as $item)
                                        @php
                                            $primaryImage = trim((string) ($item['image_url'] ?? ''));
                                            $fallbackHoverImage = $productsCount > 1
                                                ? (string) ($products[($loop->index + 1) % $productsCount]['image_url'] ?? '')
                                                : '';
                                            $secondaryImage = trim($fallbackHoverImage) !== '' ? trim($fallbackHoverImage) : $primaryImage;
                                            $productName = trim((string) ($item['name'] ?? 'Product'));
                                            $productCategory = trim((string) ($item['category_name'] ?? ($item['category_slug'] ?? 'Collection')));
                                            $productPrice = trim((string) ($item['price'] ?? ''));
                                            $productOldPrice = trim((string) ($item['old_price'] ?? ''));
                                            $currentPriceValue = (float) str_replace(',', '.', preg_replace('/[^0-9,\\.]/', '', $productPrice));
                                            $oldPriceValue = (float) str_replace(',', '.', preg_replace('/[^0-9,\\.]/', '', $productOldPrice));
                                            $salePercent = $oldPriceValue > 0 && $currentPriceValue > 0 && $oldPriceValue > $currentPriceValue
                                                ? (int) round((($oldPriceValue - $currentPriceValue) / $oldPriceValue) * 100)
                                                : 0;
                                            $productSlugValue = trim((string) ($item['slug'] ?? ''));
                                            $productHref = route('template-demos.show', ['templateSlug' => $templateSlug, 'path' => 'product'] + array_filter([
                                                'site' => $siteId !== '' ? $siteId : null,
                                                'draft' => $draft !== '' ? $draft : null,
                                                'locale' => $locale !== '' ? $locale : null,
                                                'slug' => 'product',
                                                'product_slug' => $productSlugValue !== '' ? $productSlugValue : null,
                                            ], static fn ($value) => $value !== null));
                                        @endphp
                                        <article class="ecom-card-modern" data-webu-role="ecom-card">
                                            <div data-webu-role="ecom-card-media">
                                                @php
                                                    $discountLabel = $salePercent > 0 ? 'GET ' . $salePercent . '% OFF' : ($productOldPrice !== '' ? 'GET 20% OFF' : ($loop->first ? 'GET 20% OFF' : ''));
                                                @endphp
                                                @if($discountLabel !== '')
                                                    <span class="ecom-card-badge-off" data-webu-role="ecom-card-badge-sale">{{ $discountLabel }}</span>
                                                @endif
                                                <a href="{{ $productHref }}" data-webu-role="ecom-card-image-link">
                                                    <img data-webu-role="ecom-card-image" src="{{ $primaryImage }}" alt="{{ $productName }}" loading="lazy" width="400" height="400">
                                                    <img data-webu-role="ecom-card-image-secondary" src="{{ $secondaryImage }}" alt="{{ $productName }}" loading="lazy" width="400" height="400">
                                                </a>
                                                <div class="ecom-card-actions-modern" data-webu-role="ecom-card-actions">
                                                    <a href="{{ $accountPreviewUrl ?? '#' }}" class="ecom-card-action-btn" aria-label="Wishlist" title="Wishlist">
                                                        <i class="ti ti-heart" aria-hidden="true" style="font-size:1.125rem;"></i>
                                                    </a>
                                                    <a href="{{ $productHref }}" class="ecom-card-action-btn" aria-label="Add to cart" title="Add to cart">
                                                        <i class="ti ti-shopping-cart" aria-hidden="true" style="font-size:1.125rem;"></i>
                                                    </a>
                                                </div>
                                            </div>
                                            <div data-webu-role="ecom-card-content" class="ecom-card-content-modern">
                                                <h3 data-webu-role="ecom-card-title"><a href="{{ $productHref }}">{{ $productName }}</a></h3>
                                                <span data-webu-role="ecom-card-price" class="ecom-card-price-modern">{{ $productPrice }}</span>
                                            </div>
                                        </article>
                                    @endforeach
                                </div>
                            </div>
                            @break

                        @case('webu_ecom_product_carousel_01')
                            @php
                                $productsCountCarousel = count($products);
                                $ctaLabelCarousel = trim((string) ($data['add_to_cart_label'] ?? 'Add to cart'));
                                $ctaLabelCarousel = $ctaLabelCarousel !== '' ? $ctaLabelCarousel : 'Add to cart';
                                $carouselVariant = strtolower(trim((string) ($data['layout_variant'] ?? $data['variant'] ?? 'design-01')));
                                if (! preg_match('/^design-\d+$/', $carouselVariant)) { $carouselVariant = 'design-01'; }
                                $isEditorialCarousel = in_array($carouselVariant, ['design-01', 'design-02'], true);
                                $carouselTitle = trim((string) ($data['title'] ?? 'Featured Products'));
                                $carouselSubtitle = trim((string) ($data['subtitle'] ?? ''));
                                $iconHeart = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19.5 12.57 12 20l-7.5-7.43a4.93 4.93 0 0 1 0-6.97 4.95 4.95 0 0 1 7 0L12 6.1l.5-.5a4.95 4.95 0 0 1 7 0 4.93 4.93 0 0 1 0 6.97Z"/></svg>';
                                $iconExpand = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 4H4v4"/><path d="m4 4 6 6"/><path d="M16 4h4v4"/><path d="m20 4-6 6"/><path d="M8 20H4v-4"/><path d="m4 20 6-6"/><path d="M16 20h4v-4"/><path d="m20 20-6-6"/></svg>';
                                $iconRefresh = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 5v5h-5"/><path d="M4 19v-5h5"/><path d="M6.5 9A7 7 0 0 1 18 6l2 4"/><path d="M17.5 15A7 7 0 0 1 6 18l-2-4"/></svg>';
                                $iconBag = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6.5 8h11l1.4 11.2a2 2 0 0 1-2 2.3H7.1a2 2 0 0 1-2-2.3Z"/><path d="M9 9V7a3 3 0 0 1 6 0v2"/></svg>';
                                $iconChevronLeft = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 6-6 6 6 6"/></svg>';
                                $iconChevronRight = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>';
                                $iconStar = '<svg viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m12 3.8 2.55 5.17 5.7.83-4.13 4.03.98 5.67L12 16.8 6.9 19.5l.98-5.67L3.75 9.8l5.7-.83L12 3.8Z"/></svg>';
                            @endphp
                            <div data-webby-ecommerce-product-carousel data-webu-product-carousel data-webu-carousel-variant="{{ $carouselVariant }}" class="product-carousel-wrap product-carousel-wrap--{{ $carouselVariant }}{{ $isEditorialCarousel ? ' product-carousel-wrap--editorial' : '' }}">
                                @if($carouselTitle !== '' || $carouselSubtitle !== '')
                                    <div class="product-carousel-header">
                                        @if($carouselTitle !== '')<h3 class="product-carousel-title">{{ $carouselTitle }}</h3>@endif
                                        @if($carouselSubtitle !== '')<p class="product-carousel-subtitle">{{ $carouselSubtitle }}</p>@endif
                                    </div>
                                @endif
                                @if(count($products) === 0)
                                    <p class="muted">{{ $data['empty_title'] ?? 'No products yet' }}</p>
                                    <p class="text-sm muted">{{ $data['empty_description'] ?? 'Add products in the catalog to show them here.' }}</p>
                                @else
                                    <div class="product-carousel-shell" data-webu-role="ecom-carousel-shell">
                                        <button type="button" class="product-carousel-nav product-carousel-nav--prev" data-webu-carousel-nav="prev" aria-label="Previous slide">{!! $iconChevronLeft !!}</button>
                                        <div class="product-carousel-viewport" data-webu-role="ecom-carousel-viewport">
                                            <div class="product-carousel-track" data-webu-role="ecom-carousel-track" role="list">
                                                @foreach($products as $item)
                                                    @php
                                                        $primaryImage = trim((string) ($item['image_url'] ?? ''));
                                                        $fallbackHoverImage = $productsCountCarousel > 1
                                                            ? (string) ($products[($loop->index + 1) % $productsCountCarousel]['image_url'] ?? '')
                                                            : '';
                                                        $secondaryImage = trim($fallbackHoverImage) !== '' ? trim($fallbackHoverImage) : $primaryImage;
                                                        $productName = trim((string) ($item['name'] ?? 'Product'));
                                                        $productCategory = trim((string) ($item['category_name'] ?? ($item['category_slug'] ?? 'Collection')));
                                                        $productPrice = trim((string) ($item['price'] ?? ''));
                                                        $productOldPrice = trim((string) ($item['old_price'] ?? ($item['compare_at_price'] ?? '')));
                                                        $currentPriceValue = (float) str_replace(',', '.', preg_replace('/[^0-9,\\.]/', '', $productPrice));
                                                        $oldPriceValue = (float) str_replace(',', '.', preg_replace('/[^0-9,\\.]/', '', $productOldPrice));
                                                        $salePercent = $oldPriceValue > 0 && $currentPriceValue > 0 && $oldPriceValue > $currentPriceValue
                                                            ? (int) round((($oldPriceValue - $currentPriceValue) / $oldPriceValue) * 100)
                                                            : 0;
                                                        $productSlugValue = trim((string) ($item['slug'] ?? ''));
                                                        $productHref = route('template-demos.show', ['templateSlug' => $templateSlug, 'path' => 'product'] + array_filter([
                                                            'site' => $siteId !== '' ? $siteId : null,
                                                            'draft' => $draft !== '' ? $draft : null,
                                                            'locale' => $locale !== '' ? $locale : null,
                                                            'slug' => 'product',
                                                            'product_slug' => $productSlugValue !== '' ? $productSlugValue : null,
                                                        ], static fn ($value) => $value !== null));
                                                        $discountLabelCarousel = $salePercent > 0 ? 'GET ' . $salePercent . '% OFF' : ($productOldPrice !== '' ? 'GET 20% OFF' : ($loop->first ? 'GET 20% OFF' : ''));
                                                        $reviewCount = (int) ($item['review_count'] ?? ($item['reviews_count'] ?? 1));
                                                        $reviewCount = $reviewCount > 0 ? $reviewCount : 1;
                                                        $reviewLabel = $reviewCount === 1 ? '1 review' : $reviewCount . ' reviews';
                                                    @endphp
                                                    @if($isEditorialCarousel)
                                                        <article class="product-carousel-card product-carousel-card--editorial" data-webu-role="ecom-card" role="listitem">
                                                            <div class="product-carousel-card__media" data-webu-role="ecom-card-media">
                                                                @if($salePercent > 0 || $productOldPrice !== '')
                                                                    <span class="product-carousel-card__badge">{{ $salePercent > 0 ? $salePercent . '%' : '20%' }}</span>
                                                                @endif
                                                                <a href="{{ $productHref }}" class="product-carousel-card__image-link" data-webu-role="ecom-card-image-link">
                                                                    <img data-webu-role="ecom-card-image" src="{{ $primaryImage }}" alt="{{ $productName }}" loading="lazy" width="420" height="640">
                                                                    <img data-webu-role="ecom-card-image-secondary" src="{{ $secondaryImage }}" alt="{{ $productName }}" loading="lazy" width="420" height="640">
                                                                </a>
                                                                <div class="product-carousel-card__actions">
                                                                    <a href="{{ $accountPreviewUrl ?? '#' }}" class="product-carousel-card__action" aria-label="Wishlist" title="Wishlist">{!! $iconHeart !!}</a>
                                                                    <a href="{{ $productHref }}" class="product-carousel-card__action" aria-label="Quick view" title="Quick view">{!! $iconExpand !!}</a>
                                                                    <a href="{{ $shopPreviewUrl ?? $productHref }}" class="product-carousel-card__action" aria-label="Compare" title="Compare">{!! $iconRefresh !!}</a>
                                                                    <a href="{{ $cartPreviewUrl ?? $productHref }}" class="product-carousel-card__action" aria-label="{{ $ctaLabelCarousel }}" title="{{ $ctaLabelCarousel }}">{!! $iconBag !!}</a>
                                                                </div>
                                                                <div class="product-carousel-card__gallery-indicators" aria-hidden="true">
                                                                    <span class="is-active"></span>
                                                                    <span></span>
                                                                    <span></span>
                                                                    <span></span>
                                                                </div>
                                                            </div>
                                                            <div class="product-carousel-card__meta" data-webu-role="ecom-card-content">
                                                                <div class="product-carousel-card__review">{!! $iconStar !!}<span>{{ $reviewLabel }}</span></div>
                                                                <h3 class="product-carousel-card__title" data-webu-role="ecom-card-title"><a href="{{ $productHref }}">{{ $productName }}</a></h3>
                                                                <div class="product-carousel-card__price-row">
                                                                    @if($productOldPrice !== '')
                                                                        <span class="product-carousel-card__price-old" data-webu-role="ecom-card-price-old">{{ $productOldPrice }}</span>
                                                                    @endif
                                                                    <span class="product-carousel-card__price" data-webu-role="ecom-card-price">{{ $productPrice }}</span>
                                                                </div>
                                                            </div>
                                                        </article>
                                                    @else
                                                        <article class="product-carousel-card ecom-card-modern" data-webu-role="ecom-card" role="listitem">
                                                            <div data-webu-role="ecom-card-media">
                                                                @if($discountLabelCarousel !== '')
                                                                    <span class="ecom-card-badge-off" data-webu-role="ecom-card-badge-sale">{{ $discountLabelCarousel }}</span>
                                                                @endif
                                                                <a href="{{ $productHref }}" data-webu-role="ecom-card-image-link">
                                                                    <img data-webu-role="ecom-card-image" src="{{ $primaryImage }}" alt="{{ $productName }}" loading="lazy" width="400" height="400">
                                                                    <img data-webu-role="ecom-card-image-secondary" src="{{ $secondaryImage }}" alt="{{ $productName }}" loading="lazy" width="400" height="400">
                                                                </a>
                                                                <div class="ecom-card-actions-modern" data-webu-role="ecom-card-actions">
                                                                    <a href="{{ $accountPreviewUrl ?? '#' }}" class="ecom-card-action-btn" aria-label="Wishlist" title="Wishlist">
                                                                        <i class="ti ti-heart" aria-hidden="true" style="font-size:1.125rem;"></i>
                                                                    </a>
                                                                    <a href="{{ $productHref }}" class="ecom-card-action-btn" aria-label="Add to cart" title="Add to cart">
                                                                        <i class="ti ti-shopping-cart" aria-hidden="true" style="font-size:1.125rem;"></i>
                                                                    </a>
                                                                </div>
                                                            </div>
                                                            <div data-webu-role="ecom-card-content" class="ecom-card-content-modern">
                                                                <h3 data-webu-role="ecom-card-title"><a href="{{ $productHref }}">{{ $productName }}</a></h3>
                                                                <span data-webu-role="ecom-card-price" class="ecom-card-price-modern">{{ $productPrice }}</span>
                                                            </div>
                                                        </article>
                                                    @endif
                                                @endforeach
                                            </div>
                                        </div>
                                        <button type="button" class="product-carousel-nav product-carousel-nav--next" data-webu-carousel-nav="next" aria-label="Next slide">{!! $iconChevronRight !!}</button>
                                    </div>
                                    <div class="product-carousel-pagination" data-webu-role="ecom-carousel-pagination" aria-label="Carousel pagination"></div>
                                    <script>
                                    (function(){
                                        var carousels = document.querySelectorAll('[data-webu-product-carousel]:not([data-webu-carousel-ready])');
                                        if (!carousels.length) return;

                                        Array.prototype.forEach.call(carousels, function (carousel) {
                                            carousel.setAttribute('data-webu-carousel-ready', '1');

                                            var track = carousel.querySelector('[data-webu-role="ecom-carousel-track"]');
                                            var prev = carousel.querySelector('[data-webu-carousel-nav="prev"]');
                                            var next = carousel.querySelector('[data-webu-carousel-nav="next"]');
                                            var pagination = carousel.querySelector('[data-webu-role="ecom-carousel-pagination"]');

                                            if (!track) return;

                                            var getCards = function () {
                                                return track.querySelectorAll('.product-carousel-card');
                                            };

                                            var getVisibleSlides = function () {
                                                var cards = getCards();
                                                if (!cards.length) return 1;
                                                var first = cards[0];
                                                var width = first.getBoundingClientRect().width || first.offsetWidth || 1;
                                                return Math.max(1, Math.round(track.clientWidth / width));
                                            };

                                            var getPageCount = function () {
                                                var cards = getCards().length;
                                                return Math.max(1, Math.ceil(cards / getVisibleSlides()));
                                            };

                                            var getActivePage = function () {
                                                var pageCount = getPageCount();
                                                var maxScroll = track.scrollWidth - track.clientWidth;
                                                if (pageCount <= 1 || maxScroll <= 0) return 0;
                                                var ratio = track.scrollLeft / maxScroll;
                                                return Math.max(0, Math.min(pageCount - 1, Math.round(ratio * (pageCount - 1))));
                                            };

                                            var updateControls = function () {
                                                var activePage = getActivePage();
                                                var pageCount = getPageCount();
                                                if (prev) prev.disabled = activePage <= 0;
                                                if (next) next.disabled = activePage >= pageCount - 1;
                                                if (!pagination) return;
                                                var dots = pagination.querySelectorAll('.product-carousel-pagination-dot');
                                                Array.prototype.forEach.call(dots, function (dot, index) {
                                                    dot.classList.toggle('is-active', index === activePage);
                                                });
                                            };

                                            var scrollToPage = function (pageIndex) {
                                                var cards = getCards();
                                                var visibleSlides = getVisibleSlides();
                                                var target = cards[pageIndex * visibleSlides];
                                                if (!target) return;
                                                track.scrollTo({ left: target.offsetLeft, behavior: 'smooth' });
                                            };

                                            var renderPagination = function () {
                                                if (!pagination) return;
                                                var pageCount = getPageCount();
                                                pagination.innerHTML = '';
                                                pagination.style.display = pageCount > 1 ? '' : 'none';

                                                for (var i = 0; i < pageCount; i += 1) {
                                                    (function (pageIndex) {
                                                        var dot = document.createElement('button');
                                                        dot.type = 'button';
                                                        dot.className = 'product-carousel-pagination-dot';
                                                        dot.setAttribute('aria-label', 'Go to slide ' + (pageIndex + 1));
                                                        dot.addEventListener('click', function () {
                                                            scrollToPage(pageIndex);
                                                        });
                                                        pagination.appendChild(dot);
                                                    })(i);
                                                }

                                                updateControls();
                                            };

                                            if (prev) {
                                                prev.addEventListener('click', function () {
                                                    scrollToPage(Math.max(0, getActivePage() - 1));
                                                });
                                            }

                                            if (next) {
                                                next.addEventListener('click', function () {
                                                    scrollToPage(Math.min(getPageCount() - 1, getActivePage() + 1));
                                                });
                                            }

                                            var scrollFrame = 0;
                                            track.addEventListener('scroll', function () {
                                                if (scrollFrame) window.cancelAnimationFrame(scrollFrame);
                                                scrollFrame = window.requestAnimationFrame(updateControls);
                                            }, { passive: true });

                                            window.addEventListener('resize', renderPagination);
                                            renderPagination();
                                        });
                                    })();
                                    </script>
                                @endif
                            </div>
                            @break

                        @case('webu_ecom_cart_icon_01')
                            <a href="{{ route('template-demos.show', ['templateSlug' => $templateSlug, 'path' => 'cart'] + array_filter([
                                'site' => $siteId !== '' ? $siteId : null,
                                'draft' => $draft !== '' ? $draft : null,
                                'locale' => $locale !== '' ? $locale : null,
                                'slug' => 'cart',
                            ], static fn ($value) => $value !== null)) }}"
                               style="text-decoration:none;color:inherit;">
                                <div class="summary-box" data-webby-ecommerce-cart-icon>
                                    <div class="summary-row"><span>{{ $data['items_label'] ?? '' }}</span><strong data-webu-role="ecom-cart-icon-badge">{{ $data['items_count'] ?? '' }}</strong></div>
                                    <div class="summary-row"><span>{{ $data['subtotal_label'] ?? '' }}</span><strong data-webu-role="ecom-cart-icon-total">{{ $data['estimated_subtotal'] ?? '' }}</strong></div>
                                    <div class="chip-row">
                                        @foreach((is_array($data['chips'] ?? null) ? $data['chips'] : []) as $chip)
                                            <span class="chip">{{ $chip }}</span>
                                        @endforeach
                                    </div>
                                </div>
                            </a>
                            @break

                        @case('webu_ecom_product_gallery_01')
                            @php
                                $fallbackProductSlug = (string) (($products[0]['slug'] ?? '') ?: '');
                                $productSlugForWidget = $selectedProductSlug !== '' ? $selectedProductSlug : $fallbackProductSlug;
                                $galleryItems = is_array($data['gallery_items'] ?? null) ? $data['gallery_items'] : [];
                            @endphp
                            <div class="product-single-gallery" data-webby-ecommerce-product-gallery @if($productSlugForWidget !== '') data-product-slug="{{ $productSlugForWidget }}" @endif>
                                <div class="product-single-gallery-main">
                                    <img src="{{ $data['main_image_url'] ?? '' }}" alt="{{ $data['main_image_alt'] ?? 'Product image' }}" loading="lazy" width="600" height="600">
                                </div>
                                @if(count($galleryItems) > 0)
                                    <div class="product-single-gallery-thumbs">
                                        @foreach($galleryItems as $item)
                                            <button type="button" class="product-single-gallery-thumb" aria-label="{{ $item['alt'] ?? 'Gallery image' }}">
                                                <img src="{{ $item['image_url'] ?? '' }}" alt="" loading="lazy" width="80" height="80">
                                            </button>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                            @break

                        @case('webu_ecom_product_detail_01')
                            @php
                                $product = is_array($data['product'] ?? null) ? $data['product'] : [];
                                $fallbackDetailSlug = (string) (($product['slug'] ?? '') ?: ($products[0]['slug'] ?? ''));
                                $detailSlugForWidget = $selectedProductSlug !== '' ? $selectedProductSlug : $fallbackDetailSlug;
                                $productHighlights = is_array($data['highlights'] ?? null) ? $data['highlights'] : [];
                            @endphp
                            <div class="product-single-info" data-webby-ecommerce-product-detail @if($detailSlugForWidget !== '') data-product-slug="{{ $detailSlugForWidget }}" @endif>
                                <h1 class="product-single-title">{{ $product['name'] ?? 'Product' }}</h1>
                                <p class="product-single-meta">{{ $product['sku'] ?? '' }}@if(!empty($product['sku']) && !empty($product['stock_text'])) &middot; @endif{{ $product['stock_text'] ?? '' }}</p>
                                <div class="product-single-price">
                                    <span class="product-single-price-current">{{ $product['price'] ?? '' }}</span>
                                    @if(!empty($product['old_price']))<span class="product-single-price-old">{{ $product['old_price'] }}</span>@endif
                                </div>
                                <div class="product-single-desc">{{ $data['description'] ?? '' }}</div>
                                @if(count($productHighlights) > 0)
                                    <ul class="product-single-highlights">
                                        @foreach($productHighlights as $row)
                                            <li><span class="product-single-highlight-label">{{ $row['label'] ?? '' }}</span><span class="product-single-highlight-value">{{ $row['value'] ?? '' }}</span></li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                            @break

                        @case('webu_ecom_add_to_cart_button_01')
                            @php
                                $manualAddSlug = $selectedProductSlug !== '' ? $selectedProductSlug : (string) (($products[0]['slug'] ?? '') ?: '');
                            @endphp
                            <div class="product-single-actions" data-webu-ecom-add-to-cart @if($manualAddSlug !== '') data-product-slug="{{ $manualAddSlug }}" @endif>
                                <div class="product-single-qty">
                                    <label class="product-single-qty-label">{{ $data['quantity_label'] ?? 'Quantity' }}</label>
                                    <input class="product-single-qty-input" type="number" value="{{ $data['quantity_default'] ?? 1 }}" min="1" readonly aria-label="Quantity">
                                </div>
                                <div class="product-single-buttons">
                                    <button type="button" class="product-single-btn product-single-btn-cart" data-webu-role="ecom-manual-add-to-cart">{{ $data['add_to_cart_label'] ?? 'Add to cart' }}</button>
                                    <button type="button" class="product-single-btn product-single-btn-wishlist" aria-label="Wishlist">{{ $data['wishlist_label'] ?? 'Wishlist' }}</button>
                                </div>
                            </div>
                            @break

                        @case('webu_ecom_product_tabs_01')
                            @php $productTabs = is_array($data['tabs'] ?? null) ? $data['tabs'] : []; @endphp
                            <div class="product-single-tabs">
                                @if(count($productTabs) > 0)
                                    <div class="product-single-tabs-list" role="tablist">
                                        @foreach($productTabs as $idx => $tab)
                                            <button type="button" class="product-single-tab {{ $loop->first ? 'active' : '' }}" role="tab" data-tab-index="{{ $idx }}">{{ $tab }}</button>
                                        @endforeach
                                    </div>
                                @endif
                                <div class="product-single-tabs-panel">
                                    <p>{{ $data['description'] ?? '' }}</p>
                                </div>
                            </div>
                            @break

                        @case('webu_ecom_cart_page_01')
                            @php $cartItems = is_array($data['cart_items'] ?? null) ? $data['cart_items'] : []; @endphp
                            <div data-webby-ecommerce-cart>
                                <table>
                                    <thead>
                                    <tr>
                                        @foreach((is_array($data['table_headers'] ?? null) ? $data['table_headers'] : []) as $header)
                                            <th>{{ $header }}</th>
                                        @endforeach
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($cartItems as $item)
                                        <tr>
                                            <td>{{ $item['name'] ?? '' }}</td>
                                            <td>{{ $item['price'] ?? '' }}</td>
                                            <td>{{ $item['qty'] ?? '' }}</td>
                                            <td>{{ $item['total'] ?? '' }}</td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @break

                        @case('webu_ecom_coupon_ui_01')
                            <div data-webby-ecommerce-coupon>
                                <div class="split">
                                    <div>
                                        <label class="field-label">{{ $data['coupon_label'] ?? '' }}</label>
                                        <input class="field" type="text" value="{{ $data['coupon_preview'] ?? '' }}" readonly>
                                    </div>
                                    <div style="display:flex; align-items:flex-end;">
                                        <button class="btn" style="width:100%; margin:0;">{{ $data['apply_label'] ?? '' }}</button>
                                    </div>
                                </div>
                            </div>
                            @break

                        @case('webu_ecom_order_summary_01')
                            <div data-webby-ecommerce-order-summary>
                                <div class="summary-box">
                                    @foreach((is_array($data['rows'] ?? null) ? $data['rows'] : []) as $row)
                                        <div class="summary-row"><span>{{ $row['label'] ?? '' }}</span><strong>{{ $row['value'] ?? '' }}</strong></div>
                                    @endforeach
                                    <div class="summary-row summary-total"><span>{{ $data['total_label'] ?? '' }}</span><strong>{{ $data['total_value'] ?? '' }}</strong></div>
                                </div>
                            </div>
                            @break

                        @case('webu_ecom_checkout_form_01')
                            @php $checkoutFields = is_array($data['fields'] ?? null) ? $data['fields'] : []; @endphp
                            <div data-webby-ecommerce-checkout-form>
                                <div class="form-grid">
                                    @foreach($checkoutFields as $field)
                                        <div @if(!empty($field['full_width']))style="grid-column: 1 / -1;"@endif>
                                            <label class="field-label">{{ $field['label'] ?? '' }}</label>
                                            <input class="field" value="{{ $field['value'] ?? '' }}" readonly>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                            @break

                        @case('webu_ecom_shipping_selector_01')
                            <div data-webby-ecommerce-shipping-selector>
                                <div class="summary-box">
                                    @foreach((is_array($data['options'] ?? null) ? $data['options'] : []) as $option)
                                        <div class="summary-row"><span><input type="radio" @if(!empty($option['selected']))checked @endif disabled> {{ $option['label'] ?? '' }}</span><strong>{{ $option['price'] ?? '' }}</strong></div>
                                    @endforeach
                                </div>
                            </div>
                            @break

                        @case('webu_ecom_payment_selector_01')
                            <div data-webby-ecommerce-payment-selector>
                                <div class="summary-box">
                                    @foreach((is_array($data['options'] ?? null) ? $data['options'] : []) as $option)
                                        @php $variant = $option['status_variant'] ?? 'ok'; @endphp
                                        <div class="summary-row"><span><input type="radio" @if(!empty($option['selected']))checked @endif disabled> {{ $option['label'] ?? '' }}</span><span class="status status-{{ $variant }}">{{ $option['status'] ?? '' }}</span></div>
                                    @endforeach
                                </div>
                                <div style="margin-top:10px;display:grid;gap:8px;">
                                    <button type="button" class="btn" style="margin:0;" data-webu-role="ecom-demo-start-payment">Start Payment</button>
                                    <div class="muted" data-webu-role="ecom-demo-payment-status" style="font-size:12px;">Place order first, then start payment.</div>
                                </div>
                            </div>
                            @break

                        @case('webu_ecom_auth_01')
                            @php
                                $login = is_array($data['login'] ?? null) ? $data['login'] : [];
                                $register = is_array($data['register'] ?? null) ? $data['register'] : [];
                            @endphp
                            <div data-webby-ecommerce-auth>
                                <div class="summary-box">
                                    <h3 style="margin:0 0 8px;">{{ $login['title'] ?? 'Login' }} / {{ $register['title'] ?? 'Register' }}</h3>
                                    <p class="muted" style="font-size:13px;">Runtime auth widget is loading...</p>
                                </div>
                            </div>
                            @break

                        @case('webu_ecom_account_dashboard_01')
                            <div class="grid" style="grid-template-columns: repeat(3, minmax(0, 1fr));">
                                @foreach((is_array($data['cards'] ?? null) ? $data['cards'] : []) as $card)
                                    <article class="card"><h3>{{ $card['title'] ?? '' }}</h3><p>{{ $card['value'] ?? '' }}</p></article>
                                @endforeach
                            </div>
                            @break

                        @case('webu_ecom_account_profile_01')
                            @php $profileFields = is_array($data['fields'] ?? null) ? $data['fields'] : []; @endphp
                            <div class="form-grid" data-webby-ecommerce-account-profile>
                                @foreach($profileFields as $field)
                                    <div @if(!empty($field['full_width']))style="grid-column: 1 / -1;"@endif>
                                        <label class="field-label">{{ $field['label'] ?? '' }}</label>
                                        <input class="field" value="{{ $field['value'] ?? '' }}" readonly>
                                    </div>
                                @endforeach
                            </div>
                            @break

                        @case('webu_ecom_account_security_01')
                            <div class="split" data-webby-ecommerce-account-security>
                                <div class="summary-box">
                                    <h3 style="margin-top:0;">{{ $data['policy_title'] ?? '' }}</h3>
                                    <ul class="list">
                                        @foreach((is_array($data['policy_items'] ?? null) ? $data['policy_items'] : []) as $policyItem)
                                            <li>{{ $policyItem }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                                <div class="summary-box">
                                    <h3 style="margin-top:0;">{{ $data['actions_title'] ?? '' }}</h3>
                                    <div class="chip-row">
                                        @foreach((is_array($data['action_chips'] ?? null) ? $data['action_chips'] : []) as $chip)
                                            <span class="chip">{{ $chip }}</span>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                            @break

                        @case('webu_ecom_orders_list_01')
                            <div data-webby-ecommerce-orders-list>
                                <table>
                                    <thead>
                                    <tr>
                                        @foreach((is_array($data['headers'] ?? null) ? $data['headers'] : []) as $header)
                                            <th>{{ $header }}</th>
                                        @endforeach
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach((is_array($data['orders'] ?? null) ? $data['orders'] : []) as $order)
                                        @php $variant = $order['status_variant'] ?? 'ok'; @endphp
                                        <tr>
                                            <td>{{ $order['number'] ?? '' }}</td>
                                            <td>{{ $order['date'] ?? '' }}</td>
                                            <td><span class="status status-{{ $variant }}">{{ $order['status'] ?? '' }}</span></td>
                                            <td>{{ $order['total'] ?? '' }}</td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @break

                        @case('webu_ecom_order_detail_01')
                            @php $runtimeOrderId = ctype_digit($selectedOrderId) ? $selectedOrderId : ''; @endphp
                            <div data-webby-ecommerce-order-detail @if($runtimeOrderId !== '') data-order-id="{{ $runtimeOrderId }}" @endif>
                                <div class="split">
                                    <div>
                                        <table>
                                            <thead>
                                            <tr>
                                                @foreach((is_array($data['headers'] ?? null) ? $data['headers'] : []) as $header)
                                                    <th>{{ $header }}</th>
                                                @endforeach
                                            </tr>
                                            </thead>
                                            <tbody>
                                            @foreach((is_array($data['items'] ?? null) ? $data['items'] : []) as $item)
                                                <tr>
                                                    <td>{{ $item['name'] ?? '' }}</td>
                                                    <td>{{ $item['qty'] ?? '' }}</td>
                                                    <td>{{ $item['price'] ?? '' }}</td>
                                                </tr>
                                            @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="summary-box">
                                        <h3 style="margin:0 0 8px;">{{ $data['timeline_title'] ?? '' }}</h3>
                                        <div class="timeline">
                                            @foreach((is_array($data['timeline'] ?? null) ? $data['timeline'] : []) as $timeline)
                                                <div class="timeline-item"><strong>{{ $timeline['label'] ?? '' }}:</strong> {{ $timeline['value'] ?? '' }}</div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @break

                        @case('faq_accordion_plus')
                            <ul class="list">
                                @foreach($faqItems as $faq)
                                    <li><strong>{{ $faq['q'] ?? '' }}</strong> - {{ $faq['a'] ?? '' }}</li>
                                @endforeach
                            </ul>
                            @break

                        @case('contact_split_form')
                            @php
                                $form = is_array($data['form'] ?? null) ? $data['form'] : [];
                                $channels = is_array($data['channels'] ?? null) ? $data['channels'] : [];
                            @endphp
                            <div class="split">
                                <div class="summary-box">
                                    <h3 style="margin:0 0 8px;">{{ $form['title'] ?? '' }}</h3>
                                    @foreach((is_array($form['fields'] ?? null) ? $form['fields'] : []) as $field)
                                        <label class="field-label" @if(!$loop->first)style="margin-top:8px;"@endif>{{ $field['label'] ?? '' }}</label>
                                        <input class="field" value="{{ $field['value'] ?? '' }}" readonly>
                                    @endforeach
                                    <button class="btn" style="width:100%;">{{ $form['button_label'] ?? '' }}</button>
                                </div>
                                <div class="summary-box">
                                    <h3 style="margin:0 0 8px;">{{ $channels['title'] ?? '' }}</h3>
                                    <ul class="list">
                                        @foreach((is_array($channels['items'] ?? null) ? $channels['items'] : []) as $channel)
                                            <li>{{ $channel }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                            @break

                        @case('map_contact_block')
                            <div class="split">
                                <img src="{{ $data['image_url'] ?? '' }}" alt="{{ $data['image_alt'] ?? 'Map preview' }}" style="max-height:220px;">
                                <div class="summary-box">
                                    <h3 style="margin:0 0 8px;">{{ $data['title'] ?? '' }}</h3>
                                    <p>{{ $data['address'] ?? '' }}</p>
                                    <div class="chip-row">
                                        @foreach((is_array($data['chips'] ?? null) ? $data['chips'] : []) as $chip)
                                            <span class="chip">{{ $chip }}</span>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                            @break

                        @case('webu_general_offcanvas_menu_01')
                            @php
                                $drawerSide = strtolower(trim((string) ($data['side'] ?? 'left')));
                                $drawerSide = in_array($drawerSide, ['left', 'right'], true) ? $drawerSide : 'left';
                                $drawerTitle = trim((string) ($data['title'] ?? 'Shop navigation'));
                                $drawerSubtitle = trim((string) ($data['subtitle'] ?? 'Reusable drawer for desktop hamburger and mobile navigation.'));
                                $drawerTriggerLabel = trim((string) ($data['trigger_label'] ?? 'Open menu'));
                                $drawerDescription = trim((string) ($data['description'] ?? 'Trigger and panel are bundled so the same component can be reused in headers, sidebars and mobile menus.'));
                                $drawerFooterLabel = trim((string) ($data['footer_label'] ?? 'Shop all'));
                                $drawerFooterUrl = trim((string) ($data['footer_url'] ?? '/shop'));
                                $drawerOpenByDefault = filter_var($data['open_by_default'] ?? true, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
                                $drawerOpenByDefault = $drawerOpenByDefault !== null ? $drawerOpenByDefault : true;
                                $drawerShowClose = filter_var($data['show_close'] ?? true, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
                                $drawerShowClose = $drawerShowClose !== null ? $drawerShowClose : true;
                                $drawerItemsRaw = is_array($data['menu_items'] ?? null) ? $data['menu_items'] : [];
                                $drawerItems = [];
                                foreach ($drawerItemsRaw as $item) {
                                    if (! is_array($item)) {
                                        continue;
                                    }
                                    $itemLabel = trim((string) ($item['label'] ?? 'Menu item'));
                                    $itemUrl = trim((string) ($item['url'] ?? '#'));
                                    $drawerItems[] = [
                                        'label' => $itemLabel !== '' ? $itemLabel : 'Menu item',
                                        'href' => (str_starts_with($itemUrl, 'http://') || str_starts_with($itemUrl, 'https://') || $itemUrl === '#')
                                            ? $itemUrl
                                            : route('template-demos.show', ['templateSlug' => $templateSlug, 'path' => trim($itemUrl, '/') ?: null] + ($sharedDemoQuery ?? []) + ['slug' => trim($itemUrl, '/') ?: 'home']),
                                        'description' => trim((string) ($item['description'] ?? '')),
                                    ];
                                }
                                if ($drawerItems === []) {
                                    $drawerItems = [
                                        ['label' => 'New arrivals', 'href' => route('template-demos.show', ['templateSlug' => $templateSlug, 'path' => 'shop'] + ($sharedDemoQuery ?? []) + ['slug' => 'shop']), 'description' => 'Fresh seasonal edits'],
                                        ['label' => 'Outerwear', 'href' => route('template-demos.show', ['templateSlug' => $templateSlug, 'path' => 'outerwear'] + ($sharedDemoQuery ?? []) + ['slug' => 'outerwear']), 'description' => 'Layering essentials'],
                                        ['label' => 'Contact', 'href' => route('template-demos.show', ['templateSlug' => $templateSlug, 'path' => 'contact'] + ($sharedDemoQuery ?? []) + ['slug' => 'contact']), 'description' => 'Store support'],
                                    ];
                                }
                                $drawerPanelId = 'webu-general-offcanvas-' . substr(md5($normalizedKey . json_encode($drawerItems) . $drawerSide), 0, 10);
                                $drawerFooterHref = (str_starts_with($drawerFooterUrl, 'http://') || str_starts_with($drawerFooterUrl, 'https://') || $drawerFooterUrl === '#')
                                    ? $drawerFooterUrl
                                    : route('template-demos.show', ['templateSlug' => $templateSlug, 'path' => trim($drawerFooterUrl, '/') ?: null] + ($sharedDemoQuery ?? []) + ['slug' => trim($drawerFooterUrl, '/') ?: 'shop']);
                            @endphp
                            <section class="webu-offcanvas-menu-demo">
                                <div class="webu-offcanvas-menu-demo__content">
                                    <span class="webu-offcanvas-menu-demo__eyebrow">{{ ucfirst($drawerSide) }} drawer</span>
                                    <h3 class="webu-offcanvas-menu-demo__title">{{ $drawerTriggerLabel }}</h3>
                                    <p class="webu-offcanvas-menu-demo__description">{{ $drawerDescription }}</p>
                                    <button
                                        type="button"
                                        class="webu-offcanvas-menu-demo__trigger"
                                        aria-controls="{{ $drawerPanelId }}"
                                        aria-expanded="{{ $drawerOpenByDefault ? 'true' : 'false' }}"
                                        data-webu-offcanvas-trigger="{{ $drawerPanelId }}"
                                    >
                                        <span class="webu-offcanvas-menu-demo__trigger-bars" aria-hidden="true">
                                            <span></span>
                                            <span></span>
                                            <span></span>
                                        </span>
                                        <span>{{ $drawerTriggerLabel }}</span>
                                    </button>
                                </div>
                                @include('template-demos.partials.offcanvas-menu', [
                                    'panelId' => $drawerPanelId,
                                    'panelTitle' => $drawerTitle,
                                    'panelSubtitle' => $drawerSubtitle,
                                    'panelSide' => $drawerSide,
                                    'panelItems' => $drawerItems,
                                    'panelFooterLabel' => $drawerFooterLabel,
                                    'panelFooterHref' => $drawerFooterHref,
                                    'showPanelClose' => $drawerShowClose,
                                    'previewMode' => true,
                                    'openByDefault' => $drawerOpenByDefault,
                                ])
                            </section>
                            @break

                        @case('webu_general_testimonials_01')
                            @php
                                $testimonialItems = is_array($data['items'] ?? null) ? $data['items'] : (is_array($data['testimonials'] ?? null) ? $data['testimonials'] : []);
                            @endphp
                            <div class="testimonials-block" data-webu-section="webu_general_testimonials_01">
                                @forelse($testimonialItems as $item)
                                    <blockquote class="card card-content" style="text-align:center;max-width:640px;margin:0 auto 1rem;">
                                        @if(!empty($item['image_url'] ?? $item['avatar_url'] ?? null))
                                            <img src="{{ $item['image_url'] ?? $item['avatar_url'] }}" alt="{{ $item['author'] ?? $item['name'] ?? '' }}" style="width:64px;height:64px;border-radius:50%;object-fit:cover;margin:0 auto 8px;display:block;">
                                        @endif
                                        <p style="margin:0 0 8px;">{{ $item['quote'] ?? $item['text'] ?? $item['body'] ?? '' }}</p>
                                        <cite style="font-style:normal;font-weight:600;">— {{ $item['author'] ?? $item['name'] ?? 'Customer' }}</cite>
                                    </blockquote>
                                @empty
                                    <blockquote class="card card-content" style="text-align:center;max-width:640px;margin:0 auto;">
                                        <p style="margin:0 0 8px;">What our customers say. Edit this section in the builder.</p>
                                        <cite style="font-style:normal;font-weight:600;">— Customer</cite>
                                    </blockquote>
                                @endforelse
                            </div>
                            @break

                        @case('webu_general_card_01')
                            <article class="card card-content">
                                <h3 class="card-title">{{ $data['title'] ?? '' }}</h3>
                                <p class="card-body">{{ $data['body'] ?? '' }}</p>
                                @if(!empty($data['button']) && !empty($data['button_url']))
                                    @php
                                        $cardCtaPath = ltrim((string) ($data['button_url'] ?? ''), '/');
                                        $cardCtaQuery = array_filter([
                                            'site' => $siteId !== '' ? $siteId : null,
                                            'draft' => $draft !== '' ? $draft : null,
                                            'locale' => $locale !== '' ? $locale : null,
                                            'slug' => $cardCtaPath !== '' ? $cardCtaPath : 'home',
                                        ], static fn ($v) => $v !== null);
                                    @endphp
                                    <a class="btn btn-sm" href="{{ route('template-demos.show', ['templateSlug' => $templateSlug, 'path' => $cardCtaPath !== '' ? $cardCtaPath : null] + $cardCtaQuery) }}">{{ $data['button'] }}</a>
                                @endif
                            </article>
                            @break

                        @case('container')
                            @php
                                $maxWidth = $data['max_width'] ?? 'xl';
                                $layoutConfig = config('layout-primitives.container', []);
                                $maxWidths = $layoutConfig['max_widths'] ?? ['xl' => 1280, 'lg' => 1024, 'md' => 768, 'sm' => 640];
                                $px = is_numeric($maxWidths[$maxWidth] ?? null) ? (int) $maxWidths[$maxWidth] : 1280;
                                $pad = $data['padding_token'] ?? 'space-md';
                                $padPx = match($pad) { 'space-xs' => 8, 'space-sm' => 12, 'space-md' => 20, 'space-lg' => 32, 'space-xl' => 48, default => 20 };
                            @endphp
                            <div class="layout-container" style="max-width:{{ $px }}px; margin:0 auto; padding-left:{{ $padPx }}px; padding-right:{{ $padPx }}px;">
                                @foreach(($data['sections'] ?? []) as $childSection)
                                    @include('template-demos.partials.section-content', ['section' => $childSection])
                                @endforeach
                            </div>
                            @break

                        @case('grid')
                            @php
                                $cols = (int) ($data['columns_desktop'] ?? 12);
                                $gap = $data['gap_token'] ?? 'space-md';
                                $gapPx = match($gap) { 'space-xs' => 8, 'space-sm' => 12, 'space-md' => 20, 'space-lg' => 32, default => 20 };
                            @endphp
                            <div class="layout-grid" style="display:grid; grid-template-columns:repeat({{ $cols }}, 1fr); gap:{{ $gapPx }}px;">
                                @foreach(($data['sections'] ?? []) as $childSection)
                                    @include('template-demos.partials.section-content', ['section' => $childSection])
                                @endforeach
                            </div>
                            @break

                        @case('section')
                            @php
                                $rhythm = $data['vertical_rhythm_token'] ?? 'space-lg';
                                $rhythmPx = match($rhythm) { 'space-xs' => 8, 'space-sm' => 12, 'space-md' => 20, 'space-lg' => 32, 'space-xl' => 48, default => 32 };
                                $bgVariant = $data['background_variant'] ?? 'default';
                            @endphp
                            <div class="layout-section layout-section--{{ $bgVariant }}" style="padding-top:{{ $rhythmPx }}px; padding-bottom:{{ $rhythmPx }}px;">
                                @foreach(($data['sections'] ?? []) as $childSection)
                                    @include('template-demos.partials.section-content', ['section' => $childSection])
                                @endforeach
                            </div>
                            @break

                        @case('header')
                        @case('webu_header_01')
                            @php
                                $headerVariant = strtolower(trim((string) ($data['layout_variant'] ?? $data['variant'] ?? 'header-1')));
                                if (! preg_match('/^header-\d+$/', $headerVariant)) { $headerVariant = 'header-1'; }
                                $headerLogo = trim((string) ($data['logo_text'] ?? $data['brand'] ?? 'Logo'));
                                $headerLogoUrl = trim((string) ($data['logo_url'] ?? '/'));
                                $headerLogoImageUrl = trim((string) ($data['logo_image_url'] ?? ''));
                                $headerMenu = is_array($data['menu_items'] ?? null) ? $data['menu_items'] : (is_array($data['links'] ?? null) ? $data['links'] : [['label' => 'მაღაზია', 'url' => '/shop'], ['label' => 'კოლექცია', 'url' => '/collection'], ['label' => 'კონტაქტი', 'url' => '/contact']]);
                                $headerCtaLabel = trim((string) ($data['cta_label'] ?? ''));
                                $headerCtaUrl = trim((string) ($data['cta_url'] ?? ''));
                                $headerStripText = trim((string) ($data['top_strip_text'] ?? ''));
                                $headerStripRightLinks = is_array($data['strip_right_links'] ?? null) ? $data['strip_right_links'] : null;
                                $headerAnnouncementText = trim((string) ($data['announcement_text'] ?? $data['top_strip_text'] ?? ''));
                                $headerAnnouncementCtaLabel = trim((string) ($data['announcement_cta_label'] ?? ''));
                                $headerAnnouncementCtaUrl = trim((string) ($data['announcement_cta_url'] ?? ''));
                                $headerTopBarLoginLabel = trim((string) ($data['top_bar_login_label'] ?? ''));
                                $headerTopBarLoginUrl = trim((string) ($data['top_bar_login_url'] ?? '/account/login'));
                                $headerTopBarSocialLinks = is_array($data['top_bar_social_links'] ?? null) ? $data['top_bar_social_links'] : [];
                                $headerTopBarLocationText = trim((string) ($data['top_bar_location_text'] ?? ''));
                                $headerTopBarLocationUrl = trim((string) ($data['top_bar_location_url'] ?? '#'));
                                $headerTopBarEmailText = trim((string) ($data['top_bar_email_text'] ?? ''));
                                $headerTopBarEmailUrl = trim((string) ($data['top_bar_email_url'] ?? ''));
                                $headerHotlineEyebrow = trim((string) ($data['hotline_eyebrow'] ?? ''));
                                $headerHotlineLabel = trim((string) ($data['hotline_label'] ?? ''));
                                $headerHotlineUrl = trim((string) ($data['hotline_url'] ?? ''));
                                $headerTopBarLeftText = trim((string) ($data['top_bar_left_text'] ?? ''));
                                $headerTopBarLeftCta = trim((string) ($data['top_bar_left_cta'] ?? ''));
                                $headerTopBarLeftCtaUrl = trim((string) ($data['top_bar_left_cta_url'] ?? ''));
                                $headerSocialFollowers = trim((string) ($data['social_followers'] ?? ''));
                                $headerSocialUrl = trim((string) ($data['social_url'] ?? '#'));
                                $headerTopBarRightTracking = trim((string) ($data['top_bar_right_tracking'] ?? ''));
                                $headerTopBarRightTrackingUrl = trim((string) ($data['top_bar_right_tracking_url'] ?? '#'));
                                $headerTopBarRightLang = trim((string) ($data['top_bar_right_lang'] ?? ''));
                                $headerTopBarRightCurrency = trim((string) ($data['top_bar_right_currency'] ?? ''));
                                $headerAccountUrl = trim((string) ($data['account_url'] ?? '/account'));
                                $headerSearchUrl = trim((string) ($data['search_url'] ?? '/search'));
                                $headerSearchPlaceholder = trim((string) ($data['search_placeholder'] ?? ''));
                                $headerSearchCategoryLabel = trim((string) ($data['search_category_label'] ?? ''));
                                $headerSearchButtonLabel = trim((string) ($data['search_button_label'] ?? ''));
                                $headerWishlistUrl = trim((string) ($data['wishlist_url'] ?? '/wishlist'));
                                $headerCartUrl = trim((string) ($data['cart_url'] ?? '/cart'));
                                $headerDepartmentLabel = trim((string) ($data['department_label'] ?? ''));
                                $headerDepartmentMenu = is_array($data['department_menu_items'] ?? null) ? $data['department_menu_items'] : [];
                                $headerPromoEyebrow = trim((string) ($data['promo_eyebrow'] ?? ''));
                                $headerPromoLabel = trim((string) ($data['promo_label'] ?? ''));
                                $headerPromoUrl = trim((string) ($data['promo_url'] ?? ''));
                                $headerAccountEyebrow = trim((string) ($data['account_eyebrow'] ?? ''));
                                $headerAccountLabel = trim((string) ($data['account_label'] ?? ''));
                                $headerCartLabel = trim((string) ($data['cart_label'] ?? ''));
                                $headerMenuDrawerSide = trim((string) ($data['menu_drawer_side'] ?? 'left'));
                                $headerMenuDrawerTitle = trim((string) ($data['menu_drawer_title'] ?? ''));
                                $headerMenuDrawerSubtitle = trim((string) ($data['menu_drawer_subtitle'] ?? ''));
                                $headerWishlistCount = isset($data['wishlist_count']) && is_numeric($data['wishlist_count']) ? (int) $data['wishlist_count'] : null;
                                $headerCartCount = isset($data['cart_count']) && is_numeric($data['cart_count']) ? (int) $data['cart_count'] : null;
                                $headerCartTotal = trim((string) ($data['cart_total'] ?? ''));
                                $headerLogoHref = str_starts_with($headerLogoUrl, 'http') ? $headerLogoUrl : route('template-demos.show', ['templateSlug' => $templateSlug, 'path' => trim($headerLogoUrl, '/') ?: null] + ($sharedDemoQuery ?? []) + ['slug' => trim($headerLogoUrl, '/') ?: 'home']);
                                $headerCtaHref = ($headerCtaLabel !== '' && $headerCtaUrl !== '') ? (str_starts_with($headerCtaUrl, 'http') ? $headerCtaUrl : route('template-demos.show', ['templateSlug' => $templateSlug, 'path' => trim($headerCtaUrl, '/') ?: null] + ($sharedDemoQuery ?? []) + ['slug' => trim($headerCtaUrl, '/') ?: 'home'])) : '';
                            @endphp
                            @include('template-demos.partials.header-variants', [
                                'headerVariant' => $headerVariant,
                                'headerLogo' => $headerLogo,
                                'headerLogoImageUrl' => $headerLogoImageUrl,
                                'headerLogoHref' => $headerLogoHref,
                                'headerMenu' => $headerMenu,
                                'headerCtaLabel' => $headerCtaLabel,
                                'headerCtaHref' => $headerCtaHref,
                                'headerStripText' => $headerStripText,
                                'headerStripRightLinks' => $headerStripRightLinks,
                                'headerAnnouncementText' => $headerAnnouncementText,
                                'headerAnnouncementCtaLabel' => $headerAnnouncementCtaLabel,
                                'headerAnnouncementCtaUrl' => $headerAnnouncementCtaUrl,
                                'headerTopBarLoginLabel' => $headerTopBarLoginLabel,
                                'headerTopBarLoginUrl' => $headerTopBarLoginUrl,
                                'headerTopBarSocialLinks' => $headerTopBarSocialLinks,
                                'headerTopBarLocationText' => $headerTopBarLocationText,
                                'headerTopBarLocationUrl' => $headerTopBarLocationUrl,
                                'headerTopBarEmailText' => $headerTopBarEmailText,
                                'headerTopBarEmailUrl' => $headerTopBarEmailUrl,
                                'headerHotlineEyebrow' => $headerHotlineEyebrow,
                                'headerHotlineLabel' => $headerHotlineLabel,
                                'headerHotlineUrl' => $headerHotlineUrl,
                                'headerTopBarLeftText' => $headerTopBarLeftText,
                                'headerTopBarLeftCta' => $headerTopBarLeftCta,
                                'headerTopBarLeftCtaUrl' => $headerTopBarLeftCtaUrl,
                                'headerSocialFollowers' => $headerSocialFollowers,
                                'headerSocialUrl' => $headerSocialUrl,
                                'headerTopBarRightTracking' => $headerTopBarRightTracking,
                                'headerTopBarRightTrackingUrl' => $headerTopBarRightTrackingUrl,
                                'headerTopBarRightLang' => $headerTopBarRightLang,
                                'headerTopBarRightCurrency' => $headerTopBarRightCurrency,
                                'headerAccountUrl' => $headerAccountUrl,
                                'headerSearchUrl' => $headerSearchUrl,
                                'headerSearchPlaceholder' => $headerSearchPlaceholder,
                                'headerSearchCategoryLabel' => $headerSearchCategoryLabel,
                                'headerSearchButtonLabel' => $headerSearchButtonLabel,
                                'headerWishlistUrl' => $headerWishlistUrl,
                                'headerCartUrl' => $headerCartUrl,
                                'headerDepartmentLabel' => $headerDepartmentLabel,
                                'headerDepartmentMenu' => $headerDepartmentMenu,
                                'headerPromoEyebrow' => $headerPromoEyebrow,
                                'headerPromoLabel' => $headerPromoLabel,
                                'headerPromoUrl' => $headerPromoUrl,
                                'headerAccountEyebrow' => $headerAccountEyebrow,
                                'headerAccountLabel' => $headerAccountLabel,
                                'headerCartLabel' => $headerCartLabel,
                                'headerMenuDrawerSide' => $headerMenuDrawerSide,
                                'headerMenuDrawerTitle' => $headerMenuDrawerTitle,
                                'headerMenuDrawerSubtitle' => $headerMenuDrawerSubtitle,
                                'headerWishlistCount' => $headerWishlistCount,
                                'headerCartCount' => $headerCartCount,
                                'headerCartTotal' => $headerCartTotal,
                                'templateSlug' => $templateSlug,
                                'sharedDemoQuery' => $sharedDemoQuery ?? [],
                                'sectionKey' => $normalizedKey ?: 'webu_header_01',
                            ])
                            @break

                        @case('webu_footer_01')
                        @case('footer')
                            @php
                                $footerVariant = strtolower(trim((string) ($data['layout_variant'] ?? $data['variant'] ?? 'footer-1')));
                                if (! preg_match('/^footer-\d+$/', $footerVariant)) { $footerVariant = 'footer-1'; }
                                $footerMenusRaw = is_array($data['menus'] ?? null) ? $data['menus'] : [];
                                $footerLinksFlat = is_array($data['links'] ?? null) ? $data['links'] : [];
                                $footerMenus = $footerMenusRaw !== [] ? $footerMenusRaw : ($footerLinksFlat !== [] ? ['Footer' => $footerLinksFlat] : []);
                                $footerLogo = trim((string) ($data['logo_text'] ?? $data['brand'] ?? 'Logo'));
                                $footerCopyright = trim((string) ($data['copyright'] ?? ''));
                                $footerAddress = trim((string) ($data['contact_address'] ?? $data['address'] ?? ''));
                                $footerDefaultColumns = [
                                    [
                                        'title' => 'Explore',
                                        'links' => [
                                            ['label' => 'Jewellery', 'url' => '/shop'],
                                            ['label' => 'High Jewellery', 'url' => '/collections/high-jewellery'],
                                            ['label' => 'Wedding & Engagement', 'url' => '/collections/wedding-engagement'],
                                            ['label' => 'Provenance and Peace', 'url' => '/collections/provenance-and-peace'],
                                            ['label' => 'Clocks', 'url' => '/collections/clocks'],
                                        ],
                                    ],
                                    [
                                        'title' => 'Follow Us',
                                        'links' => [
                                            ['label' => 'Facebook', 'url' => 'https://facebook.com'],
                                            ['label' => 'Twitter', 'url' => 'https://twitter.com'],
                                            ['label' => 'Instagram', 'url' => 'https://instagram.com'],
                                            ['label' => 'YouTube', 'url' => 'https://youtube.com'],
                                            ['label' => 'Pinterest', 'url' => 'https://pinterest.com'],
                                        ],
                                    ],
                                    [
                                        'title' => 'About Us',
                                        'links' => [
                                            ['label' => 'About Us', 'url' => '/about'],
                                            ['label' => 'The Rewards Stack', 'url' => '/rewards'],
                                            ['label' => 'Sustainability', 'url' => '/sustainability'],
                                            ['label' => 'Careers', 'url' => '/careers'],
                                            ['label' => 'Blog', 'url' => '/blog'],
                                        ],
                                    ],
                                ];
                                $footerMenuColumns = [];
                                foreach ($footerMenus as $menuKey => $menuLinks) {
                                    if (! is_array($menuLinks) || $menuLinks === []) {
                                        continue;
                                    }
                                    $footerMenuColumns[] = [
                                        'title' => ucwords(str_replace(['-', '_'], ' ', is_string($menuKey) ? $menuKey : 'Footer')),
                                        'links' => array_values(array_filter($menuLinks, static fn ($link) => is_array($link) && trim((string) ($link['label'] ?? '')) !== '')),
                                    ];
                                }
                                $footerIsFallbackMenu = count($footerMenuColumns) === 1
                                    && strtolower((string) ($footerMenuColumns[0]['title'] ?? '')) === 'footer'
                                    && implode('|', array_map(static fn ($link) => strtolower(trim((string) ($link['label'] ?? ''))), $footerMenuColumns[0]['links'] ?? [])) === 'shop|about|contact';
                                if ($footerMenuColumns === [] || $footerIsFallbackMenu) {
                                    $footerMenuColumns = $footerDefaultColumns;
                                }
                                $footerMenuColumns = array_slice($footerMenuColumns, 0, 3);
                                $footerResolvedCopyright = $footerCopyright !== '' ? $footerCopyright : '© ' . date('Y') . ' — ' . $footerLogo . '. All Rights Reserved.';
                                $footerPaymentBadges = [
                                    ['label' => 'AMEX', 'tone' => 'amex'],
                                    ['label' => 'MC', 'tone' => 'mastercard'],
                                    ['label' => 'VISA', 'tone' => 'visa'],
                                    ['label' => 'PayPal', 'tone' => 'paypal'],
                                    ['label' => 'Apple Pay', 'tone' => 'applepay'],
                                    ['label' => 'Klarna', 'tone' => 'klarna'],
                                ];
                                $footerResolveHref = static function (string $url) use ($templateSlug, $sharedDemoQuery): string {
                                    $url = trim($url);
                                    if ($url === '') {
                                        return '#';
                                    }
                                    if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
                                        return $url;
                                    }

                                    return route('template-demos.show', ['templateSlug' => $templateSlug, 'path' => trim($url, '/') ?: null] + ($sharedDemoQuery ?? []) + ['slug' => trim($url, '/') ?: 'home']);
                                };
                            @endphp
                            <footer class="webu-footer webu-footer--{{ $footerVariant }}" data-webu-section="{{ $normalizedKey ?: 'webu_footer_01' }}">
                                <div class="webu-footer__inner">
                                    <div class="webu-footer__grid">
                                        <div class="webu-footer__newsletter-block">
                                            <p class="webu-footer__heading">OUR NEWSLETTER</p>
                                            <p class="webu-footer__newsletter-copy">It only takes a second to be the first to find out about our latest news</p>
                                            @if($footerAddress !== '')<p class="webu-footer__support-text">{{ $footerAddress }}</p>@endif
                                            <form class="webu-footer__newsletter-form" action="#" method="get" onsubmit="return false;">
                                                <input type="email" class="webu-footer__newsletter-input" placeholder="Your Email..." aria-label="Your Email...">
                                                <button type="submit" class="webu-footer__newsletter-button">SUBSCRIBE</button>
                                            </form>
                                            <div class="webu-footer__payments" aria-label="Secure payment methods">
                                                <span class="webu-footer__payments-label">SECURE PAYMENT:</span>
                                                <div class="webu-footer__payment-list">
                                                    @foreach($footerPaymentBadges as $badge)
                                                        <span class="webu-footer__payment-chip webu-footer__payment-chip--{{ $badge['tone'] }}">{{ $badge['label'] }}</span>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </div>
                                        @foreach($footerMenuColumns as $column)
                                            @if(is_array($column['links'] ?? null) && ($column['links'] ?? []) !== [])
                                                <nav class="webu-footer__nav" aria-label="{{ $column['title'] ?? 'Footer' }}">
                                                    <p class="webu-footer__heading">{{ strtoupper((string) ($column['title'] ?? 'Footer')) }}</p>
                                                    <div class="webu-footer__menu">
                                                        @foreach($column['links'] as $link)
                                                            @php
                                                                $footerLinkUrl = trim((string) ($link['url'] ?? ''));
                                                                $footerIsExternalLink = str_starts_with($footerLinkUrl, 'http://') || str_starts_with($footerLinkUrl, 'https://');
                                                            @endphp
                                                            <a class="webu-footer__link" href="{{ $footerResolveHref($footerLinkUrl) }}" @if($footerIsExternalLink) target="_blank" rel="noreferrer" @endif>{{ $link['label'] ?? '' }}</a>
                                                        @endforeach
                                                    </div>
                                                </nav>
                                            @endif
                                        @endforeach
                                    </div>
                                    <div class="webu-footer__bottom">
                                        <span class="webu-footer__copyright">{{ $footerResolvedCopyright }}</span>
                                    </div>
                                </div>
                            </footer>
                            @break

                        @default
                            @php $renderGeneric = true; @endphp
                    @endswitch
                @endif

                @if($renderGeneric)
                    @if($component === 'header')
                        @php
                            $headerLogo = trim((string) ($data['logo_text'] ?? $data['brand'] ?? 'Logo'));
                            $headerLogoUrl = trim((string) ($data['logo_url'] ?? '/'));
                            $headerMenu = is_array($data['menu_items'] ?? null) ? $data['menu_items'] : (is_array($data['links'] ?? null) ? $data['links'] : [['label' => 'მაღაზია', 'url' => '/shop'], ['label' => 'კოლექცია', 'url' => '/collection'], ['label' => 'კონტაქტი', 'url' => '/contact']]);
                            $headerVariant = strtolower(trim((string) ($data['layout_variant'] ?? $data['variant'] ?? 'header-1')));
                            if (! preg_match('/^header-\d+$/', $headerVariant)) { $headerVariant = 'header-1'; }
                            $headerLogoHref = str_starts_with($headerLogoUrl, 'http') ? $headerLogoUrl : route('template-demos.show', ['templateSlug' => $templateSlug, 'path' => trim($headerLogoUrl, '/') ?: null] + ($sharedDemoQuery ?? []) + ['slug' => trim($headerLogoUrl, '/') ?: 'home']);
                            $headerCtaLabel = trim((string) ($data['cta_label'] ?? ''));
                            $headerCtaUrl = trim((string) ($data['cta_url'] ?? ''));
                            $headerCtaHref = ($headerCtaLabel !== '' && $headerCtaUrl !== '') ? (str_starts_with($headerCtaUrl, 'http') ? $headerCtaUrl : route('template-demos.show', ['templateSlug' => $templateSlug, 'path' => trim($headerCtaUrl, '/') ?: null] + ($sharedDemoQuery ?? []) + ['slug' => trim($headerCtaUrl, '/') ?: 'home'])) : '';
                            $headerStripText = trim((string) ($data['top_strip_text'] ?? ''));
                            $headerStripRightLinks = is_array($data['strip_right_links'] ?? null) ? $data['strip_right_links'] : null;
                            $headerAnnouncementText = trim((string) ($data['announcement_text'] ?? $data['top_strip_text'] ?? ''));
                            $headerAnnouncementCtaLabel = trim((string) ($data['announcement_cta_label'] ?? ''));
                            $headerAnnouncementCtaUrl = trim((string) ($data['announcement_cta_url'] ?? ''));
                            $headerTopBarLoginLabel = trim((string) ($data['top_bar_login_label'] ?? ''));
                            $headerTopBarLoginUrl = trim((string) ($data['top_bar_login_url'] ?? '/account/login'));
                            $headerTopBarSocialLinks = is_array($data['top_bar_social_links'] ?? null) ? $data['top_bar_social_links'] : [];
                            $headerTopBarLocationText = trim((string) ($data['top_bar_location_text'] ?? ''));
                            $headerTopBarLocationUrl = trim((string) ($data['top_bar_location_url'] ?? '#'));
                            $headerTopBarEmailText = trim((string) ($data['top_bar_email_text'] ?? ''));
                            $headerTopBarEmailUrl = trim((string) ($data['top_bar_email_url'] ?? ''));
                            $headerHotlineEyebrow = trim((string) ($data['hotline_eyebrow'] ?? ''));
                            $headerHotlineLabel = trim((string) ($data['hotline_label'] ?? ''));
                            $headerHotlineUrl = trim((string) ($data['hotline_url'] ?? ''));
                            $headerTopBarLeftText = trim((string) ($data['top_bar_left_text'] ?? ''));
                            $headerTopBarLeftCta = trim((string) ($data['top_bar_left_cta'] ?? ''));
                            $headerTopBarLeftCtaUrl = trim((string) ($data['top_bar_left_cta_url'] ?? ''));
                            $headerSocialFollowers = trim((string) ($data['social_followers'] ?? ''));
                            $headerSocialUrl = trim((string) ($data['social_url'] ?? '#'));
                            $headerTopBarRightTracking = trim((string) ($data['top_bar_right_tracking'] ?? ''));
                            $headerTopBarRightTrackingUrl = trim((string) ($data['top_bar_right_tracking_url'] ?? '#'));
                            $headerTopBarRightLang = trim((string) ($data['top_bar_right_lang'] ?? ''));
                            $headerTopBarRightCurrency = trim((string) ($data['top_bar_right_currency'] ?? ''));
                            $headerAccountUrl = trim((string) ($data['account_url'] ?? '/account'));
                            $headerSearchUrl = trim((string) ($data['search_url'] ?? '/search'));
                            $headerSearchPlaceholder = trim((string) ($data['search_placeholder'] ?? ''));
                            $headerSearchCategoryLabel = trim((string) ($data['search_category_label'] ?? ''));
                            $headerSearchButtonLabel = trim((string) ($data['search_button_label'] ?? ''));
                            $headerWishlistUrl = trim((string) ($data['wishlist_url'] ?? '/wishlist'));
                            $headerCartUrl = trim((string) ($data['cart_url'] ?? '/cart'));
                            $headerDepartmentLabel = trim((string) ($data['department_label'] ?? ''));
                            $headerDepartmentMenu = is_array($data['department_menu_items'] ?? null) ? $data['department_menu_items'] : [];
                            $headerPromoEyebrow = trim((string) ($data['promo_eyebrow'] ?? ''));
                            $headerPromoLabel = trim((string) ($data['promo_label'] ?? ''));
                            $headerPromoUrl = trim((string) ($data['promo_url'] ?? ''));
                            $headerAccountEyebrow = trim((string) ($data['account_eyebrow'] ?? ''));
                            $headerAccountLabel = trim((string) ($data['account_label'] ?? ''));
                            $headerCartLabel = trim((string) ($data['cart_label'] ?? ''));
                            $headerMenuDrawerSide = trim((string) ($data['menu_drawer_side'] ?? 'left'));
                            $headerMenuDrawerTitle = trim((string) ($data['menu_drawer_title'] ?? ''));
                            $headerMenuDrawerSubtitle = trim((string) ($data['menu_drawer_subtitle'] ?? ''));
                            $headerWishlistCount = isset($data['wishlist_count']) && is_numeric($data['wishlist_count']) ? (int) $data['wishlist_count'] : null;
                            $headerCartCount = isset($data['cart_count']) && is_numeric($data['cart_count']) ? (int) $data['cart_count'] : null;
                            $headerCartTotal = trim((string) ($data['cart_total'] ?? ''));
                        @endphp
                        @include('template-demos.partials.header-variants', [
                            'headerVariant' => $headerVariant,
                            'headerLogo' => $headerLogo,
                            'headerLogoHref' => $headerLogoHref,
                            'headerMenu' => $headerMenu,
                            'headerCtaLabel' => $headerCtaLabel,
                            'headerCtaHref' => $headerCtaHref,
                            'headerStripText' => $headerStripText,
                            'headerStripRightLinks' => $headerStripRightLinks,
                            'headerAnnouncementText' => $headerAnnouncementText,
                            'headerAnnouncementCtaLabel' => $headerAnnouncementCtaLabel,
                            'headerAnnouncementCtaUrl' => $headerAnnouncementCtaUrl,
                            'headerTopBarLoginLabel' => $headerTopBarLoginLabel,
                            'headerTopBarLoginUrl' => $headerTopBarLoginUrl,
                            'headerTopBarSocialLinks' => $headerTopBarSocialLinks,
                            'headerTopBarLocationText' => $headerTopBarLocationText,
                            'headerTopBarLocationUrl' => $headerTopBarLocationUrl,
                            'headerTopBarEmailText' => $headerTopBarEmailText,
                            'headerTopBarEmailUrl' => $headerTopBarEmailUrl,
                            'headerHotlineEyebrow' => $headerHotlineEyebrow,
                            'headerHotlineLabel' => $headerHotlineLabel,
                            'headerHotlineUrl' => $headerHotlineUrl,
                            'headerTopBarLeftText' => $headerTopBarLeftText,
                            'headerTopBarLeftCta' => $headerTopBarLeftCta,
                            'headerTopBarLeftCtaUrl' => $headerTopBarLeftCtaUrl,
                            'headerSocialFollowers' => $headerSocialFollowers,
                            'headerSocialUrl' => $headerSocialUrl,
                            'headerTopBarRightTracking' => $headerTopBarRightTracking,
                            'headerTopBarRightTrackingUrl' => $headerTopBarRightTrackingUrl,
                            'headerTopBarRightLang' => $headerTopBarRightLang,
                            'headerTopBarRightCurrency' => $headerTopBarRightCurrency,
                            'headerAccountUrl' => $headerAccountUrl,
                            'headerSearchUrl' => $headerSearchUrl,
                            'headerSearchPlaceholder' => $headerSearchPlaceholder,
                            'headerSearchCategoryLabel' => $headerSearchCategoryLabel,
                            'headerSearchButtonLabel' => $headerSearchButtonLabel,
                            'headerWishlistUrl' => $headerWishlistUrl,
                            'headerCartUrl' => $headerCartUrl,
                            'headerDepartmentLabel' => $headerDepartmentLabel,
                            'headerDepartmentMenu' => $headerDepartmentMenu,
                            'headerPromoEyebrow' => $headerPromoEyebrow,
                            'headerPromoLabel' => $headerPromoLabel,
                            'headerPromoUrl' => $headerPromoUrl,
                            'headerAccountEyebrow' => $headerAccountEyebrow,
                            'headerAccountLabel' => $headerAccountLabel,
                            'headerCartLabel' => $headerCartLabel,
                            'headerMenuDrawerSide' => $headerMenuDrawerSide,
                            'headerMenuDrawerTitle' => $headerMenuDrawerTitle,
                            'headerMenuDrawerSubtitle' => $headerMenuDrawerSubtitle,
                            'headerWishlistCount' => $headerWishlistCount,
                            'headerCartCount' => $headerCartCount,
                            'headerCartTotal' => $headerCartTotal,
                            'templateSlug' => $templateSlug,
                            'sharedDemoQuery' => $sharedDemoQuery ?? [],
                        ])
                    @elseif($component === 'hero')
                        @php
                            $heroHeadline = trim((string) ($data['headline'] ?? 'Hero headline'));
                            $heroSubheading = trim((string) ($data['subheading'] ?? $data['subtitle'] ?? ''));
                            $heroCtaLabel = trim((string) ($data['hero_cta_label'] ?? $data['primary_cta']['label'] ?? ''));
                            $heroCtaUrl = trim((string) ($data['hero_cta_url'] ?? $data['primary_cta']['url'] ?? '/'));
                            $heroCtaPath = trim($heroCtaUrl, '/');
                            $heroCtaQuery = array_filter(['site' => $siteId !== '' ? $siteId : null, 'draft' => $draft !== '' ? $draft : null, 'locale' => $locale !== '' ? $locale : null, 'slug' => $heroCtaPath !== '' ? $heroCtaPath : 'home'], static fn ($v) => $v !== null);
                            $heroCtaHref = (str_starts_with($heroCtaUrl, 'http') ? $heroCtaUrl : route('template-demos.show', ['templateSlug' => $templateSlug, 'path' => $heroCtaPath !== '' ? $heroCtaPath : null] + $heroCtaQuery));
                            $heroImageUrl = trim((string) ($data['image_url'] ?? $data['hero_image_url'] ?? $thumb ?? ''));
                            $heroImageAlt = trim((string) ($data['image_alt'] ?? $data['hero_image_alt'] ?? 'Hero'));
                            $heroSplitLeftBg = trim((string) ($data['left_background_color'] ?? '#f2eeee'));
                            $heroSplitRightBg = trim((string) ($data['right_background_color'] ?? '#f2eeee'));
                            $heroEyebrow = trim((string) ($data['eyebrow'] ?? ''));
                            $heroBadge = trim((string) ($data['badge_text'] ?? ''));
                        @endphp
                        <section class="webu-hero-split-image webu-hero-split-image--hero-1">
                            <div class="webu-hero-split-image__grid">
                                <div class="webu-hero-split-image__left" style="background-color:{{ $heroSplitLeftBg }};">
                                        <div class="webu-hero-split-image__slider">
                                            <div class="webu-hero-split-image__slide webu-hero-split-image__slide--active">
                                                <div class="webu-hero-split-image__content">
                                                    @if($heroEyebrow !== '' || $heroBadge !== '')
                                                        <div class="webu-hero-split-image__top">
                                                            @if($heroBadge !== '')<span class="webu-hero-split-image__badge">{{ $heroBadge }}</span>@endif
                                                            @if($heroEyebrow !== '')<span class="webu-hero-split-image__eyebrow">{{ $heroEyebrow }}</span>@endif
                                                        </div>
                                                    @endif
                                                @if($heroHeadline !== '')<h1 class="webu-hero-split-image__headline">{{ $heroHeadline }}</h1>@endif
                                                @if($heroSubheading !== '')<p class="webu-hero-split-image__description">{{ $heroSubheading }}</p>@endif
                                                @if($heroCtaLabel !== '' && $heroCtaHref !== '')
                                                    <div class="webu-hero-split-image__actions">
                                                        <a href="{{ $heroCtaHref }}" class="webu-hero-split-image__cta">{{ $heroCtaLabel }}</a>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="webu-hero-split-image__right" style="background-color:{{ $heroSplitRightBg }};">
                                    @if($heroImageUrl !== '')
                                        <img src="{{ $heroImageUrl }}" alt="{{ $heroImageAlt }}" class="webu-hero-split-image__static-img" loading="lazy">
                                    @endif
                                </div>
                            </div>
                            <div class="webu-hero-split-image__indicators webu-hero-split-image__indicators--static" aria-hidden="true">
                                <span class="webu-hero-split-image__dot webu-hero-split-image__dot--active"></span>
                                <span class="webu-hero-split-image__dot"></span>
                                <span class="webu-hero-split-image__dot"></span>
                            </div>
                        </section>
                    @elseif($component === 'footer')
                        @php
                            $footerMenusRaw = is_array($data['menus'] ?? null) ? $data['menus'] : [];
                            $footerLinksFlat = is_array($data['links'] ?? null) ? $data['links'] : [];
                            $footerMenus = $footerMenusRaw !== [] ? $footerMenusRaw : ($footerLinksFlat !== [] ? ['Footer' => $footerLinksFlat] : []);
                            $footerLogo = trim((string) ($data['logo_text'] ?? $data['brand'] ?? 'Logo'));
                            $footerCopyright = trim((string) ($data['copyright'] ?? ''));
                            $footerAddress = trim((string) ($data['contact_address'] ?? $data['address'] ?? ''));
                            $footerDefaultColumns = [
                                [
                                    'title' => 'Explore',
                                    'links' => [
                                        ['label' => 'Jewellery', 'url' => '/shop'],
                                        ['label' => 'High Jewellery', 'url' => '/collections/high-jewellery'],
                                        ['label' => 'Wedding & Engagement', 'url' => '/collections/wedding-engagement'],
                                        ['label' => 'Provenance and Peace', 'url' => '/collections/provenance-and-peace'],
                                        ['label' => 'Clocks', 'url' => '/collections/clocks'],
                                    ],
                                ],
                                [
                                    'title' => 'Follow Us',
                                    'links' => [
                                        ['label' => 'Facebook', 'url' => 'https://facebook.com'],
                                        ['label' => 'Twitter', 'url' => 'https://twitter.com'],
                                        ['label' => 'Instagram', 'url' => 'https://instagram.com'],
                                        ['label' => 'YouTube', 'url' => 'https://youtube.com'],
                                        ['label' => 'Pinterest', 'url' => 'https://pinterest.com'],
                                    ],
                                ],
                                [
                                    'title' => 'About Us',
                                    'links' => [
                                        ['label' => 'About Us', 'url' => '/about'],
                                        ['label' => 'The Rewards Stack', 'url' => '/rewards'],
                                        ['label' => 'Sustainability', 'url' => '/sustainability'],
                                        ['label' => 'Careers', 'url' => '/careers'],
                                        ['label' => 'Blog', 'url' => '/blog'],
                                    ],
                                ],
                            ];
                            $footerMenuColumns = [];
                            foreach ($footerMenus as $menuKey => $menuLinks) {
                                if (! is_array($menuLinks) || $menuLinks === []) {
                                    continue;
                                }
                                $footerMenuColumns[] = [
                                    'title' => ucwords(str_replace(['-', '_'], ' ', is_string($menuKey) ? $menuKey : 'Footer')),
                                    'links' => array_values(array_filter($menuLinks, static fn ($link) => is_array($link) && trim((string) ($link['label'] ?? '')) !== '')),
                                ];
                            }
                            $footerIsFallbackMenu = count($footerMenuColumns) === 1
                                && strtolower((string) ($footerMenuColumns[0]['title'] ?? '')) === 'footer'
                                && implode('|', array_map(static fn ($link) => strtolower(trim((string) ($link['label'] ?? ''))), $footerMenuColumns[0]['links'] ?? [])) === 'shop|about|contact';
                            if ($footerMenuColumns === [] || $footerIsFallbackMenu) {
                                $footerMenuColumns = $footerDefaultColumns;
                            }
                            $footerMenuColumns = array_slice($footerMenuColumns, 0, 3);
                            $footerResolvedCopyright = $footerCopyright !== '' ? $footerCopyright : '© ' . date('Y') . ' — ' . $footerLogo . '. All Rights Reserved.';
                            $footerPaymentBadges = [
                                ['label' => 'AMEX', 'tone' => 'amex'],
                                ['label' => 'MC', 'tone' => 'mastercard'],
                                ['label' => 'VISA', 'tone' => 'visa'],
                                ['label' => 'PayPal', 'tone' => 'paypal'],
                                ['label' => 'Apple Pay', 'tone' => 'applepay'],
                                ['label' => 'Klarna', 'tone' => 'klarna'],
                            ];
                            $footerResolveHref = static function (string $url) use ($templateSlug, $sharedDemoQuery): string {
                                $url = trim($url);
                                if ($url === '') {
                                    return '#';
                                }
                                if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
                                    return $url;
                                }

                                return route('template-demos.show', ['templateSlug' => $templateSlug, 'path' => trim($url, '/') ?: null] + ($sharedDemoQuery ?? []) + ['slug' => trim($url, '/') ?: 'home']);
                            };
                        @endphp
                        <footer class="webu-footer webu-footer--footer-1">
                            <div class="webu-footer__inner">
                                <div class="webu-footer__grid">
                                    <div class="webu-footer__newsletter-block">
                                        <p class="webu-footer__heading">OUR NEWSLETTER</p>
                                        <p class="webu-footer__newsletter-copy">It only takes a second to be the first to find out about our latest news</p>
                                        @if($footerAddress !== '')<p class="webu-footer__support-text">{{ $footerAddress }}</p>@endif
                                        <form class="webu-footer__newsletter-form" action="#" method="get" onsubmit="return false;">
                                            <input type="email" class="webu-footer__newsletter-input" placeholder="Your Email..." aria-label="Your Email...">
                                            <button type="submit" class="webu-footer__newsletter-button">SUBSCRIBE</button>
                                        </form>
                                        <div class="webu-footer__payments" aria-label="Secure payment methods">
                                            <span class="webu-footer__payments-label">SECURE PAYMENT:</span>
                                            <div class="webu-footer__payment-list">
                                                @foreach($footerPaymentBadges as $badge)
                                                    <span class="webu-footer__payment-chip webu-footer__payment-chip--{{ $badge['tone'] }}">{{ $badge['label'] }}</span>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                    @foreach($footerMenuColumns as $column)
                                        @if(is_array($column['links'] ?? null) && ($column['links'] ?? []) !== [])
                                            <nav class="webu-footer__nav" aria-label="{{ $column['title'] ?? 'Footer' }}">
                                                <p class="webu-footer__heading">{{ strtoupper((string) ($column['title'] ?? 'Footer')) }}</p>
                                                <div class="webu-footer__menu">
                                                    @foreach($column['links'] as $link)
                                                        @php
                                                            $footerLinkUrl = trim((string) ($link['url'] ?? ''));
                                                            $footerIsExternalLink = str_starts_with($footerLinkUrl, 'http://') || str_starts_with($footerLinkUrl, 'https://');
                                                        @endphp
                                                        <a class="webu-footer__link" href="{{ $footerResolveHref($footerLinkUrl) }}" @if($footerIsExternalLink) target="_blank" rel="noreferrer" @endif>{{ $link['label'] ?? '' }}</a>
                                                    @endforeach
                                                </div>
                                            </nav>
                                        @endif
                                    @endforeach
                                </div>
                                <div class="webu-footer__bottom">
                                    <span class="webu-footer__copyright">{{ $footerResolvedCopyright }}</span>
                                </div>
                            </div>
                        </footer>
                    @elseif($normalizedKey === 'webu_general_offcanvas_menu_01')
                        @php
                            $drawerSide = strtolower(trim((string) ($data['side'] ?? 'left')));
                            $drawerSide = in_array($drawerSide, ['left', 'right'], true) ? $drawerSide : 'left';
                            $drawerTitle = trim((string) ($data['title'] ?? 'Shop navigation'));
                            $drawerSubtitle = trim((string) ($data['subtitle'] ?? 'Reusable drawer for desktop hamburger and mobile navigation.'));
                            $drawerTriggerLabel = trim((string) ($data['trigger_label'] ?? 'Open menu'));
                            $drawerDescription = trim((string) ($data['description'] ?? 'Trigger and panel are bundled so the same component can be reused in headers, sidebars and mobile menus.'));
                            $drawerFooterLabel = trim((string) ($data['footer_label'] ?? 'Shop all'));
                            $drawerFooterUrl = trim((string) ($data['footer_url'] ?? '/shop'));
                            $drawerOpenByDefault = filter_var($data['open_by_default'] ?? true, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
                            $drawerOpenByDefault = $drawerOpenByDefault !== null ? $drawerOpenByDefault : true;
                            $drawerShowClose = filter_var($data['show_close'] ?? true, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
                            $drawerShowClose = $drawerShowClose !== null ? $drawerShowClose : true;
                            $drawerItemsRaw = is_array($data['menu_items'] ?? null) ? $data['menu_items'] : [];
                            $drawerItems = [];
                            foreach ($drawerItemsRaw as $item) {
                                if (! is_array($item)) {
                                    continue;
                                }
                                $itemLabel = trim((string) ($item['label'] ?? 'Menu item'));
                                $itemUrl = trim((string) ($item['url'] ?? '#'));
                                $drawerItems[] = [
                                    'label' => $itemLabel !== '' ? $itemLabel : 'Menu item',
                                    'href' => (str_starts_with($itemUrl, 'http://') || str_starts_with($itemUrl, 'https://') || $itemUrl === '#')
                                        ? $itemUrl
                                        : route('template-demos.show', ['templateSlug' => $templateSlug, 'path' => trim($itemUrl, '/') ?: null] + ($sharedDemoQuery ?? []) + ['slug' => trim($itemUrl, '/') ?: 'home']),
                                    'description' => trim((string) ($item['description'] ?? '')),
                                ];
                            }
                            $drawerPanelId = 'webu-general-offcanvas-generic-' . substr(md5($normalizedKey . json_encode($drawerItems) . $drawerSide), 0, 10);
                            $drawerFooterHref = (str_starts_with($drawerFooterUrl, 'http://') || str_starts_with($drawerFooterUrl, 'https://') || $drawerFooterUrl === '#')
                                ? $drawerFooterUrl
                                : route('template-demos.show', ['templateSlug' => $templateSlug, 'path' => trim($drawerFooterUrl, '/') ?: null] + ($sharedDemoQuery ?? []) + ['slug' => trim($drawerFooterUrl, '/') ?: 'shop']);
                        @endphp
                        <section class="webu-offcanvas-menu-demo">
                            <div class="webu-offcanvas-menu-demo__content">
                                <span class="webu-offcanvas-menu-demo__eyebrow">{{ ucfirst($drawerSide) }} drawer</span>
                                <h3 class="webu-offcanvas-menu-demo__title">{{ $drawerTriggerLabel }}</h3>
                                <p class="webu-offcanvas-menu-demo__description">{{ $drawerDescription }}</p>
                                <button
                                    type="button"
                                    class="webu-offcanvas-menu-demo__trigger"
                                    aria-controls="{{ $drawerPanelId }}"
                                    aria-expanded="{{ $drawerOpenByDefault ? 'true' : 'false' }}"
                                    data-webu-offcanvas-trigger="{{ $drawerPanelId }}"
                                >
                                    <span class="webu-offcanvas-menu-demo__trigger-bars" aria-hidden="true">
                                        <span></span>
                                        <span></span>
                                        <span></span>
                                    </span>
                                    <span>{{ $drawerTriggerLabel }}</span>
                                </button>
                            </div>
                            @include('template-demos.partials.offcanvas-menu', [
                                'panelId' => $drawerPanelId,
                                'panelTitle' => $drawerTitle,
                                'panelSubtitle' => $drawerSubtitle,
                                'panelSide' => $drawerSide,
                                'panelItems' => $drawerItems,
                                'panelFooterLabel' => $drawerFooterLabel,
                                'panelFooterHref' => $drawerFooterHref,
                                'showPanelClose' => $drawerShowClose,
                                'previewMode' => true,
                                'openByDefault' => $drawerOpenByDefault,
                            ])
                        </section>
                    @elseif(in_array($component, ['newsletter', 'webu_general_newsletter_01'], true))
                        @php
                            $newsletterTitle = trim((string) ($data['title'] ?? 'Stay updated'));
                            $newsletterText = trim((string) ($data['text'] ?? $data['subtitle'] ?? 'Subscribe for offers and news.'));
                            $newsletterPlaceholder = trim((string) ($data['placeholder'] ?? 'Your email'));
                            $newsletterButtonLabel = trim((string) ($data['button_label'] ?? 'Subscribe'));
                        @endphp
                        <section class="webu-newsletter webu-newsletter--newsletter-1">
                            <div class="webu-newsletter__inner">
                                <h3 class="webu-newsletter__title">{{ $newsletterTitle }}</h3>
                                <p class="webu-newsletter__text">{{ $newsletterText }}</p>
                                <form class="webu-newsletter__form" action="#" method="get" onsubmit="return false;">
                                    <input type="email" class="webu-newsletter__input" placeholder="{{ $newsletterPlaceholder }}" aria-label="{{ $newsletterPlaceholder }}">
                                    <button type="submit" class="webu-newsletter__button">{{ $newsletterButtonLabel }}</button>
                                </form>
                            </div>
                        </section>
                    @elseif($component === 'products')
                        <div class="grid">
                            @foreach(($data['products'] ?? []) as $item)
                                <article class="card">
                                    @if(!empty($item['image_url']))<img src="{{ $item['image_url'] }}" alt="{{ $item['name'] ?? 'Product' }}" loading="lazy">@endif
                                    <h3>{{ $item['name'] ?? 'Product' }}</h3>
                                    <p>{{ $item['price'] ?? '' }} @if(!empty($item['old_price']))<span style="text-decoration:line-through;">{{ $item['old_price'] }}</span>@endif</p>
                                </article>
                            @endforeach
                        </div>
                    @elseif(in_array($component, ['gallery', 'team', 'categories', 'testimonials', 'logos'], true))
                        @php
                            $collection = $data['items'] ?? $data['gallery'] ?? $data['members'] ?? $data['categories'] ?? $data['logos'] ?? [];
                        @endphp
                        <div class="grid">
                            @foreach($collection as $item)
                                <article class="card">
                                    @if(!empty($item['image_url']))<img src="{{ $item['image_url'] }}" alt="{{ $item['title'] ?? $item['name'] ?? 'Item' }}" loading="lazy">@endif
                                    @if(!empty($item['avatar_url']))<img src="{{ $item['avatar_url'] }}" alt="{{ $item['name'] ?? 'Member' }}" loading="lazy">@endif
                                    <h3>{{ $item['title'] ?? $item['name'] ?? $item['label'] ?? 'Item' }}</h3>
                                    <p>{{ $item['role'] ?? $item['quote'] ?? $item['slug'] ?? '' }}</p>
                                </article>
                            @endforeach
                        </div>
                    @elseif($component === 'faq')
                        <ul class="list">
                            @foreach(($data['items'] ?? []) as $faq)
                                <li><strong>{{ $faq['q'] ?? 'Question' }}</strong> - {{ $faq['a'] ?? '' }}</li>
                            @endforeach
                        </ul>
                    @elseif($component === 'contact')
                        @php $contact = $data['contact'] ?? []; @endphp
                        <ul class="list">
                            <li>Email: {{ $contact['email'] ?? 'demo@example.com' }}</li>
                            <li>Phone: {{ $contact['phone'] ?? '+995 555 00 00 00' }}</li>
                            <li>Address: {{ $contact['address'] ?? 'Tbilisi, Georgia' }}</li>
                        </ul>
                    @elseif($component === 'stats')
                        <div class="grid">
                            @foreach(($data['items'] ?? []) as $stat)
                                <article class="card">
                                    <h3>{{ $stat['value'] ?? '-' }}</h3>
                                    <p>{{ $stat['label'] ?? '' }}</p>
                                </article>
                            @endforeach
                        </div>
                    @elseif($component === 'table')
                        @php $columns = $data['columns'] ?? []; @endphp
                        <table>
                            @if($columns)
                                <thead><tr>@foreach($columns as $column)<th>{{ $column }}</th>@endforeach</tr></thead>
                            @endif
                            <tbody>
                            @foreach(($data['rows'] ?? []) as $row)
                                <tr>@foreach($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>
                            @endforeach
                            </tbody>
                        </table>
                    @else
                        @if(!empty($data['headline']))<h3 style="margin:8px 0;">{{ $data['headline'] }}</h3>@endif
                        <p>{{ $data['subtitle'] ?? $data['body'] ?? $data['summary'] ?? 'Demo section content.' }}</p>
                    @endif
                @endif
