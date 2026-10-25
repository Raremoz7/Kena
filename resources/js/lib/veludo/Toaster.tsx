import { Toaster as Sonner } from 'sonner';

/**
 * Host dos toasts, montado em app.tsx fora do <App> do Inertia. Por isso não
 * pode usar hooks de página (usePage) — quem converte flash em toast é o
 * useFlashToasts(), chamado dentro de cada layout.
 *
 * O visual vem inteiro de `veludoToast` (sonner.custom), então não configuramos
 * tema nem as CSS vars do toast padrão do sonner.
 *
 * No celular os toasts sobem acima da barra de navegação inferior.
 */
export function Toaster() {
    return (
        <Sonner
            position="bottom-right"
            mobileOffset={{
                bottom: 'calc(5.25rem + env(safe-area-inset-bottom))',
            }}
        />
    );
}
