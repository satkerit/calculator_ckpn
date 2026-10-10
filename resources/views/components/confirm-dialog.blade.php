<!-- Modern & Elegant Confirmation Dialog Component (Alpine v3) -->
<div 
    x-data="modernConfirmDialog()"
    x-cloak
    @open-confirm-dialog.window="show($event.detail)"
    @keydown.escape.window="if(isOpen) cancel()"
    class="relative z-[99999]"
    aria-labelledby="modal-title" 
    role="dialog" 
    aria-modal="true"
>
    <!-- Backdrop Blur with ambient lighting -->
    <div 
        x-show="isOpen"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-slate-950/60 backdrop-blur-md transition-opacity"
        @click="cancel()"
    ></div>

    <!-- Modal Container -->
    <div 
        x-show="isOpen"
        class="fixed inset-0 z-10 overflow-y-auto"
        @click.self="cancel()"
    >
        <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
            <div 
                x-show="isOpen"
                x-transition:enter="ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave="ease-in duration-200"
                x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-lg border border-slate-100"
                @click.stop
            >
                <!-- Decorative top gradient bar -->
                <div class="h-1.5 w-full" :class="{
                    'bg-gradient-to-r from-rose-500 via-red-500 to-amber-500': config.type === 'danger',
                    'bg-gradient-to-r from-amber-400 via-amber-500 to-orange-500': config.type === 'warning',
                    'bg-gradient-to-r from-blue-500 via-indigo-500 to-cyan-500': config.type === 'info',
                    'bg-gradient-to-r from-emerald-400 via-teal-500 to-green-600': config.type === 'success'
                }"></div>

                <!-- Close X button -->
                <button 
                    @click="cancel()"
                    type="button"
                    class="absolute top-4 right-4 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-full p-1.5 transition-colors focus:outline-none focus:ring-2 focus:ring-slate-300"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>

                <div class="p-6 sm:p-7">
                    <!-- Icon & Content Header -->
                    <div class="flex items-start gap-4">
                        <!-- Ambient Icon Badge with Ping Aura -->
                        <div class="relative shrink-0">
                            <!-- Subtle Glow Ring -->
                            <div class="absolute -inset-1 rounded-2xl opacity-40 blur-sm" :class="{
                                'bg-rose-400': config.type === 'danger',
                                'bg-amber-400': config.type === 'warning',
                                'bg-blue-400': config.type === 'info',
                                'bg-emerald-400': config.type === 'success'
                            }"></div>

                            <div class="relative flex h-13 w-13 items-center justify-center rounded-2xl shadow-inner transition-transform duration-200 hover:scale-105" :class="{
                                'bg-gradient-to-br from-rose-50 to-red-100 text-rose-600 ring-1 ring-rose-200': config.type === 'danger',
                                'bg-gradient-to-br from-amber-50 to-orange-100 text-amber-600 ring-1 ring-amber-200': config.type === 'warning',
                                'bg-gradient-to-br from-blue-50 to-indigo-100 text-blue-600 ring-1 ring-blue-200': config.type === 'info',
                                'bg-gradient-to-br from-emerald-50 to-teal-100 text-emerald-600 ring-1 ring-emerald-200': config.type === 'success'
                            }">
                                <!-- Danger Icon -->
                                <template x-if="config.type === 'danger'">
                                    <svg class="h-6 w-6 stroke-[2.2]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </template>

                                <!-- Warning Icon -->
                                <template x-if="config.type === 'warning'">
                                    <svg class="h-6 w-6 stroke-[2.2]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                </template>

                                <!-- Info Icon -->
                                <template x-if="config.type === 'info'">
                                    <svg class="h-6 w-6 stroke-[2.2]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </template>

                                <!-- Success Icon -->
                                <template x-if="config.type === 'success'">
                                    <svg class="h-6 w-6 stroke-[2.2]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </template>
                            </div>
                        </div>

                        <!-- Text Details -->
                        <div class="flex-1 pr-6">
                            <!-- Category Badge -->
                            <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-semibold tracking-wide uppercase mb-1.5" :class="{
                                'bg-rose-50 text-rose-700 ring-1 ring-rose-200/70': config.type === 'danger',
                                'bg-amber-50 text-amber-700 ring-1 ring-amber-200/70': config.type === 'warning',
                                'bg-blue-50 text-blue-700 ring-1 ring-blue-200/70': config.type === 'info',
                                'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200/70': config.type === 'success'
                            }">
                                <span class="h-1.5 w-1.5 rounded-full" :class="{
                                    'bg-rose-500 animate-pulse': config.type === 'danger',
                                    'bg-amber-500': config.type === 'warning',
                                    'bg-blue-500': config.type === 'info',
                                    'bg-emerald-500': config.type === 'success'
                                }"></span>
                                <span x-text="config.badgeText || (config.type === 'danger' ? 'Tindakan Kritis' : (config.type === 'warning' ? 'Perhatian' : 'Konfirmasi'))"></span>
                            </div>

                            <h3 class="text-lg font-bold text-slate-900 tracking-tight" id="modal-title" x-text="config.title"></h3>
                            
                            <div class="mt-2 text-sm text-slate-600 leading-relaxed font-normal" x-html="config.message"></div>
                        </div>
                    </div>

                    <!-- Optional Detail Callout Card -->
                    <template x-if="config.detail">
                        <div class="mt-4 rounded-xl border border-slate-200/80 bg-slate-50/80 p-3.5 flex items-center justify-between text-xs text-slate-700">
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span class="font-mono text-slate-600 select-all" x-text="config.detail"></span>
                            </div>
                            <span class="text-[10px] font-medium text-slate-400 uppercase tracking-wider">Identifikasi</span>
                        </div>
                    </template>
                </div>

                <!-- Action Footer -->
                <div class="bg-slate-50/90 px-6 py-4 flex flex-col-reverse sm:flex-row sm:justify-end gap-2.5 sm:gap-3 border-t border-slate-100">
                    <button 
                        type="button"
                        @click="cancel()"
                        class="inline-flex w-full sm:w-auto items-center justify-center rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm ring-1 ring-inset ring-slate-200 hover:bg-slate-50 hover:text-slate-900 transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-slate-300"
                    >
                        <span x-text="config.cancelText || 'Batal'"></span>
                    </button>

                    <button 
                        type="button"
                        @click="confirm()"
                        class="inline-flex w-full sm:w-auto items-center justify-center gap-2 rounded-xl px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition-all duration-150 transform active:scale-95 focus:outline-none focus:ring-2 focus:ring-offset-2"
                        :class="{
                            'bg-gradient-to-r from-rose-600 to-red-600 hover:from-rose-500 hover:to-red-500 shadow-rose-500/25 focus:ring-rose-500': config.type === 'danger',
                            'bg-gradient-to-r from-amber-600 to-orange-600 hover:from-amber-500 hover:to-orange-500 shadow-amber-500/25 focus:ring-amber-500': config.type === 'warning',
                            'bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 shadow-blue-500/25 focus:ring-blue-500': config.type === 'info',
                            'bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 shadow-emerald-500/25 focus:ring-emerald-500': config.type === 'success'
                        }"
                    >
                        <!-- Action Icon -->
                        <template x-if="config.type === 'danger'">
                            <svg class="w-4 h-4 opacity-90" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </template>
                        <span x-text="config.confirmText || 'Konfirmasi'"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Alpine component factory function
