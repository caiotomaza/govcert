@extends('layouts.app')

@section('title', 'Gestão de Usuários')

@section('content')
<div class="space-y-6">

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Gestão de Usuários</h1>
            <p class="text-gray-500 text-sm mt-1">Controle de perfis e status de acesso</p>
        </div>
        <x-button id="btn-create-user" variant="primary">
            + Cadastrar Usuário
        </x-button>
    </div>

    <div id="user-flash" class="hidden bg-green-100 border border-green-400 text-green-800 px-4 py-3 rounded"></div>

    <div class="bg-white rounded-xl shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-gray-50 text-gray-600 uppercase text-xs tracking-wider">
                    <tr>
                        <th class="px-4 py-3">ID</th>
                        <th class="px-4 py-3">Nome Completo</th>
                        <th class="px-4 py-3">E-mail</th>
                        <th class="px-4 py-3">Perfil</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100" id="users-tbody">
                    @foreach($users as $user)
                    <tr id="user-row-{{ $user->id }}" class="hover:bg-gray-50 transition-colors">
                        <td class="px-4 py-3 font-mono text-gray-500">#{{ $user->id }}</td>
                        <td class="px-4 py-3 font-medium text-gray-900 cell-name">{{ $user->name }}</td>
                        <td class="px-4 py-3 text-gray-600 cell-email">{{ $user->email }}</td>
                        <td class="px-4 py-3 cell-role">
                            @if($user->role === 'admin')
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-800">Administrador</span>
                            @elseif($user->role === 'auditor')
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700">Auditor</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-teal-100 text-teal-800">Usuário</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if($user->is_active)
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">Ativo</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">Inativo</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-2">
                                <x-button
                                    class="btn-edit-user"
                                    variant="soft"
                                    size="sm"
                                    data-id="{{ $user->id }}"
                                    data-name="{{ $user->name }}"
                                    data-email="{{ $user->email }}"
                                    data-role="{{ $user->role }}"
                                    data-action="{{ route('admin.users.update', $user) }}">
                                    Editar
                                </x-button>
                                <form method="POST" action="{{ route('admin.users.toggle', $user) }}">
                                    @csrf
                                    <x-button type="submit" size="sm"
                                        :variant="$user->is_active ? 'soft-danger' : 'soft-success'">
                                        {{ $user->is_active ? 'Desativar' : 'Ativar' }}
                                    </x-button>
                                </form>
                                <form method="POST" action="{{ route('admin.users.reset-link', $user) }}">
                                    @csrf
                                    <x-button type="submit" variant="soft-indigo" size="sm">
                                        Enviar Link de Acesso
                                    </x-button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($users->hasPages())
        <div class="px-4 py-3 border-t border-gray-100">
            {{ $users->links() }}
        </div>
        @endif
    </div>

</div>

{{-- Modal: Cadastrar Usuário --}}
<x-modal id="create-modal" max-width="max-w-md">
    <div class="flex items-center justify-between px-5 py-4 bg-indigo-800 text-white">
        <h3 class="font-semibold">Cadastrar Usuário</h3>
        <button id="create-close" type="button"
            class="text-indigo-200 hover:text-white hover:bg-indigo-700 rounded-lg w-8 h-8 flex items-center justify-center text-xl leading-none transition-all duration-300 ease-in-out"
            aria-label="Fechar">&times;</button>
    </div>

    <form id="create-form" class="p-5 space-y-4">
        <p class="text-sm text-gray-500">
            O novo usuário receberá um e-mail com um link para criar sua própria senha.
        </p>

        <div id="cm-error" class="hidden bg-red-100 border border-red-400 text-red-800 px-3 py-2 rounded text-sm"></div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nome Completo *</label>
            <input type="text" id="cm-name" autocomplete="off"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400">
            <p class="mt-1 text-sm text-red-600 hidden" data-error-for="name"></p>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">E-mail *</label>
            <input type="email" id="cm-email" autocomplete="off"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400">
            <p class="mt-1 text-sm text-red-600 hidden" data-error-for="email"></p>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Perfil *</label>
            <select id="cm-role"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400">
                <option value="auditor" selected>Auditor</option>
                <option value="admin">Administrador</option>
                <option value="usuario">Usuário</option>
            </select>
            <p id="cm-role-hint" class="mt-1 text-xs text-gray-400"></p>
            <p class="mt-1 text-sm text-red-600 hidden" data-error-for="role"></p>
        </div>

        <div class="flex items-center gap-2 pt-2">
            <x-button type="submit" id="cm-submit" variant="primary">Cadastrar</x-button>
            <x-button type="button" variant="secondary" data-close>Cancelar</x-button>
        </div>
    </form>
