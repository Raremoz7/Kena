/** Comprador logado, como sai do share() (só os campos que as telas usam). */
export type User = {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
};

/** Conta do painel (organizador/staff) — guard próprio, fora de `users`. */
export type PanelUser = {
    id: number;
    name: string;
    email: string;
    role: 'organizer' | 'staff';
};

/** Comprador e painel são independentes: os dois podem ser nulos ao mesmo tempo. */
export type Auth = {
    user: User | null;
    panelUser: PanelUser | null;
};

/* @chisel-passkeys */
export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};
/* @end-chisel-passkeys */

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
