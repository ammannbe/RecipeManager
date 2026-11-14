{{-- Web Push on this device (profile, ANNOUNCEMENTS_DRIVER=account-manager with VAPID keys): registers public/push-sw.js and
     the browser's push subscription (POST/DELETE /push-subscriptions, PushSubscriptionController). Same flow as Chorelist. --}}
<div
    data-push-toggle
    x-data="{
        key: @js($vapidPublicKey),
        supported: 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window,
        permission: 'Notification' in window ? Notification.permission : 'unsupported',
        subscribed: false,
        busy: false,
        error: '',
        async init() {
            if (! this.supported) return;
            const registration = await navigator.serviceWorker.getRegistration('/');
            this.subscribed = Boolean(await registration?.pushManager.getSubscription());
        },
        async send(method, body) {
            const response = await fetch(@js(route('push-subscriptions.store')), {
                method,
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @js(csrf_token()) },
                body: JSON.stringify(body),
            });
            if (! response.ok) throw new Error(String(response.status));
        },
        keyBytes() {
            const base64 = (this.key + '='.repeat((4 - (this.key.length % 4)) % 4)).replace(/-/g, '+').replace(/_/g, '/');
            return Uint8Array.from([...atob(base64)].map((char) => char.charCodeAt(0)));
        },
        async enable() {
            this.busy = true;
            this.error = '';
            try {
                this.permission = await Notification.requestPermission();
                if (this.permission !== 'granted') throw new Error(this.permission);
                await navigator.serviceWorker.register('/push-sw.js', { scope: '/' });
                const registration = await navigator.serviceWorker.ready;
                const subscription = await registration.pushManager.getSubscription()
                    ?? await registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: this.keyBytes() });
                const json = subscription.toJSON();
                await this.send('POST', { endpoint: json.endpoint, keys: json.keys, content_encoding: (PushManager.supportedContentEncodings ?? ['aesgcm'])[0] });
                this.subscribed = true;
            } catch (error) {
                this.error = this.permission === 'denied' ? @js(__('Notifications are blocked in the browser settings.')) : @js(__('Push notifications could not be turned on.'));
            } finally {
                this.busy = false;
            }
        },
        async disable() {
            this.busy = true;
            this.error = '';
            try {
                const registration = await navigator.serviceWorker.getRegistration('/');
                const subscription = await registration?.pushManager.getSubscription();
                if (subscription) {
                    await this.send('DELETE', { endpoint: subscription.endpoint });
                    await subscription.unsubscribe();
                }
                this.subscribed = false;
            } catch (error) {
                this.error = @js(__('Push notifications could not be turned off.'));
            } finally {
                this.busy = false;
            }
        },
    }"
    style="display: flex; flex-direction: column; gap: 0.5rem;"
>
    <p style="margin: 0; font-size: 0.875rem; font-weight: 500;">{{ __('Push notifications on this device') }}</p>
    <p style="margin: 0; font-size: 0.875rem; color: var(--gray-500);">{{ __('News of :app as notifications of this browser. Turn it on on every device you want them on.', ['app' => config('app.name')]) }}</p>

    <template x-if="! supported">
        <p style="margin: 0; font-size: 0.875rem; color: var(--gray-500);">{{ __('This browser does not support push notifications (on iPhone and iPad only for apps added to the home screen).') }}</p>
    </template>

    <div x-show="supported" x-cloak style="display: flex; gap: 0.5rem; align-items: center;">
        <x-filament::button x-show="! subscribed" x-on:click="enable()" x-bind:disabled="busy" icon="heroicon-o-bell" color="gray" size="sm">
            {{ __('Turn on') }}
        </x-filament::button>
        <x-filament::button x-show="subscribed" x-on:click="disable()" x-bind:disabled="busy" icon="heroicon-o-bell-slash" color="gray" size="sm">
            {{ __('Turn off') }}
        </x-filament::button>
        <span x-show="subscribed" style="font-size: 0.875rem; color: var(--success-600);">{{ __('On for this device') }}</span>
    </div>

    <p x-show="error" x-text="error" x-cloak style="margin: 0; font-size: 0.875rem; color: var(--danger-600);"></p>
</div>