</x-modal>

{{-- Modal: Editar Usuário --}}
<x-modal id="edit-modal" max-width="max-w-md">
    <div class="flex items-center justify-between px-5 py-4 bg-indigo-800 text-white">
        <h3 class="font-semibold">Editar Usuário <span id="em-id" class="text-indigo-300 text-sm font-mono"></span></h3>
        <button id="edit-close" type="button"
            class="text-indigo-200 hover:text-white hover:bg-indigo-700 rounded-lg w-8 h-8 flex items-center justify-center text-xl leading-none transition-all duration-300 ease-in-out"
            aria-label="Fechar">&times;</button>
    </div>

    <form id="edit-form" class="p-5 space-y-4">
        <div id="em-error" class="hidden bg-red-100 border border-red-400 text-red-800 px-3 py-2 rounded text-sm"></div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nome Completo</label>
            <input type="text" id="em-name"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400">
            <p class="mt-1 text-sm text-red-600 hidden" data-error-for="name"></p>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">E-mail</label>
            <input type="email" id="em-email"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400">
            <p class="mt-1 text-sm text-red-600 hidden" data-error-for="email"></p>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Perfil</label>
            <select id="em-role"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400">
                <option value="auditor">Auditor</option>
                <option value="admin">Administrador</option>
                <option value="usuario">Usuário (somente extensão)</option>
            </select>
            <p class="mt-1 text-sm text-red-600 hidden" data-error-for="role"></p>
        </div>

        <div class="flex items-center gap-2 pt-2">
            <x-button type="submit" id="em-submit" variant="primary">Salvar Alterações</x-button>
            <x-button type="button" variant="secondary" data-close>Cancelar</x-button>
        </div>
    </form>
</x-modal>
@endsection

