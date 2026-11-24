export const pushSupported = () => 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;

const toBytes = (base64url: string) => {
    const base64 = base64url.replace(/-/g, '+').replace(/_/g, '/');

    return Uint8Array.from(atob(base64), (char) => char.charCodeAt(0));
};

const sameKey = (subscription: PushSubscription, publicKey: string) => {
    const own = subscription.options.applicationServerKey;
    const current = toBytes(publicKey);

    return own !== null && own.byteLength === current.length && new Uint8Array(own).every((byte, index) => byte === current[index]);
};

const request = async (method: 'POST' | 'DELETE', body: unknown) => {
    const token = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/)?.[1] ?? '';
    const response = await fetch('/push-subscription', {
        method,
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': decodeURIComponent(token) },
        body: JSON.stringify(body),
    });
    if (!response.ok) throw new Error(`The server answered ${response.status}.`);
};

const currentSubscription = async () => (await (await navigator.serviceWorker.getRegistration())?.pushManager.getSubscription()) ?? null;

/** Whether this browser is subscribed. Tells the server again, which also covers a new session. */
export async function resumePush(publicKey: string): Promise<boolean> {
    const subscription = await currentSubscription();
    if (!subscription) return false;

    // The keys of the server changed, so this subscription no longer works.
    if (!sameKey(subscription, publicKey)) {
        await subscription.unsubscribe();
        return false;
    }
    await request('POST', subscription);

    return true;
}

export async function enablePush(publicKey: string): Promise<void> {
    await navigator.serviceWorker.register('/sw.js');
    const { pushManager } = await navigator.serviceWorker.ready;
    const subscription = await pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: toBytes(publicKey) });

    try {
        await request('POST', subscription);
    } catch (error) {
        await subscription.unsubscribe();
        throw error;
    }
}

export async function disablePush(): Promise<void> {
    const subscription = await currentSubscription();
    if (!subscription) return;

    await request('DELETE', { endpoint: subscription.endpoint });
    await subscription.unsubscribe();
}

/** The user of this browser is changing: whatever it was subscribed for ends. Never blocks the change. */
export async function dropPush(): Promise<void> {
    if (!pushSupported()) return;

    const drop = async () => {
        await (await currentSubscription())?.unsubscribe();
    };
    await Promise.race([drop().catch(() => undefined), new Promise((resolve) => setTimeout(resolve, 2000))]);
}
