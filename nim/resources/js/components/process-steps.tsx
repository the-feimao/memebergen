import { Check } from 'lucide-react';

export type ProcessStep = {
    title: string;
    description: string;
    state: 'complete' | 'current' | 'upcoming';
};

export default function ProcessSteps({ steps }: { steps: ProcessStep[] }) {
    return (
        <ol className="grid gap-3 md:grid-cols-3">
            {steps.map((step, index) => {
                const isCurrent = step.state === 'current';
                const isComplete = step.state === 'complete';

                return (
                    <li
                        key={step.title}
                        className={`relative rounded-xl border px-4 py-4 ${
                            isCurrent
                                ? 'border-verdigris/50 bg-verdigris/5'
                                : 'border-line bg-card'
                        }`}
                    >
                        <div className="flex items-center gap-3">
                            <span
                                className={`flex size-8 shrink-0 items-center justify-center rounded-full border text-sm font-semibold tabular-nums ${
                                    isComplete
                                        ? 'border-verdigris bg-verdigris text-white'
                                        : isCurrent
                                          ? 'border-verdigris text-verdigris'
                                          : 'border-line text-muted-foreground'
                                }`}
                            >
                                {isComplete ? (
                                    <Check className="size-4" />
                                ) : (
                                    index + 1
                                )}
                            </span>
                            <p className="font-medium text-ink">{step.title}</p>
                        </div>
                        <p className="mt-3 text-sm leading-relaxed text-muted-foreground">
                            {step.description}
                        </p>
                    </li>
                );
            })}
        </ol>
    );
}