@push('scripts')
<script>
(function () {
    'use strict';

    const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    function openModal(id) {
        document.getElementById(id).classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    }
    function closeModal(id) {
        document.getElementById(id).classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }
    function clearErrors(formId) {
        const form = document.getElementById(formId);
        form.querySelectorAll('[data-error-for]').forEach(el => { el.classList.add('hidden'); el.textContent = ''; });
        const box = form.querySelector('[id$="-error"]');
        if (box) { box.classList.add('hidden'); box.textContent = ''; }
    }
    function showFieldErrors(formId, errors) {
        const form = document.getElementById(formId);
        Object.keys(errors || {}).forEach(field => {
            const el = form.querySelector('[data-error-for="' + field + '"]');
            if (el) { el.textContent = errors[field][0]; el.classList.remove('hidden'); }
        });
    }
    function flashSuccess(msg) {
        const el = document.getElementById('user-flash');
        el.textContent = msg;
        el.classList.remove('hidden');
        window.scrollTo({ top: 0, behavior: 'smooth' });
        setTimeout(() => el.classList.add('hidden'), 6000);
    }
    function roleBadge(role) {
        if (role === 'admin') return '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-800">Administrador</span>';
        if (role === 'usuario') return '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-teal-100 text-teal-800">Usuário</span>';
        return '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700">Auditor</span>';
    }
    function escHtml(str) {
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // Fechar modais via backdrop/botão/Esc
    ['create-modal', 'edit-modal'].forEach(id => {
        const m = document.getElementById(id);
        m.querySelectorAll('[data-close]').forEach(btn => btn.addEventListener('click', () => closeModal(id)));
        m.querySelector('[id$="-close"]')?.addEventListener('click', () => closeModal(id));
        m.querySelector('[class*="absolute inset-0"]')?.addEventListener('click', () => closeModal(id));
    });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            ['create-modal', 'edit-modal'].forEach(id => {
                if (!document.getElementById(id).classList.contains('hidden')) closeModal(id);
            });
        }
    });

    // ── Modal: Cadastrar ───────────────────────────────────────────────────
    const createForm   = document.getElementById('create-form');
    const createSubmit = document.getElementById('cm-submit');
    const STORE_URL    = '{{ route("admin.users.store") }}';

    // Hint dinâmico de perfil no formulário de cadastro
    const cmRole     = document.getElementById('cm-role');
    const cmRoleHint = document.getElementById('cm-role-hint');
    const roleHints  = {
        auditor:  'Acessa o painel web (Visão Geral, Auditoria, Tokens).',
        admin:    'Acesso completo: painel, usuários e configurações do sistema.',
        usuario:  'Usado apenas para login na extensão e envio de logs ao GovCert. Não acessa o painel web.',
    };
    cmRole.addEventListener('change', function () {
        cmRoleHint.textContent = roleHints[this.value] || '';
    });

    document.getElementById('btn-create-user').addEventListener('click', () => {
        clearErrors('create-form');
        createForm.reset();
        cmRoleHint.textContent = roleHints['auditor'];
        openModal('create-modal');
        setTimeout(() => document.getElementById('cm-name').focus(), 50);
    });

    createForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        clearErrors('create-form');
        createSubmit.disabled = true;
        createSubmit.textContent = 'Cadastrando...';

        try {
            const res = await fetch(STORE_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    name:  document.getElementById('cm-name').value,
                    email: document.getElementById('cm-email').value,
                    role:  document.getElementById('cm-role').value,
                }),
            });

            if (res.status === 201) {
                const data = await res.json();
                closeModal('create-modal');
                flashSuccess(data.message);
                appendUserRow(data.user);
            } else if (res.status === 422) {
                showFieldErrors('create-form', (await res.json()).errors);
            } else {
                const box = document.getElementById('cm-error');
                box.textContent = 'Erro inesperado. Tente novamente.';
                box.classList.remove('hidden');
            }
        } catch {
            const box = document.getElementById('cm-error');
            box.textContent = 'Falha de comunicação com o servidor.';
            box.classList.remove('hidden');
        } finally {
            createSubmit.disabled = false;
            createSubmit.textContent = 'Cadastrar';
        }
    });

    function appendUserRow(user) {
        document.getElementById('users-tbody').insertAdjacentHTML('beforeend', `
            <tr id="user-row-${user.id}" class="hover:bg-gray-50 transition-colors bg-green-50">
                <td class="px-4 py-3 font-mono text-gray-500">#${user.id}</td>
                <td class="px-4 py-3 font-medium text-gray-900 cell-name">${escHtml(user.name)}</td>
                <td class="px-4 py-3 text-gray-600 cell-email">${escHtml(user.email)}</td>
                <td class="px-4 py-3 cell-role">${roleBadge(user.role)}</td>
                <td class="px-4 py-3"><span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">Ativo</span></td>
                <td class="px-4 py-3 text-right text-xs text-gray-400 italic">Recarregue para ações completas</td>
            </tr>
        `);
    }

    // ── Modal: Editar ──────────────────────────────────────────────────────
    const editForm   = document.getElementById('edit-form');
    const editSubmit = document.getElementById('em-submit');
    let editActionUrl = null;

    document.querySelectorAll('.btn-edit-user').forEach(btn => {
        btn.addEventListener('click', function () {
            clearErrors('edit-form');
            editActionUrl = this.dataset.action;
            document.getElementById('em-id').textContent    = '#' + this.dataset.id;
            document.getElementById('em-name').value        = this.dataset.name;
            document.getElementById('em-email').value       = this.dataset.email;
            document.getElementById('em-role').value        = this.dataset.role;
            openModal('edit-modal');
            setTimeout(() => document.getElementById('em-name').focus(), 50);
        });
    });

    editForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        clearErrors('edit-form');
        editSubmit.disabled = true;
        editSubmit.textContent = 'Salvando...';

        try {
            const res = await fetch(editActionUrl, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    name:  document.getElementById('em-name').value,
                    email: document.getElementById('em-email').value,
                    role:  document.getElementById('em-role').value,
                }),
            });

            if (res.ok) {
                const data = await res.json();
                updateRow(data.user);
                closeModal('edit-modal');
                flashSuccess(data.message || 'Usuário atualizado com sucesso.');
            } else if (res.status === 422) {
                showFieldErrors('edit-form', (await res.json()).errors);
            } else {
                const box = document.getElementById('em-error');
                box.textContent = 'Erro inesperado (' + res.status + '). Tente novamente.';
                box.classList.remove('hidden');
            }
        } catch {
            const box = document.getElementById('em-error');
            box.textContent = 'Falha de comunicação com o servidor.';
            box.classList.remove('hidden');
        } finally {
            editSubmit.disabled = false;
            editSubmit.textContent = 'Salvar Alterações';
        }
    });

    function updateRow(user) {
        const row = document.getElementById('user-row-' + user.id);
        if (!row) return;
        row.querySelector('.cell-name').textContent  = user.name;
        row.querySelector('.cell-email').textContent = user.email;
        row.querySelector('.cell-role').innerHTML    = roleBadge(user.role);
        const btn = row.querySelector('.btn-edit-user');
        if (btn) {
            btn.dataset.name  = user.name;
            btn.dataset.email = user.email;
            btn.dataset.role  = user.role;
        }
    }
})();
</script>
@endpush
