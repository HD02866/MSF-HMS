import { cn } from '@/lib/utils';
import { cva, type VariantProps } from 'class-variance-authority';
import { forwardRef } from 'react';

const buttonVariants = cva(
    'inline-flex items-center justify-center rounded-md text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-green-500 disabled:opacity-50',
    {
        variants: {
            variant: {
                default: 'bg-green-600 text-white hover:bg-green-700',
                secondary: 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50',
                accent: 'bg-yellow-400 text-gray-900 hover:bg-yellow-500',
                ghost: 'hover:bg-green-50 text-green-700',
            },
            size: {
                default: 'h-10 px-4 py-2',
                sm: 'h-8 px-3 text-xs',
            },
        },
        defaultVariants: { variant: 'default', size: 'default' },
    },
);

export interface ButtonProps
    extends React.ButtonHTMLAttributes<HTMLButtonElement>,
        VariantProps<typeof buttonVariants> {}

export const UiButton = forwardRef<HTMLButtonElement, ButtonProps>(
    ({ className, variant, size, ...props }, ref) => (
        <button className={cn(buttonVariants({ variant, size, className }))} ref={ref} {...props} />
    ),
);
UiButton.displayName = 'UiButton';

export function Card({ className, children }: React.PropsWithChildren<{ className?: string }>) {
    return <div className={cn('rounded-lg border bg-white shadow-sm', className)}>{children}</div>;
}

export function CardHeader({ className, children }: React.PropsWithChildren<{ className?: string }>) {
    return <div className={cn('border-b px-4 py-3 font-semibold text-gray-800', className)}>{children}</div>;
}

export function CardContent({ className, children }: React.PropsWithChildren<{ className?: string }>) {
    return <div className={cn('p-4', className)}>{children}</div>;
}
