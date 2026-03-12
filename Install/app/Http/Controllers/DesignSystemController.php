<?php

namespace App\Http\Controllers;

use App\Services\WebuCmsResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Component Playground — dev-only UI to inspect and refine design-system components.
 * Uses real CMS data when available (e.g. site in context), else demo data from WebuCmsResolver.
 * Exclude /design-system from sitemap; response sends X-Robots-Tag: noindex.
 */
class DesignSystemController extends Controller
{
    public function index(Request $request, WebuCmsResolver $cms)
    {
        $site = null;

        $siteSettings = $cms->getSiteSettings($site);
        $navigation = $cms->getNavigation($site, null, $request->get('locale'));
        $products = $cms->getProducts($site, ['limit' => 12]);
        $categories = $cms->getCategories($site);
        $footerData = $cms->getFooterData($site, $request->get('locale'));
        $testimonials = $cms->getTestimonials($site);
        $features = $cms->getFeatures($site);
        $faq = $cms->getFaq($site);
        $blogPosts = $cms->getBlogPosts($site, 6);
        $announcement = $cms->getAnnouncement($site);
        $stats = $cms->getStats($site);
        $team = $cms->getTeam($site);

        $footerMenusForPlayground = [];
        foreach ($footerData['menus'] as $key => $items) {
            $footerMenusForPlayground[$key] = $items;
        }
        if ($footerMenusForPlayground === []) {
            $footerMenusForPlayground = [
                'footer' => [
                    ['label' => 'Shop', 'url' => '/shop'],
                    ['label' => 'About', 'url' => '/about'],
                    ['label' => 'Contact', 'url' => '/contact'],
                ],
            ];
        }

        $productsForCms = array_map(function ($p) {
            return [
                'id' => $p['id'] ?? $p['slug'],
                'name' => $p['name'] ?? $p['title'] ?? 'Product',
                'slug' => $p['slug'] ?? 'product',
                'price' => $p['price'] ?? '0 GEL',
                'old_price' => $p['old_price'] ?? null,
                'image_url' => $p['image_url'] ?? null,
                'url' => $p['url'] ?? '/shop/'.$p['slug'],
            ];
        }, $products);

        $inertiaResponse = Inertia::render('DesignSystem', [
            'demoMode' => true,
            'cms' => [
                'siteSettings' => $siteSettings,
                'navigation' => $navigation,
                'products' => $productsForCms,
                'categories' => $categories,
                'footer' => [
                    'menus' => $footerMenusForPlayground,
                    'contactAddress' => $footerData['layout']['contact_address'] ?? '',
                ],
                'testimonials' => $testimonials,
                'features' => $features,
                'faq' => $faq,
                'blogPosts' => $blogPosts,
                'announcement' => $announcement,
                'stats' => $stats,
                'team' => $team,
            ],
        ]);
        $response = $inertiaResponse->toResponse($request);
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
