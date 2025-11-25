<?php

use App\Http\Controllers\Api\Agence\AgenceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TarifController;
use App\Http\Controllers\Api\Client\ColisController;
use App\Http\Controllers\Api\TarificationController;
use App\Http\Controllers\Api\Livreur\MissionController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Agence\AgenceUserController;
use App\Http\Controllers\Api\Agence\AgenceNotificationController;
use App\Http\Controllers\Api\Agence\AgenceTarifController;
use App\Http\Controllers\Api\Agence\AgenceTarifGroupageController;
use App\Http\Controllers\Api\Backoffice\TarifSimpleController;
use App\Http\Controllers\Api\Backoffice\TarifGroupageController;
use App\Http\Controllers\Api\Backoffice\ProduitsController;
use App\Http\Controllers\Api\Backoffice\CategoryProductController;
use App\Http\Controllers\Api\Backoffice\ZoneController;
use App\Http\Controllers\Api\Backoffice\BackofficeController;
use App\Http\Controllers\Api\Backoffice\BackofficeUserController;
use App\Http\Controllers\Api\Agence\AgenceExpeditionController;
use App\Http\Controllers\Api\Client\ClientExpeditionController;
use App\Http\Controllers\Api\ExpeditionArticleController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
 * |--------------------------------------------------------------------------
 * | API Routes
 * |--------------------------------------------------------------------------
 * |
 * | Here is where you can register API routes for your application. These
 * | routes are loaded by the RouteServiceProvider and all of them will
 * | be assigned to the "api" middleware group. Make something great!
 * |
 */

// Routes publiques (sans authentification)
Route::get('/test-cors', function (Request $request) {
    return response()->json([
        'success' => true,
        'message' => 'CORS fonctionne !',
        'timestamp' => now(),
        'origin' => $request->header('Origin'),
        'method' => $request->method(),
        'headers' => $request->headers->all()
    ]);
});

Route::post('/register', [AuthController::class, 'register']); // Inscription
Route::post('/login', [AuthController::class, 'login']); // Connexion

