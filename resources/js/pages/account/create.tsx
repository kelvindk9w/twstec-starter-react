import { useForm } from '@inertiajs/react';
import { Building2 } from 'lucide-react';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { SectionCard } from '@/components/section-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTrans } from '@/lib/i18n';
import { useRoute } from '@/lib/routes';

/**
 * CRIAR UMA CONTA de empresa: só o nome. A pessoa vira a dona, a conta nova
 * passa a ser a atual e a página dela abre (para convidar a equipe).
 */
export default function AccountCreate() {
    const { t } = useTrans();
    const { route } = useRoute();
    const form = useForm({ name: '' });

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title={t('panel.account.create_title')}
                description={t('panel.account.create_subtitle')}
            />
            <SectionCard title={t('panel.account.create_name')}>
                <form
                    className="flex max-w-xl flex-wrap items-end gap-3"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(route('panel.accounts.store'));
                    }}
                    data-create-account
                >
                    <div className="grid min-w-0 flex-1 basis-64 gap-2">
                        <Label htmlFor="account-name">
                            {t('panel.common.name')}
                        </Label>
                        <Input
                            id="account-name"
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            maxLength={255}
                            placeholder={t('panel.account.create_placeholder')}
                            autoFocus
                            aria-invalid={form.errors.name ? true : undefined}
                        />
                        <InputError message={form.errors.name} />
                    </div>
                    <Button type="submit" disabled={form.processing}>
                        {form.processing ? <Spinner /> : <Building2 />}
                        {t('panel.account.create_submit')}
                    </Button>
                </form>
            </SectionCard>
        </div>
    );
}

AccountCreate.layout = {
    breadcrumbs: [
        { title: 'panel.nav.account', route: 'panel.account' },
        { title: 'panel.account.create_title', route: 'panel.accounts.create' },
    ],
};