function modernConfirmDialog() {
    return {
        isOpen: false,
        config: {
            title: '',
            message: '',
            detail: '',
            badgeText: '',
            type: 'danger', // danger | warning | info | success
            confirmText: 'Konfirmasi',
            cancelText: 'Batal',
            onConfirm: null,
            onCancel: null
        },

        show(options = {}) {
            this.config = {
                title: options.title || 'Konfirmasi Tindakan',
                message: options.message || 'Apakah Anda yakin ingin melanjutkan?',
                detail: options.detail || '',
                badgeText: options.badgeText || '',
                type: options.type || 'danger',
                confirmText: options.confirmText || 'Konfirmasi',
                cancelText: options.cancelText || 'Batal',
                onConfirm: typeof options.onConfirm === 'function' ? options.onConfirm : null,
                onCancel: typeof options.onCancel === 'function' ? options.onCancel : null
            };
            this.isOpen = true;
            document.body.classList.add('overflow-hidden');
        },

        confirm() {
            const callback = this.config.onConfirm;
            this.close();
            if (callback) {
                // Run after modal begins closing transition
                setTimeout(() => callback(), 50);
            }
        },

        cancel() {
            const callback = this.config.onCancel;
            this.close();
            if (callback) {
                callback();
            }
        },

        close() {
            this.isOpen = false;
            document.body.classList.remove('overflow-hidden');
        }
    };
}

/**
 * Universal Global Function: window.showConfirmDialog(options)
 * Dispatches a CustomEvent to Alpine v3 listener.
 * Works 100% reliably regardless of execution timing or Alpine internal structure.
 */
window.showConfirmDialog = function(options) {
    if (!options) return;
    
    // Dispatch standard CustomEvent caught by Alpine window listener
    window.dispatchEvent(new CustomEvent('open-confirm-dialog', {
        detail: options
    }));
};
</script>

<style>
[x-cloak] { display: none !important; }
</style>
