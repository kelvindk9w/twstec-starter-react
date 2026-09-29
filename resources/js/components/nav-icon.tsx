import type { LucideIcon } from 'lucide-react';
import {
    Bell,
    CircleUser,
    Folder,
    KeyRound,
    LayoutGrid,
    Lock,
    Users,
} from 'lucide-react';

/**
 * O servidor manda o NOME do ícone de cada item do menu
 * (App\Support\Navigation); aqui ele vira o desenho do lucide-react.
 */
const icons: Record<string, LucideIcon> = {
    bell: Bell,
    'circle-user': CircleUser,
    folder: Folder,
    'key-round': KeyRound,
    'layout-grid': LayoutGrid,
    lock: Lock,
    users: Users,
};

export function NavIcon({ name }: { name: string }) {
    const Icon = icons[name] ?? LayoutGrid;

    return <Icon />;
}
