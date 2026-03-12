'use client';

import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Sparkles, Loader2, Target } from 'lucide-react';
import type { ComponentScoringReport } from '@/builder/ai/componentScoring';

export interface AIImprovementItem {
  id: string;
  label: string;
}

export interface AIImproveSitePanelProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  /** List of improvement suggestions (e.g. "Add testimonials section", "Upgrade hero layout"). */
  improvements: AIImprovementItem[];
  onApplyImprovement: (index: number) => void;
  onApplyAllImprovements?: () => void;
  applyingIndex?: number | null;
  isApplyingAll?: boolean;
  hasSections?: boolean;
  /** Part 11 — Component scores (e.g. hero 6/10, cta 3/10). AI focuses on lowest. */
  scoring?: ComponentScoringReport | null;
  /** Part 12 — When true, suggestions are from continuous improvement mode (auto-analyze). */
  isAutoImproveMode?: boolean;
  t?: (key: string) => string;
}

export function AIImproveSitePanel({
  open,
  onOpenChange,
  improvements,
  onApplyImprovement,
  onApplyAllImprovements,
  applyingIndex = null,
  isApplyingAll = false,
  hasSections = true,
  scoring = null,
  isAutoImproveMode = false,
  t = (k) => k,
}: AIImproveSitePanelProps) {
  const count = improvements.length;
  const hasSuggestions = hasSections && count > 0;
  const canApplyAll = hasSuggestions && onApplyAllImprovements && !isApplyingAll && applyingIndex === null;

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md" showClose>
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <Sparkles className="h-5 w-5 text-primary" />
            {t('AI Improve Site')}
          </DialogTitle>
          <DialogDescription>
            {isAutoImproveMode && (
              <span className="block">{t('Auto Improve is on. Layout is analyzed continuously; suggestions update as you edit.')}</span>
            )}
            {hasSuggestions ? (
              <span className="block font-medium">{t('Your site can be improved.')}</span>
            ) : count === 0 && !isAutoImproveMode ? (
              t('No improvements suggested for this page right now.')
            ) : null}
            {hasSuggestions && (
              <span className="block text-muted-foreground">
                {count === 1 ? t('1 suggestion') : t('X suggestions').replace('X', String(count))}
              </span>
            )}
          </DialogDescription>
        </DialogHeader>

        {scoring && scoring.scores.length > 0 ? (
          <div className="rounded-lg border bg-muted/30 px-3 py-2 text-sm">
            <p className="font-medium text-muted-foreground">{t('Component scores')}</p>
            <p className="mt-0.5 font-mono text-xs">{scoring.summary}</p>
            {scoring.lowestSectionKind ? (
              <p className="mt-1 flex items-center gap-1.5 text-xs text-primary">
                <Target className="h-3.5 w-3.5 shrink-0" />
                {t('Focus')}: {scoring.lowestSectionKind} ({t('lowest score')})
              </p>
            ) : null}
          </div>
        ) : null}

        {!hasSections ? (
          <p className="text-sm text-muted-foreground">
            {t('Add at least one section to the page to get AI suggestions.')}
          </p>
        ) : !hasSuggestions ? null : (
          <div className="space-y-3">
            {canApplyAll ? (
              <Button
                type="button"
                className="w-full gap-2"
                onClick={onApplyAllImprovements}
                disabled={isApplyingAll}
              >
                {isApplyingAll ? (
                  <>
                    <Loader2 className="h-4 w-4 animate-spin shrink-0" />
                    {t('Applying…')}
                  </>
                ) : (
                  t('Apply all improvements')
                )}
              </Button>
            ) : null}
            <p className="text-xs font-medium text-muted-foreground">{t('Suggestions')}</p>
            <ul className="space-y-2">
              {improvements.map((item, index) => (
                <li
                  key={item.id}
                  className="flex items-center justify-between gap-3 rounded-lg border bg-card px-3 py-2"
                >
                  <span className="text-sm">{item.label}</span>
                  <Button
                    type="button"
                    size="sm"
                    variant="secondary"
                    disabled={(applyingIndex !== null && applyingIndex !== index) || isApplyingAll}
                    onClick={() => onApplyImprovement(index)}
                  >
                    {applyingIndex === index ? (
                      <>
                        <Loader2 className="h-3.5 w-3.5 animate-spin shrink-0" />
                        {t('Applying…')}
                      </>
                    ) : (
                      t('Apply improvement')
                    )}
                  </Button>
                </li>
              ))}
            </ul>
          </div>
        )}
      </DialogContent>
    </Dialog>
  );
}
