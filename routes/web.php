<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AssessmentPeriodController;
use App\Http\Controllers\OfficeController;
use App\Http\Controllers\CollateralController;
use App\Http\Controllers\CollateralSetupController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ReportingController;
use App\Http\Controllers\DataImportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SnapshotHistoryController;
use App\Http\Controllers\ProductAkadMappingController;
use App\Http\Controllers\AkadTypeController;
use App\Http\Controllers\Calculation\ConfigurationController;
use App\Http\Controllers\Calculation\ClassificationController;
use App\Http\Controllers\Calculation\PdNetFlowController;
use App\Http\Controllers\Calculation\PdNetflowDetailController;
use App\Http\Controllers\Calculation\PdMigrationController;
use App\Http\Controllers\Calculation\LgdExpectedRecoveryController;
use App\Http\Controllers\Calculation\LgdCollateralShortfallController;
use App\Http\Controllers\Calculation\LgdDetailController;
use App\Http\Controllers\Calculation\LgdWeightedController;
use App\Http\Controllers\Calculation\DetailPdEadController;
use App\Http\Controllers\Calculation\DetailCkpnController;
use App\Http\Controllers\Calculation\CkpnDetailController;
use App\Http\Controllers\Export\CalculationExportController;
use App\Http\Controllers\Export\FullCkpnExportController;
use App\Http\Controllers\BucketConfigurationController;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
});

