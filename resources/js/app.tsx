import { createInertiaApp } from '@inertiajs/react';
import AdminLayout from '@/layouts/admin-layout';
import AuthLayout from '@/layouts/auth-layout';
import BuyerLayout from '@/layouts/buyer-layout';
import SettingsLayout from '@/layouts/settings-layout';
import { Toaster } from '@/lib/veludo/Toaster';

const appName = import.meta.env.VITE_APP_NAME || 'Kena';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name.startsWith('admin/'):
                return AdminLayout;
            case name.startsWith('auth/'):
                return AuthLayout;
            // Settings é área do comprador: mesmo header/rodapé, com sub-nav própria.
            case name.startsWith('settings/'):
                return [BuyerLayout, SettingsLayout];
            default:
                return BuyerLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <>
                {app}
                <Toaster />
            </>
        );
    },
    progress: {
        color: '#b14a52',
    },
});
