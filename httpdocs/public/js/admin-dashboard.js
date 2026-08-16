(function () {
    const TOKEN_KEY = 'sanad_admin_token';
    const LOGIN_URL = '/admin/login';

    const token = localStorage.getItem(TOKEN_KEY);
    if (!token) {
        window.location.href = LOGIN_URL;
        return;
    }

    const titles = {
        overview: 'نظرة عامة',
        users: 'المستخدمون',
        specialists: 'الأخصائيون',
        organizations: 'المؤسسات',
        appointments: 'المواعيد',
        library: 'المكتبة',
        wallet: 'المحفظة',
        settings: 'الإعدادات',
    };

    const loaded = {};

    async function api(path, options = {}) {
        const res = await fetch('/api' + path, {
            ...options,
            headers: {
                Accept: 'application/json',
                Authorization: 'Bearer ' + token,
                'Content-Type': 'application/json',
                ...(options.headers || {}),
            },
        });
        if (res.status === 401) {
            localStorage.removeItem(TOKEN_KEY);
            window.location.href = LOGIN_URL;
            throw new Error('انتهت الجلسة');
        }
        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
            throw new Error(data.message || 'حدث خطأ');
        }
        return data;
    }

    function showError(msg) {
        const el = document.getElementById('global-error');
        el.textContent = msg;
        el.classList.remove('admin-hidden');
    }

    function showSuccess(msg) {
        const el = document.getElementById('global-error');
        el.textContent = msg;
        el.className = 'alert alert-success';
        el.classList.remove('admin-hidden');
        setTimeout(() => {
            el.classList.add('admin-hidden');
            el.className = 'alert alert-error admin-hidden';
        }, 3200);
    }

    function statusBadge(status) {
        const s = (status || '').toLowerCase();
        let cls = 'badge-pending';
        if (s === 'approved' || s === 'active') cls = 'badge-approved';
        if (s === 'rejected') cls = 'badge-rejected';
        return `<span class="badge ${cls}">${status || '—'}</span>`;
    }

    function renderTable(columns, rows, rowHtml) {
        if (!rows.length) {
            return '<p style="padding:1rem;color:#6F7A92">لا توجد بيانات</p>';
        }
        const head = columns.map((c) => `<th>${c}</th>`).join('');
        const body = rows.map(rowHtml).join('');
        return `<table class="data-table"><thead><tr>${head}</tr></thead><tbody>${body}</tbody></table>`;
    }

    async function loadMe() {
        const me = await api('/auth/me');
        if (me.role !== 'admin') {
            localStorage.removeItem(TOKEN_KEY);
            window.location.href = LOGIN_URL;
            return;
        }
        document.getElementById('admin-user-label').textContent = me.name || me.email;
    }

    async function loadOverview() {
        const data = await api('/v1/admin/dashboard');
        const c = data.counters || {};
        document.querySelector('[data-stat="users"]').textContent = c.users ?? '0';
        document.querySelector('[data-stat="specialists"]').textContent = c.specialists ?? '0';
        document.querySelector('[data-stat="organizations"]').textContent = c.organizations ?? '0';
        document.querySelector('[data-stat="appointments_today"]').textContent = c.appointments_today ?? '0';
    }

    async function loadUsers() {
        const data = await api('/v1/admin/users');
        const rows = data.data || [];
        document.getElementById('users-count').textContent = rows.length + ' سجل';
        document.getElementById('users-table-wrap').innerHTML = renderTable(
            ['#', 'الاسم', 'البريد'],
            rows,
            (r) => `<tr><td>${r.id}</td><td>${escapeHtml(r.name)}</td><td>${escapeHtml(r.email || '—')}</td></tr>`
        );
    }

    async function loadSpecialists() {
        const data = await api('/v1/admin/specialists');
        const rows = data.data || [];
        document.getElementById('specialists-table-wrap').innerHTML = renderTable(
            ['#', 'الاسم', 'التخصص', 'الحالة', 'إجراء'],
            rows,
            (r) => `<tr>
                <td>${r.id}</td>
                <td>${escapeHtml(r.name)}</td>
                <td>${escapeHtml(r.specialty || '—')}</td>
                <td>${statusBadge(r.status)}</td>
                <td>${r.status === 'pending' ? actionButtons('specialist', r.id) : '—'}</td>
            </tr>`
        );
        bindActions('specialists-table-wrap', 'specialist');
    }

    async function loadOrganizations() {
        const data = await api('/v1/admin/organizations');
        const rows = data.data || [];
        document.getElementById('organizations-table-wrap').innerHTML = renderTable(
            ['#', 'الاسم', 'الحالة', 'إجراء'],
            rows,
            (r) => `<tr>
                <td>${r.id}</td>
                <td>${escapeHtml(r.name)}</td>
                <td>${statusBadge(r.status)}</td>
                <td>${r.status === 'pending' ? actionButtons('organization', r.id) : '—'}</td>
            </tr>`
        );
        bindActions('organizations-table-wrap', 'organization');
    }

    async function loadLibrary() {
        const data = await api('/v1/admin/library/posts');
        const rows = data.data || [];
        document.getElementById('library-table-wrap').innerHTML = renderTable(
            ['#', 'العنوان', 'الحالة', 'إجراء'],
            rows,
            (r) => `<tr>
                <td>${r.id}</td>
                <td>${escapeHtml(r.title || '—')}</td>
                <td>${statusBadge(r.status)}</td>
                <td><button type="button" class="btn-sm btn-approve" data-toggle-post="${r.id}">تبديل النشر</button></td>
            </tr>`
        );
        document.getElementById('library-table-wrap').querySelectorAll('[data-toggle-post]').forEach((btn) => {
            btn.addEventListener('click', async () => {
                try {
                    await api(`/v1/admin/library/posts/${btn.dataset.togglePost}/toggle`, { method: 'POST' });
                    loaded.library = false;
                    await loadLibrary();
                    loaded.library = true;
                    showSuccess('تم تحديث حالة المقال');
                } catch (e) {
                    showError(e.message);
                }
            });
        });
    }

    async function loadSettings() {
        const data = await api('/v1/admin/settings');
        const form = document.getElementById('settings-form');
        if (!form) return;
        form.privacy_policy.value = data.privacy_policy || '';
        form.contact_info.value = data.contact_info || '';
        form.platform_fee_percent.value = data.platform_fee_percent ?? '';
    }

    async function loadAppointments() {
        const data = await api('/v1/admin/appointments');
        const rows = data.data || [];
        document.getElementById('appointments-table-wrap').innerHTML = renderTable(
            ['#', 'المريض', 'الأخصائي', 'البدء', 'الحالة'],
            rows,
            (r) => `<tr>
                <td>${r.id}</td>
                <td>#${r.patient_id ?? '—'}</td>
                <td>#${r.specialist_id ?? '—'}</td>
                <td>${escapeHtml(r.starts_at || '—')}</td>
                <td>${statusBadge(r.status)}</td>
            </tr>`
        );
    }

    function actionButtons(type, id) {
        return `<button type="button" class="btn-sm btn-approve" data-action="approve" data-type="${type}" data-id="${id}">اعتماد</button>
            <button type="button" class="btn-sm btn-reject" data-action="reject" data-type="${type}" data-id="${id}">رفض</button>`;
    }

    function bindActions(wrapId, type) {
        document.getElementById(wrapId).querySelectorAll('[data-action]').forEach((btn) => {
            btn.addEventListener('click', async () => {
                const id = btn.dataset.id;
                const action = btn.dataset.action;
                const path =
                    type === 'specialist'
                        ? `/v1/admin/specialists/${id}/${action}`
                        : `/v1/admin/organizations/${id}/${action}`;
                try {
                    await api(path, {
                        method: 'POST',
                        body: action === 'reject' ? JSON.stringify({ reason: 'مراجعة لوحة الإدارة' }) : undefined,
                    });
                    if (type === 'specialist') await loadSpecialists();
                    else await loadOrganizations();
                } catch (e) {
                    showError(e.message);
                }
            });
        });
    }

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function switchPanel(name) {
        document.querySelectorAll('.admin-panel').forEach((p) => p.classList.add('admin-hidden'));
        document.getElementById('panel-' + name).classList.remove('admin-hidden');
        document.getElementById('panel-title').textContent = titles[name] || name;
        document.querySelectorAll('#admin-nav button').forEach((b) => {
            b.classList.toggle('active', b.dataset.panel === name);
        });
        if (!loaded[name]) {
            loaded[name] = true;
            const loaders = {
                overview: loadOverview,
                users: loadUsers,
                specialists: loadSpecialists,
                organizations: loadOrganizations,
                appointments: loadAppointments,
                library: loadLibrary,
                settings: loadSettings,
            };
            loaders[name]?.().catch((e) => showError(e.message));
        }
    }

    document.getElementById('coupon-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        try {
            await api('/v1/admin/wallet/coupon', {
                method: 'POST',
                body: JSON.stringify({ code: fd.get('code'), points: Number(fd.get('points')) }),
            });
            e.target.reset();
            showSuccess('تم إنشاء الكوبون');
        } catch (err) {
            showError(err.message);
        }
    });

    document.getElementById('credit-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        try {
            await api('/v1/admin/wallet/credit', {
                method: 'POST',
                body: JSON.stringify({
                    user_id: Number(fd.get('user_id')),
                    points: Number(fd.get('points')),
                }),
            });
            e.target.reset();
            showSuccess('تم إضافة الرصيد');
        } catch (err) {
            showError(err.message);
        }
    });

    document.getElementById('settings-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        try {
            await api('/v1/admin/settings', {
                method: 'PUT',
                body: JSON.stringify({
                    privacy_policy: fd.get('privacy_policy'),
                    contact_info: fd.get('contact_info'),
                    platform_fee_percent: fd.get('platform_fee_percent')
                        ? Number(fd.get('platform_fee_percent'))
                        : null,
                }),
            });
            showSuccess('تم حفظ الإعدادات');
        } catch (err) {
            showError(err.message);
        }
    });

    document.getElementById('password-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        try {
            await api('/v1/admin/profile/password', {
                method: 'POST',
                body: JSON.stringify({
                    current_password: fd.get('current_password'),
                    new_password: fd.get('new_password'),
                    new_password_confirmation: fd.get('new_password_confirmation'),
                }),
            });
            e.target.reset();
            showSuccess('تم تحديث كلمة المرور');
        } catch (err) {
            showError(err.message || 'تعذّر تحديث كلمة المرور');
        }
    });

    document.querySelectorAll('#admin-nav button').forEach((btn) => {
        btn.addEventListener('click', () => switchPanel(btn.dataset.panel));
    });

    document.getElementById('logout-btn').addEventListener('click', async () => {
        try {
            await api('/auth/logout', { method: 'POST' });
        } catch (_) {
            /* ignore */
        }
        localStorage.removeItem(TOKEN_KEY);
        window.location.href = LOGIN_URL;
    });

    loadMe()
        .then(() => {
            loaded.overview = true;
            return loadOverview();
        })
        .catch((e) => showError(e.message));
})();
