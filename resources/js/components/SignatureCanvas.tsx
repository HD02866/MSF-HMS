import { useRef, useState, useCallback, useEffect } from 'react';

interface SignatureCanvasProps {
    value: string;
    onChange: (dataUrl: string) => void;
    width?: number;
    height?: number;
    className?: string;
    disabled?: boolean;
}

export default function SignatureCanvas({
    value,
    onChange,
    width = 400,
    height = 120,
    className = '',
    disabled = false,
}: SignatureCanvasProps) {
    const canvasRef = useRef<HTMLCanvasElement>(null);
    const [isDrawing, setIsDrawing] = useState(false);
    const [hasDrawn, setHasDrawn] = useState(!!value);

    // Redraw canvas from saved value on mount / value change
    useEffect(() => {
        const canvas = canvasRef.current;
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        if (!ctx) return;

        ctx.clearRect(0, 0, canvas.width, canvas.height);

        if (value) {
            const img = new Image();
            img.onload = () => {
                ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
            };
            img.src = value;
        } else {
            // Draw placeholder line
            ctx.strokeStyle = '#d1d5db';
            ctx.lineWidth = 1;
            ctx.setLineDash([6, 4]);
            ctx.beginPath();
            ctx.moveTo(20, canvas.height - 20);
            ctx.lineTo(canvas.width - 20, canvas.height - 20);
            ctx.stroke();
            ctx.setLineDash([]);
        }
    }, [value]);

    const getPos = useCallback((e: React.MouseEvent | React.TouchEvent) => {
        const canvas = canvasRef.current;
        if (!canvas) return { x: 0, y: 0 };
        const rect = canvas.getBoundingClientRect();
        const scaleX = canvas.width / rect.width;
        const scaleY = canvas.height / rect.height;

        if ('touches' in e) {
            const touch = e.touches[0];
            return {
                x: (touch.clientX - rect.left) * scaleX,
                y: (touch.clientY - rect.top) * scaleY,
            };
        }
        return {
            x: (e.clientX - rect.left) * scaleX,
            y: (e.clientY - rect.top) * scaleY,
        };
    }, []);

    const startDraw = useCallback((e: React.MouseEvent | React.TouchEvent) => {
        if (disabled) return;
        const canvas = canvasRef.current;
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        if (!ctx) return;

        const pos = getPos(e);
        ctx.beginPath();
        ctx.moveTo(pos.x, pos.y);
        ctx.strokeStyle = '#111827';
        ctx.lineWidth = 2;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        setIsDrawing(true);
    }, [disabled, getPos]);

    const draw = useCallback((e: React.MouseEvent | React.TouchEvent) => {
        if (!isDrawing || disabled) return;
        e.preventDefault();
        const canvas = canvasRef.current;
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        if (!ctx) return;

        const pos = getPos(e);
        ctx.lineTo(pos.x, pos.y);
        ctx.stroke();
    }, [isDrawing, disabled, getPos]);

    const endDraw = useCallback(() => {
        if (!isDrawing) return;
        setIsDrawing(false);
        setHasDrawn(true);

        const canvas = canvasRef.current;
        if (canvas) {
            onChange(canvas.toDataURL('image/png'));
        }
    }, [isDrawing, onChange]);

    const clear = useCallback(() => {
        const canvas = canvasRef.current;
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        if (!ctx) return;

        ctx.clearRect(0, 0, canvas.width, canvas.height);

        // Redraw placeholder
        ctx.strokeStyle = '#d1d5db';
        ctx.lineWidth = 1;
        ctx.setLineDash([6, 4]);
        ctx.beginPath();
        ctx.moveTo(20, canvas.height - 20);
        ctx.lineTo(canvas.width - 20, canvas.height - 20);
        ctx.stroke();
        ctx.setLineDash([]);

        setHasDrawn(false);
        onChange('');
    }, [onChange]);

    return (
        <div className={className}>
            <div className="relative border border-gray-300 rounded-lg overflow-hidden bg-white">
                <canvas
                    ref={canvasRef}
                    width={width}
                    height={height}
                    className={`w-full cursor-crosshair touch-none ${disabled ? 'opacity-50 cursor-not-allowed' : ''}`}
                    style={{ height: `${height}px` }}
                    onMouseDown={startDraw}
                    onMouseMove={draw}
                    onMouseUp={endDraw}
                    onMouseLeave={endDraw}
                    onTouchStart={startDraw}
                    onTouchMove={draw}
                    onTouchEnd={endDraw}
                />
            </div>
            <div className="flex items-center justify-between mt-1.5">
                <span className="text-xs text-gray-400">
                    {hasDrawn ? 'Signature captured' : 'Sign above'}
                </span>
                {!disabled && (
                    <button
                        type="button"
                        onClick={clear}
                        className="text-xs text-gray-400 hover:text-red-500 hover:underline"
                    >
                        Clear
                    </button>
                )}
            </div>
        </div>
    );
}
