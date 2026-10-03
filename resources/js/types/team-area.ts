import type { RoleOption } from '@/types';

export type TeamTab = {
    id: string;
    label: string;
    href: string;
    current: boolean;
};

export type SummaryCard = {
    key: string;
    label: string;
    figure: string;
    caption: string;
};

export type TeamAreaHeader = {
    title: string;
    section: string;
    description: string;
    tabsLabel: string;
    teamName: string;
    teamSlug: string;
    tabs: TeamTab[];
    canInvite: boolean;
    inviteRoles: RoleOption[];
};

export type PageLinks = {
    showing: string;
    previousUrl: string | null;
    nextUrl: string | null;
};

export type RowAction =
    | {
          kind: 'roles';
          label: string;
          url: string;
          options: { value: string; label: string }[];
      }
    | {
          kind: 'post';
          label: string;
          url: string;
          confirm: string | null;
          fields: Record<string, string | boolean>;
          /** Shown apart from routine actions, in the destructive style. */
          destructive?: boolean;
      }
    | {
          kind: 'delete';
          label: string;
          url: string;
      };
