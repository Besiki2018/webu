@php
    $panelId = trim((string) ($panelId ?? 'webu-offcanvas-menu'));
    $panelTitle = trim((string) ($panelTitle ?? 'Menu'));
    $panelSubtitle = trim((string) ($panelSubtitle ?? ''));
    $panelSideRaw = strtolower(trim((string) ($panelSide ?? 'left')));
    $panelSide = in_array($panelSideRaw, ['left', 'right'], true) ? $panelSideRaw : 'left';
    $panelItems = is_array($panelItems ?? null) ? $panelItems : [];
    $panelFooterLabel = trim((string) ($panelFooterLabel ?? ''));
    $panelFooterHref = trim((string) ($panelFooterHref ?? '#'));
    $showPanelClose = (bool) ($showPanelClose ?? true);
    $previewMode = (bool) ($previewMode ?? false);
    $openByDefault = (bool) ($openByDefault ?? false);
@endphp
<div class="webu-offcanvas-menu-shell{{ $previewMode ? ' webu-offcanvas-menu-shell--preview' : '' }}{{ $openByDefault ? ' is-open' : '' }}" data-webu-offcanvas="{{ $panelId }}" data-webu-offcanvas-side="{{ $panelSide }}">
    <button type="button" class="webu-offcanvas-menu__backdrop" data-webu-offcanvas-close aria-label="Close menu"></button>
    <aside class="webu-offcanvas-menu webu-offcanvas-menu--{{ $panelSide }}" role="dialog" aria-modal="true" aria-label="{{ $panelTitle }}">
        <div class="webu-offcanvas-menu__header">
            <div class="webu-offcanvas-menu__heading">
                <span class="webu-offcanvas-menu__eyebrow">Navigation</span>
                <h3 class="webu-offcanvas-menu__title">{{ $panelTitle }}</h3>
                @if($panelSubtitle !== '')
                    <p class="webu-offcanvas-menu__subtitle">{{ $panelSubtitle }}</p>
                @endif
            </div>
            @if($showPanelClose)
                <button type="button" class="webu-offcanvas-menu__close" data-webu-offcanvas-close aria-label="Close menu">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M6 6L18 18"></path>
                        <path d="M18 6L6 18"></path>
                    </svg>
                </button>
            @endif
        </div>
        <div class="webu-offcanvas-menu__body">
            <nav class="webu-offcanvas-menu__nav" aria-label="{{ $panelTitle }}">
                @foreach($panelItems as $item)
                    @php
                        $label = trim((string) ($item['label'] ?? 'Menu item'));
                        $href = trim((string) ($item['href'] ?? $item['url'] ?? '#'));
                        $description = trim((string) ($item['description'] ?? ''));
                    @endphp
                    <a href="{{ $href !== '' ? $href : '#' }}" class="webu-offcanvas-menu__link">
                        <span class="webu-offcanvas-menu__link-copy">
                            <span class="webu-offcanvas-menu__link-label">{{ $label }}</span>
                            @if($description !== '')
                                <span class="webu-offcanvas-menu__link-description">{{ $description }}</span>
                            @endif
                        </span>
                        <span class="webu-offcanvas-menu__link-arrow" aria-hidden="true">→</span>
                    </a>
                @endforeach
            </nav>
        </div>
        @if($panelFooterLabel !== '')
            <div class="webu-offcanvas-menu__footer">
                <a href="{{ $panelFooterHref !== '' ? $panelFooterHref : '#' }}" class="webu-offcanvas-menu__footer-link">{{ $panelFooterLabel }}</a>
            </div>
        @endif
    </aside>
</div>
