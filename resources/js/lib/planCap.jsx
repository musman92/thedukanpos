export function planResource(plan, resource) {
    const limit = plan?.[`${resource}_limit`];
    const count = Number(plan?.[`${resource}_count`] ?? 0);
    const nouns = {
        product: ['product', 'products'],
        user: ['login user', 'login users'],
        branch: ['branch', 'branches'],
    };
    const [singular, plural] = nouns[resource] || [resource, resource];
    const noun = Number(limit) === 1 ? singular : plural;
    const name = plan?.name || 'current';

    if (limit == null || limit === '') {
        return { limited: false, full: false, text: '' };
    }

    const full = count >= Number(limit);

    return {
        limited: true,
        full,
        text: full
            ? `The ${name} plan allows ${limit} ${noun}. This shop already has ${count}.`
            : `${count} of ${limit} ${noun} on the ${name} plan.`,
    };
}

export function PlanCapNote({ plan, resource }) {
    const cap = planResource(plan, resource);
    if (!cap.text) return null;

    return (
        <p
            className={`mb-3 rounded-lg px-3 py-2 text-sm ${
                cap.full
                    ? 'bg-amber-500/15 text-amber-900'
                    : 'bg-theme-bg text-theme-ink-soft'
            }`}
        >
            {cap.text}
            {resource === 'user' && cap.full
                ? ' You can still add someone who cannot sign in.'
                : ''}
        </p>
    );
}
