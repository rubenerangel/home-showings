import '@inertiajs/core';

import type { SessionUser } from './types';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            user: SessionUser | null;
            unreadNotifications?: number;
        };
        flashDataType: {
            message?: string;
        };
    }
}
