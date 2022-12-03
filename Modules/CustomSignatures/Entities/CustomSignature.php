<?php

namespace Modules\CustomSignatures\Entities;

use App\Thread;
use Illuminate\Database\Eloquent\Model;

class CustomSignature extends Model
{
    public $timestamps = false;

    // Cache.
    public static $conversation_signatures = [];

    public function mailbox()
    {
        return $this->belongsTo('App\Mailbox');
    }

    public function deleteSignature()
    {
        \Eventy::action('custom_signature.before_delete', $this);
        $this->delete();
    }

    public static function getConversationSignatureId($conversation_id)
    {
        if (isset(self::$conversation_signatures[$conversation_id])) {
            return self::$conversation_signatures[$conversation_id];
        }
        
        $signature_id = 0;

    	$threads = Thread::select('id', 'state', 'meta')
            ->where('conversation_id', $conversation_id)
            ->where('type', Thread::TYPE_MESSAGE)
            ->get();
        
        if (count($threads)) {
            $threads = $threads->sortByDesc('id');
            foreach ($threads as $thread) {
                if ($thread->state == Thread::STATE_PUBLISHED) {
                    $signature_id = (int)$thread->getMeta('cs.signature_id');
                    break;
                }
            }
        }

        self::$conversation_signatures[$conversation_id] = $signature_id;

        return $signature_id;
    }
}