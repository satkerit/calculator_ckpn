@extends('layouts.app')

@section('title', 'Impor Snapshot Data - CKPN Calculator')

@section('content')
<div class="max-w-4xl mx-auto">
    <h1 class="text-3xl font-bold mb-2">Impor Snapshot Historis</h1>
    <p class="text-gray-600 mb-8">Unggah file Excel dengan data historis pembiayaan</p>

    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-8">
        <div class="bg-white rounded-lg p-4 border border-gray-200">
            <p class="text-gray-500 text-sm">Format Wajib</p>
            <p class="text-lg font-bold text-gray-900">.xlsx / .xls / .csv</p>
        </div>
        <div class="bg-white rounded-lg p-4 border border-gray-200">
            <p class="text-gray-500 text-sm">Kolom Baru</p>
            <p class="text-lg font-bold text-gray-900">usage_type, tglwo</p>
        </div>
        <div class="bg-white rounded-lg p-4 border border-gray-200">
            <p class="text-gray-500 text-sm">Format Tglwo</p>
            <p class="text-lg font-bold text-gray-900">yyyymmdd (20240101)</p>
        </div>
        <div class="bg-white rounded-lg p-4 border border-gray-200">
            <p class="text-gray-500 text-sm">Write-off Flag</p>
            <p class="text-lg font-bold text-gray-900">'W' jika Write-off</p>
        </div>
        <div class="bg-white rounded-lg p-4 border border-gray-200">
            <p class="text-gray-500 text-sm">Pemrosesan</p>
            <p class="text-lg font-bold text-gray-900">Realtime SSE</p>
        </div>
    </div>

    <div id="uploadContainer" class="bg-white rounded-lg shadow-lg p-8">
        <form id="uploadForm" class="space-y-6" enctype="multipart/form-data">
            @csrf

            <div id="dropZone" class="border-2 border-dashed border-gray-300 rounded-lg p-12 text-center cursor-pointer hover:border-blue-500 hover:bg-blue-50 transition-all focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2" role="button" tabindex="0" onclick="document.getElementById('fileInput').click()" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();document.getElementById('fileInput').click();}">
                <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                    <path d="M28 8H12a4 4 0 00-4 4v20a4 4 0 004 4h24a4 4 0 004-4V16a4 4 0 00-4-4zm0-4l8 8M12 24l8-8m0 0l-8-8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <h3 class="text-lg font-semibold text-gray-700 mb-2">Tarik & Lepas File di Sini</h3>
                <p class="text-gray-500 mb-4">atau klik untuk memilih file</p>
                <input type="file" id="fileInput" name="file" class="hidden" accept=".csv,.txt,.xlsx,.xls" required>
                <p class="text-xs text-gray-400">Didukung: .csv (Direkomendasikan untuk >50rb baris), .xlsx, .xls</p>
            </div>

            <div id="fileInfo" class="hidden bg-gray-50 rounded-lg p-4 flex justify-between items-center">
                <div class="flex items-center gap-3">
                    <svg class="h-8 w-8 text-emerald-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 112 8z" clip-rule="evenodd"/>
                    </svg>
                    <div>
                        <p id="fileName" class="font-semibold text-gray-700"></p>
                        <p id="fileSize" class="text-xs text-gray-500"></p>
                    </div>
                </div>
                <button type="button" onclick="resetForm()" class="text-red-600 hover:text-red-700 text-sm">Hapus</button>
            </div>

            <!-- Submit Button -->
            <div class="flex flex-wrap gap-3">
                <button type="submit" id="submitBtn" class="flex-1 min-w-[200px] inline-flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-semibold shadow-sm hover:shadow transition-all disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    Mulai Unggah
                </button>
                <a href="{{ route('template.download', 'snapshot') }}" class="inline-flex items-center justify-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white px-6 py-3 rounded-lg font-semibold shadow-sm hover:shadow transition-all">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Unduh Template
                </a>
                <a href="{{ route('snapshot-history.index') }}" class="inline-flex items-center justify-center gap-2 bg-gray-500 hover:bg-gray-600 text-white px-6 py-3 rounded-lg font-semibold shadow-sm hover:shadow transition-all">
                    Batal
                </a>
            </div>
        </form>
    </div>

    <div id="progressContainer" class="hidden mt-8 bg-white rounded-lg shadow-lg p-8">
        <h2 class="text-xl font-bold mb-6">Progres Unggah</h2>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
            <div class="bg-blue-50 rounded-lg p-4">
                <p class="text-gray-600 text-sm">Diproses</p>
                <p id="processedCount" class="text-2xl font-bold text-blue-600 font-mono">0</p>
            </div>
            <div class="bg-emerald-50 rounded-lg p-4">
                <p class="text-gray-600 text-sm">Berhasil</p>
                <p id="successCount" class="text-2xl font-bold text-emerald-600 font-mono">0</p>
            </div>
            <div class="bg-red-50 rounded-lg p-4">
                <p class="text-gray-600 text-sm">Gagal</p>
                <p id="failedCount" class="text-2xl font-bold text-red-600 font-mono">0</p>
            </div>
        </div>

        <div class="text-center mb-6">
            <p id="percentageText" class="text-4xl font-bold text-blue-600 font-mono mb-2">0%</p>
            <p id="statusMessage" class="text-gray-600">Menginisialisasi...</p>
        </div>

        <div class="mb-8">
            <div class="relative h-3 bg-gray-200 rounded-full overflow-hidden">
                <div id="progressBar" class="h-full bg-blue-600 rounded-full transition-all duration-300 ease-out" style="width: 0%"></div>
            </div>
            <div class="flex justify-between text-xs text-gray-500 mt-2">
                <span>Progres</span>
                <span id="progressText">0 / 0</span>
            </div>
        </div>

        <div id="successMessage" class="hidden mt-6 bg-emerald-50 rounded-lg p-4 border border-emerald-200">
            <p id="successText" class="text-emerald-700 font-semibold mb-3"></p>
            <div class="flex gap-3">
                <a href="{{ route('calculation.module.config') }}" class="bg-emerald-600 text-white px-4 py-2 rounded hover:bg-emerald-700">Jalankan Perhitungan</a>
                <button type="button" onclick="resetForm()" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">Impor Lagi</button>
            </div>
        </div>

        <!-- Real-time Log Panel -->
        <div id="logPanel" class="mt-6 bg-gray-50 rounded-lg border border-gray-200">
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 bg-gray-100 rounded-t-lg">
                <h3 class="font-semibold text-gray-700 flex items-center gap-2">
                    <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Log Impor
                </h3>
                <button type="button" onclick="clearLog()" class="text-xs text-gray-600 hover:text-gray-800 px-2 py-1 rounded hover:bg-gray-200">Bersihkan</button>
            </div>
            <div id="logContainer" class="p-4 max-h-96 overflow-y-auto font-mono text-xs space-y-1">
                <p class="text-gray-400 italic">Log akan muncul di sini saat upload berjalan...</p>
            </div>
        </div>
    </div>
