// Components
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/hooks/useTranslations';
import AuthLayout from '@/layouts/auth-layout';
import { logout } from '@/routes';
import { send } from '@/routes/verification';
import { Form, Head } from '@inertiajs/react';

export default function VerifyEmail({ status }: { status?: string }) {
    const t = useTranslations();

    return (
        <AuthLayout title={t('email')} description={t('email_address')}>
            <Head title={t('email')} />

            {status === 'verification-link-sent' && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    A new verification link has been sent to the email address
                    you provided during registration.
                </div>
            )}

            <Form {...send.form()} className="space-y-6 text-center">
                {({ processing }) => (
                    <>
                        <Button disabled={processing} variant="secondary">
                            {processing && <Spinner />}
                            {t('email')}
                        </Button>

                        <TextLink
                            href={logout()}
                            className="mx-auto block text-sm"
                        >
                            {t('sign_out')}
                        </TextLink>
                    </>
                )}
            </Form>
        </AuthLayout>
    );
}