// Routes protégées par l'authentification (nécessitent un jeton API valide)
Route::middleware('auth:sanctum')->group(function () {
    // Routes d'authentification protégées
    Route::post('/logout', [AuthController::class, 'logout']); // Déconnexion
    Route::get('/profil', [AuthController::class, 'profile']); // Profil de l'utilisateur connecté

    // Routes clients
    /*Route::prefix('client')->group(function () {
        Route::get('/colis', [ColisController::class, 'index']);
        Route::post('/colis', [ColisController::class, 'store']);
        Route::get('/colis/{id}', [ColisController::class, 'show']);
        Route::post('/colis/{id}/annuler', [ColisController::class, 'annuler']);
        Route::get('/suivre/{codesuivi}', [ColisController::class, 'suivre']);
        Route::get('/colis/search-destinataires', [ColisController::class, 'searchDestinataires']);
        Route::post('/tarification/simuler', [TarificationController::class, 'simuler']);
        Route::get('/agences-proches', [TarificationController::class, 'agencesProches']);
    });*/

    // Routes livreurs
    /*Route::prefix('livreur')->group(function () {
        Route::get('/dashboard', [MissionController::class, 'dashboard']);
        Route::get('/missions-disponibles', [MissionController::class, 'missionsDisponibles']);
        Route::post('/missions/{colis}/accepter', [MissionController::class, 'accepterMission']);
        Route::get('/mes-missions', [MissionController::class, 'mesMissions']);
        Route::post('/missions/{colis}/confirmer-enlevement', [MissionController::class, 'confirmerEnlevement']);
        Route::post('/missions/{colis}/confirmer-livraison', [MissionController::class, 'confirmerLivraison']);
        Route::post('/disponibilite', [MissionController::class, 'changerDisponibilite']);
    });*/

    // Tableau de bord et statistiques
    /*Route::get('/dashboard', [AgenceController::class, 'dashboard']);  // Tableau de bord avec statistiques
    Route::get('/statistiques', [AgenceController::class, 'statistiques']);  // Statistiques détaillées
    Route::get('/livreurs-disponibles', [AgenceController::class, 'livreursDisponibles']);  // Liste des livreurs

    // Application Agence: workflow opérationnel
    Route::get('/expeditions', [ExpeditionController::class, 'colis']);
    Route::get('/expeditions/recherche', [ExpeditionController::class, 'rechercheColis']);  // Recherche avancée
    Route::get('/expeditions/{colis}', [ExpeditionController::class, 'detailsColis']);  // Détails d'un colis
    Route::post('/expeditions/{colis}/accepter', [ExpeditionController::class, 'accepter']);
    Route::post('/expeditions/{colis}/refuser', [ExpeditionController::class, 'refuser']);
    Route::post('/expeditions/{colis}/assign-livreur', [ExpeditionController::class, 'assignerLivreur']);
    Route::post('/expeditions/{colis}/statut', [ExpeditionController::class, 'changerStatut']);
    Route::post('/expeditions/{colis}/preuves', [ExpeditionController::class, 'ajouterPreuves']);
    Route::post('/expeditions/{colis}/verifier', [ExpeditionController::class, 'verifier']);


    // Notifications de l'agence
    Route::get('/notifications', [AgenceNotificationController::class, 'index']);
    Route::put('/notifications/{notificationId}/lue', [AgenceNotificationController::class, 'marquerLue']);
    Route::put('/notifications/toutes-lues', [AgenceNotificationController::class, 'marquerToutesLues']);
    Route::delete('/notifications/{notificationId}', [AgenceNotificationController::class, 'supprimer']);*/

    // Routes agences
    Route::prefix('agence')->group(function () {
        Route::get('/list', [AgenceController::class, 'listAgences']);
        Route::post('/setup', [AgenceController::class, 'setupAgence']);
        Route::get('/show', [AgenceController::class, 'showAgence']);
        Route::put('/update', [AgenceController::class, 'updateAgence']);

        // Gestion des utilisateurs de l'agence (réservé à l'admin créateur)
        Route::get('/list-users', [AgenceUserController::class, 'listUsers']);
        Route::post('/create-user', [AgenceUserController::class, 'createUser']);
        Route::get('/show-user/{user}', [AgenceUserController::class, 'showUser']);
        Route::put('/edit-user/{user}', [AgenceUserController::class, 'editUser']);
        Route::put('/status-user/{user}', [AgenceUserController::class, 'toggleStatusUser']);
        Route::delete('/delete-user/{user}', [AgenceUserController::class, 'deleteUser']);

        // Gestion des tarifs simple de l'agence
        Route::get('/list-tarifs', [AgenceTarifController::class, 'list']);
        Route::post('/add-tarif-simple', [AgenceTarifController::class, 'add']);
        Route::put('/edit-tarif-simple/{tarif}', [AgenceTarifController::class, 'edit']);
        Route::get('/show-tarif/{tarif}', [AgenceTarifController::class, 'show']);
        Route::delete('/delete-tarif/{tarif}', [AgenceTarifController::class, 'delete']);
        Route::put('/status-tarif/{tarif}', [AgenceTarifController::class, 'toggleStatus']);

        // Gestion des tarifs groupage de l'agence
        Route::get('/list-tarifs-groupage', [AgenceTarifGroupageController::class, 'list']);
        Route::post('/add-tarif-groupage', [AgenceTarifGroupageController::class, 'add']);
        Route::put('/edit-tarif-groupage/{tarif}', [AgenceTarifGroupageController::class, 'edit']);
        Route::get('/show-tarif-groupage/{tarif}', [AgenceTarifGroupageController::class, 'show']);
        Route::delete('/delete-tarif-groupage/{tarif}', [AgenceTarifGroupageController::class, 'delete']);
        Route::put('/status-tarif-groupage/{tarif}', [AgenceTarifGroupageController::class, 'toggleStatus']);

        // Gestion des expéditions de l'agence
        Route::get('/expeditions', [AgenceExpeditionController::class, 'list']);
        Route::post('/expeditions', [AgenceExpeditionController::class, 'create']);
        Route::get('/expeditions/{id}', [AgenceExpeditionController::class, 'show']);
        Route::put('/expeditions/{id}/accept', [AgenceExpeditionController::class, 'accept']);
        Route::put('/expeditions/{id}/refuse', [AgenceExpeditionController::class, 'refuse']);
        Route::put('/expeditions/{id}/status', [AgenceExpeditionController::class, 'updateStatus']);

        // Gestion des articles d'expédition
        Route::get('/expeditions/{expeditionId}/articles', [ExpeditionArticleController::class, 'list']);
        Route::post('/expeditions/{expeditionId}/articles', [ExpeditionArticleController::class, 'add']);
        Route::put('/expeditions/{expeditionId}/articles/{articleId}', [ExpeditionArticleController::class, 'edit']);
        Route::delete('/expeditions/{expeditionId}/articles/{articleId}', [ExpeditionArticleController::class, 'delete']);
    });

    // Routes clients
    Route::prefix('client')->group(function () {
        // Gestion des expéditions du client
        Route::get('/expeditions', [ClientExpeditionController::class, 'list']);
        Route::post('/expeditions', [ClientExpeditionController::class, 'initiate']);
        Route::get('/expeditions/{id}', [ClientExpeditionController::class, 'show']);
        Route::put('/expeditions/{id}/cancel', [ClientExpeditionController::class, 'cancel']);
        Route::post('/expeditions/simulate', [ClientExpeditionController::class, 'simulate']);
        Route::get('/expeditions/statistics', [ClientExpeditionController::class, 'statistics']);

        // Gestion des articles d'expédition
        Route::get('/expeditions/{expeditionId}/articles', [ExpeditionArticleController::class, 'list']);
        Route::post('/expeditions/{expeditionId}/articles', [ExpeditionArticleController::class, 'add']);
        Route::put('/expeditions/{expeditionId}/articles/{articleId}', [ExpeditionArticleController::class, 'edit']);
        Route::delete('/expeditions/{expeditionId}/articles/{articleId}', [ExpeditionArticleController::class, 'delete']);
    });

    // Routes générales pour les articles d'expédition (simulation)
    Route::post('/expeditions/simulate-articles', [ExpeditionArticleController::class, 'simulate']);

    // Routes backoffice
    Route::prefix('backoffice')->group(function () {
        Route::post('/setup', [BackofficeController::class, 'setupBackoffice']);
        Route::get('/show', [BackofficeController::class, 'showBackoffice']);
        Route::put('/update', [BackofficeController::class, 'updateBackoffice']);

        // Gestion des utilisateurs du système (réservé au backoffice)
        Route::get('/list-users', [BackofficeUserController::class, 'listUsers']);
        Route::post('/create-user', [BackofficeUserController::class, 'createUser']);
        Route::get('/show-user/{user}', [BackofficeUserController::class, 'showUser']);
        Route::put('/edit-user/{user}', [BackofficeUserController::class, 'editUser']);
        Route::put('/status-user/{user}', [BackofficeUserController::class, 'toggleStatusUser']);
        Route::delete('/delete-user/{user}', [BackofficeUserController::class, 'deleteUser']);
    });

    // Routes tarification par le backoffice
    Route::prefix('tarification')->group(function () {
        // Tarification simple
        Route::get('/list-simple', [TarifSimpleController::class, 'list']);
        Route::post('/add-simple', [TarifSimpleController::class, 'add']);
        Route::put('/edit-simple/{tarif}', [TarifSimpleController::class, 'edit']);
        Route::get('/show-simple/{tarif}', [TarifSimpleController::class, 'show']);
        Route::delete('/delete-simple/{tarif}', [TarifSimpleController::class, 'delete']);
        Route::put('/status-simple/{tarif}', [TarifSimpleController::class, 'toggleStatus']);

        // Tarification groupage
        Route::get('/list-groupage', [TarifGroupageController::class, 'list']);
        Route::post('/add-groupage', [TarifGroupageController::class, 'add']);
        Route::put('/edit-groupage/{tarif}', [TarifGroupageController::class, 'edit']);
        Route::get('/show-groupage/{tarif}', [TarifGroupageController::class, 'show']);
        Route::delete('/delete-groupage/{tarif}', [TarifGroupageController::class, 'delete']);
        Route::put('/status-groupage/{tarif}', [TarifGroupageController::class, 'toggleStatus']);
    });

    // Routes catégories, produits
    Route::prefix('produits')->group(function () {
        Route::get('/list-categories', [CategoryProductController::class, 'list']);
        Route::post('/add-category', [CategoryProductController::class, 'add']);
        Route::put('/edit-category/{category}', [CategoryProductController::class, 'edit']);
        Route::get('/show-category/{category}', [CategoryProductController::class, 'show']);
        Route::delete('/delete-category/{category}', [CategoryProductController::class, 'delete']);
        Route::put('/status-category/{category}', [CategoryProductController::class, 'toggleStatus']);

        Route::get('/list', [ProduitsController::class, 'list']);
        Route::post('/add', [ProduitsController::class, 'add']);
        Route::put('/edit/{product}', [ProduitsController::class, 'edit']);
        Route::get('/show/{product}', [ProduitsController::class, 'show']);
        Route::delete('/delete/{product}', [ProduitsController::class, 'delete']);
        Route::put('/status/{product}', [ProduitsController::class, 'toggleStatus']);
    });

    // Routes zones
    Route::prefix('zones')->group(function () {
        Route::get('/list', [ZoneController::class, 'listZones']);
        Route::post('/add', [ZoneController::class, 'addZone']);
        Route::get('/show/{zone}', [ZoneController::class, 'showZone']);
        Route::put('/edit/{zone}', [ZoneController::class, 'editZone']);
        Route::delete('/delete/{zone}', [ZoneController::class, 'deleteZone']);
        Route::put('/status/{zone}', [ZoneController::class, 'toggleStatusZone']);
    });
});
