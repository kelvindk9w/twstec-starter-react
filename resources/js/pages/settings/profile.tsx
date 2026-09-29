import { Form, usePage } from '@inertiajs/react';
import { Monitor, Moon, Sun } from 'lucide-react';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import PasswordInput from '@/components/password-input';
import { ProfilePhotoCard } from '@/components/profile-photo-card';
import { SectionCard } from '@/components/section-card';
import { TransactionPasswordForm } from '@/components/transaction-password-form';
import { TwoFactorCard } from '@/components/two-factor-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { UserAvatar } from '@/components/user-info';
import { useTheme } from '@/hooks/use-theme';
import { useTrans } from '@/lib/i18n';
import { useRoute } from '@/lib/routes';
import type { Theme } from '@/types';

type Props = {
    passwordHint: string;
    transactionPasswordMinLength: number;
    /** Foto de perfil — só com o pacote de uploads. */
    photo: { maxKb: number; accept: string } | null;
    twoFactor: {
        available: boolean;
        enabled: boolean;
        blockedReason: string | null;
    };
};

/**
 * Perfil — os mesmos blocos do starter Livewire, na mesma tela: dados,
 * foto (com o pacote de uploads), aparência, senha de login, senha de
 * transação e verificação em duas etapas. Cada bloco tem o próprio envio e o próprio botão.
 */
export default function Profile({ passwordHint, photo, twoFactor }: Props) {
    const { t } = useTrans();
    const { route } = useRoute();
    const { auth, app } = usePage().props;
    const { theme, setTheme } = useTheme();

    if (!auth.user) {
        return null;
    }

    const user = auth.user;

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <div className="flex items-center gap-4">
                <UserAvatar user={user} className="size-12" />
                <PageHeader
                    title={t('panel.profile.title')}
                    description={t('panel.profile.subtitle')}
                />
            </div>

            <SectionCard title={t('panel.profile.data_heading')}>
                <Form
                    action={route('panel.profile.update')}
                    method="patch"
                    options={{ preserveScroll: true }}
                    className="grid max-w-md gap-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">
                                    {t('panel.common.name')}
                                </Label>
                                <Input
                                    id="name"
                                    name="name"
                                    defaultValue={user.name}
                                    required
                                    autoComplete="name"
                                    aria-invalid={
                                        errors.name ? true : undefined
                                    }
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">
                                    {t('auth.ui.email')}
                                </Label>
                                <Input
                                    id="email"
                                    value={user.email}
                                    readOnly
                                    disabled
                                    aria-describedby="email-hint"
                                />
                                <p
                                    id="email-hint"
                                    className="text-xs text-muted-foreground"
                                >
                                    {t('panel.profile.email_readonly')}
                                </p>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="locale">
                                    {t('panel.profile.locale_label')}
                                </Label>
                                <Select
                                    name="locale"
                                    defaultValue={user.locale}
                                >
                                    <SelectTrigger
                                        id="locale"
                                        aria-describedby="locale-hint"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {app.locales.map((locale) => (
                                            <SelectItem
                                                key={locale.code}
                                                value={locale.code}
                                            >
                                                {locale.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <p
                                    id="locale-hint"
                                    className="text-xs text-muted-foreground"
                                >
                                    {t('panel.profile.locale_hint')}
                                </p>
                                <InputError message={errors.locale} />
                            </div>

                            <div>
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    data-test="save-profile"
                                >
                                    {processing && <Spinner />}
                                    {t('panel.profile.save_data')}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </SectionCard>

            {photo && <ProfilePhotoCard photo={photo} />}

            <SectionCard
                title={t('panel.profile.theme_heading')}
                description={t('panel.profile.theme_hint')}
            >
                <ToggleGroup
                    type="single"
                    variant="outline"
                    value={theme}
                    onValueChange={(value) => value && setTheme(value as Theme)}
                    aria-label={t('ui.theme.label')}
                >
                    <ToggleGroupItem value="system" className="gap-2 px-3">
                        <Monitor className="size-4" />
                        {t('ui.theme.system')}
                    </ToggleGroupItem>
                    <ToggleGroupItem value="light" className="gap-2 px-3">
                        <Sun className="size-4" />
                        {t('ui.theme.light')}
                    </ToggleGroupItem>
                    <ToggleGroupItem value="dark" className="gap-2 px-3">
                        <Moon className="size-4" />
                        {t('ui.theme.dark')}
                    </ToggleGroupItem>
                </ToggleGroup>
            </SectionCard>

            <SectionCard
                title={t('panel.profile.password_heading')}
                description={t('panel.profile.password_hint', {
                    rules: passwordHint,
                })}
            >
                <Form
                    action={route('panel.password.update')}
                    method="put"
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    resetOnError
                    className="grid max-w-md gap-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="current_password">
                                    {t('panel.profile.current_password')}
                                </Label>
                                <PasswordInput
                                    id="current_password"
                                    name="current_password"
                                    autoComplete="current-password"
                                    aria-invalid={
                                        errors.current_password
                                            ? true
                                            : undefined
                                    }
                                />
                                <InputError message={errors.current_password} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="password">
                                    {t('auth.ui.new_password')}
                                </Label>
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    autoComplete="new-password"
                                    aria-invalid={
                                        errors.password ? true : undefined
                                    }
                                />
                                <InputError message={errors.password} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="password_confirmation">
                                    {t('auth.ui.password_confirmation')}
                                </Label>
                                <PasswordInput
                                    id="password_confirmation"
                                    name="password_confirmation"
                                    autoComplete="new-password"
                                />
                                <InputError
                                    message={errors.password_confirmation}
                                />
                            </div>
                            <div>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    {t('panel.profile.save_password')}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </SectionCard>

            <SectionCard
                id="transaction-password"
                title={t('panel.profile.transaction_password_heading')}
                description={t('panel.profile.transaction_password_hint')}
            >
                <div className="mb-4">
                    <Badge
                        variant={
                            user.hasTransactionPassword
                                ? 'default'
                                : 'secondary'
                        }
                    >
                        {user.hasTransactionPassword
                            ? t('panel.profile.transaction_password_set')
                            : t('panel.profile.transaction_password_not_set')}
                    </Badge>
                </div>
                <TransactionPasswordForm
                    hasTransactionPassword={user.hasTransactionPassword}
                    submitLabel={t('panel.profile.save_transaction_password')}
                />
            </SectionCard>

            <TwoFactorCard twoFactor={twoFactor} />
        </div>
    );
}

Profile.layout = {
    breadcrumbs: [{ title: 'panel.nav.profile', route: 'panel.profile' }],
};
