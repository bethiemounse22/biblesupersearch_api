<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the controller to call when that URI is requested.
|
*/

/* Routes for the API  */
Route::get('/api/{action?}' , 'ApiController@genericAction')->middleware('api'); // 'Action' defaults to 'query'
Route::post('/api/{action?}', 'ApiController@genericAction')->middleware('api'); // 'Action' defaults to 'query'

/* Route for Documentation UI */
Route::get('/', 'DocumentationController');
Route::get('/documentation', 'DocumentationController');


/* EVERYTHING BELOW IS EXPERIMENTAL, NON-PRODUCTION CODE */


/* Routes for (administrative) backend */
Route::get('/admin', function() {
    if(Auth::check()) {
        return redirect('/admin/main');
    }

    return view('admin.login');
});

Route::get('/admin/login', function() {
    return redirect('/admin');
});

//Route::get('/admin/login', 'Auth\AuthController@getLogin');
//Route::get('/auth/login', function () {
//    return redirect('/admin');
//});

//Route::post('/auth/login', 'Auth\AuthController@postLogin');
Route::get('/login', 'Auth\AuthController@viewLogin')->name('login');
Route::post('/login', 'Auth\AuthController@login');
Route::get('/auth/login', 'Auth\AuthController@viewLogin');
Route::post('/auth/login', 'Auth\AuthController@login');
Route::get('/logout', 'Auth\AuthController@logout')->name('logout');
Route::get('/landing', 'Auth\AuthController@landing')->name('auth.landing')->middleware('auth');
Route::get('/auth/reset', 'Auth\PasswordController@showLinkRequestForm')->name('password.request');
//Route::get('/auth/reset', 'Auth\PasswordController@showResetForm')->name('password.request');
Route::post('/auth/reset', 'Auth\PasswordController@sendResetLinkEmail')->name('password.email');
Route::get('/auth/change', 'Auth\PasswordController@showResetForm')->name('password.reset');
Route::post('/auth/change', 'Auth\PasswordController@reset');
Route::post('/auth/success', 'Auth\PasswordController@success');
Route::get('/auth/success', 'Auth\PasswordController@success');
Route::get('/admin/main', 'AdminController@getMain')->name('admin.main');

Route::get('/admin/bibles/grid', 'Admin\BibleController@grid');
Route::post('/admin/bibles/enable/{id}', 'Admin\BibleController@enable');
Route::post('/admin/bibles/disable/{id}', 'Admin\BibleController@disable');
Route::post('/admin/bibles/install/{id}', 'Admin\BibleController@install');
Route::post('/admin/bibles/uninstall/{id}', 'Admin\BibleController@uninstall');
Route::post('/admin/bibles/export/{id}', 'Admin\BibleController@export');

Route::get('/admin/tos', 'Admin\PostConfigController@tos')->name('admin.tos');
Route::post('/admin/tos', 'Admin\PostConfigController@saveTos');
Route::get('/admin/privacy', 'Admin\PostConfigController@privacy')->name('admin.privacy');
Route::post('/admin/privacy', 'Admin\PostConfigController@savePrivacy');

Route::resource('/admin/bibles', 'Admin\BibleController', ['as' => 'admin', 'except' => [
    'create', 'edit'
]]);

Route::get('/admin/config', 'Admin\ConfigController@index')->name('admin.configs');
Route::post('/admin/config', 'Admin\ConfigController@store')->name('admin.configs.store');
Route::delete('/admin/config', 'Admin\ConfigController@destroy')->name('admin.configs.destroy');

//Route::controller('admin', 'AdminController');

// Installers
Route::get('/install/{action?}' , 'Admin\InstallController@index')->name('admin.install');
//Route::post('/install/{action?}', 'Admin\InstallController@genericAction'); // Inside controller actions are required to be post
Route::post('/install/check', 'Admin\InstallController@check')->name('admin.install.check'); // Inside controller actions are required to be post

// todos
Route::get('/admin/options', 'AdminController@todo')->name('admin.options');
Route::get('/admin/test', 'AdminController@todo')->name('admin.test');