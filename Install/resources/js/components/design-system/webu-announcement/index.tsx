import type { WebuAnnouncementProps } from './types';
import { Announcement1 } from './variants/announcement-1';

const VARIANTS = ['announcement-1', 'announcement-2', 'announcement-3'] as const;
const DEFAULT: (typeof VARIANTS)[number] = 'announcement-1';

export type { WebuAnnouncementProps };

export function WebuAnnouncement({ variant, ...props }: WebuAnnouncementProps) {
  const v = variant && VARIANTS.includes(variant as (typeof VARIANTS)[number]) ? variant : DEFAULT;
  return <Announcement1 {...props} />;
}
