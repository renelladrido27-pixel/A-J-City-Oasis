{{-- Shared brand palette + Bootstrap theme-color override, so auth/tenant/admin
     screens pick up the same green/gold identity used on the public site
     (resources/views/home.blade.php) instead of Bootstrap's default blue. --}}
<style>
    :root {
        --oasis-green: #1b4332;
        --oasis-green-dark: #10291f;
        --oasis-green-light: #2d6a4f;
        --oasis-gold: #c9963e;
        --oasis-gold-dark: #b8852f;
        --oasis-sand: #faf6ee;
        --oasis-ink: #1f2d27;

        /* Re-point Bootstrap's own theme-color variables at the brand palette.
           Bootstrap 5.3 components (btn-primary, text-primary, bg-primary-subtle,
           form focus rings, links, ...) read these at render time, so every
           existing view that already uses btn-primary/text-primary picks up the
           brand color automatically — no per-view changes needed. */
        --bs-primary: var(--oasis-green);
        --bs-primary-rgb: 27, 67, 50;
        --bs-primary-text-emphasis: var(--oasis-green-dark);
        --bs-primary-bg-subtle: #e9f2ec;
        --bs-primary-border-subtle: #bcd3c5;
        --bs-link-color: var(--oasis-green);
        --bs-link-color-rgb: 27, 67, 50;
        --bs-link-hover-color: var(--oasis-green-dark);
        --bs-link-hover-color-rgb: 16, 41, 31;
    }

    .btn-oasis { background: var(--oasis-gold); border-color: var(--oasis-gold); color: var(--oasis-ink); font-weight: 600; }
    .btn-oasis:hover, .btn-oasis:focus { background: var(--oasis-gold-dark); border-color: var(--oasis-gold-dark); color: #fff; }
    .text-oasis { color: var(--oasis-green) !important; }
    .bg-oasis { background: var(--oasis-green) !important; }
</style>