</div>

<script>
const fileInput = document.getElementById('fileInput');
const uploadForm = document.getElementById('uploadForm');
const uploadContainer = document.getElementById('uploadContainer');
const progressContainer = document.getElementById('progressContainer');
const progressBar = document.getElementById('progressBar');
const percentageText = document.getElementById('percentageText');
const statusMessage = document.getElementById('statusMessage');
const successMessage = document.getElementById('successMessage');
const successText = document.getElementById('successText');
const logContainer = document.getElementById('logContainer');

let abortController = null;

function addLog(message, type = 'info') {
    const timestamp = new Date().toLocaleTimeString('id-ID');
    const colors = {
        info: 'text-blue-600',
        success: 'text-emerald-600',
        error: 'text-red-600',
        warning: 'text-amber-600'
    };
    const icons = {
        info: '[INFO]',
        success: '[OK]',
        error: '[ERROR]',
        warning: '[PERINGATAN]'
    };
    
    if (logContainer.querySelector('.text-gray-400')) {
        logContainer.innerHTML = '';
    }
    
    const logEntry = document.createElement('div');
    logEntry.className = `${colors[type]} py-1`;
    const timeSpan = document.createElement('span');
    timeSpan.className = 'text-gray-500';
    timeSpan.textContent = `[${timestamp}]`;
    logEntry.append(timeSpan, ` ${icons[type]} ${message}`);
    logContainer.appendChild(logEntry);
    logContainer.scrollTop = logContainer.scrollHeight;
}

function clearLog() {
    logContainer.innerHTML = '<p class="text-gray-400 italic">Log dibersihkan.</p>';
}

document.getElementById('dropZone').addEventListener('dragover', (e) => {
    e.preventDefault();
    e.currentTarget.classList.add('border-blue-500', 'bg-blue-50');
});

document.getElementById('dropZone').addEventListener('dragleave', (e) => {
    e.currentTarget.classList.remove('border-blue-500', 'bg-blue-50');
});

document.getElementById('dropZone').addEventListener('drop', (e) => {
    e.preventDefault();
    e.currentTarget.classList.remove('border-blue-500', 'bg-blue-50');
    fileInput.files = e.dataTransfer.files;
    updateFileInfo();
});

