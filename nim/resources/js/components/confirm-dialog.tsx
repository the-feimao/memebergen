import type { ReactNode } from 'react';
import { AlertTriangle, ArrowRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type ConfirmDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description: string;
    details?: ReactNode;
    confirmLabel: string;
    pendingLabel?: string;
    cancelLabel?: string;
    pending?: boolean;
    tone?: 'primary' | 'danger';
    onConfirm: () => void;
};

export default function ConfirmDialog({
    open,
    onOpenChange,
    title,
    description,
    details,
    confirmLabel,
    pendingLabel = 'Working…',
    cancelLabel = 'Cancel',
    pending = false,
    tone = 'primary',
    onConfirm,
}: ConfirmDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="gap-0 overflow-hidden p-0 sm:max-w-xl">
                <div className="border-b border-line bg-muted/40 px-6 py-5">
                    <DialogHeader className="text-left">
                        <div className="flex items-start gap-4">
                            <div className="flex size-10 shrink-0 items-center justify-center rounded-full border border-brass/50 bg-brass/10 text-ink">
                                <AlertTriangle className="size-5 text-brass" />
                            </div>
                            <div className="space-y-1.5">
                                <DialogTitle className="font-display text-2xl font-normal tracking-tight">
                                    {title}
                                </DialogTitle>
                                <DialogDescription className="text-sm leading-relaxed">
                                    {description}
                                </DialogDescription>
                            </div>
                        </div>
                    </DialogHeader>
                </div>

                {details && (
                    <div className="px-6 py-5 text-sm text-muted-foreground">
                        {details}
                    </div>
                )}

                <DialogFooter className="gap-3 border-t border-line bg-paper/60 px-6 py-4">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                        disabled={pending}
                    >
                        {cancelLabel}
                    </Button>
                    <Button
                        type="button"
                        variant={tone === 'danger' ? 'destructive' : 'default'}
                        onClick={onConfirm}
                        disabled={pending}
                    >
                        {pending ? pendingLabel : confirmLabel}
                        {!pending && <ArrowRight className="size-4" />}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
