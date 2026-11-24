import '@inertiajs/core';

import type { SessionUser } from './types';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            user: SessionUser | null;
            unreadNotifications: number | null;
        };
        flashDataType: {
            message?: string;
        };
    }
}
