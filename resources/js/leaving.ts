/** Whether a visit leaves the page: opening another one, or logging out. Saving something in it does not. */
export const leaves = (visit: { method: string; url: { pathname: string }; prefetch?: boolean }) =>
    !visit.prefetch && (visit.method === 'get' || visit.url.pathname === '/session');
