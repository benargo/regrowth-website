import { useState, useEffect } from 'react';

export default function FlashMessage({ type = 'error', message, onDismiss }) {
    const [isVisible, setIsVisible] = useState(true);
    const [isLeaving, setIsLeaving] = useState(false);

    useEffect(() => {
        if (!message) return;

        // Auto-dismiss after 10 seconds
        const timer = setTimeout(() => {
            handleDismiss();
        }, 10000);

        return () => clearTimeout(timer);
    }, [message]);

    const handleDismiss = () => {
        setIsLeaving(true);
        setTimeout(() => {
            setIsVisible(false);
            if (onDismiss) onDismiss();
        }, 300); // Match the CSS transition duration
    };

    if (!message || !isVisible) return null;

    const styles = {
        error: {
            bg: 'bg-red-900/90',
            border: 'border-red-600',
            icon: 'fa-exclamation-circle',
            iconColor: 'text-red-400',
        },
        success: {
            bg: 'bg-green-900/90',
            border: 'border-green-600',
            icon: 'fa-check-circle',
            iconColor: 'text-green-400',
        },
        warning: {
            bg: 'bg-yellow-900/90',
            border: 'border-yellow-600',
            icon: 'fa-exclamation-triangle',
            iconColor: 'text-yellow-400',
        },
        info: {
            bg: 'bg-blue-900/90',
            border: 'border-blue-600',
            icon: 'fa-info-circle',
            iconColor: 'text-blue-400',
        },
    };

    const style = styles[type] || styles.error;

    return (
        <div
            className={`
                fixed top-16 left-1/2 -translate-x-1/2 z-50 w-full max-w-lg mx-auto px-4
                transition-all duration-300 ease-in-out
                ${isLeaving ? 'opacity-0 -translate-y-2' : 'opacity-100 translate-y-0'}
            `}
        >
            <div
                className={`
                    ${style.bg} ${style.border}
                    border rounded-lg shadow-lg backdrop-blur-sm
                    px-4 py-3 flex items-start gap-3
                `}
            >
                <i className={`far ${style.icon} ${style.iconColor} text-lg mt-0.5`}></i>
                <p className="flex-1 text-white text-sm">{message}</p>
                <button
                    onClick={handleDismiss}
                    className="text-gray-400 hover:text-white transition-colors"
                    aria-label="Dismiss"
                >
                    <i className="far fa-times"></i>
                </button>
            </div>
        </div>
    );
}
