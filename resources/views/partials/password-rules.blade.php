{{--
    Live checklist for the password policy (AppServiceProvider: Password::defaults()).
    @include('partials.password-rules', ['for' => 'signup-password'])
--}}
<ul class="list-unstyled small mb-3 password-rules" data-password-rules="{{ $for }}">
    <li data-rule="length"><i class="bi bi-circle me-1"></i>At least 8 characters</li>
    <li data-rule="case"><i class="bi bi-circle me-1"></i>Upper- and lower-case letters</li>
    <li data-rule="number"><i class="bi bi-circle me-1"></i>A number</li>
    <li data-rule="symbol"><i class="bi bi-circle me-1"></i>A symbol (e.g. ! @ # $)</li>
</ul>

@once
    <style>
        .password-rules li { color: #6c757d; }
        .password-rules li.ok { color: #198754; }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const tests = {
                length: v => v.length >= 8,
                case: v => /\p{Ll}/u.test(v) && /\p{Lu}/u.test(v),
                number: v => /\d/.test(v),
                symbol: v => /[^\p{L}\p{N}\s]/u.test(v),
            };
            document.querySelectorAll('[data-password-rules]').forEach(function (list) {
                const input = document.getElementById(list.dataset.passwordRules);
                if (!input) return;
                const update = function () {
                    list.querySelectorAll('[data-rule]').forEach(function (item) {
                        const ok = tests[item.dataset.rule](input.value);
                        item.classList.toggle('ok', ok);
                        item.querySelector('i').className = 'bi me-1 ' + (ok ? 'bi-check-circle-fill' : 'bi-circle');
                    });
                };
                input.addEventListener('input', update);
                update();
            });
        });
    </script>
@endonce
