<?php

Route::group(['middleware' => 'web', 'prefix' => \Helper::getSubdirectory(), 'namespace' => 'Modules\CustomerDataEnrichment\Http\Controllers'], function()
{
    Route::get('/', 'CustomerDataEnrichmentController@index');
});
