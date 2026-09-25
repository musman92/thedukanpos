import { usePage } from '@inertiajs/react';

const components = Object.fromEntries(
    Object.entries(import.meta.glob('../../../../addons/*/resources/js/slots/*.jsx', { eager: true }))
        .map(([path, module]) => {
            const match = path.match(/addons\/([^/]+)\/resources\/js\/slots\/([^/]+)\.jsx$/);
            return [`${match?.[1]}/${match?.[2]}`, module.default];
        }),
);

export default function SlotHost({ name, ...props }) {
    const registrations = usePage().props.addons?.slots?.[name] || [];

    return registrations.map((registration) => {
        const Component = components[registration.component];
        if (!Component) return null;

        return (
            <Component
                key={`${registration.addon}:${registration.component}`}
                registration={registration}
                {...props}
            />
        );
    });
}