fileInput.addEventListener('change', updateFileInfo);

function updateFileInfo() {
    const file = fileInput.files[0];
    if (file) {
        document.getElementById('fileInfo').classList.remove('hidden');
        document.getElementById('fileName').textContent = file.name;
        document.getElementById('fileSize').textContent = (file.size / 1024).toFixed(2) + ' KB';
    }
}

function resetForm() {
    fileInput.value = '';
    document.getElementById('fileInfo').classList.add('hidden');
    uploadContainer.classList.remove('hidden');
    progressContainer.classList.add('hidden');
    successMessage.classList.add('hidden');
    progressBar.style.width = '0%';
    percentageText.textContent = '0%';
    clearLog();
    if (abortController) abortController.abort();
}

// Form submission - fetch POST + ReadableStream SSE parsing
uploadForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    if (!fileInput.files[0]) {
        alert('Silakan pilih file terlebih dahulu');
        return;
    }

    uploadContainer.classList.add('hidden');
    progressContainer.classList.remove('hidden');
    document.getElementById('submitBtn').disabled = true;
    clearLog();
    addLog('Unggah dimulai...', 'info');

    abortController = new AbortController();

    const formData = new FormData();
    formData.append('file', fileInput.files[0]);
    formData.append('_token', document.querySelector('input[name="_token"]').value);

    try {
        addLog('Mengirim file ke server...', 'info');
        const response = await fetch('{{ route("import.snapshot.upload") }}', {
            method: 'POST',
            body: formData,
            headers: {
                'Accept': 'text/event-stream',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
            signal: abortController.signal,
        });

        if (!response.ok) throw new Error(`HTTP ${response.status}`);

        addLog('Koneksi berhasil, membaca data...', 'success');

        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';

        while (true) {
            const { done, value } = await reader.read();
            if (done) break;

            buffer += decoder.decode(value, { stream: true });
            const lines = buffer.split('\n\n');
            buffer = lines.pop();

            for (const chunk of lines) {
                const match = chunk.match(/^data:\s*(.+)$/m);
                if (!match) continue;
                const data = JSON.parse(match[1]);

                if (data.status === 'starting') {
                    addLog(data.message, 'info');
                } else if (data.status === 'log') {
                    addLog(data.message, data.type || 'info');
                } else if (data.status === 'progress') {
                    const percentage = Math.round(data.percentage);
                    progressBar.style.width = percentage + '%';
                    percentageText.textContent = percentage + '%';
                    statusMessage.textContent = data.message;
                    document.getElementById('processedCount').textContent = data.current;
                    document.getElementById('progressText').textContent = `${data.current} / ${data.total}`;
                    
                    if (data.current % 100 === 0) {
                        addLog(`Diproses ${data.current} baris...`, 'info');
                    }
                } else if (data.status === 'complete') {
                    progressBar.style.width = '100%';
                    percentageText.textContent = '100%';
                    statusMessage.textContent = 'Selesai!';
                    document.getElementById('successCount').textContent = data.imported;
                    document.getElementById('failedCount').textContent = data.failed;
                    
                    addLog(`Unggah selesai: ${data.imported} berhasil, ${data.failed} gagal`, data.failed > 0 ? 'warning' : 'success');
                    
                    if (data.errors && data.errors.length > 0) {
                        addLog(`Menampilkan ${data.errors.length} error pertama:`, 'warning');
                        data.errors.slice(0, 50).forEach(err => {
                            addLog(`Baris ${err.row}: ${err.error}`, 'error');
                        });
                        if (data.errors.length > 50) {
                            addLog(`... dan ${data.errors.length - 50} error lainnya (cek log server)`, 'warning');
                        }
                    }
                    
                    successMessage.classList.remove('hidden');
                    successText.textContent = data.failed > 0 
                        ? `Impor selesai dengan ${data.failed} kesalahan. ${data.imported} data berhasil diimpor.`
                        : `Impor berhasil! ${data.imported} data snapshot diimpor.`;
                    document.getElementById('submitBtn').disabled = false;
                } else if (data.status === 'error') {
                    addLog(`ERROR: ${data.message}`, 'error');
                    statusMessage.textContent = 'Error: ' + data.message;
                    document.getElementById('submitBtn').disabled = false;
                }
            }
        }
    } catch (err) {
        if (err.name === 'AbortError') {
            addLog('Unggah dibatalkan', 'warning');
            return;
        }
        addLog(`ERROR: ${err.message}`, 'error');
        statusMessage.textContent = 'Terjadi kesalahan saat mengunggah: ' + err.message;
        document.getElementById('submitBtn').disabled = false;
    }
});
</script>
@endsection
