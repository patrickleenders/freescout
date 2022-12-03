<?php

Route::group(['middleware' => 'web', 'prefix' => \Helper::getSubdirectory(), 'namespace' => 'Modules\ImapMove\Http\Controllers'], function()
{
    Route::get('/', 'ImapMoveController@index');
});
