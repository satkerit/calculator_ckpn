{{--
    Global SweetAlert2 Confirmation + Loading Layer
    - Konfirmasi otomatis untuk SEMUA form POST non-import (kecuali di-opt-out via data-no-confirm).
    - Efek loading persisten lintas redirect (LocalStorage flag + sebelum submit).
    - Kustomisasi via atribut form:
        data-confirm-title, data-confirm-message, data-confirm-detail,
        data-confirm-type, data-confirm-button, data-loading-message, data-no-confirm, data-no-loading
--}}
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
    .ckpn-swal-popup {
        border-radius: 1rem !important;
        padding: 1.75rem !important;
        box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.4) !important;
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif !important;
    }
    .ckpn-swal-title { font-size: 1.2rem !important; font-weight: 700 !important; color: #0f172a !important; }
    .ckpn-swal-html { color: #475569 !important; font-size: 0.9rem !important; line-height: 1.6 !important; }
    .ckpn-swal-confirm, .ckpn-swal-cancel {
        border-radius: 0.6rem !important; font-weight: 600 !important;
        padding: 0.65rem 1.4rem !important; font-size: 0.875rem !important;
    }
    .ckpn-swal-confirm { box-shadow: 0 4px 14px -4px rgba(37, 99, 235, 0.5) !important; }
    /* Loading overlay */
    #ckpn-loading-overlay {
        position: fixed; inset: 0; z-index: 99998;
        display: flex; align-items: center; justify-content: center;
        background: rgba(15, 23, 42, 0.55); backdrop-filter: blur(6px);
    }
    .ckpn-spinner {
        width: 56px; height: 56px; border-radius: 50%;
        border: 4px solid rgba(255, 255, 255, 0.25);
        border-top-color: #60a5fa;
        animation: ckpn-spin 0.8s linear infinite;
    }
    @keyframes ckpn-spin { to { transform: rotate(360deg); } }
</style>

<div id="ckpn-loading-overlay" style="display: none;">
    <div class="flex flex-col items-center gap-4">
        <div class="ckpn-spinner"></div>
        <p id="ckpn-loading-text" class="text-white text-sm font-semibold tracking-wide">Memproses data, mohon tunggu…</p>
        <div id="ckpn-progress-wrap" style="display: none;" class="flex flex-col items-center gap-2 w-72">
            <div class="w-full h-2.5 rounded-full bg-white/20 overflow-hidden">
                <div id="ckpn-progress-bar" class="h-full rounded-full bg-blue-400 transition-all duration-500 ease-out" style="width: 0%;"></div>
            </div>
            <div class="flex items-center justify-between w-full">
                <span id="ckpn-progress-msg" class="text-white/80 text-xs"></span>
                <span id="ckpn-progress-pct" class="text-white text-xs font-bold tabular-nums">0%</span>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const LS_KEY = 'ckpn_loading_active';
    const PROGRESS_URL = @json(route('calculation.progress'));

    function showLoading(message) {
        const overlay = document.getElementById('ckpn-loading-overlay');
        const text = document.getElementById('ckpn-loading-text');
        if (message) text.textContent = message;
        overlay.style.display = 'flex';
    }

    function hideLoading() {
        const overlay = document.getElementById('ckpn-loading-overlay');
        if (overlay) overlay.style.display = 'none';
        resetProgress();
        localStorage.removeItem(LS_KEY);
    }

    function showProgress(message, percentage) {
        const wrap = document.getElementById('ckpn-progress-wrap');
        const bar = document.getElementById('ckpn-progress-bar');
        const pct = document.getElementById('ckpn-progress-pct');
        const msg = document.getElementById('ckpn-progress-msg');
        if (!wrap) return;
        wrap.style.display = 'flex';
        const clamped = Math.max(0, Math.min(100, Number(percentage) || 0));
        bar.style.width = clamped + '%';
        pct.textContent = Math.round(clamped) + '%';
        if (msg) msg.textContent = message || '';
    }

    function resetProgress() {
        const wrap = document.getElementById('ckpn-progress-wrap');
        const bar = document.getElementById('ckpn-progress-bar');
        const pct = document.getElementById('ckpn-progress-pct');
        if (wrap) wrap.style.display = 'none';
        if (bar) bar.style.width = '0%';
        if (pct) pct.textContent = '0%';
    }

    // Escape karakter HTML agar aman disisipkan ke innerHTML.
    // PENTING: div.innerHTML hanya meng-escape `&` dan `<`, sehingga `>` dan `"` tetap lolos.
    function escapeHtml(str) {
        return String(str == null ? '' : str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    // Hentikan animasi + tampilkan pesan error (dipakai global, termasuk modul kalkulasi).
    function showError(message) {
        hideLoading();
        if (window.Swal && typeof Swal.fire === 'function') {
            Swal.fire({
                title: 'Proses Gagal',
                text: message || 'Terjadi kesalahan saat memproses data.',
                icon: 'error',
                iconColor: '#dc2626',
                confirmButtonText: 'Tutup',
                confirmButtonColor: '#dc2626',
                customClass: {
                    popup: 'ckpn-swal-popup',
                    title: 'ckpn-swal-title',
                    htmlContainer: 'ckpn-swal-html',
                    confirmButton: 'ckpn-swal-confirm',
                },
            });
        } else {
            window.alert(message || 'Terjadi kesalahan saat memproses data.');
        }
    }
    window.ckpnShowError = showError;

    // Intersep klik link nav agar loading tidak "nyangkut" dari operasi sebelumnya
    document.addEventListener('click', function (e) {
        const link = e.target.closest('a[href]');
        if (link && localStorage.getItem(LS_KEY)) {
            localStorage.removeItem(LS_KEY);
            hideLoading();
        }
    }, true);

    // Jaring pengaman global: bila terjadi error tak terduga selagi overlay proses
    // aktif, hentikan animasi agar pesan error di halaman tetap terlihat.
    let ckpnOverlayWasActive = false;
    window.addEventListener('error', function () {
        ckpnOverlayWasActive = document.getElementById('ckpn-loading-overlay')?.style.display !== 'none';
        if (ckpnOverlayWasActive) hideLoading();
    });
    window.addEventListener('unhandledrejection', function () {
        if (document.getElementById('ckpn-loading-overlay')?.style.display !== 'none') hideLoading();
    });

    // Tampilkan loading lagi setelah redirect (persist lintas halaman).    // PENTING: jangan hanya mengandalkan event 'load' karena event itu menunggu
    // SEMUA resource (CDN, font, dsb). Bila ada resource lambat, overlay tak pernah
    // tertutup. Gunakan DOMContentLoaded + safety timeout.
    if (localStorage.getItem(LS_KEY)) {
        const msg = localStorage.getItem(LS_KEY + '_msg');
        const autoHide = () => setTimeout(hideLoading, 700);
        if (document.readyState === 'complete' || document.readyState === 'interactive') {
            showLoading(msg || undefined);
            autoHide();
        } else {
            document.addEventListener('DOMContentLoaded', () => {
                showLoading(msg || undefined);
                autoHide();
            });
        }
        // Safety net: paksa tutup maksimal 6 detik apa pun yang terjadi.
        setTimeout(hideLoading, 6000);
    }

    const DEFAULT_LOADING_MSG = 'Memproses data, mohon tunggu…';

    function inferConfirmConfig(form) {
        let title = form.dataset.confirmTitle;
        let message = form.dataset.confirmMessage;
        let type = form.dataset.confirmType || 'question';

        const btnText = (form.querySelector('button[type="submit"]')?.textContent || '').toLowerCase();
        const action = (form.getAttribute('action') || '').toLowerCase();

        // Heuristik konteks bila tidak diset eksplisit
        if (!title) {
            if (action.includes('destroy') || action.includes('delete') || btnText.includes('hapus')) {
                title = 'Konfirmasi Hapus Data';
                type = 'danger';
                message = message || 'Data yang dihapus tidak dapat dikembalikan. Lanjutkan?';
            } else if (action.includes('store') || action.includes('update') || btnText.includes('simpan')) {
                title = 'Konfirmasi Simpan Data';
                type = 'question';
                message = message || 'Data akan disimpan ke sistem. Lanjutkan?';
            } else if (btnText.includes('rekal') || btnText.includes('hitung ulang')) {
                title = 'Konfirmasi Rekalkulasi';
                type = 'warning';
                message = message || 'Hasil perhitungan sebelumnya akan diganti dengan hasil baru. Lanjutkan?';
            } else if (btnText.includes('finalize') || btnText.includes('finalisasi') || btnText.includes('hitung ulang ckpn')) {
                title = 'Konfirmasi Finalisasi CKPN';
                type = 'warning';
                message = message || 'Proses finalisasi akan menghitung ulang CKPN final periode ini. Lanjutkan?';
            } else {
                title = 'Konfirmasi Proses';
                type = 'question';
                message = message || 'Proses ini akan dijalankan pada periode assessment aktif. Lanjutkan?';
            }
        }

        const btnLabel = form.querySelector('button[type="submit"]')?.textContent.trim();

        return {
            title: title,
            message: message || '',
            detail: form.dataset.confirmDetail || '',
            type: type,
            confirmText: form.dataset.confirmButton || btnLabel || 'Ya, Lanjutkan',
            loadingMessage: form.dataset.loadingMessage || DEFAULT_LOADING_MSG,
        };
    }

    function swalTheme(type) {
        const map = {
            danger:  { confirm: '#dc2626', iconColor: '#dc2626' },
            warning: { confirm: '#d97706', iconColor: '#f59e0b' },
            question:{ confirm: '#2563eb', iconColor: '#3b82f6' },
            info:    { confirm: '#2563eb', iconColor: '#3b82f6' },
            success: { confirm: '#059669', iconColor: '#10b981' },
        };
        return map[type] || map.question;
    }

    function submitForm(form, loadingMessage) {
        localStorage.setItem(LS_KEY, '1');
        localStorage.setItem(LS_KEY + '_msg', loadingMessage || DEFAULT_LOADING_MSG);
        form.dataset.ckpnConfirmed = '1';

        // Form modul kalkulasi berat → fetch + polling persentase realtime
        if (isProgressForm(form)) {
            submitFormWithProgress(form, loadingMessage);
            return;
        }

        form.submit();
        // Overlay ditampilkan setelah submit agar tidak memblok native submit di beberapa browser
        setTimeout(() => showLoading(loadingMessage), 10);
    }

    // Form berat = punya atribut data-progress atau menuju modul kalkulasi CKPN
    function isProgressForm(form) {
        if (form.hasAttribute('data-progress')) return true;
        return (form.getAttribute('action') || '').includes('/calculation/module/');
    }

    function newProgressToken() {
        // Token acak kuat: UUID v4 bila tersedia, fallback crypto.getRandomValues.
        if (window.crypto && crypto.randomUUID) {
            return crypto.randomUUID().replace(/[^A-Za-z0-9_.\-]/g, '').slice(0, 60);
        }
        const bytes = new Uint8Array(20);
        (window.crypto || {}).getRandomValues ? crypto.getRandomValues(bytes) : bytes.set([Date.now() & 255]);
        return Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('').slice(0, 60);
    }

    function submitFormWithProgress(form, loadingMessage) {
        const token = newProgressToken();
        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'progress_token';
        hidden.value = token;
        form.appendChild(hidden);

        form.dataset.ckpnConfirmed = '1';
        showLoading(loadingMessage || DEFAULT_LOADING_MSG);
        showProgress('Memulai proses…', 0);

        let finished = false;
        const poll = setInterval(function () {
            fetch(PROGRESS_URL + '?token=' + encodeURIComponent(token), {
                headers: { 'Accept': 'application/json' },
            })
                .then((r) => r.json())
                .then((data) => {
                    if (!finished && data && data.status === 'running') {
                        showProgress(data.message, data.percentage);
                    }
                })
                .catch(function () { /* polling bersifat best-effort */ });
        }, 400);

        fetch(form.getAttribute('action'), {
            method: 'POST',
            body: new FormData(form),
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(async (r) => {
                clearInterval(poll);
                const ct = r.headers.get('content-type') || '';
                const isJson = ct.includes('application/json');
                const payload = isJson ? await r.json().catch(() => null) : null;

                // HTTP error (419 CSRF, 422 validasi, 500 server) → hentikan animasi & tampilkan error.
                if (!r.ok) {
                    finished = true;
                    hidden.remove();
                    const msg = (payload && (payload.error || payload.message)) ||
                        ('Proses gagal dijalankan (kode ' + r.status + '). Periksa log server untuk detail.');
                    showError(msg);
                    return;
                }

                // Server sukses tapi balas JSON berisi error eksplisit (dari backTo()).
                if (isJson && payload && payload.error) {
                    finished = true;
                    hidden.remove();
                    showError(payload.error);
                    return;
                }
                finished = true;
                showProgress('Selesai. Memuat hasil…', 100);

                if (isJson && payload && payload.redirect) {
                    // Bersihkan flag agar overlay TIDAK muncul lagi di halaman hasil.
                    localStorage.removeItem(LS_KEY);
                    window.location.href = payload.redirect;
                    return;
                }

                if (r.ok && r.redirected) {
                    // Server balas HTML (bukan JSON). Proses sudah jalan
                    // → cukup reload halaman; jangan submit ulang (risiko dobel proses).
                    localStorage.removeItem(LS_KEY);
                    window.location.href = r.url;
                    return;
                }

                hideLoading();
            })
            .catch(function () {
                // JANGAN submit ulang: bila koneksi putus SETELAH server memproses,
                // submit native akan menjalankan kalkulasi dua kali. Cukup hentikan
                // animasi dan minta user memuat ulang halaman secara manual.
                finished = true;
                clearInterval(poll);
                hidden.remove();
                localStorage.removeItem(LS_KEY);
                resetProgress();
                showError('Koneksi terputus saat proses berjalan. Muat ulang halaman untuk melihat status terkini — jangan jalankan ulang proses sebelum memastikan.');
            });
    }

    // Intersep submit global (capture) untuk SEMUA form POST
    document.addEventListener('submit', function (e) {
        const form = e.target;

        // Abaikan form import (punya progress sendiri) & non-POST
        if (form.dataset.noConfirm !== undefined || form.hasAttribute('data-no-confirm')) return;
        if ((form.method || '').toUpperCase() !== 'POST') return;
        if (form.classList.contains('import-form') || form.hasAttribute('data-import')) return;

        // Sudah dikonfirmasi / loading-only
        if (form.dataset.ckpnConfirmed === '1') return;

        // Form tanpa tombol submit (submit programatik murni) → hanya loading bila diminta
        const hasSubmitBtn = !!form.querySelector('button[type="submit"], input[type="submit"]');
        if (!hasSubmitBtn) return;

        e.preventDefault();

        const cfg = inferConfirmConfig(form);
        const theme = swalTheme(cfg.type);

        const detailHtml = cfg.detail
            ? `<div class="mt-3 rounded-lg bg-slate-50 border border-slate-200 px-3 py-2 text-xs text-slate-600">${escapeHtml(cfg.detail)}</div>`
            : '';
        const html = `${cfg.message ? `<p>${escapeHtml(cfg.message)}</p>` : ''}${detailHtml}`;

        // Hook supaya test/QA bisa mem-bypass dialog
        if (window.__ckpnSkipConfirm) { submitForm(form, cfg.loadingMessage); return; }

        Swal.fire({
            title: cfg.title,
            html: html,
            icon: cfg.type === 'danger' ? 'warning' : (cfg.type === 'question' ? 'question' : cfg.type),
            iconColor: theme.iconColor,
            showCancelButton: true,
            confirmButtonText: cfg.confirmText,
            cancelButtonText: 'Batal',
            confirmButtonColor: theme.confirm,
            cancelButtonColor: '#94a3b8',
            reverseButtons: true,
            focusCancel: cfg.type === 'danger',
            customClass: {
                popup: 'ckpn-swal-popup',
                title: 'ckpn-swal-title',
                htmlContainer: 'ckpn-swal-html',
                confirmButton: 'ckpn-swal-confirm',
                cancelButton: 'ckpn-swal-cancel',
            },
            buttonsStyling: true,
        }).then((result) => {
            if (result.isConfirmed) {
                submitForm(form, cfg.loadingMessage);
            }
        });
    }, true);

    // Bila halaman termuat ulang penuh (bukan via fetch), pastikan overlay ikut hilang saat load selesai tanpa redirect
    window.addEventListener('pageshow', function (e) {
        if (e.persisted) hideLoading();
    });

    window.ckpnShowLoading = showLoading;
    window.ckpnHideLoading = hideLoading;

    // ---------------------------------------------------------------------
    // Unifikasi: seluruh pemanggil lama window.showConfirmDialog(...) kini
    // diarahkan ke SweetAlert2 agar gaya dialog konsisten satu sistem.
    // API dipertahankan: { title, message, detail, type, confirmText, cancelText,
    //                      onConfirm, onCancel, loadingMessage }
    // ---------------------------------------------------------------------
    window.showConfirmDialog = function (options) {
        options = options || {};
        const type = options.type || 'question';
        const theme = swalTheme(type);

        const detailHtml = options.detail
            ? `<div class="mt-3 rounded-lg bg-slate-50 border border-slate-200 px-3 py-2 text-xs text-slate-600">${escapeHtml(options.detail)}</div>`
            : '';
        const html = `${options.message ? `<p>${escapeHtml(options.message)}</p>` : ''}${detailHtml}`;

        Swal.fire({
            title: options.title || 'Konfirmasi Tindakan',
            html: html,
            icon: type === 'danger' ? 'warning' : (type === 'question' ? 'question' : type),
            iconColor: theme.iconColor,
            showCancelButton: true,
            confirmButtonText: options.confirmText || 'Konfirmasi',
            cancelButtonText: options.cancelText || 'Batal',
            confirmButtonColor: theme.confirm,
            cancelButtonColor: '#94a3b8',
            reverseButtons: true,
            focusCancel: type === 'danger',
            customClass: {
                popup: 'ckpn-swal-popup',
                title: 'ckpn-swal-title',
                htmlContainer: 'ckpn-swal-html',
                confirmButton: 'ckpn-swal-confirm',
                cancelButton: 'ckpn-swal-cancel',
            },
        }).then((result) => {
            if (result.isConfirmed) {
                if (typeof options.onConfirm === 'function') {
                    if (options.loadingMessage) {
                        localStorage.setItem(LS_KEY, '1');
                        localStorage.setItem(LS_KEY + '_msg', options.loadingMessage);
                        showLoading(options.loadingMessage);
                    }
                    setTimeout(() => options.onConfirm(), 50);
                }
            } else if (typeof options.onCancel === 'function') {
                options.onCancel();
            }
        });
    };
})();
</script>
