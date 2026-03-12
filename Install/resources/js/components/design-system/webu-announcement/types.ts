export type AnnouncementVariant = 'announcement-1' | 'announcement-2' | 'announcement-3';

export interface WebuAnnouncementProps {
  variant?: AnnouncementVariant;
  /** From CMS */
  text: string;
  linkUrl?: string;
  linkLabel?: string;
  /** For countdown variant */
  countdownEnd?: string;
  basePath?: string;
  className?: string;
}
