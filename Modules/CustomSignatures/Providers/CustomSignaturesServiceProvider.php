<?php

namespace Modules\CustomSignatures\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Factory;

define('CS_MODULE', 'customsignatures');

class CustomSignaturesServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Boot the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerConfig();
        $this->registerViews();
        $this->registerFactories();
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
        $this->hooks();
    }

    /**
     * Module hooks.
     */
    public function hooks()
    {
        // Add module's CSS file to the application layout.
        \Eventy::addFilter('stylesheets', function($styles) {
            $styles[] = \Module::getPublicPath(CS_MODULE).'/css/module.css';
            return $styles;
        });
        
        // Add module's JS file to the application layout.
        \Eventy::addFilter('javascripts', function($javascripts) {
            $javascripts[] = \Module::getPublicPath(CS_MODULE).'/js/laroute.js';
            // if (!preg_grep("/html5sortable\.js$/", $javascripts)) {
            //     $javascripts[] = \Module::getPublicPath(CS_MODULE).'/js/html5sortable.js';
            // }
            $javascripts[] = \Module::getPublicPath(CS_MODULE).'/js/module.js';

            return $javascripts;
        });

        // JavaScript in the bottom
        \Eventy::addAction('javascript', function() {
            if (\Route::is('mailboxes.update')) {
                echo 'csInitMailboxSettings();';
            }
            if (\Route::is('conversations.view') || \Route::is('conversations.create')) {
                echo 'csInitSignatureSelect();';
            }
        });

        \Eventy::addAction('mailbox.update.after_signature', function($mailbox) {
            $signatures = \CustomSignature::where('mailbox_id', $mailbox->id)->get();
            
            echo \View::make('customsignatures::partials/mailbox_settings', [
                'mailbox' => $mailbox,
                'signatures' => $signatures,
            ])->render();
        });

        \Eventy::addAction('conv_editor.editor_toolbar_prepend', function($mailbox, $conversation) {
            $signatures = \CustomSignature::where('mailbox_id', $mailbox->id)->get();
            
            if (count($signatures)) {
                $signature_id = \CustomSignature::getConversationSignatureId($conversation->id);

                echo \View::make('customsignatures::partials/editor_toolbar_select', [
                    'signatures' => $signatures,
                    'signature_id' => $signature_id,
                    'mailbox' => $mailbox,
                    'conversation' => $conversation,
                ])->render();
            }
        }, 20, 2);

        \Eventy::addAction('mailbox.settings_before_save', function($mailbox, $request) {
            if (!\Auth::user()->can('updateEmailSignature', $mailbox)) {
                return;
            }
            // Delete signatures.
            $signatures = \CustomSignature::where('mailbox_id', $mailbox->id)->get();
            $signature_ids = [];
            if (!empty($request->cs_signature_name) && is_array($request->cs_signature_name)) {
                $signature_ids = array_keys($request->cs_signature_name);
            }

            foreach ($signatures as $signature) {
                if (!in_array($signature->id, $signature_ids)) {
                    $signature->deleteSignature();
                }
            }

            // Save new signatures.
            if (!empty($request->cs_signature_name_new)) {
                foreach ($request->cs_signature_name_new as $i => $name) {
                    if (empty($name) || empty($request->cs_signature_text_new[$i])) {
                        continue;
                    }
                    $signature = new \CustomSignature();
                    $signature->mailbox_id = $mailbox->id;
                    $signature->name = $name;
                    $signature->text = $request->cs_signature_text_new[$i] ?? '';
                    $signature->save();
                }
            }

            // Update existing.
            if (!empty($request->cs_signature_name)) {
                foreach ($request->cs_signature_name as $id => $name) {
                    if (empty($name) || empty($request->cs_signature_text[$id])) {
                        continue;
                    }
                    \CustomSignature::where('id', $id)->update([
                        'name' => $name,
                        'text' => $request->cs_signature_text[$id],
                    ]);
                }
            }
        }, 20, 2);

        \Eventy::addAction('thread.before_save_from_request', function ($thread, $request) {
            if (!empty($request->cs_signature) && (int)$request->cs_signature) {
                $thread->setMeta('cs.signature_id', (int)$request->cs_signature);
            }
        }, 20, 2);

        \Eventy::addFilter('thread.body_output', function ($body, $thread, $conversation, $mailbox) {
            $signature_id = $thread->getMeta('cs.signature_id');
            if ($signature_id) {
                $signature = \CustomSignature::find($signature_id);
                if ($signature) {
                    return $body.'<br>'.$conversation->replaceTextVars($signature->text, ['user' => $thread->created_by_user], true);
                }
            }
            return $body;
        }, 20, 4);

        // Add selected signature when sending the thread
        \Eventy::addFilter('conversation.signature_processed', function ($signature_html, $conversation, $data, $escape) {
            if (!empty($data['thread'])) {
                // In email.
                // 'thread' is passed only when sending an email.
                $thread = $data['thread'];
                $signature_id = $thread->getMeta('cs.signature_id');
                if ($signature_id) {
                    $signature = \CustomSignature::find($signature_id);
                    if ($signature) {
                        return $conversation->replaceTextVars($signature->text, $data, $escape);
                    }
                }
            } elseif ((request()->action ?? '') != 'load_signature') {
                // Frontend.
                $signature_id = \CustomSignature::getConversationSignatureId($conversation->id);
                $signature = \CustomSignature::find($signature_id);
                if ($signature) {
                    return $conversation->replaceTextVars($signature->text, $data, $escape);
                }
            }

            return $signature_html;
        }, 20, 4);
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->registerTranslations();
    }

    /**
     * Register config.
     *
     * @return void
     */
    protected function registerConfig()
    {
        $this->publishes([
            __DIR__.'/../Config/config.php' => config_path('customsignatures.php'),
        ], 'config');
        $this->mergeConfigFrom(
            __DIR__.'/../Config/config.php', 'customsignatures'
        );
    }

    /**
     * Register views.
     *
     * @return void
     */
    public function registerViews()
    {
        $viewPath = resource_path('views/modules/customsignatures');

        $sourcePath = __DIR__.'/../Resources/views';

        $this->publishes([
            $sourcePath => $viewPath
        ],'views');

        $this->loadViewsFrom(array_merge(array_map(function ($path) {
            return $path . '/modules/customsignatures';
        }, \Config::get('view.paths')), [$sourcePath]), 'customsignatures');
    }

    /**
     * Register translations.
     *
     * @return void
     */
    public function registerTranslations()
    {
        $this->loadJsonTranslationsFrom(__DIR__ .'/../Resources/lang');
    }

    /**
     * Register an additional directory of factories.
     * @source https://github.com/sebastiaanluca/laravel-resource-flow/blob/develop/src/Modules/ModuleServiceProvider.php#L66
     */
    public function registerFactories()
    {
        if (! app()->environment('production')) {
            app(Factory::class)->load(__DIR__ . '/../Database/factories');
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [];
    }
}
