import { Link, usePage } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { cn } from '@/lib/utils';

/**
 * Reminder bell with the unread count, for facility staff while patients is
 * on. The server sends null to hide it.
 */
export function NotificationBell({ className }: { className?: string }) {
    const { notifications } = usePage().props;

    if (!notifications) {
        return null;
    }

    return (
        <Link
            href={notifications.url}
            aria-label={notifications.label}
            data-test="notification-bell"
            className={cn(
                'relative inline-flex size-11 shrink-0 items-center justify-center rounded-full hover:bg-accent focus-visible:ring-2 focus-visible:ring-[#14294c] focus-visible:outline-none',
                className,
            )}
        >
            <Bell aria-hidden="true" className="size-5" />
            {notifications.unread > 0 ? (
                <span
                    aria-hidden="true"
                    data-test="notification-count"
                    className="absolute top-1 right-1 min-w-5 rounded-full bg-red-700 px-1 text-center text-xs leading-5 font-semibold text-white"
                >
                    {notifications.unread > 99 ? '99+' : notifications.unread}
                </span>
            ) : null}
        </Link>
    );
}
