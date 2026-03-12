<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

/**
 * Webu Builder JSON preview routes (e.g. Ekka demo-8 conversion).
 * Redirects to the template live demo for the given slug.
 */
class BuilderPreviewController extends Controller
{
    /**
     * Show Ekka demo-8 template preview (redirects to template-demos).
     */
    public function ekkaDemo8(): RedirectResponse
    {
        return redirect()->route('template-demos.show', ['templateSlug' => 'ekka-demo-8'], 302);
    }
}
