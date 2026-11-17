import type { ReactNode } from 'react';

type PageIntroProps = {
    label: string;
    title: string;
    description: string;
    actions?: ReactNode;
};

export default function PageIntro({
    label,
    title,
    description,
    actions,
}: PageIntroProps) {
    return (
        <header className="flex flex-wrap items-end justify-between gap-6 border-b border-line pb-6">
            <div className="max-w-2xl space-y-2">
                <p className="text-sm font-medium text-verdigris">{label}</p>
                <h1 className="font-display text-3xl leading-tight tracking-tight text-ink sm:text-4xl">
                    {title}
                </h1>
                <p className="text-sm leading-relaxed text-muted-foreground sm:text-base">
                    {description}
                </p>
            </div>
            {actions && (
                <div className="flex flex-wrap items-center gap-2">
                    {actions}
                </div>
            )}
        </header>
    );
}
