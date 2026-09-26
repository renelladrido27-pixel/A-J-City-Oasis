{{-- Shared styling for the auth pages (login/register, forgot-password, reset-password). --}}
<style>
    .auth-a { display: flex; justify-content: center; padding: 1.5rem 0; }
    .auth-a .card { max-width: 400px; width: 100%; background: #fff; border: 1px solid #e2e5eb; border-radius: 14px; padding: 2rem; box-shadow: 0 1px 2px rgba(18,20,26,.04); }
    .auth-a .brandmark { display: flex; align-items: center; gap: 9px; margin-bottom: 1.5rem; }
    .auth-a .brandmark .mark { width: 32px; height: 32px; flex-shrink: 0; }
    .auth-a .brandmark .mark img { width: 100%; height: 100%; display: block; }
    .auth-a .brandmark span { font-weight: 700; color: #12141a; }

    .auth-a .subtitle { color: #565c69; font-size: .88rem; margin: -1rem 0 1.25rem; }

    .auth-a .tabs { display: flex; margin-bottom: 1.5rem; }
    .auth-a .tab { flex: 1; background: none; border: none; padding: 0 0 .7rem; font-weight: 600; font-size: .92rem; color: #8b909a; border-bottom: 2px solid #e2e5eb; cursor: pointer; transition: color .15s ease, border-color .15s ease; }
    .auth-a .tab.active { color: #12141a; border-color: var(--oasis-green); }

    .auth-a .field-float { position: relative; margin-bottom: 1rem; }
    .auth-a .field-float input { width: 100%; padding: 1.15rem .9rem .4rem; border: 1px solid #e2e5eb; border-radius: 10px; font-size: .95rem; outline: none; transition: border-color .15s ease; background: #fff; }
    .auth-a .field-float input:focus { border-color: var(--oasis-green); }
    .auth-a .field-float.is-invalid input { border-color: #dc3545; }
    .auth-a .field-float label { position: absolute; left: .9rem; top: 50%; transform: translateY(-50%); color: #8b909a; font-size: .95rem; pointer-events: none; background: #fff; padding: 0 .25rem; transition: top .15s ease, transform .15s ease, font-size .15s ease, color .15s ease; }
    .auth-a .field-float input:focus + label,
    .auth-a .field-float input:not(:placeholder-shown) + label { top: 0; transform: translateY(-50%) scale(.8); color: var(--oasis-green); }
    .auth-a .field-float.is-invalid input:focus + label,
    .auth-a .field-float.is-invalid input:not(:placeholder-shown) + label { color: #dc3545; }
    .auth-a .field-error { color: #dc3545; font-size: .8rem; margin: -.6rem 0 1rem; }

    .auth-a .pw-wrap { position: relative; }
    .auth-a .pw-wrap input { padding-right: 2.6rem; }
    .auth-a .pw-toggle { position: absolute; right: .5rem; top: 50%; transform: translateY(-50%); background: none; border: none; color: #8b909a; padding: .3rem; line-height: 1; cursor: pointer; }
    .auth-a .pw-toggle:hover { color: #12141a; }

    .auth-a .row-options { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; font-size: .85rem; }
    .auth-a .row-options .checkbox { display: flex; align-items: center; gap: .4rem; color: #565c69; cursor: pointer; }
    .auth-a .link-muted { color: var(--oasis-green); text-decoration: none; }
    .auth-a .link-muted:hover { text-decoration: underline; }

    .auth-a .submit-btn { width: 100%; background: var(--oasis-gold); color: var(--oasis-ink); border: none; border-radius: 10px; padding: .75rem; font-weight: 600; font-size: .95rem; transition: background .15s ease, color .15s ease; }
    .auth-a .submit-btn:hover { background: var(--oasis-gold-dark); color: #fff; }
    .auth-a .submit-btn:disabled { opacity: .7; cursor: not-allowed; }

    .auth-a .switch-line { text-align: center; font-size: .85rem; color: #8b909a; margin: 1rem 0 0; }
    .auth-a .switch-line a { color: var(--oasis-green); text-decoration: none; font-weight: 600; }
    .auth-a .switch-line a:hover { text-decoration: underline; }

    .auth-a .status-note { background: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; border-radius: 10px; padding: .75rem 1rem; font-size: .85rem; margin-bottom: 1.25rem; }

    .auth-a .avatar-upload { position: relative; width: 76px; height: 76px; margin: 0 auto 1.25rem; }
    .auth-a .avatar-preview { position: relative; width: 76px; height: 76px; border-radius: 50%; background: #e4e6ec; display: flex; align-items: center; justify-content: center; overflow: hidden; color: #8b909a; font-size: 1.75rem; }
    .auth-a .avatar-preview img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; display: none; }
    .auth-a .avatar-badge { position: absolute; right: -2px; bottom: -2px; width: 26px; height: 26px; border-radius: 50%; background: var(--oasis-green); color: #fff; display: flex; align-items: center; justify-content: center; border: 2px solid #fff; cursor: pointer; font-size: .7rem; }
    .auth-a .avatar-badge:hover { background: var(--oasis-green-dark); }
</style>
