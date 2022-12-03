<?php

namespace Modules\Kanban\Entities;

use App\Mailbox;
use Modules\Kanban\Entities\KnCard;
use Illuminate\Database\Eloquent\Model;

class KnBoard extends Model
{    
    protected $casts = [
        'columns' => 'array',
        'swimlanes' => 'array',
    ];

    public $timestamps = false;

    /**
     * Attributes fillable using fill() method.
     *
     * @var [type]
     */
    //protected $fillable = ['mailbox_id'];

    public function mailbox()
    {
        return $this->belongsTo('App\Mailbox');
    }

    public function mailbox_cached()
    {
        return $this->belongsTo('App\Mailbox')->rememberForever();
    }

    public function created_by_user()
    {
        return $this->belongsTo('App\User');
    }

    public static function boardsUserCanView($user, $mailbox_ids = [])
    {
        if ($mailbox_ids === []) {
            $mailbox_ids = $user->mailboxesIdsCanView();
        }
        
        $boards = KnBoard::where('created_by_user_id', $user->id)
            ->orWhereIn('mailbox_id', $mailbox_ids)
            ->get();

        return $boards;
    }

    public function userCanView($user)
    {
        if ($this->created_by_user_id == $user->id) {
            return true;
        }
        $mailbox_ids = $user->mailboxesIdsCanView();
        
        return in_array($this->mailbox_id, $mailbox_ids);
    }

    public function userCanUpdate($user)
    {
        return $this->created_by_user_id == $user->id;
    }

    public function userCanDelete($user)
    {
        if ($this->created_by_user_id == $user->id) {
            return true;
        }
        if ($this->created_by_user && $this->created_by_user->isDeleted() && $user->isAdmin()) {
            return true;
        }

        return false;
    }

    public function getMaxColumnId()
    {
        if (empty($this->columns)) {
            return 0;
        }

        return collect($this->columns)->max('id');
    }

    public function getMaxSwimlaneId()
    {
        if (empty($this->swimlanes)) {
            return 0;
        }

        return collect($this->swimlanes)->max('id');
    }

    public function deleteBoard()
    {
        \Eventy::action('kanban.board.before_delete', $this);
        KnCard::where('kn_board_id', $this->id)->delete();
        $this->delete();
    }

    public function url()
    {
        if ($this->id) {
            $params['board_id'] = $this->id;
        } else {
            $params['mailbox_id'] = $this->mailbox_id;
        }
        return \Kanban::url($params);
    }

    /**
     * Load mailboxes.
     */
    public static function loadMailboxes($boards)
    {
        $ids = $boards->pluck('mailbox_id')->unique()->toArray();
        if (!$ids) {
            return;
        }

        $mailboxes = Mailbox::whereIn('id', $ids)->get();
        if (!$mailboxes) {
            return;
        }

        foreach ($boards as $board) {
            if (empty($board->mailbox_id)) {
                continue;
            }
            foreach ($mailboxes as $mailbox) {
                if ($mailbox->id == $board->mailbox_id) {
                    $board->mailbox = $mailbox;

                    continue 2;
                }
            }
        }
    }
}