Route::middleware(['auth'])->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Assessment Periods
    Route::resource('assessment', AssessmentPeriodController::class);
    Route::delete('/assessment-bulk-destroy', [AssessmentPeriodController::class, 'bulkDestroy'])->name('assessment.bulk-destroy');

    // Offices
    Route::resource('office', OfficeController::class)->middleware('permission:office.view');
    Route::delete('/office-bulk-destroy', [OfficeController::class, 'bulkDestroy'])
        ->middleware('permission:office.bulk-delete')->name('office.bulk-destroy');

    // Collateral & Collateral Setup
    Route::resource('collateral', CollateralController::class)->middleware('permission:collateral.view');
    Route::delete('/collateral-bulk-destroy', [CollateralController::class, 'bulkDestroy'])
        ->middleware('permission:collateral.bulk-delete')->name('collateral.bulk-destroy');
    Route::resource('collateral-setup', CollateralSetupController::class)->middleware('permission:collateral.view');
    Route::delete('/collateral-setup-bulk-destroy', [CollateralSetupController::class, 'bulkDestroy'])
        ->middleware('permission:collateral.delete')->name('collateral-setup.bulk-destroy');

    // Snapshot History
    Route::get('/snapshot-history', [SnapshotHistoryController::class, 'index'])->name('snapshot-history.index');
    Route::get('/snapshot-history/{id}', [SnapshotHistoryController::class, 'show'])->name('snapshot-history.show');
    Route::post('/snapshot-history/export', [SnapshotHistoryController::class, 'export'])->name('snapshot-history.export');

    // User Management Routes (read = user.view, tulis per-aksi)
    Route::get('/user', [UserController::class, 'index'])->middleware('permission:user.view')->name('user.index');
    Route::get('/user/{user}', [UserController::class, 'show'])->middleware('permission:user.view')->name('user.show');
    Route::middleware('permission:user.create')->group(function () {
        Route::get('/user/create', [UserController::class, 'create'])->name('user.create');
        Route::post('/user', [UserController::class, 'store'])->name('user.store');
    });
    Route::middleware('permission:user.edit')->group(function () {
        Route::get('/user/{user}/edit', [UserController::class, 'edit'])->name('user.edit');
        Route::put('/user/{user}', [UserController::class, 'update'])->name('user.update');
    });
    Route::delete('/user/{user}', [UserController::class, 'destroy'])
        ->middleware('permission:user.delete')->name('user.destroy');
    Route::delete('/user-bulk-destroy', [UserController::class, 'bulkDestroy'])
        ->middleware('permission:user.delete')->name('user.bulk-destroy');

    // Role Management Routes (read = role.view, tulis per-aksi)
    Route::get('/role', [RoleController::class, 'index'])->middleware('permission:role.view')->name('role.index');
    Route::get('/role/{role}', [RoleController::class, 'show'])->middleware('permission:role.view')->name('role.show');
    Route::middleware('permission:role.create')->group(function () {
        Route::get('/role/create', [RoleController::class, 'create'])->name('role.create');
        Route::post('/role', [RoleController::class, 'store'])->name('role.store');
    });
    Route::middleware('permission:role.edit')->group(function () {
        Route::get('/role/{role}/edit', [RoleController::class, 'edit'])->name('role.edit');
        Route::put('/role/{role}', [RoleController::class, 'update'])->name('role.update');
    });
    Route::delete('/role/{role}', [RoleController::class, 'destroy'])
        ->middleware('permission:role.delete')->name('role.destroy');
    Route::delete('/role-bulk-destroy', [RoleController::class, 'bulkDestroy'])
        ->middleware('permission:role.delete')->name('role.bulk-destroy');

    // Akad Type
    Route::resource('akad-type', AkadTypeController::class)->middleware('permission:financing.view');
    Route::delete('/akad-type-bulk-destroy', [AkadTypeController::class, 'bulkDestroy'])
        ->middleware('permission:financing.delete')->name('akad-type.bulk-destroy');
    Route::post('/akad-type-bulk-store', [AkadTypeController::class, 'bulkStore'])
        ->middleware('permission:financing.create')->name('akad-type.bulk-store');
    Route::put('/akad-type-bulk-update', [AkadTypeController::class, 'bulkUpdate'])
        ->middleware('permission:financing.edit')->name('akad-type.bulk-update');
    Route::get('/import/akad-type', [AkadTypeController::class, 'importForm'])
        ->middleware('permission:financing.import')->name('import.akad-type.form');
    Route::post('/import/akad-type', [AkadTypeController::class, 'uploadImport'])
        ->middleware('permission:financing.import')->name('import.akad-type.upload');

    // Product Akad Mapping
    Route::resource('product-akad', ProductAkadMappingController::class)->middleware('permission:financing.view');
    Route::delete('/product-akad-bulk-destroy', [ProductAkadMappingController::class, 'bulkDestroy'])
        ->middleware('permission:financing.delete')->name('product-akad.bulk-destroy');
    Route::get('/api/product-akad/resolve', [ProductAkadMappingController::class, 'resolve'])->name('product-akad.resolve');
    Route::get('/import/product-akad', [ProductAkadMappingController::class, 'importForm'])
        ->middleware('permission:financing.import')->name('import.product-akad.form');
    Route::post('/import/product-akad', [ProductAkadMappingController::class, 'uploadImport'])
        ->middleware('permission:financing.import')->name('import.product-akad.upload');

    // CKPN Calculation — 8 Modul
    // Polling progress realtime untuk proses kalkulasi berat (dipakai global).
    Route::get('calculation/progress', function (\Illuminate\Http\Request $request) {
        $token = (string) $request->query('token', '');

        if ($token === '' || ! preg_match('/^[A-Za-z0-9_.\-]{8,80}$/', $token)) {
            return response()->json(['status' => 'idle']);
        }

        $progress = \Illuminate\Support\Facades\Cache::store(config('calculation.progress_store', 'file'))->get("ckpn_progress_{$token}");

        return response()->json($progress ? ['status' => 'running'] + $progress : ['status' => 'idle']);
    })->name('calculation.progress');

    Route::prefix('calculation/module')->name('calculation.module.')->group(function () {
        Route::get('/config',         [ConfigurationController::class, 'index'])->name('config');
        Route::post('/config',        [ConfigurationController::class, 'save'])
            ->middleware('permission:calculation.run')->name('config.save');

        Route::get('/classification', [ClassificationController::class, 'index'])->name('classification');
        Route::post('/classification',[ClassificationController::class, 'run'])
            ->middleware('permission:calculation.run')->name('classification.run');

        Route::get('/pd-netflow',     [PdNetFlowController::class, 'index'])->name('pd_netflow');
        Route::post('/pd-netflow',    [PdNetFlowController::class, 'run'])
            ->middleware('permission:calculation.run')->name('pd_netflow.run');
        Route::get('/pd-netflow/{assessmentPeriod}/detail', [PdNetflowDetailController::class, 'show'])->name('pd_netflow.detail');

        Route::get('/pd-migration',   [PdMigrationController::class, 'index'])->name('pd_migration');
        Route::post('/pd-migration',  [PdMigrationController::class, 'run'])
            ->middleware('permission:calculation.run')->name('pd_migration.run');

        Route::get('/lgd-er',         [LgdExpectedRecoveryController::class, 'index'])->name('lgd_er');
        Route::post('/lgd-er',        [LgdExpectedRecoveryController::class, 'run'])
            ->middleware('permission:calculation.run')->name('lgd_er.run');

        Route::get('/lgd-cs',         [LgdCollateralShortfallController::class, 'index'])->name('lgd_cs');
        Route::post('/lgd-cs',        [LgdCollateralShortfallController::class, 'run'])
            ->middleware('permission:calculation.run')->name('lgd_cs.run');

        Route::get('/lgd-detail/{method}', [LgdDetailController::class, 'show'])->name('lgd_detail');

        Route::get('/lgd-weighted',   [LgdWeightedController::class, 'index'])->name('lgd_weighted');

        Route::get('/detail-pd-ead',  [DetailPdEadController::class, 'index'])->name('detail_pd_ead');

        Route::get('/detail-ckpn',    [DetailCkpnController::class, 'index'])->name('detail_ckpn');
        Route::post('/detail-ckpn',   [DetailCkpnController::class, 'finalize'])
            ->middleware('permission:calculation.run')->name('detail_ckpn.finalize');
    });

    // Bucket Configuration Routes
    Route::resource('bucket', BucketConfigurationController::class)->middleware('permission:calculation.view');
    Route::post('bucket/{bucket}/restore', [BucketConfigurationController::class, 'restore'])
        ->middleware('permission:calculation.run')->name('bucket.restore');
    Route::delete('bucket-bulk-destroy', [BucketConfigurationController::class, 'bulkDestroy'])
        ->middleware('permission:calculation.run')->name('bucket.bulk-destroy');
    Route::post('bucket-bulk-restore', [BucketConfigurationController::class, 'bulkRestore'])
        ->middleware('permission:calculation.run')->name('bucket.bulk-restore');

    // Tampilan hasil CKPN per komponen (Individual & Kolektif)
    Route::prefix('calculation/ckpn')->name('calculation.ckpn.')->group(function () {
        Route::get('/individual', [CkpnDetailController::class, 'individual'])
            ->middleware('permission:calculation.view')->name('individual');
        Route::get('/collective', [CkpnDetailController::class, 'collective'])
            ->middleware('permission:calculation.view')->name('collective');
    });

    // Export XLSX per modul + Backtest Bundle + Full Multi-Sheet Workbook
    // Literal segments first agar tidak match oleh `{module}/{assessmentPeriod}`
    Route::prefix('calculation/export')->name('calculation.export.')
        ->middleware(['permission:reporting.export', 'throttle:20,1'])
        ->group(function () {
        Route::get('/full/{assessmentPeriod}', [FullCkpnExportController::class, 'export'])->name('full.period');
        Route::get('/full', [FullCkpnExportController::class, 'export'])->name('full');
        Route::get('/pd-netflow-backtest/{assessmentPeriod}', [CalculationExportController::class, 'pdNetflowBacktest'])->name('pd_netflow_backtest.period');
        Route::get('/pd-migration-matrix/{assessmentPeriod}', [CalculationExportController::class, 'pdMigrationMatrixDetail'])->name('pd_migration_matrix.period');
        Route::get('/{module}/{assessmentPeriod}', [CalculationExportController::class, 'module'])->name('module.period');
        Route::get('/module/{module}', [CalculationExportController::class, 'moduleByName'])->name('module');
        Route::get('/backtest/{assessmentPeriod}', [CalculationExportController::class, 'backtestBundle'])->name('backtest.period');
        Route::get('/backtest', [CalculationExportController::class, 'backtestBundleByName'])->name('backtest');
    });

    // Reporting Routes
    Route::get('/reporting/ckpn-summary', [ReportingController::class, 'ckpnSummary'])
        ->middleware('permission:reporting.view')->name('reporting.ckpn-summary');
    Route::get('/reporting/risk-matrix', [ReportingController::class, 'riskMatrix'])
        ->middleware('permission:reporting.view')->name('reporting.risk-matrix');
    Route::get('/reporting/aging-analysis', [ReportingController::class, 'agingAnalysis'])
        ->middleware('permission:reporting.view')->name('reporting.aging-analysis');
    Route::get('/reporting/export/{type}', [ReportingController::class, 'exportReport'])
        ->middleware(['permission:reporting.export', 'throttle:20,1'])->name('reporting.export-report');

    // Data Import
    Route::middleware('permission:financing.import')->group(function () {
        Route::get('/import/snapshot', [DataImportController::class, 'snapshotImportForm'])->name('import.snapshot.form');
        Route::post('/import/snapshot', [DataImportController::class, 'uploadSnapshot'])
            ->middleware('throttle:10,1')->name('import.snapshot.upload');
        Route::get('/import/collateral', [DataImportController::class, 'collateralImportForm'])->name('import.collateral.form');
        Route::post('/import/collateral', [DataImportController::class, 'uploadCollateral'])
            ->middleware('throttle:10,1')->name('import.collateral.upload');
        Route::get('/import/office', [DataImportController::class, 'officeImportForm'])->name('import.office.form');
        Route::post('/import/office', [DataImportController::class, 'uploadOffice'])
            ->middleware('throttle:10,1')->name('import.office.upload');
        Route::get('/import/collateral-setup', [DataImportController::class, 'collateralSetupImportForm'])->name('import.collateral_setup.form');
        Route::post('/import/collateral-setup', [DataImportController::class, 'uploadCollateralSetup'])
            ->middleware('throttle:10,1')->name('import.collateral_setup.upload');
    });
    Route::get('/template/download/{type}', [DataImportController::class, 'downloadTemplate'])->name('template.download');
});

require __DIR__.'/auth.php';
