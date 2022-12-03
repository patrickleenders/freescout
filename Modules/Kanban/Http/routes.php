<?php

Route::group(['middleware' => 'web', 'prefix' => \Helper::getSubdirectory(), 'namespace' => 'Modules\Kanban\Http\Controllers'], function()
{
    Route::get('/kanban', ['uses' => 'KanbanController@show', 'middleware' => ['auth', 'roles'], 'roles' => ['user', 'admin']])->name('kanban.show');
    Route::post('/kanban/ajax', ['uses' => 'KanbanController@ajax', 'middleware' => ['auth', 'roles'], 'roles' => ['user', 'admin'], 'laroute' => true])->name('kanban.ajax');
    Route::get('/kanban/ajax_html', ['uses' => 'KanbanController@ajaxHtml', 'middleware' => ['auth', 'roles'], 'roles' => ['user', 'admin']])->name('kanban.ajax_html');
});
