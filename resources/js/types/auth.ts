export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
    /** Solo para mostrar u ocultar elementos: el backend siempre vuelve a autorizar. */
    roles: string[];
    permissions: string[];
};


export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
