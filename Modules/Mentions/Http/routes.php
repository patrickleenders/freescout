<?php

Route::group(['middleware' => 'web', 'prefix' => \Helper::getSubdirectory(), 'namespace' => 'Modules\Mentions\Http\Controllers'], function()
{
    Route::any('/mentions/ajax', ['uses' => 'MentionsController@ajax', 'middleware' => ['auth', 'roles'], 'roles' => ['admin', 'user'], 'laroute' => true])->name('mentions.ajax');
});
