/**
 * Série diária em barras, sem biblioteca de gráfico (nada de script extra
 * nem de estilo inline gerado em tempo de execução além da altura de cada
 * barra). Cada barra tem o rótulo e o valor para leitor de tela.
 */
export function BarChart({
    labels,
    values,
    seriesLabel,
}: {
    labels: string[];
    values: number[];
    seriesLabel: string;
}) {
    const max = Math.max(1, ...values);

    return (
        <div
            className="flex h-40 items-end gap-0.5"
            role="list"
            aria-label={seriesLabel}
        >
            {values.map((value, index) => (
                <div
                    key={labels[index] ?? index}
                    role="listitem"
                    aria-label={`${labels[index] ?? ''}: ${value}`}
                    title={`${labels[index] ?? ''}: ${value}`}
                    className="flex h-full flex-1 items-end"
                >
                    <div
                        className="w-full rounded-t-sm bg-primary/80"
                        style={{
                            height: `${Math.max(value === 0 ? 0 : 4, (value / max) * 100)}%`,
                        }}
                    />
                </div>
            ))}
        </div>
    );
}
