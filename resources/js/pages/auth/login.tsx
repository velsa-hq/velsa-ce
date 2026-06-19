import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import PasskeyVerify from '@/components/passkey-verify';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

type SsoProvider = {
    key: string;
    label: string;
    url: string;
};

type Props = {
    status?: string;
    canResetPassword: boolean;
    ssoProviders?: SsoProvider[];
    consentBanner?: { text: string } | null;
};

export default function Login({
    status,
    canResetPassword,
    ssoProviders = [],
    consentBanner = null,
}: Props) {
    // Consent banner (STIG AC-8): the user must acknowledge before signing in.
    const [acknowledged, setAcknowledged] = useState(false);
    const mustConsent = consentBanner !== null;

    return (
        <>
            <Head title="Log in" />

            {consentBanner && (
                <div
                    role="alertdialog"
                    aria-label="System use notification"
                    className="mb-4 rounded-md border border-amber-300 bg-amber-50 p-3 text-xs leading-relaxed text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-100"
                >
                    {consentBanner.text}
                </div>
            )}

            <PasskeyVerify />

            {ssoProviders.length > 0 && (
                <div className="flex flex-col gap-3">
                    {ssoProviders.map((provider) => (
                        <a
                            key={provider.key}
                            href={provider.url}
                            className="flex w-full items-center justify-center rounded-md border border-input bg-background px-4 py-2 text-sm font-medium ring-offset-background transition-colors hover:bg-accent hover:text-accent-foreground"
                        >
                            {provider.label}
                        </a>
                    ))}
                    <div className="relative my-2">
                        <div className="absolute inset-0 flex items-center">
                            <span className="w-full border-t" />
                        </div>
                        <div className="relative flex justify-center text-xs uppercase">
                            <span className="bg-background px-2 text-muted-foreground">
                                Or continue with
                            </span>
                        </div>
                    </div>
                </div>
            )}

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="email">Email address</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    autoFocus
                                    autoComplete="email"
                                    placeholder="email@example.com"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <div className="flex items-center">
                                    <Label htmlFor="password">Password</Label>
                                    {canResetPassword && (
                                        <TextLink
                                            href={request()}
                                            className="ml-auto text-sm"
                                        >
                                            Forgot your password?
                                        </TextLink>
                                    )}
                                </div>
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    required
                                    autoComplete="current-password"
                                    placeholder="Password"
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="flex items-center space-x-3">
                                <Checkbox id="remember" name="remember" />
                                <Label htmlFor="remember">Remember me</Label>
                            </div>

                            {mustConsent && (
                                <div className="flex items-start space-x-3">
                                    <Checkbox
                                        id="consent"
                                        checked={acknowledged}
                                        onCheckedChange={(v) =>
                                            setAcknowledged(v === true)
                                        }
                                    />
                                    <Label
                                        htmlFor="consent"
                                        className="text-sm leading-snug font-normal"
                                    >
                                        I have read and consent to the notice
                                        above.
                                    </Label>
                                </div>
                            )}

                            <Button
                                type="submit"
                                className="mt-4 w-full"
                                disabled={
                                    processing || (mustConsent && !acknowledged)
                                }
                                data-test="login-button"
                            >
                                {processing && <Spinner />}
                                Log in
                            </Button>
                        </div>
                    </>
                )}
            </Form>

            {status && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    {status}
                </div>
            )}
        </>
    );
}

Login.layout = {
    title: 'Log in to your account',
    description: 'Enter your email and password below to log in',
};
