import { forwardRef, useEffect, useImperativeHandle, useRef } from 'react';

export default forwardRef(function TextInput(
    { type = 'text', className = '', isFocused = false, ...props },
    ref,
) {
    const localRef = useRef(null);

    useImperativeHandle(ref, () => ({
        focus: () => localRef.current?.focus(),
    }));

    useEffect(() => {
        if (isFocused) {
            localRef.current?.focus();
        }
    }, [isFocused]);

    return (
        <input
            {...props}
            type={type}
            className={
                'rounded border border-camel-600 bg-forever-800 px-4 py-2 text-white placeholder-gray-400 focus:border-camel-500 focus:outline-hidden focus:ring-2 focus:ring-camel-500 ' +
                className
            }
            ref={localRef}
        />
    );
});
