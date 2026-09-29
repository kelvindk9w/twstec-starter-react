import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import type { SharedProps } from '@/types';

/**
 * O aviso de uma requisição só (`status` na sessão — as respostas do pacote
 * de autenticação e as telas do painel) aparece como toast, como no starter
 * Livewire: na primeira carga e a cada resposta do servidor (nunca ao voltar
 * pelo histórico, que traz a página guardada). O motivo de um reenvio
 * recusado (`verification_error`) NÃO vira toast: fica fixo na tela, ao lado
 * do botão que resolve.
 */
export function FlashToast({ initial }: { initial: string | null }) {
    useEffect(() => {
        if (initial) {
            toast.success(initial, { id: initial });
        }

        return router.on('success', (event) => {
            const props = event.detail.page.props as unknown as SharedProps;

            if (props.flash?.status) {
                toast.success(props.flash.status, { id: props.flash.status });
            }
        });
    }, [initial]);

    return null;
}
