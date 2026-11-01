<?php

use App\Http\Controllers\Admin\AstrophotoController as AdminAstrophotoController;
use App\Http\Controllers\Admin\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Admin\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Admin\CelestialObjectController;
use App\Http\Controllers\Admin\CelestialPreviewController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EnrichCelestialDistanceController;
use App\Http\Controllers\Admin\EquipmentSetupController;
use App\Http\Controllers\Admin\GenerateObjectDescriptionController;
use App\Http\Controllers\Admin\NewsletterSubscriberController;
use App\Http\Controllers\Admin\NightScopeForecastController;
use App\Http\Controllers\Admin\NightScopePlannerController;
use App\Http\Controllers\Admin\NightScopeTargetController;
use App\Http\Controllers\Admin\NightScopeTonightController;
use App\Http\Controllers\Admin\NightScopeWishlistController;
use App\Http\Controllers\Admin\ObservingElevationController;
use App\Http\Controllers\Admin\ObservingLocationController;
use App\Http\Controllers\Admin\SecurityController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SetupItemController;
use App\Http\Controllers\Admin\SyncAstrophotoDistancesController;
use App\Http\Controllers\Admin\TaskRunController;
use App\Http\Controllers\Admin\TranslateAstrophotoController;
use App\Http\Controllers\AstrophotoController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\ObservatoryController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\TimeMachineController;
use App\Http\Controllers\WebManifestController;
use App\Http\Controllers\WelcomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', WelcomeController::class)->name('welcome');
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('robots');
Route::get('/manifest.webmanifest', WebManifestController::class)->name('manifest');
Route::get('/sw.js', function () {
    return response(file_get_contents(public_path('sw.js')), 200, [
        'Content-Type' => 'application/javascript',
        'Cache-Control' => 'no-cache',
        'Service-Worker-Allowed' => '/',
    ]);
})->name('service-worker');

Route::get('/observatoire', ObservatoryController::class)->name('observatory');
Route::get('/remonter-le-temps', TimeMachineController::class)->name('time-machine');

Route::post('/astrophoto/{photo}/click', [AstrophotoController::class, 'recordView'])
    ->middleware('throttle:30,1')
    ->name('astrophoto.click');

Route::post('/astrophoto/{photo}/like', [AstrophotoController::class, 'toggleLike'])
    ->middleware('throttle:30,1')
    ->name('astrophoto.like');

Route::get('/astrophoto/{astrophoto}/full', [AstrophotoController::class, 'full'])
    ->name('astrophoto.full');

Route::get('/astrophoto/{astrophoto}', [AstrophotoController::class, 'show'])->name('astrophoto.show');

Route::post('/newsletter', [NewsletterController::class, 'subscribe'])
    ->middleware('throttle:10,1')
    ->name('newsletter.subscribe');

Route::get('/newsletter/confirmation/{token}', [NewsletterController::class, 'confirm'])
    ->middleware('throttle:10,1')
    ->name('newsletter.confirm');

Route::get('/newsletter/desinscription/{token}', [NewsletterController::class, 'confirmUnsubscribe'])
    ->middleware('throttle:30,1')
    ->name('newsletter.unsubscribe');

