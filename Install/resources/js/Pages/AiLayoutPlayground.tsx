/**
 * AI Layout Generator Playground.
 * Enter prompt → generate layout JSON → render with mock CMS data.
 * Test that components, variants, and bindings work.
 */

import { Head, Link, router } from '@inertiajs/react';
import { route } from 'ziggy-js';
import { useState, useMemo } from 'react';
import { LayoutRenderer } from '@/ai-layout/LayoutRenderer';
import type { AILayoutSchema, CMSData } from '@/ai-layout/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

const MOCK_CMS_DATA: CMSData = {
  site: {
    name: 'Webu Store',
    logo: 'Webu Store',
    hero: {
      title: 'Welcome to Our Store',
      subtitle: 'Discover amazing products for every need.',
      image: 'https://via.placeholder.com/1200x600/5B5BD6/ffffff?text=Hero',
      cta_text: 'Shop Now',
      cta_url: '/shop',
    },
    categories_title: 'Shop by Category',
    featured_title: 'Featured Products',
    footer: {
      links: [
        { label: 'Shop', url: '/shop' },
        { label: 'About', url: '/about' },
        { label: 'Contact', url: '/contact' },
      ],
      contact: 'contact@example.com',
      copyright: '© Webu AI Layout. Demo only.',
    },
  },
  products: {
    featured: [
      { id: '1', slug: 'p1', title: 'Product One', price: 49.99, currency: 'GEL', image: 'https://via.placeholder.com/400', badge: 'New', rating: 4.5 },
      { id: '2', slug: 'p2', title: 'Product Two', price: 29.99, currency: 'GEL', image: 'https://via.placeholder.com/400', badge: 'Sale', rating: 4 },
      { id: '3', slug: 'p3', title: 'Product Three', price: 79.99, currency: 'GEL', image: 'https://via.placeholder.com/400', rating: 5 },
    ],
  },
  categories: {
    main: [
      { id: 'c1', title: 'Electronics', image: 'https://via.placeholder.com/400x300', link: '/shop?cat=electronics' },
      { id: 'c2', title: 'Fashion', image: 'https://via.placeholder.com/400x300', link: '/shop?cat=fashion' },
      { id: 'c3', title: 'Home', image: 'https://via.placeholder.com/400x300', link: '/shop?cat=home' },
    ],
  },
  banners: {
    promo: {
      title: 'Limited offer',
      subtitle: 'Free shipping on orders over 100 GEL.',
      cta_text: 'Shop now',
      cta_url: '/shop',
    },
    home: {
      title: 'Summer Sale',
      subtitle: 'Up to 50% off.',
      cta_text: 'View deals',
      cta_url: '/shop',
    },
  },
  newsletter: {
    form: { title: 'Stay updated', subtitle: 'Subscribe for offers and news.' },
  },
};

const DEFAULT_PROMPT = 'I need an online store selling cosmetics. Minimal design. Soft pastel colors. Featured products on homepage. Newsletter signup.';

export default function AiLayoutPlaygroundPage() {
  const projectIdFromUrl = useMemo(() => {
    if (typeof window === 'undefined') return null;
    const params = new URLSearchParams(window.location.search);
    return params.get('project_id');
  }, []);
  const [prompt, setPrompt] = useState(DEFAULT_PROMPT);
  const [layout, setLayout] = useState<AILayoutSchema | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [showJson, setShowJson] = useState(false);
  const [createLoading, setCreateLoading] = useState(false);
  const [projectName, setProjectName] = useState('AI Store');

  const handleGenerate = async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await fetch('/api/ai-layout/generate', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': getCsrfToken(), Accept: 'application/json' },
        body: JSON.stringify({ prompt }),
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data.error || 'Generate failed');
      setLayout(data.layout ?? null);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to generate layout');
    } finally {
      setLoading(false);
    }
  };

  const handleCreateProject = async () => {
    if (!layout) return;
    setCreateLoading(true);
    setError(null);
    try {
      const res = await fetch('/api/ai-layout/create-project', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': getCsrfToken(), Accept: 'application/json' },
        body: JSON.stringify({
          layout,
          theme_tokens: {},
          project_name: projectName.trim() || 'AI Store',
        }),
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data.error || 'Create project failed');
      if (data.redirect_url) {
        router.visit(data.redirect_url);
      } else if (data.project_id) {
        router.visit(route('project.cms', data.project_id));
      }
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to create project');
    } finally {
      setCreateLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-background">
      <Head title="AI Layout Playground" />
      <div className="max-w-4xl mx-auto p-6 space-y-6">
        <h1 className="text-2xl font-bold">AI Layout Generator Playground</h1>
        <p className="text-muted-foreground">
          Enter a prompt to generate layout JSON. Layout is rendered with Webu components and mock CMS data. No raw HTML.{' '}
          <Link href="/design-system" className="text-primary underline">Component playground</Link>
        </p>
        {projectIdFromUrl && (
          <p className="rounded-lg border bg-muted/50 px-4 py-2 text-sm text-muted-foreground">
            Opened from project. Generate a new layout and &quot;Create project&quot; to make a new site, or{' '}
            <Link href={route('project.cms', projectIdFromUrl)} className="text-primary underline">return to the builder</Link>.
          </p>
        )}

        <div className="grid gap-4 sm:grid-cols-2">
          <div className="space-y-2">
            <Label htmlFor="prompt">Prompt</Label>
            <Textarea
              id="prompt"
              value={prompt}
              onChange={(e) => setPrompt(e.target.value)}
              placeholder="e.g. Build me an online clothing store. Modern design. Featured products. Newsletter."
              rows={4}
              className="w-full"
            />
          </div>
          <div className="space-y-2">
            <Label htmlFor="project-name">Project name (for Create project)</Label>
            <Input
              id="project-name"
              type="text"
              value={projectName}
              onChange={(e) => setProjectName(e.target.value)}
              placeholder="AI Store"
            />
          </div>
        </div>

        <div className="flex flex-wrap gap-2">
          <Button onClick={handleGenerate} disabled={loading}>
            {loading ? 'Generating…' : 'Generate layout'}
          </Button>
          <Button variant="outline" onClick={() => setShowJson((v) => !v)} disabled={!layout}>
            {showJson ? 'Hide JSON' : 'Show JSON'}
          </Button>
          <Button variant="secondary" onClick={handleCreateProject} disabled={!layout || createLoading}>
            {createLoading ? 'Creating…' : 'Create project'}
          </Button>
        </div>

        {error && (
          <div className="rounded-md bg-destructive/10 text-destructive px-4 py-2 text-sm">
            {error}
          </div>
        )}

        {showJson && layout && (
          <pre className="rounded-lg bg-muted p-4 text-xs overflow-auto max-h-64">
            {JSON.stringify(layout, null, 2)}
          </pre>
        )}

        {layout && (
          <div className="border-t pt-8">
            <h2 className="text-lg font-semibold mb-4">Rendered layout (mock CMS data)</h2>
            {layout.sections?.length ? (
              <LayoutRenderer layout={layout} cmsData={MOCK_CMS_DATA} basePath="" />
            ) : (
              <p className="text-muted-foreground">No sections in this layout. Try a different prompt or preset.</p>
            )}
          </div>
        )}
        {!layout && !loading && (
          <p className="text-muted-foreground text-sm">Click &quot;Generate layout&quot; to create a layout from your prompt and see the preview below.</p>
        )}
      </div>
    </div>
  );
}

function getCsrfToken(): string {
  const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
  return match ? decodeURIComponent(match[1]) : '';
}
