import { cn } from '@/lib/utils';

type BrandLogoProps = {
    size?: 'sm' | 'md' | 'lg' | 'xl';
    showText?: boolean;
    variant?: 'light' | 'dark';
    className?: string;
};

const sizes = {
    sm: 'h-10 w-10',
    md: 'h-14 w-14',
    lg: 'h-20 w-20',
    xl: 'h-28 w-28',
};

export default function BrandLogo({
    size = 'md',
    showText = true,
    variant = 'dark',
    className,
}: BrandLogoProps) {
    const textClass = variant === 'light' ? 'text-white' : 'text-gray-900';
    const subtitleClass = variant === 'light' ? 'text-green-100' : 'text-gray-500';

    return (
        <div className={cn('flex items-center gap-3', className)}>
            <img
                src="/images/Logo.jpg"
                alt="Metahara Sugar Factory"
                className={cn('rounded-full object-cover shrink-0 ring-2 ring-green-600/10', sizes[size])}
            />
            {showText && (
                <div className={cn('min-w-0', className?.includes('text-center') && 'text-center')}>
                    <p className={cn('font-bold leading-tight', textClass, size === 'xl' ? 'text-2xl' : size === 'lg' ? 'text-lg' : 'text-base')}>
                        MSF HMS
                    </p>
                    <p className={cn('text-xs leading-snug', subtitleClass)}>
                        Metahara Sugar Factory
                    </p>
                </div>
            )}
        </div>
    );
}
