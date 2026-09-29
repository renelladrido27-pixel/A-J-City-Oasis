{{--
    Real-time notifications via Laravel Echo + Pusher. Loaded from the CDN like
    Bootstrap (this app has no Vite build step). Renders nothing unless
    BROADCAST_CONNECTION=pusher and Pusher keys are set, so the app behaves
    exactly as before — reload-to-see — when real-time isn't configured.
--}}
@auth
    @if (config('broadcasting.default') === 'pusher' && config('broadcasting.connections.pusher.key'))
        <div class="toast-container position-fixed bottom-0 end-0 p-3" id="liveToasts" style="z-index: 1090;"></div>

        <script src="https://cdn.jsdelivr.net/npm/pusher-js@8.6.0/dist/web/pusher.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/laravel-echo@2.5.0/dist/echo.iife.js"></script>
        <script>
            (function () {
                // The IIFE build exposes the module object; the class is its default export.
                const echo = new Echo.default({
                    broadcaster: 'pusher',
                    key: @json(config('broadcasting.connections.pusher.key')),
                    cluster: @json(config('broadcasting.connections.pusher.options.cluster')),
                    forceTLS: true,
                    authEndpoint: @json(url('/broadcasting/auth')),
                    auth: { headers: { 'X-CSRF-TOKEN': @json(csrf_token()) } },
                });

                function setUnread(count) {
                    document.querySelectorAll('[data-live-unread]').forEach(function (badge) {
                        badge.textContent = count > 9 ? '9+' : count;
                        badge.classList.toggle('d-none', count === 0);
                    });
                }

                function showToast(n) {
                    const toast = document.createElement('div');
                    toast.className = 'toast';
                    toast.setAttribute('role', 'status');
                    toast.innerHTML =
                        '<div class="toast-header">' +
                            '<i class="bi bi-bell-fill text-primary me-2"></i>' +
                            '<strong class="me-auto"></strong>' +
                            '<small class="text-muted">just now</small>' +
                            '<button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>' +
                        '</div>' +
                        '<a class="toast-body d-block text-decoration-none text-body"></a>';
                    // textContent, not innerHTML — titles/messages include tenant-entered text.
                    toast.querySelector('strong').textContent = n.title;
                    toast.querySelector('.toast-body').textContent = n.message;
                    toast.querySelector('.toast-body').href = n.url;

                    document.getElementById('liveToasts').appendChild(toast);
                    toast.addEventListener('hidden.bs.toast', function () { toast.remove(); });
                    bootstrap.Toast.getOrCreateInstance(toast, { delay: 8000 }).show();
                }

                echo.private('users.{{ auth()->id() }}')
                    .listen('.notification.created', function (n) {
                        setUnread(n.unread_count);
                        showToast(n);
                    });
            })();
        </script>
    @endif
@endauth
