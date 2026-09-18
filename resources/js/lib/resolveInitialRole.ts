// Pure function so Register.tsx's dependency on window.location.search is
// a single call site, and this logic is testable without touching jsdom's
// location object at all.
export function resolveInitialRole(search: string): 'customer' | 'provider' {
    return new URLSearchParams(search).get('role') === 'provider' ? 'provider' : 'customer';
}
