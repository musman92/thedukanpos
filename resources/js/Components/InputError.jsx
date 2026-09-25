export default function InputError({ message, className = '', ...props }) {
    return message ? (
        <p
            {...props}
            className={'text-sm text-theme-danger ' + className}
        >
            {message}
        </p>
    ) : null;
}
