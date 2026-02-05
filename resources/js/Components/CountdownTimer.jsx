import { useState, useEffect } from 'react';

export default function CountdownTimer({ targetDate, className = '', children }) {
    const [timeRemaining, setTimeRemaining] = useState(calculateTimeRemaining());

    function calculateTimeRemaining() {
        const now = new Date();
        const target = new Date(targetDate);
        const difference = target - now;

        if (difference <= 0) {
            return null;
        }

        return {
            days: Math.floor(difference / (1000 * 60 * 60 * 24)),
            hours: Math.floor((difference / (1000 * 60 * 60)) % 24),
            minutes: Math.floor((difference / (1000 * 60)) % 60),
            seconds: Math.floor((difference / 1000) % 60),
        };
    }

    useEffect(() => {
        const timer = setInterval(() => {
            const remaining = calculateTimeRemaining();
            setTimeRemaining(remaining);
            if (!remaining) {
                clearInterval(timer);
            }
        }, 1000);

        return () => clearInterval(timer);
    }, [targetDate]);

    if (!timeRemaining) {
        return null;
    }

    const TimeUnit = ({ value, label }) => (
        <div className="flex flex-col items-center">
            <span className="text-4xl font-bold md:text-6xl">
                {String(value).padStart(2, '0')}
            </span>
            <span className="text-sm uppercase tracking-wide md:text-base">{label}</span>
        </div>
    );

    return (
        <div className={className}>
            {children}
            <div className="flex justify-center gap-4 md:gap-8">
                {timeRemaining.days > 0 && <TimeUnit value={timeRemaining.days} label="Days" />}
                <TimeUnit value={timeRemaining.hours} label="Hours" />
                <TimeUnit value={timeRemaining.minutes} label="Minutes" />
                <TimeUnit value={timeRemaining.seconds} label="Seconds" />
            </div>
        </div>
    );
}
