import { useState } from 'react';
import Icon from '@/Components/FontAwesome/Icon';
import Tooltip from '@/Components/Tooltip';

export default function CopyButton({ getValue, label = null, className = '', successMessage = 'Copied!' }) {
    const [copied, setCopied] = useState(false);

    function handleCopy() {
        const value = typeof getValue === 'function' ? getValue() : getValue;
        navigator.clipboard.writeText(value).then(() => {
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        });
    }

    return (
        <Tooltip text={copied ? successMessage : 'Copy to clipboard'}>
            <button onClick={handleCopy} className={className}>
                <Icon icon={copied ? 'check' : 'copy'} style="solid" className={label ? 'mr-2' : ''} />
                {label && <span>{copied ? successMessage : label}</span>}
            </button>
        </Tooltip>
    );
}
