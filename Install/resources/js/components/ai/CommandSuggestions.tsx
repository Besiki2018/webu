/**
 * Quick command suggestion buttons for the AI editing command library.
 * Use near the instruction input so users can apply common commands with one click.
 */
import React from 'react';
import { QUICK_COMMAND_SUGGESTIONS } from '@/ai/commands/patterns';
import { Button } from '@/components/ui/button';

export interface CommandSuggestionsProps {
  /** Called when user clicks a suggestion; receives the example command text */
  onSelectCommand: (example: string) => void;
  /** Optional: disable all buttons (e.g. while AI is running) */
  disabled?: boolean;
  /** Optional: max number of suggestions to show (default: all) */
  maxSuggestions?: number;
  /** Optional class for the container */
  className?: string;
  /** Optional: show labels only (no wrapping layout hint) */
  compact?: boolean;
  /** Optional: title above the buttons (e.g. translated "Quick commands") */
  title?: string;
}

export function CommandSuggestions({
  onSelectCommand,
  disabled = false,
  maxSuggestions = QUICK_COMMAND_SUGGESTIONS.length,
  className = '',
  compact = false,
  title = 'Quick commands',
}: CommandSuggestionsProps) {
  const suggestions = QUICK_COMMAND_SUGGESTIONS.slice(0, maxSuggestions);

  return (
    <div className={className}>
      {!compact && title ? (
        <p className="text-xs text-muted-foreground mb-2">
          {title}
        </p>
      ) : null}
      <div className="flex flex-wrap gap-1.5">
        {suggestions.map(({ label, example }) => (
          <Button
            key={example}
            type="button"
            variant="outline"
            size="sm"
            className="text-xs h-7"
            disabled={disabled}
            onClick={() => onSelectCommand(example)}
            aria-label={label}
          >
            {label}
          </Button>
        ))}
      </div>
    </div>
  );
}
