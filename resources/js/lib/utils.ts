import { type ClassValue, clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function formatTime(time: string): string {
    return time?.substring(0, 5) ?? '';
}

export function canManagePatients(roleName?: string): boolean {
    return ['Admin', 'Card Officer', 'Department Head'].includes(roleName ?? '');
}
