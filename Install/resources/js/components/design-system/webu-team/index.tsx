import type { WebuTeamProps, TeamMember } from './types';
import { Team1 } from './variants/team-1';

const VARIANTS = ['team-1', 'team-2'] as const;
const DEFAULT: (typeof VARIANTS)[number] = 'team-1';

export type { WebuTeamProps, TeamMember };

export function WebuTeam({ variant, ...props }: WebuTeamProps) {
  const v = variant && VARIANTS.includes(variant as (typeof VARIANTS)[number]) ? variant : DEFAULT;
  return <Team1 {...props} />;
}
