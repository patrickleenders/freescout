<?php

Route::group(['middleware' => 'web', 'prefix' => \Helper::getSubdirectory(), 'namespace' => 'Modules\CustomSignatures\Http\Controllers'], function()
{
    Route::post('/custom-signatures/ajax', ['middleware' => ['auth', 'roles'], 'roles' => ['user', 'admin'], 'uses' => 'CustomSignaturesController@ajax', 'laroute' => true])->name('custom_signatures.ajax');
});
