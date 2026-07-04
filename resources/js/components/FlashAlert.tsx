import { useEffect, useState } from 'react';
import { usePage } from '@inertiajs/react';
import { cn } from '@/lib/utils';

type FlashProps = {
    success?: string | null;
    error?: string | null;
};

export default function FlashAlert() {
    const { flash } = usePage().props as { flash?: FlashProps };
    const [visible, setVisible] = useState(false);
    const [message, setMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null);

    useEffect(() => {
        if (flash?.success) {
            setMessage({ type: 'success', text: flash.success });
            setVisible(true);
            return;
        }

        if (flash?.error) {
            setMessage({ type: 'error', text: flash.error });
            setVisible(true);
        }
    }, [flash?.success, flash?.error]);

    useEffect(() => {
        if (!visible || !message) {
            return undefined;
        }

        const timer = window.setTimeout(() => setVisible(false), 6000);
        return () => window.clearTimeout(timer);
    }, [visible, message]);

    if (!visible || !message) {
        return null;
    }

    return (
        <div
            className={cn(
                'mb-4 rounded-md border px-4 py-3 text-sm shadow-sm',
                message.type === 'success'
                    ? 'bg-green-50 border-green-200 text-green-800'
                    : 'bg-red-50 border-red-200 text-red-800',
            )}
            role="alert"
        >
            <div className="flex items-start justify-between gap-3">
                <p>{message.text}</p>
                <button
                    type="button"
                    onClick={() => setVisible(false)}
                    className="text-current opacity-70 hover:opacity-100"
                    aria-label="Dismiss notification"
                >
                    ×
                </button>
            </div>
        </div>
    );
}
