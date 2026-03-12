export type TeamVariant = 'team-1' | 'team-2';

export interface TeamMember {
  name: string;
  role?: string;
  avatar?: string;
}

export interface WebuTeamProps {
  variant?: TeamVariant;
  title?: string;
  members: TeamMember[];
  basePath?: string;
  className?: string;
}
