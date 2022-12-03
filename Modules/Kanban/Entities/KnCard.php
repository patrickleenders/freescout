<?php

namespace Modules\Kanban\Entities;

use App\Conversation;
use App\Thread;
use Illuminate\Database\Eloquent\Model;

class KnCard extends Model
{   
    public $timestamps = false;
    
    public static $conversation_object = null;

    /**
     * Attributes fillable using fill() method.
     *
     * @var [type]
     */
    protected $fillable = ['id', 'name', 'kn_board_id', 'kn_column_id', 'kn_swimlane_id', 'linked', 'conversation_id'];

    public function kn_board()
    {
        return $this->belongsTo('Modules\Kanban\Entities\KnBoard');
    }

    public function conversation()
    {
        return $this->belongsTo('App\Conversation');
    }

    public function conversation_cached()
    {
        if (!empty($this->conversation_object)) {
            return $this->conversation_object;
        }
        
        $this->conversation_object = $this->conversation;

        return $this->conversation_object;
    }

    /**
     * Is card linked to conversation. Cards must be always linked.
     */
    // public function isLinked()
    // {
    //     return !empty($this->conversation_id);
    // }

    public static function create($data, $save = false)
    {
        try {
            $card = new self();
            $card->fill($data);

            if (!empty($data['conversation_id'])) {
                $card->linked = true;
            }

            if (!empty($data['conversation'])) {
                $card->conversation_id = $data['conversation']->id;
                $card->conversation_object = $data['conversation'];
            } elseif (empty($data['conversation_id'])) {
                // Create convesation.
                $conversation = Conversation::create([
                        'type' => Conversation::TYPE_PHONE,
                        'subject' => $card->name,
                        'mailbox_id' => $card->kn_board->mailbox_id,
                        'source_type' => Conversation::SOURCE_TYPE_WEB,
                        'imported' => $data['imported'] ?? false,
                    ], [[
                        'type' => Thread::TYPE_NOTE,
                        'created_by_user_id' => $data['created_by_user_id'],
                        'body' => $data['body'] ?: $data['name'],
                        //'attachments' => $attachments,
                    ]],
                    \Kanban::getCustomer()
                );
                if ($conversation) {
                     $card->conversation_id = $conversation['conversation']->id;
                }
            }

            if ($save) {

                // Sort order.
                $sort_order = KnCard::where('kn_board_id', $data['kn_board_id'])
                    ->where('kn_column_id', $data['kn_column_id'])
                    ->where('kn_swimlane_id', $data['kn_swimlane_id'])
                    ->max('sort_order');

                $card->sort_order = (int)$sort_order + 1;

                if (isset($card->conversation_object)) {
                    unset($card->conversation_object);
                }

                $card->save();
            }
        } catch (\Exception $e) {
            \Helper::logException($e);
            return null;
        }

        return $card;
    }

    /**
     * Only for real cards.
     */
    public function changeColumnAndSwimlane($column_id, $swimlane_id, $prev_card_id, $user = null)
    {
        $this->kn_column_id = $column_id;
        $this->kn_swimlane_id = $swimlane_id;

        // Sort order.
        $sorted = false;
        if ($prev_card_id) {
            $prev_card = KnCard::find($prev_card_id);
            if ($prev_card) {
                // Insert new card after prev.
                $this->sort_order = (int)$prev_card->sort_order + 1;
                KnCard::where('kn_board_id', $this->kn_board_id)
                    ->where('kn_column_id', $this->kn_column_id)
                    ->where('kn_swimlane_id', $this->kn_swimlane_id)
                    ->where('sort_order', '>', (int)$prev_card->sort_order)
                    ->increment('sort_order');
                $sorted = true;
            }
        }
        if (!$sorted) {
            // Simply set max sort_order.
            $sort_order = KnCard::where('kn_board_id', $this->kn_board_id)
                    ->where('kn_column_id', $this->kn_column_id)
                    ->where('kn_swimlane_id', $this->kn_swimlane_id)
                    ->max('sort_order');
            $this->sort_order = (int)$sort_order + 1;
        }

        $this->save();
    }

    public function getName()
    {
        return $this->name ?: $this->conversation->getSubject();
    }

    public function userCanUpdate($user)
    {
        return $this->kn_board->userCanView($user);
    }

    public function deleteCard()
    {
        \Eventy::action('kanban.card.before_delete', $this);
        $this->delete();
    }
}
