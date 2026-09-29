import { LayoutGrid, Rows3 } from 'lucide-react';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import type { ViewMode } from '@/hooks/use-view-mode';
import { useTrans } from '@/lib/i18n';

/**
 * Alternador tabela/cartões das listas do painel (o <x-view-toggle> do
 * starter Livewire). Só no desktop: no celular a lista já é de cartões.
 */
export function ViewToggle({
    value,
    onChange,
}: {
    value: ViewMode;
    onChange: (mode: ViewMode) => void;
}) {
    const { t } = useTrans();

    return (
        <ToggleGroup
            type="single"
            variant="outline"
            size="sm"
            value={value}
            onValueChange={(next) => next && onChange(next as ViewMode)}
            aria-label={t('ui.view_toggle.label')}
            className="hidden sm:flex"
            data-view-toggle
        >
            <ToggleGroupItem
                value="table"
                data-view="table"
                aria-label={t('ui.view_toggle.table')}
                title={t('ui.view_toggle.table')}
            >
                <Rows3 className="size-4" />
            </ToggleGroupItem>
            <ToggleGroupItem
                value="cards"
                data-view="cards"
                aria-label={t('ui.view_toggle.cards')}
                title={t('ui.view_toggle.cards')}
            >
                <LayoutGrid className="size-4" />
            </ToggleGroupItem>
        </ToggleGroup>
    );
}
