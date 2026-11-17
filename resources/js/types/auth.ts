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
    organizationName: string | null;
};

/** Super admin da plataforma (/admin): conta separada da do painel. */
export type SuperAdmin = {
    id: number;
    name: string;
    email: string;
};

/** Comprador, painel e super admin são independentes: podem ser nulos ao mesmo tempo. */
export type Auth = {
    user: User | null;
    panelUser: PanelUser | null;
    superAdmin: SuperAdmin | null;
};

/** Algo impede a organização de vender (aviso fixo no painel). */
export type OrganizationAlert = {
    kind: 'warning' | 'danger';
    message: string;
    actionUrl: string | null;
    actionLabel: string | null;
};

/** Super admin "dentro" do painel de um organizador. */
export type Impersonation = {
    adminName: string;
    organizationName: string;
    stopUrl: string;
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
