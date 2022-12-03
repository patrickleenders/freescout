<?php

namespace Modules\Mentions\Providers;

use App\Subscription;
use App\Thread;
use App\User;
use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Factory;

// Module alias
define('MENTIONS_MODULE', 'mentions');

class MentionsServiceProvider extends ServiceProvider
{
    const EVENT_I_AM_MENTIONED = 14;

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
        // Add module's JS file to the application layout.
        \Eventy::addFilter('javascripts', function($javascripts) {
            $javascripts[] = \Module::getPublicPath(MENTIONS_MODULE).'/js/laroute.js';
            $javascripts[] = \Module::getPublicPath(MENTIONS_MODULE).'/js/module.js';
            return $javascripts;
        });

        // JavaScript in the bottom
        \Eventy::addAction('javascript', function() {
            if (\Route::is('conversations.view')) {
                echo 'mentionsInitConv();';
            }
        });

        \Eventy::addAction('notifications_table.general.append', function($vars) {
            echo \View::make('mentions::partials/notifications_table', $vars)->render();
        }, 20, 1);

        // Note added.
        \Eventy::addAction('conversation.note_added', function($conversation, $thread) {
            if (self::getMentionedUsers($thread->body)) {
                Subscription::registerEvent(self::EVENT_I_AM_MENTIONED, $conversation, $thread->created_by_user_id);
            }
        }, 20, 2);

        \Eventy::addFilter('subscription.events_by_type', function($events, $event_type, $thread) {
            if ($event_type == Subscription::EVENT_TYPE_USER_ADDED_NOTE && self::getMentionedUsers($thread->body)) {
                $events[] = self::EVENT_I_AM_MENTIONED;
            }

            return $events;
        }, 20, 3);

        \Eventy::addFilter('subscription.filter_out', function($filter_out, $subscription, $thread) {
            if ($subscription->event != self::EVENT_I_AM_MENTIONED) {
                return $filter_out;
            }
            $mentioned_users = self::getMentionedUsers($thread->body);
            if (!in_array($subscription->user_id, $mentioned_users)) {
                return true;
            } else {
                return false;
            }
        }, 20, 3);

        \Eventy::addFilter('thread.action_text', function($did_this, $thread, $conversation_number, $escape, $viewed_by_user) {
            if ($thread->type == Thread::TYPE_NOTE 
                && $thread->state != Thread::STATE_DRAFT
                && $viewed_by_user
            ) {
                $mentioned_users = self::getMentionedUsers($thread->body);
                if ($mentioned_users && in_array($viewed_by_user->id, $mentioned_users)) {
                    $did_this = __(':person mentioned you in a note on #:conversation_number', ['conversation_number' => $conversation_number]);
                }
            }

            return $did_this;
        }, 20, 5);

        \Eventy::addFilter('subscription.is_related_to_user', function($is_related, $subscription, $thread) {
            if ($subscription->event == self::EVENT_I_AM_MENTIONED
                && in_array($subscription->user_id, self::getMentionedUsers($thread->body))
            ) {
                return true;
            }

            return $is_related;
        }, 20, 3);

        // Always show @mentions notification in the menu.
        \Eventy::addFilter('subscription.users_to_notify', function($users_to_notify, $event_type, $events, $thread) {
            if (in_array(self::EVENT_I_AM_MENTIONED, $events)) {
                $mentioned_users = self::getMentionedUsers($thread->body);
                if (count($mentioned_users)) {
                    $users = User::whereIn('id', $mentioned_users)->get();
                    foreach ($users as $user) {
                        $users_to_notify[Subscription::MEDIUM_MENU][] = $user;
                        $users_to_notify[Subscription::MEDIUM_MENU] = array_unique($users_to_notify[Subscription::MEDIUM_MENU]);
                    }
                }
            }

            return $users_to_notify;
        }, 20, 4);
    }

    public static function getMentionedUsers($text)
    {
        preg_match_all('/data\-mentioned\-id="(\d+)">[^<]+/', $text ?? '', $m);

        if (!empty($m[1])) {
            return array_unique($m[1]);
        } else {
            return [];
        }
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
            __DIR__.'/../Config/config.php' => config_path('mentions.php'),
        ], 'config');
        $this->mergeConfigFrom(
            __DIR__.'/../Config/config.php', 'mentions'
        );
    }

    /**
     * Register views.
     *
     * @return void
     */
    public function registerViews()
    {
        $viewPath = resource_path('views/modules/mentions');

        $sourcePath = __DIR__.'/../Resources/views';

        $this->publishes([
            $sourcePath => $viewPath
        ],'views');

        $this->loadViewsFrom(array_merge(array_map(function ($path) {
            return $path . '/modules/mentions';
        }, \Config::get('view.paths')), [$sourcePath]), 'mentions');
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
