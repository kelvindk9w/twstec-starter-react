import type { ReactNode } from 'react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

/**
 * Um bloco de configuração do painel (título, apoio e o formulário).
 */
export function SectionCard({
    title,
    description,
    children,
    id,
}: {
    title: string;
    description?: ReactNode;
    children: ReactNode;
    id?: string;
}) {
    return (
        <Card id={id}>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
                {description && (
                    <CardDescription>{description}</CardDescription>
                )}
            </CardHeader>
            <CardContent>{children}</CardContent>
        </Card>
    );
}
