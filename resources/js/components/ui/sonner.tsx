import { Toaster as Sonner, type ToasterProps } from 'sonner';
import { useCurrentTheme } from '@/hooks/use-theme';

function Toaster({ ...props }: ToasterProps) {
    const theme = useCurrentTheme();

    return (
        <Sonner
            theme={theme}
            className="toaster group"
            position="bottom-right"
            style={
                {
                    '--normal-bg': 'var(--popover)',
                    '--normal-text': 'var(--popover-foreground)',
                    '--normal-border': 'var(--border)',
                } as React.CSSProperties
            }
            {...props}
        />
    );
}

export { Toaster };
