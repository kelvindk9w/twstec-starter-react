import type { FlashData, SharedProps } from '@/types/shared';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: SharedProps;
        flashDataType: FlashData;
    }
}
