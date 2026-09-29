import { router, usePage } from '@inertiajs/react';
import { ImageUp, Trash2 } from 'lucide-react';
import { useRef, useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import InputError from '@/components/input-error';
import { SectionCard } from '@/components/section-card';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { UserAvatar } from '@/components/user-info';
import { useTrans } from '@/lib/i18n';
import { useRoute } from '@/lib/routes';

/**
 * FOTO DE PERFIL (twstec/kit-uploads, opcional): escolher, enviar e tirar.
 *
 * O arquivo é validado no servidor pelo CONTEÚDO e reprocessado antes de ser
 * gravado (a extensão e o tipo que o navegador diz não bastam); a foto volta
 * por URL assinada. A prévia usa o MESMO avatar do menu.
 */
export function ProfilePhotoCard({
    photo,
}: {
    photo: { maxKb: number; accept: string };
}) {
    const { t } = useTrans();
    const { route } = useRoute();
    const { auth, errors } = usePage().props;
    const input = useRef<HTMLInputElement>(null);

    const [file, setFile] = useState<File | null>(null);
    const [processing, setProcessing] = useState(false);
    const [confirmingRemove, setConfirmingRemove] = useState(false);

    if (!auth.user) {
        return null;
    }

    const reset = () => {
        setFile(null);

        if (input.current) {
            input.current.value = '';
        }
    };

    const upload = () => {
        if (!file) {
            return;
        }

        router.post(
            route('panel.avatar.update'),
            { avatar: file },
            {
                forceFormData: true,
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: reset,
            },
        );
    };

    const remove = () => {
        router.delete(route('panel.avatar.destroy'), {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => {
                setProcessing(false);
                setConfirmingRemove(false);
            },
        });
    };

    return (
        <SectionCard
            id="profile-photo"
            title={t('panel.profile.avatar_heading')}
            description={t('panel.profile.avatar_hint')}
        >
            <form
                className="flex flex-wrap items-center gap-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    upload();
                }}
                data-profile-photo
            >
                <UserAvatar user={auth.user} className="size-16 text-lg" />

                <div className="grid min-w-0 flex-1 gap-2">
                    <input
                        ref={input}
                        id="avatar"
                        name="avatar"
                        type="file"
                        accept={photo.accept}
                        className="sr-only"
                        onChange={(event) =>
                            setFile(event.target.files?.[0] ?? null)
                        }
                    />
                    <div className="flex flex-wrap items-center gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => input.current?.click()}
                        >
                            <ImageUp />
                            {t('panel.profile.choose_file')}
                        </Button>
                        <span
                            className="min-w-0 truncate text-sm text-muted-foreground"
                            data-photo-file
                        >
                            {file ? file.name : t('ui.file.empty')}
                        </span>
                    </div>
                    <InputError message={errors.avatar} />
                </div>

                <div className="flex flex-wrap gap-2">
                    <Button
                        type="submit"
                        disabled={!file || processing}
                        data-photo-save
                    >
                        {processing && <Spinner />}
                        {processing
                            ? t('panel.profile.avatar_uploading')
                            : t('panel.profile.save_avatar')}
                    </Button>
                    {auth.user.avatarUrl && (
                        <Button
                            type="button"
                            variant="ghost"
                            className="text-destructive hover:text-destructive"
                            onClick={() => setConfirmingRemove(true)}
                            data-photo-remove
                        >
                            <Trash2 />
                            {t('ui.profile_photo.remove')}
                        </Button>
                    )}
                </div>
            </form>

            <ConfirmDialog
                open={confirmingRemove}
                onClose={() => setConfirmingRemove(false)}
                title={t('ui.profile_photo.remove')}
                description={t('ui.profile_photo.remove_warning')}
                confirmLabel={t('ui.profile_photo.remove')}
                onConfirm={remove}
                processing={processing}
                confirmTest="remove-photo"
            />
        </SectionCard>
    );
}
