import { Card, CardContent } from '@/components/ui/card';

export function StatCard({
    label,
    value,
    hint,
}: {
    label: string;
    value: string | number;
    hint?: string;
}) {
    return (
        <Card className="gap-2 py-4">
            <CardContent className="space-y-1 px-4">
                <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    {label}
                </p>
                <p className="text-2xl font-semibold tabular-nums">{value}</p>
                {hint && (
                    <p className="text-xs text-muted-foreground">{hint}</p>
                )}
            </CardContent>
        </Card>
    );
}