Route::post('/newsletter/desinscription/{token}', [NewsletterController::class, 'unsubscribe'])
    ->middleware('throttle:30,1')
    ->name('newsletter.unsubscribe.store');

Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::middleware('guest')->group(function (): void {
        Route::get('/connexion', [AuthenticatedSessionController::class, 'create'])->name('login');
        Route::post('/connexion', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
        Route::get('/connexion/verification', [TwoFactorChallengeController::class, 'create'])->name('two-factor.challenge');
        Route::post('/connexion/verification', [TwoFactorChallengeController::class, 'store'])->middleware('throttle:10,1')->name('two-factor.challenge.store');
    });

    Route::middleware(['auth', 'auth.session', 'admin'])->group(function (): void {
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::post('/deconnexion', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
        Route::resource('astrophotos', AdminAstrophotoController::class)->except('show');
        Route::post('/astrophotos/object-description', GenerateObjectDescriptionController::class)
            ->middleware('throttle:10,1')->name('astrophotos.object-description');
        Route::post('/astrophotos/translate', TranslateAstrophotoController::class)
            ->middleware('throttle:10,1')->name('astrophotos.translate');
        Route::patch('/astrophotos/{astrophoto}/publication', [AdminAstrophotoController::class, 'updatePublication'])->name('astrophotos.publication');
        Route::post('/astrophotos/{astrophoto}/solve', [AdminAstrophotoController::class, 'solve'])->name('astrophotos.solve');
        Route::post('/astrophotos/{astrophoto}/retry-conversions', [AdminAstrophotoController::class, 'retryConversions'])->name('astrophotos.retry-conversions');
        Route::post('/astrophotos/{astrophoto}/distances', SyncAstrophotoDistancesController::class)
            ->middleware('throttle:10,1')
            ->name('astrophotos.distances');
        Route::get('/astrophotos/{astrophoto}/processing-status', [AdminAstrophotoController::class, 'processingStatus'])->name('astrophotos.processing-status');
        Route::resource('equipment', SetupItemController::class)->except('show');
        Route::resource('equipment-setups', EquipmentSetupController::class)->except('show');
        Route::get('/observing-locations/elevation', ObservingElevationController::class)
            ->middleware('throttle:30,1')->name('observing-locations.elevation');
        Route::resource('observing-locations', ObservingLocationController::class)->except('show');
        Route::get('/nightscope/tonight', NightScopeTonightController::class)->name('nightscope.tonight');
        Route::get('/nightscope/wishlist', [NightScopeWishlistController::class, 'index'])->name('nightscope.wishlist');
        Route::put('/nightscope/wishlist/{celestialObject}', [NightScopeWishlistController::class, 'update'])->name('nightscope.wishlist.update');
        Route::delete('/nightscope/wishlist/{celestialObject}', [NightScopeWishlistController::class, 'destroy'])->name('nightscope.wishlist.destroy');
        Route::get('/nightscope/forecast', NightScopeForecastController::class)->name('nightscope.forecast');
        Route::get('/nightscope/planner', [NightScopePlannerController::class, 'index'])->name('nightscope.planner');
        Route::post('/nightscope/planner', [NightScopePlannerController::class, 'store'])->name('nightscope.planner.store');
        Route::post('/nightscope/planner/draft/exclude', [NightScopePlannerController::class, 'excludeDraftTarget'])->name('nightscope.planner.draft.exclude');
        Route::post('/nightscope/planner/draft/targets', [NightScopePlannerController::class, 'addDraftTarget'])->name('nightscope.planner.draft.targets');
        Route::post('/nightscope/planner/draft/reset', [NightScopePlannerController::class, 'resetDraft'])->name('nightscope.planner.draft.reset');
        Route::get('/nightscope/plans/{imagingPlan}', [NightScopePlannerController::class, 'show'])->name('nightscope.plans.show');
        Route::put('/nightscope/plans/{imagingPlan}', [NightScopePlannerController::class, 'update'])->name('nightscope.plans.update');
        Route::delete('/nightscope/plans/{imagingPlan}', [NightScopePlannerController::class, 'destroy'])->name('nightscope.plans.destroy');
        Route::post('/nightscope/plans/{imagingPlan}/duplicate', [NightScopePlannerController::class, 'duplicate'])->name('nightscope.plans.duplicate');
        Route::post('/nightscope/plans/{imagingPlan}/start', [NightScopePlannerController::class, 'start'])->name('nightscope.plans.start');
        Route::post('/nightscope/plans/{imagingPlan}/targets', [NightScopePlannerController::class, 'addTarget'])->name('nightscope.plans.targets.store');
        Route::put('/nightscope/plans/{imagingPlan}/targets/{imagingPlanTarget}', [NightScopePlannerController::class, 'updateTarget'])->name('nightscope.plans.targets.update');
        Route::post('/nightscope/plans/{imagingPlan}/targets/{imagingPlanTarget}/up', [NightScopePlannerController::class, 'moveTargetUp'])->name('nightscope.plans.targets.up');
        Route::post('/nightscope/plans/{imagingPlan}/targets/{imagingPlanTarget}/down', [NightScopePlannerController::class, 'moveTargetDown'])->name('nightscope.plans.targets.down');
        Route::delete('/nightscope/plans/{imagingPlan}/targets/{imagingPlanTarget}', [NightScopePlannerController::class, 'removeTarget'])->name('nightscope.plans.targets.destroy');
        Route::post('/nightscope/sessions/{imagingSession}/finish', [NightScopePlannerController::class, 'finish'])->name('nightscope.sessions.finish');
        Route::put('/nightscope/sessions/{imagingSession}', [NightScopePlannerController::class, 'updateSession'])->name('nightscope.sessions.update');
        Route::get('/nightscope/preview', CelestialPreviewController::class)
            ->middleware('throttle:60,1')
            ->name('nightscope.preview');
        Route::get('/nightscope/targets/{celestialObject}', NightScopeTargetController::class)->name('nightscope.targets.show');
        Route::post('/nightscope/targets/{celestialObject}/distance', EnrichCelestialDistanceController::class)
            ->middleware('throttle:20,1')
            ->name('nightscope.targets.distance');
        Route::get('/celestial-objects/search', [CelestialObjectController::class, 'search'])
            ->middleware('throttle:60,1')
            ->name('celestial-objects.search');
        Route::resource('celestial-objects', CelestialObjectController::class)->except('show');
        Route::resource('taches', TaskRunController::class)->only(['index', 'store'])->names('tasks');
        Route::get('/newsletter', [NewsletterSubscriberController::class, 'index'])->name('newsletter.index');
        Route::post('/newsletter/{subscriber}/resend', [NewsletterSubscriberController::class, 'resend'])->name('newsletter.resend');
        Route::delete('/newsletter/{subscriber}', [NewsletterSubscriberController::class, 'destroy'])->name('newsletter.destroy');
        Route::get('/parametres', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('/parametres', [SettingsController::class, 'update'])->name('settings.update');
        Route::put('/parametres/notifications', [SettingsController::class, 'updateNotifications'])->name('settings.notifications.update');
        Route::post('/parametres/test-smtp', [SettingsController::class, 'test'])->name('settings.test');
        Route::get('/securite', [SecurityController::class, 'edit'])->name('security.edit');
        Route::post('/securite/double-authentification', [SecurityController::class, 'enableTwoFactor'])->middleware('throttle:10,1')->name('security.two-factor.enable');
        Route::delete('/securite/double-authentification', [SecurityController::class, 'disableTwoFactor'])->middleware('throttle:10,1')->name('security.two-factor.disable');
        Route::post('/securite/sessions', [SecurityController::class, 'logoutOtherSessions'])->middleware('throttle:10,1')->name('security.sessions.destroy');
        Route::post('/securite/codes-de-secours', [SecurityController::class, 'regenerateRecoveryCodes'])->middleware('throttle:10,1')->name('security.recovery-codes');
        Route::post('/parametres/test-notifications', [SettingsController::class, 'testNotification'])->name('settings.notifications.test');
    });
});
