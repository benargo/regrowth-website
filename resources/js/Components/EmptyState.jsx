import Icon from '@/Components/FontAwesome/Icon';

export default function EmptyState({ icon, iconStyle = 'solid', message, size = 'text-4xl', children }) {
    return (
        <div className="py-12 text-center text-gray-400">
            <Icon icon={icon} style={iconStyle} className={`mb-4 ${size}`} />
            <p>{message}</p>
            {children && <div className="mt-4">{children}</div>}
        </div>
    );
}
