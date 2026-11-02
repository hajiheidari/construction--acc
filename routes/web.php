<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{AuthController,AccountingController};
Route::middleware('guest')->group(function(){Route::get('/login',[AuthController::class,'form'])->name('login');Route::post('/login',[AuthController::class,'login'])->middleware('throttle:10,1');});
Route::post('/logout',[AuthController::class,'logout'])->middleware('auth');
Route::middleware('auth')->group(function(){
 Route::get('/',[AccountingController::class,'home']);
 foreach(['','/api/v1'] as $prefix){Route::prefix($prefix)->group(function(){
  Route::get('/projects',[AccountingController::class,'home']);
  Route::post('/projects',[AccountingController::class,'createProject']);
  Route::get('/projects/{project}',[AccountingController::class,'show']);
  Route::post('/projects/{project}/settings/{resource}',[AccountingController::class,'resource']);
  Route::post('/projects/{project}/fiscal/{fiscal}/close',[AccountingController::class,'closeFiscal']);
  Route::post('/projects/{project}/documents',[AccountingController::class,'draft']);
  Route::get('/projects/{project}/documents/{document}',[AccountingController::class,'document']);
  Route::post('/projects/{project}/documents/{document}/approve',[AccountingController::class,'approve']);
  Route::post('/projects/{project}/documents/{document}/reverse',[AccountingController::class,'reverse']);
  Route::post('/projects/{project}/documents/{document}/check-events',[AccountingController::class,'checkEvent']);
  Route::post('/projects/{project}/documents/{document}/attachments',[AccountingController::class,'upload']);
  Route::get('/projects/{project}/attachments/{attachment}',[AccountingController::class,'attachment']);
  Route::get('/projects/{project}/reports',[AccountingController::class,'report']);
  Route::get('/projects/{project}/imports/template',[AccountingController::class,'template']);
  Route::post('/projects/{project}/imports',[AccountingController::class,'import']);
  Route::post('/projects/{project}/imports/{batch}/approve',[AccountingController::class,'approveImport']);
 });}
});
