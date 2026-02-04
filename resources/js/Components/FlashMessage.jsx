import { useState, useEffect } from "react";
import Icon from "./FontAwesome/Icon";

export default function FlashMessage({ type = "error", message, onDismiss }) {
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
            bg: "bg-red-900/90",
            border: "border-red-600",
            icon: <Icon icon="exclamation-circle" style="regular" className="text-red-400" />,
        },
        success: {
            bg: "bg-green-900/90",
            border: "border-green-600",
            icon: <Icon icon="check-circle" style="regular" className="text-green-400" />,
        },
        warning: {
            bg: "bg-yellow-900/90",
            border: "border-yellow-600",
            icon: <Icon icon="exclamation-triangle" style="regular" className="text-yellow-400" />,
        },
        info: {
            bg: "bg-blue-900/90",
            border: "border-blue-600",
            icon: <Icon icon="info-circle" style="regular" className="text-blue-400" />,
        },
    };

    const style = styles[type] || styles.error;

    return (
        <div
            className={`fixed left-1/2 top-16 z-50 mx-auto w-full max-w-lg -translate-x-1/2 px-4 transition-all duration-300 ease-in-out ${isLeaving ? "-translate-y-2 opacity-0" : "translate-y-0 opacity-100"} `}
        >
            <div
                className={` ${style.bg} ${style.border} flex items-start gap-3 rounded-lg border px-4 py-3 shadow-lg backdrop-blur-sm`}
            >
                {style.icon}
                <p className="flex-1 text-sm text-white">{message}</p>
                <button
                    onClick={handleDismiss}
                    className="text-gray-400 transition-colors hover:text-white"
                    aria-label="Dismiss"
                >
                    <Icon icon="times" style="regular" />
                </button>
            </div>
        </div>
    );
}
