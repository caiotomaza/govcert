@php
    $tab = function (string ...$patterns): string {
        $active = request()->routeIs(...$patterns);

        return 'flex items-center h-16 border-b-2 px-1 text-sm '
             . 'transition-all duration-300 ease-in-out '
             . ($active
                 ? 'border-gov-yellow text-white font-semibold'
                 : 'border-transparent text-indigo-200 hover:text-white hover:border-indigo-300');
    };
@endphp

<nav class="bg-indigo-800 text-white shadow-lg">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">

            <div class="flex items-center gap-6">
                <a href="{{ route('dashboard') }}"
                   class="flex items-center gap-2.5 transition-all duration-300 ease-in-out hover:opacity-90">
                    <img src="{{ asset('img/govcert-mark.svg') }}" alt="GovCert" class="h-9 w-9">
                    <span class="font-bold text-xl tracking-tight">GovCert</span>
                </a>

                <a href="{{ route('dashboard') }}" class="{{ $tab('dashboard') }}">Visão Geral</a>
                <a href="{{ route('audit.index') }}" class="{{ $tab('audit.*') }}">Auditoria</a>
                <a href="{{ route('tokens.index') }}" class="{{ $tab('tokens.*') }}">Tokens</a>

                @if (Auth::user()->isAdmin())
                    <a href="{{ route('admin.users.index') }}" class="{{ $tab('admin.users.*') }}">Usuários</a>
                    <a href="{{ route('admin.activity.index') }}" class="{{ $tab('admin.activity.*') }}">Atividades</a>
                @endif
            </div>

            <div class="flex items-center gap-4 text-sm">

                {{-- Dropdown do usuário --}}
                <div class="relative" id="user-menu-wrapper">
                    <button id="user-menu-btn" type="button"
                        class="flex items-center gap-1.5 h-16 border-b-2 border-transparent px-1 text-sm transition-all duration-300 ease-in-out hover:text-white hover:border-indigo-300 focus:outline-none"
                        aria-haspopup="true" aria-expanded="false">
                        <span>{{ Auth::user()->name }}</span>
                        <span class="text-indigo-400 text-xs">({{ Auth::user()->isAdmin() ? 'Admin' : 'Auditor' }})</span>
                        <svg class="h-3 w-3 text-indigo-300 mt-0.5 transition-transform duration-200" id="user-menu-chevron"
                             xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    <div id="user-menu-dropdown"
                         class="hidden absolute right-0 top-full mt-1 w-56 bg-white rounded-xl shadow-xl border border-gray-100 z-50 overflow-hidden"
                         role="menu">
                        <a href="{{ route('profile.edit') }}"
                           class="flex items-center gap-2.5 px-4 py-3 text-sm text-gray-700 hover:bg-indigo-50 hover:text-indigo-700 transition-colors"
                           role="menuitem">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                            Configurações de perfil
                        </a>

                        @if (Auth::user()->isAdmin())
                        <a href="{{ route('admin.settings.index') }}"
                           class="flex items-center gap-2.5 px-4 py-3 text-sm text-gray-700 hover:bg-indigo-50 hover:text-indigo-700 transition-colors"
                           role="menuitem">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            Configurações do GovCert
                        </a>
                        @endif

                        <div class="border-t border-gray-100"></div>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit"
                                class="w-full flex items-center gap-2.5 px-4 py-3 text-sm text-red-600 hover:bg-red-50 transition-colors text-left"
                                role="menuitem">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                </svg>
                                Sair
                            </button>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </div>
</nav>

<script>
(function () {
    const btn      = document.getElementById('user-menu-btn');
    const dropdown = document.getElementById('user-menu-dropdown');
    const chevron  = document.getElementById('user-menu-chevron');
    if (!btn || !dropdown) return;

    function openMenu() {
        dropdown.classList.remove('hidden');
        btn.setAttribute('aria-expanded', 'true');
        chevron.style.transform = 'rotate(180deg)';
    }
    function closeMenu() {
        dropdown.classList.add('hidden');
        btn.setAttribute('aria-expanded', 'false');
        chevron.style.transform = '';
    }

    btn.addEventListener('click', function (e) {
        e.stopPropagation();
        dropdown.classList.contains('hidden') ? openMenu() : closeMenu();
    });

    document.addEventListener('click', function (e) {
        if (!document.getElementById('user-menu-wrapper').contains(e.target)) {
            closeMenu();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeMenu();
    });
})();
</script>
