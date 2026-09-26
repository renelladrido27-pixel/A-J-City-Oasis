{{-- Shared motion: scroll-reveal for landing sections, hover-lift for cards,
     and a subtle page-content fade-in. All respect prefers-reduced-motion. --}}
<style>
    .reveal { opacity: 0; transform: translateY(16px); transition: opacity .5s ease, transform .5s ease; }
    .reveal.is-visible { opacity: 1; transform: translateY(0); }

    .hover-lift { transition: transform .18s ease, box-shadow .18s ease; }
    .hover-lift:hover { transform: translateY(-4px); box-shadow: 0 1rem 2rem rgba(27,67,50,.15); }

    .btn { transition: transform .15s ease, background-color .15s ease, border-color .15s ease, box-shadow .15s ease, color .15s ease; }
    .btn:hover { transform: translateY(-1px); }
    .btn:active { transform: translateY(0); }

    .sidebar .nav-link { transition: background-color .15s ease, color .15s ease; }

    main { animation: oasisFadeIn .35s ease; }
    @keyframes oasisFadeIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }

    @media (prefers-reduced-motion: reduce) {
        .reveal { opacity: 1; transform: none; transition: none; }
        .hover-lift:hover { transform: none; }
        .btn, .btn:hover, .btn:active { transform: none; }
        main { animation: none; }
    }
</style>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var targets = document.querySelectorAll('.reveal');
        if (!targets.length) return;

        if (!('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            targets.forEach(function (el) { el.classList.add('is-visible'); });
            return;
        }

        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.15 });

        targets.forEach(function (el) { observer.observe(el); });
    });
</script>
