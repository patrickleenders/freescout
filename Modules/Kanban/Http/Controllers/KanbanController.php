<?php

namespace Modules\Kanban\Http\Controllers;

use App\Conversation;
use App\Mailbox;
use App\User;
use Modules\Kanban\Entities\KnBoard;
use Modules\Kanban\Entities\KnCard;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class KanbanController extends Controller
{
    public function show(Request $request)
    {
        $board = null;

        // Params.
        $params = $this->getParams($request);

        $board_id = $params['board_id'] ?? null;
        $mailbox_id = $params['mailbox_id'] ?? null;

        $user = auth()->user();

        $mailboxes = $user->mailboxesCanView(true);
        $mailboxes = $mailboxes->sortBy('name');

        $boards = KnBoard::boardsUserCanView($user, $mailboxes->pluck('id'));
        KnBoard::loadMailboxes($boards);

        if ($mailbox_id === null && $board_id === null) {
            if (count($boards)) {
                if (count($mailboxes)) {
                    return redirect()->route('kanban.show', ['kn' => ['board_id' => $boards->first()->id]]);
                }
            } else {
            	if (count($mailboxes)) {
            		return redirect()->route('kanban.show', ['kn' => ['mailbox_id' => $mailboxes->first()->id]]);
            	}
            }
        }

        $data = ['data' => []];
        $empty_data = [];

        if ($mailbox_id && $mailbox_id != \Kanban::ALL_MAILBOXES) {

            $mailbox = Mailbox::find($mailbox_id);

            if (!$mailbox || !in_array($mailbox_id, $mailboxes->pluck('id')->all())) {
                return $this->redirectToFirst($boards, $mailboxes);
            }

            if (!$user->can('view', $mailbox)) {
                $empty_data = ['icon' => 'ban-circle', 'empty_text' => __("You don't have access to this mailbox")];
            }

            if (!$empty_data) {
                $data = $this->getMailboxData($mailbox, $params);
            }

            $board_id = '';
        } elseif ($mailbox_id == \Kanban::ALL_MAILBOXES) {
            $data = $this->getMailboxData(\Kanban::getGlobalMailbox(), $params);
            $board_id = '';
        } elseif ($board_id) {
            $board = KnBoard::find($board_id);
            $mailbox_id = '';

            if (!$board) {
                return $this->redirectToFirst($boards, $mailboxes);
            }

            if (!in_array($board_id, $boards->pluck('id')->all())) {
                $empty_data = ['icon' => 'ban-circle', 'empty_text' => __("You don't have access to this board")];
            } else {
                $data = $this->getBoardData($board, $params);
            }
        }

        return view('kanban::show', array_merge([
            'boards' => $boards,
            'mailboxes' => $mailboxes,
            'board_id' => $board_id,
            'mailbox_id' => $mailbox_id,
            'board' => $board,
            'empty_data' => $empty_data,
            'params' => $params,
            'user' => $user,
        ], $data));
    }

    public function redirectToFirst($boards, $mailboxes)
    {
        if (count($boards)) {
            return redirect()->route('kanban.show', ['kn' => ['board_id' => $boards->first()->id]]);
        } elseif (count($mailboxes)) {
            return redirect()->route('kanban.show', ['kn' => ['mailbox_id' => $mailboxes->first()->id]]);
        } else {
            return redirect()->route('kanban.show');
        }
    }

    public function getBoardData($board, $params)
    {
        $cards = [];
        $columns = [];
        $column_ids = [];

        $board_id = $board->id;
        $mailbox = $board->mailbox_cached;

        // Mailbox may be deleted.
        if (!$mailbox) {
            $mailbox = new Mailbox();
        }

        // Build columns. Columns must contain all elements non-filtered.
        $columns = $this->buildColumns($params, $mailbox, $board);
        $column_ids = $columns->pluck('id');
        $columns = $columns->toArray();

        // Turn conversations into cards.
        $swimlanes = $board->swimlanes ?: [[
            'id' => 0,
            'name' => '',
        ]];

        // Fetch conversations by columns using UNION.
        // This can't be done without UNIONs as we need to limit items.
        $conversations = [];
        $query = null;

        foreach ($swimlanes as $swimlane) {
            foreach ($column_ids as $i => $column_id) {
                $swimlane_id = $swimlane['id'];
                if (count($swimlanes) == 1) {
                    $swimlane_id = null;
                }
                $next_query = $this->getColumnConversationsQuery($mailbox->id, $board_id, $column_id, $params, 0, $swimlane_id);

                if ($query !== null) {
                    $query->union($next_query);
                } else {
                    $query = $next_query;
                }

                if (!\Kanban::useUnions() || $i % \Kanban::SELECTS_PER_UNION == 0 || $i == count($column_ids)-1) {
                    $conversations = array_merge($conversations, $query->get()->all());
                    $query = null;
                }
            }
        }

        // Preload conversations data.
        $conversations = $this->loadConversationsData($conversations);

        $group_by_field = $params['group_by'];
        foreach ($columns as $c => $column) {
            foreach ($swimlanes as $swimlane) {
                $cards[$swimlane['id']][$column['id']]['cards'] = [];
                foreach ($conversations as $conversation) {
                    // Check column.
                    if ($conversation->$group_by_field != $column['id']) {
                        continue;
                    }
                    // Check swimlane.
                    if ($conversation->kn_swimlane_id != $swimlane['id']) {
                        continue;
                    }

                    $card = KnCard::create([
                        'id' => $conversation->kn_card_id,
                        'name' => $conversation->kn_card_name,
                        'conversation' => $conversation,
                        'kn_column_id' => $conversation->kn_column_id,
                        'kn_swimlane_id' => $swimlane['id'],
                        'linked' => $conversation->linked,
                    ]);

                    $cards[$swimlane['id']][$column['id']]['cards'][] = $card;
                }

                $cards[$swimlane['id']][$column['id']]['total_count'] = count($cards[$swimlane['id']][$column['id']]['cards']);
            }
        }

        // Count totals.
        $cards = $this->countTotals($cards, $params, null, $board_id, $columns, $column_ids, $group_by_field, $swimlanes);

        // Count closed conversations.
        $cards = $this->countClosed($cards, $params, null, $board_id, $columns, $column_ids, $group_by_field, $swimlanes);

        return [
            'data' => $cards,
            'columns' => $columns,
            'swimlanes' => $swimlanes,
        ];
    }

    public function getMailboxData($mailbox, $params)
    {
        $cards = [];
        $columns = [];
        $column_ids = [];

        $mailbox_id = $mailbox->id;

        // Build columns. Columns must contain all elements non-filtered.
        $columns = $this->buildColumns($params, $mailbox);
        $column_ids = $columns->pluck('id');
        $columns = $columns->toArray();

        // Fetch conversations by columns using UNION.
        // This can't be done without UNIONs as we need to limit items.
        $conversations = [];
        $query = null;
        foreach ($column_ids as $i => $group_id) {
            $next_query = $this->getColumnConversationsQuery($mailbox_id, null, $group_id, $params);

            if ($query !== null) {
                $query->union($next_query);
            } else {
                $query = $next_query;
            }

            if (!\Kanban::useUnions() || $i % \Kanban::SELECTS_PER_UNION == 0 || $i == count($column_ids)-1) {
                $conversations = array_merge($conversations, $query->get()->all());
                $query = null;
            }
        }

        // Preload conversations data.
        $conversations = $this->loadConversationsData($conversations);

        // Turn conversations into cards.
        $swimlanes = [[
            'id' => 0,
            'name' => '',
        ]];
        $group_by_field = $params['group_by'];
        foreach ($columns as $c => $column) {
            $cards[0][$column['id']]['cards'] = [];
            $count = 0;
            foreach ($conversations as $conversation) {
                if ($conversation->$group_by_field != $column['id']) {
                    continue;
                }

                $card = KnCard::create([
                    'conversation' => $conversation,
                    'kn_column_id' => $column['id'],
                    'kn_swimlane_id' => 0,
                    'linked' =>true,
                ]);

                $cards[0][$column['id']]['cards'][] = $card;
            }

            $cards[0][$column['id']]['total_count'] = count($cards[0][$column['id']]['cards']);
        }

        // Count totals.
        $cards = $this->countTotals($cards, $params, $mailbox_id, null, $columns, $column_ids, $group_by_field, $swimlanes);

        // Count closed conversations.
        $cards = $this->countClosed($cards, $params, $mailbox_id, null, $columns, $column_ids, $group_by_field, $swimlanes);

        return [
            'data' => $cards,
            'columns' => $columns,
            'swimlanes' => $swimlanes,
        ];
    }

    public function countTotals($cards, $params, $mailbox_id, $board_id, $columns, $column_ids, $group_by_field, $swimlanes)
    {
        $total_counts = [];
        $query = null;

        foreach ($swimlanes as $swimlane) {
            // Count only for columns filled with cards.
            $counter = 0;
            foreach ($column_ids as $i => $column_id) {
                if (isset($cards[$swimlane['id']][$column_id]['total_count'])
                    && $cards[$swimlane['id']][$column_id]['total_count'] <= \Kanban::CARDS_PER_COLUMN
                ) {
                    // Do nothing.
                } else {
                    $next_query = $this->getMainConversationsQuery($mailbox_id, $board_id, $params, $column_id, $swimlane['id']);
                    if ($params['group_by'] == \Kanban::GROUP_BY_TAG) {
                        $next_query->select(['conversation_tag.tag_id as tag', \DB::raw('count(*) as total_count')]);
                    } else {
                        $next_query->select([\DB::raw($column_id.' as group_by_field'), \DB::raw('count(*) as total_count')]);
                    }
                    if ($query !== null) {
                        $query->union($next_query);
                    } else {
                        $query = $next_query;
                    }
                    $counter++;
                }

                if (!$query) {
                    continue;
                }
                if (!\Kanban::useUnions() || $counter % \Kanban::SELECTS_PER_UNION == 0 || $i == count($column_ids)-1) {
                    $total_counts = $query->get()->all();
                    foreach ($total_counts as $count) {
                        foreach ($columns as $c => $column) {
                            if ($column['id'] == $count->group_by_field) {
                                $cards[$swimlane['id']][$column['id']]['total_count'] = (int)$count->total_count;
                                break;
                            }
                        }
                    }
                    $query = null;
                }
            }
        }

        return $cards;
    }

    public function countClosed($cards, $params, $mailbox_id, $board_id, $columns, $column_ids, $group_by_field, $swimlanes)
    {
        $closed_counts = [];
        $query = null;
        if ($params['group_by'] == \Kanban::GROUP_BY_STATUS) {
            return $cards;
        }
        
        foreach ($swimlanes as $swimlane) {
            foreach ($column_ids as $i => $group_id) {
                $closed_params = $params;
                if (!is_array($closed_params['filters'])) {
                    $closed_params['filters'] = [];
                }
                $closed_params['filters']['status'] = [Conversation::STATUS_CLOSED];
                $next_query = $this->getMainConversationsQuery($mailbox_id, $board_id, $closed_params, $group_id, $swimlane['id']);

                $group_by_field_sql = \DB::raw('MAX('.$group_by_field.')');
                if ($params['group_by'] == \Kanban::GROUP_BY_TAG) {
                    $group_by_field_sql = \DB::raw('MAX('.'conversation_tag.tag_id'.') as tag');
                } elseif ($params['group_by'] == \Kanban::GROUP_BY_COLUMN) {
                    $group_by_field_sql = \DB::raw('MAX('.'kn_cards.kn_column_id'.')');
                }
                $next_query->select([$group_by_field_sql, \DB::raw('count(*) as closed_count')]);
                if ($query !== null) {
                    $query->union($next_query);
                } else {
                    $query = $next_query;
                }

                if (!\Kanban::useUnions() || $i % \Kanban::SELECTS_PER_UNION == 0 || $i == count($column_ids)-1) {
                    $closed_counts = $query->get()->all();

                    foreach ($closed_counts as $count) {
                        foreach ($columns as $column) {
                            if ($column['id'] == $count->$group_by_field) {
                                $cards[$swimlane['id']][$column['id']]['closed'] = $count->closed_count;
                                break;
                            }
                        }
                    }
                    $query = null;
                }
            }
        }

        return $cards;
    }

    public function buildColumns($params, $mailbox, $board = null)
    {
        $columns = collect([]);

        switch ($params['group_by']) {
            case \Kanban::GROUP_BY_COLUMN:
                $columns = collect($board->columns);
                $columns = $this->filterColumns($columns, $params['filters'][\Kanban::FILTER_BY_COLUMN]);
                break;

            case \Kanban::GROUP_BY_ASSIGNEE:
                // $column_ids = $this->getMainConversationsQuery($mailbox_id, $params)
                //     ->select('user_id')
                //     ->where('user_id', '!=', '')
                //     ->groupBy('user_id')
                //     ->pluck('user_id');

                // $users = User::select('id', 'first_name', 'last_name')
                //     ->whereIn('id', $column_ids)
                //     ->get();
                $users = [];

                if ($mailbox->id != \Kanban::ALL_MAILBOXES) {
                    $users = $mailbox->usersHavingAccess();
                } else {
                    $user = auth()->user();

                    // All users for admins.
                    // Users from available mailboxes for others.
                    if ($user->isAdmin()) {
                        $users = User::where('status', User::STATUS_ACTIVE)->get();
                    } else {
                        $users = $user->whichUsersCanView();
                    }
                }
                foreach ($users as $assignee) {
                    $columns[] = [
                        'id' => $assignee->id,
                        'name' => $assignee->getFullName(),
                    ];
                }

                $columns = collect($columns)->sortBy('name');

                $columns = $this->filterColumns($columns, $params['filters'][\Kanban::FILTER_BY_ASSIGNEE]);

                $columns->prepend([
                    'id' => 0,
                    'name' => __('Unassigned'),
                ]);
                break;

            case \Kanban::GROUP_BY_STATUS:
                $columns = collect([
                    [
                        'id' => Conversation::STATUS_ACTIVE,
                        'name' => Conversation::statusCodeToName(Conversation::STATUS_ACTIVE),
                    ],
                    [
                        'id' => Conversation::STATUS_PENDING,
                        'name' => Conversation::statusCodeToName(Conversation::STATUS_PENDING),
                    ],
                    [
                        'id' => Conversation::STATUS_AWAITING_CUSTOMER,
                        'name' => Conversation::statusCodeToName(Conversation::STATUS_AWAITING_CUSTOMER),
                    ],
                    [
                        'id' => Conversation::STATUS_AWAITING_SUPPLIER,
                        'name' => Conversation::statusCodeToName(Conversation::STATUS_AWAITING_SUPPLIER),
                    ],
                    [
                        'id' => Conversation::STATUS_AWAITING_TODO,
                        'name' => Conversation::statusCodeToName(Conversation::STATUS_AWAITING_TODO),
                    ],
                    [
                        'id' => Conversation::STATUS_AWAITING_PROJECT,
                        'name' => Conversation::statusCodeToName(Conversation::STATUS_AWAITING_PROJECT),
                    ],
                    [
                        'id' => Conversation::STATUS_AWAITING_DUPLICATE,
                        'name' => Conversation::statusCodeToName(Conversation::STATUS_AWAITING_DUPLICATE),
                    ],
                    [
                        'id' => Conversation::STATUS_CLOSED,
                        'name' => Conversation::statusCodeToName(Conversation::STATUS_CLOSED),
                    ],
                ]);

                if (in_array(Conversation::STATUS_SPAM,  $params['filters'][\Kanban::FILTER_BY_STATUS])) {
                    $columns[] = [
                        'id' => Conversation::STATUS_SPAM,
                        'name' => Conversation::statusCodeToName(Conversation::STATUS_SPAM),
                    ];
                }

                $columns = $this->filterColumns($columns, $params['filters'][\Kanban::FILTER_BY_STATUS]);
                break;

            case \Kanban::GROUP_BY_TAG:
                if (\Module::isActive('tags')) {
                    $tags = \Modules\Tags\Entities\Tag::select(['id', 'name'])
                        ->where('counter', '>', 0)
                        ->get();
                    foreach ($tags as $tag) {
                        $columns[] = [
                            'id' => $tag->id,
                            'name' => $tag->name,
                        ];
                    }
                }
                $columns = collect($columns)->sortBy('name');
                $columns = $this->filterColumns($columns, $params['filters'][\Kanban::FILTER_BY_TAG]);
                break;
        }

        return $columns;
    }

    /**
     * Remove columns not mentioned in the filter.
     */
    public function filterColumns($columns, $filter)
    {
        if (empty($filter)) {
            return $columns;
        }

        foreach ($columns as $i => $column) {
            if (!in_array($column['id'], $filter)) {
                unset($columns[$i]);
            }
        }

        return $columns;
    }

    /**
     * Ajax controller.
     */
    public function ajax(Request $request)
    {
        $response = [
            'status' => 'error',
            'msg'    => '', // this is error message
        ];

        $user = auth()->user();

        switch ($request->action) {

            // Load more cards.
            case 'more':
                $params = $this->getParams($request);

                $board_id = null;

                // Check if user has access to the mailbox or board.
                $mailbox_id = $params['mailbox_id'] ?? null;
                if ($mailbox_id > 0 && !$user->hasAccessToMailbox($mailbox_id)) {
                    $response['msg'] = __('Not enough permissions');
                }
                $board_id = $params['board_id'] ?? null;
                if ($board_id) {
                    $board = KnBoard::find($board_id);
                    if (!$board) {
                        $response['msg'] = __('Board not found');
                    } else if (!$board->userCanView($user)) {
                        $response['msg'] = __('Not enough permissions');
                    }
                }

                if (!$response['msg']) {
                    
                    if ($mailbox_id) {
                        // Mailbox cards.
                        $cards = [];

                        $conversations = $this->getColumnConversationsQuery($mailbox_id, 
                            null,
                            $request->column_id,
                            $params,
                            $request->offset
                        )->get();
                       
                        // Preload conversations data.
                        $conversations = $this->loadConversationsData($conversations);

                        foreach ($conversations as $conversation) {
                            $cards[] = KnCard::create([
                                'conversation' => $conversation,
                                'kn_column_id' => $request->column_id,
                                'kn_swimlane_id' => 0,
                                'linked' => $mailbox_id ?? false,
                            ]);
                        }

                        $response['html'] = \View::make('kanban::partials/cards')->with([
                            'cards' => $cards,
                            'mailbox_id' => $mailbox_id,
                            'board_id' => $board_id,
                        ])->render();
                        
                    } else {
                        // Board cards.
                        $cards = [];

                        $conversations = $this->getColumnConversationsQuery(null, 
                            $board_id,
                            $request->column_id,
                            $params,
                            $request->offset,
                            $request->swimlane_id
                        )->get();
                       
                        // Preload conversations data.
                        $conversations = $this->loadConversationsData($conversations);

                        foreach ($conversations as $conversation) {
                            $cards[] = KnCard::create([
                                'id' => $conversation->kn_card_id,
                                'name' => $conversation->kn_card_name,
                                'conversation' => $conversation,
                                'kn_column_id' => $request->column_id,
                                'kn_swimlane_id' => 0, // todo
                                'linked' => $conversation->linked,
                            ]);
                        }

                        $response['html'] = \View::make('kanban::partials/cards')->with([
                            'cards' => $cards,
                            'mailbox_id' => $mailbox_id,
                            'board_id' => $board_id,
                        ])->render();
                    }
                    $response['status'] = 'success';
                }
                break;

            // Show board
            case 'show':
                $params = $this->getParams($request);

                $mailbox_id = $params['mailbox_id'] ?? '';
                $mailbox = null;

                if ($mailbox_id > 0) {
                    $mailbox = Mailbox::find($mailbox_id);
                    if (!$mailbox) {
                        $response['msg'] = __('Mailbox not found');
                    }
                }

                if (!$response['msg'] && $mailbox && !$mailbox->userHasAccess($user->id, $user)) {
                    $response['msg'] = __('Not enough permissions');
                }

                $board_id = $params['board_id'] ?? '';
                $board = null;

                if ($board_id) {
                    $board = KnBoard::find($board_id);
                    if (!$board) {
                        $response['msg'] = __('Board not found');
                    }
                }

                if (!$response['msg'] && $board && !$board->userCanView($user)) {
                    $response['msg'] = __('Not enough permissions');
                }

                if (!$response['msg']) {
                    if (!$mailbox) {
                        $mailbox = \Kanban::getGlobalMailbox();
                    }
                    if ($mailbox_id) {
                        $data = $this->getMailboxData($mailbox, $params);
                    } else {
                        $data = $this->getBoardData($board, $params);
                    }
                    $data['mailbox_id'] = $mailbox_id;
                    $data['board_id'] = $board_id;
                    $data['params'] = $params;
                    $response['html'] = \View::make('kanban::partials/board')->with($data)->render();
                    $response['status'] = 'success';
                }
                break;

            // Move card to column.
            case 'move_card':
                $params = $this->getParams($request);

                $conversation_id = $request->conversation_id;
                $card = null;

                if (!$request->card_id) {
                    // Mailbox
                    if ($conversation_id) {
                        $conversation = Conversation::find($conversation_id);
                        if ($conversation) {
                            $response['status'] = 'success';
                        } else {
                            $response['msg'] = __('Conversation not found');
                        }
                    } else {
                        $response['msg'] = __('Conversation not found');
                    }
                } else {
                    // Board
                    $card = KnCard::find($request->card_id);
                    if ($card) {
                        if (!$card->kn_board->userCanView($user)) {
                            $response['status'] = 'Not enough permissions';
                        } else {
                            $conversation = $card->conversation;
                            $response['status'] = 'success';
                        }
                    } else {
                        $response['msg'] = __('Card not found');
                    }
                }

                if (!$response['msg']) {
                    switch ($request->group_by) {
                        case \Kanban::GROUP_BY_COLUMN:
                            $card->changeColumnAndSwimlane($request->column_id, $request->swimlane_id, $request->prev_card_id, $user);
                            break;

                        case \Kanban::GROUP_BY_ASSIGNEE:
                            if ($conversation->user_id != $request->column_id) {
                                $conversation->changeUser($request->column_id, $user);
                            }
                            break;

                        case \Kanban::GROUP_BY_STATUS:
                            if ($conversation->status != $request->column_id) {
                                $conversation->changeStatus($request->column_id, $user);
                            }
                            break;

                        case \Kanban::GROUP_BY_TAG:
                            if (\Module::isActive('tags')) {
                                $tag = \Modules\Tags\Entities\Tag::find($request->column_id);
                                if ($tag) {
                                    \Modules\Tags\Entities\Tag::add($tag->name, $conversation_id);
                                }
                            }
                            break;
                    }
                    // Card was moved to another swimlane.
                    if ($request->group_by != \Kanban::GROUP_BY_COLUMN
                        && !empty($card) && $card->kn_swimlane_id != $request->swimlane_id
                    ) {
                        $card->changeColumnAndSwimlane($request->column_id, $request->swimlane_id, $request->prev_card_id, $user);
                    }
                    // Close or open.
                    if ($request->group_by != \Kanban::GROUP_BY_STATUS) {
                        // Close conversation.
                        if ($conversation->status != Conversation::STATUS_CLOSED && (int)$request->closed) {
                            $conversation->changeStatus(Conversation::STATUS_CLOSED, $user);
                        }
                        // Open conversation.
                        if ($conversation->status == Conversation::STATUS_CLOSED && !(int)$request->closed) {
                            if ($conversation->last_reply_from == Conversation::PERSON_USER) {
                                $conversation->changeStatus(Conversation::STATUS_PENDING, $user);
                            } else {
                                $conversation->changeStatus(Conversation::STATUS_ACTIVE, $user);
                            }
                        }
                    }
                }
                break;

            // New board.
            case 'new_board':
                $board = new KnBoard();
                $board->name = $request->name;
                $board->mailbox_id = $request->mailbox_id;
                $board->created_by_user_id = $user->id;
                $board->columns = $this->processColumns($request->columns);
                $board->swimlanes = $this->processSwimlanes($request->swimlanes);
                $board->save();

                $response['status'] = 'success';
                $response['board_url'] = route('kanban.show', ['kn' => ['board_id' => $board->id]]);
                break;

            // Update board.
            case 'update_board':
                $board = KnBoard::find($request->board_id);
                if (!$board) {
                    $response['msg'] = __('Board not found');
                } elseif (!$board->userCanUpdate($user)) {
                    $response['msg'] = __('Not enough permissions');
                } else {
                    $board->name = $request->name;
                    $board->mailbox_id = $request->mailbox_id;
                    $board->columns = $this->processColumns($request->columns, $request->board_id);
                    $board->swimlanes = $this->processSwimlanes($request->swimlanes, $request->board_id);
                    $board->save();

                    $response['status'] = 'success';
                }
                break;

            // Count cards connected to the board.
            case 'delete_column':
                $response['confirmation_text'] = '';
                $column_cards = KnCard::where('kn_board_id', $request->board_id)
                    ->where('kn_column_id', $request->column_id)
                    ->count();
                if ($column_cards) {
                    $response['confirmation_text'] = __(':column_cards card(s) connected to this column will be deleted. Would you like to continue?', [
                        'column_cards' => $column_cards
                    ]);
                }
                $response['status'] = 'success';
                break;

            // Count cards connected to the board.
            case 'delete_swimlane':
                $response['confirmation_text'] = '';
                $swimlane_cards = KnCard::where('kn_board_id', $request->board_id)
                    ->where('kn_swimlane_id', $request->swimlane_id)
                    ->count();
                if ($swimlane_cards) {
                    $response['confirmation_text'] = __(':swimlane_cards card(s) connected to this swimlane will be deleted. Would you like to continue?', [
                        'swimlane_cards' => $swimlane_cards
                    ]);
                }
                $response['status'] = 'success';
                break;

            // New card.
            case 'new_card':
                // Check conversation.
                $conversation = null;
                if (!empty($request->conversation_number)) {
                    $conversation = Conversation::where(Conversation::numberFieldName(), $request->conversation_number)->first();
                    if (!$conversation) {
                        $response['msg'] = __('Conversation with #:number number not found', ['number' => $request->conversation_number]);
                    } elseif (!$conversation->mailbox->userHasAccess($user->id, $user)) {
                        $response['msg'] = __("You don't have access to the #:number conversation", ['number' => $request->conversation_number]);
                    }
                }
                if (!$response['msg']) {
                    $card = KnCard::create([
                        'name' => $request->name,
                        'kn_board_id' => $request->kn_board_id,
                        'kn_column_id' => $request->kn_column_id,
                        'kn_swimlane_id' => $request->kn_swimlane_id,
                        'body' => $request->body ?: $request->name,
                        'created_by_user_id' => $user->id,
                        'imported' => (isset($request->kn_notify_users) ? false : true),
                        'conversation_id' => $conversation->id ?? null,
                    ], true);

                    if ($card) {
                        // Depending on group by update conversation.
                        if (!$conversation) {
                            $conversation = $card->conversation;
                        }
                        switch ($request->group_by) {
                            case \Kanban::GROUP_BY_STATUS:
                                $conversation->changeStatus($request->group_by_id, $user);
                                break;
                            case \Kanban::GROUP_BY_ASSIGNEE:
                                $conversation->changeUser($request->group_by_id, $user);
                                break;
                            case \Kanban::GROUP_BY_TAG:
                                if (\Module::isActive('tags')) {
                                    $tag = \Modules\Tags\Entities\Tag::find($request->group_by_id);
                                    if ($tag) {
                                        \Modules\Tags\Entities\Tag::add($tag->name, $conversation->id);
                                    }
                                }
                                break;
                        }

                        $response['status'] = 'success';
                        $response['msg_success'] = __("Card created");
                        $response['board_url'] = route('kanban.show', ['kn' => ['board_id' => $card->kn_board_id]]);
                    }
                }
                break;

            // Update card.
            case 'update_card':
                $card = KnCard::find($request->card_id);
                if (!$card || !$card->userCanUpdate($user)) {
                    \Helper::denyAccess();
                }
                $card->name = $request->name;
                $card->save();
                $response['status'] = 'success';
                $response['msg_success'] = __("Card updated");
                break;

            // Delete board.
            case 'delete_board':
                $board = KnBoard::find($request->board_id);
                if (!$board) {
                    $response['msg'] = __('Board not found');
                } elseif (!$board->userCanDelete($user)) {
                    $response['msg'] = __('Not enough permissions');
                } else {
                    $board->deleteBoard();
                    \Session::flash('flash_success_floating', __('Board deleted'));
                    $response['status'] = 'success';
                }
                break;

            // Delete card.
            case 'delete_card':
                $card = KnCard::find($request->card_id);
                if (!$card) {
                    $response['msg'] = __('Card not found');
                } elseif (!$card->linked) {
                    $response['msg'] = __('The card is not linked to a conversation and can not be deleted');
                } elseif (!$card->kn_board->userCanView($user)) {
                    $response['msg'] = __('Not enough permissions');
                } else {
                    $card->deleteCard();
                    \Session::flash('flash_success_floating', __('Card deleted'));
                    $response['status'] = 'success';
                }
                break;

            default:
                $response['msg'] = 'Unknown action';
                break;
        }

        if ($response['status'] == 'error' && empty($response['msg'])) {
            $response['msg'] = 'Unknown error occurred';
        }

        return \Response::json($response);
    }

    public function getColumnConversationsQuery($mailbox_id, $board_id, $group_id, $params, $offset = 0, $swimlane_id = null)
    {
        $query = $this->getMainConversationsQuery($mailbox_id, $board_id, $params, $group_id, $swimlane_id);

        // Skip and limit.
        $query->skip($offset)->limit(\Kanban::CARDS_PER_COLUMN+1);

        // Sort.
        switch ($params['sort']) {
            case \Kanban::SORT_MANUAL:
                $query->orderBy('kn_cards.sort_order', 'desc');
                break;
            case \Kanban::SORT_ACTIVE:
                $query->orderBy('status', 'asc')->orderBy('last_reply_at', 'desc');
                break;
            case \Kanban::SORT_LAST_REPLY_NEW:
                $query->orderBy('last_reply_at', 'desc');
                break;
            case \Kanban::SORT_LAST_REPLY_OLD:
                $query->orderBy('last_reply_at', 'asc');
                break;
            case \Kanban::SORT_CREATED_NEW:
                $query->orderBy('created_at', 'desc');
                break;
            case \Kanban::SORT_CREATED_OLD:
                $query->orderBy('created_at', 'asc');
                break;
            // For sorting closed conversations
            case \Kanban::SORT_CLOSED_NEW:
                $query->orderBy('closed_at', 'desc');
                break;
        }
            
        return $query;
    }

    public function getMainConversationsQuery($mailbox_id, $board_id, $params, $group_id = null, $swimlane_id = null)
    {
        $select = ['conversations.*'];

        // Filter: State.
        if (!empty($params['filters']['state'])) {
            if (count($params['filters']['state']) == 1 && isset($params['filters']['state'][0])) {
                $query = Conversation::where('state', $params['filters']['state'][0]);
            } else {
                $query = Conversation::whereIn('state', $params['filters']['state']);
            }
        } else {
            $query = Conversation::query();
        }

        if ($board_id) {
            $query->join('kn_cards', function ($join) {
                $join->on('kn_cards.conversation_id', '=', 'conversations.id');
            })->where('kn_cards.kn_board_id', $board_id);

            if (!empty($swimlane_id)) {
                $query->where('kn_cards.kn_swimlane_id', $swimlane_id);
            }

            $select[] = 'kn_cards.id as kn_card_id';
            $select[] = 'kn_cards.name as kn_card_name';
            $select[] = 'kn_cards.kn_column_id';
            $select[] = 'kn_cards.kn_swimlane_id';
            $select[] = 'kn_cards.linked';
            //$select[] = 'kn_cards.sort_order';
        } elseif ($mailbox_id > 0) {
            $query->where('mailbox_id', $mailbox_id);
        } elseif ($mailbox_id == \Kanban::ALL_MAILBOXES) {
            $mailbox_ids = auth()->user()->mailboxes_cached->pluck('id');
            if ($mailbox_ids) {
                $query->whereIn('mailbox_id', $mailbox_ids);
            }
        }

        // Filter: Status.
        $status = $params['filters'][\Kanban::FILTER_BY_STATUS] ?? [Conversation::STATUS_ACTIVE, Conversation::STATUS_PENDING];
        // Remove Closed.
        if ($params['group_by'] == \Kanban::GROUP_BY_STATUS && $group_id == Conversation::STATUS_CLOSED) {
            // Keep Conversation::STATUS_CLOSED.
        } elseif ($params['filters'][\Kanban::FILTER_BY_STATUS] != [Conversation::STATUS_CLOSED]) {
            foreach ($status as $s => $status_item) {
                if ($status_item == Conversation::STATUS_CLOSED) {
                    unset($status[$s]);
                }
            }
        }
        $query->whereIn('status', $status);
        // Filter: Assignee.
        if (!empty($params['filters']['user_id'])) {
            $query->whereIn('user_id', $params['filters']['user_id']);
        }
        // Filter: Tag.
        if (!empty($params['filters'][\Kanban::FILTER_BY_TAG]) && \Module::isActive('tags')) {
            if ($params['group_by'] != \Kanban::GROUP_BY_TAG) {
                $query->join('conversation_tag', function ($join) {
                    $join->on('conversation_tag.conversation_id', '=', 'conversations.id');
                });
            }
            $query->whereIn('conversation_tag.tag_id', $params['filters'][\Kanban::FILTER_BY_TAG]);
        }
        // Filter: Column.
        if (!empty($params['filters'][\Kanban::FILTER_BY_COLUMN])) {
            $query->whereIn('kn_cards.kn_column_id', $params['filters'][\Kanban::FILTER_BY_COLUMN]);
        }
        // Filter: Custom Fields.
        if (!empty($params['filters'][\Kanban::FILTER_BY_CF]) 
            && is_array($params['filters'][\Kanban::FILTER_BY_CF])
            && \Module::isActive('customfields')
        ) {
            $query->join('conversation_custom_field', function ($join) {
                $join->on('conversation_custom_field.conversation_id', '=', 'conversations.id');
            });
            $cf_filters = $params['filters'][\Kanban::FILTER_BY_CF];
            $query->where(function ($q) use ($cf_filters) {
                foreach ($cf_filters as $filter_json) {
                    $filter = json_decode($filter_json, true);
                    if (empty($filter['id']) || empty($filter['op'])) {
                        continue;
                    }
                    $q->orWhere('conversation_custom_field.custom_field_id', '=', $filter['id'])
                        ->where('conversation_custom_field.value', $filter['op'], $filter['value']);
                }
            });
        }

        // Group by.
        if ($group_id !== null) {
            if ($params['group_by'] == \Kanban::GROUP_BY_TAG) {
                $query->join('conversation_tag', function ($join) {
                    $join->on('conversation_tag.conversation_id', '=', 'conversations.id');
                })->where('conversation_tag.tag_id', $group_id);
                $select[] = 'conversation_tag.tag_id as tag';
            } else {
                if ($params['group_by'] == \Kanban::GROUP_BY_ASSIGNEE && !$group_id) {
                    // Unassigned.
                    $group_id = null;
                }
                $group_by = $params['group_by'];
                if ($group_by == \Kanban::GROUP_BY_COLUMN) {
                    $group_by = 'kn_cards.kn_column_id';
                }
                $query->where($group_by, $group_id);
            }
        }

        // Select.
        $query->select($select);

        return $query;
    }

    public function loadConversationsData($conversations)
    {
        if (!$conversations instanceof \Illuminate\Support\Collection) { 
            $conversations = collect($conversations);
        }
        if (\Module::isActive('tags')) {
            $conversations = \Eventy::filter('conversations_table.preload_table_data', $conversations);
        }
        // Preload users and customers
        Conversation::loadUsers($conversations);
        Conversation::loadCustomers($conversations);
        Conversation::loadMailboxes($conversations);
        if (\Module::isActive('customfields')) {
            $conversations = \Modules\CustomFields\Entities\CustomField::loadCustomFieldsForConversations($conversations);
        }

        return $conversations;
    }

    public function getParams($request)
    {
        $default = [
            'sort' => \Kanban::SORT_ACTIVE,
            'filters' => \Kanban::$default_filters,
            'group_by' => \Kanban::GROUP_BY_STATUS,
        ];

        if (!empty($request->kn['board_id'])) {
            $default['group_by'] = \Kanban::GROUP_BY_COLUMN;
            $default['sort'] = \Kanban::SORT_MANUAL;
        }

        $params = array_merge($default, $request->kn ?? []);

        // Filters.
        foreach ($params['filters'] as $i => $value) {
            if (!is_array($value)) {
                $params['filters'][$i] = explode(\Kanban::FILTERS_SEPARATOR, $value);
            }
        }
        $params['filters'] = array_merge($default['filters'], $params['filters']);

        // Group by.
        if ($params['group_by'] == \Kanban::GROUP_BY_TAG && !\Module::isActive('tags')) {
            $params['group_by'] = \Kanban::GROUP_BY_ASSIGNEE;
        }

        if (!empty($params['mailbox_id']) && !empty($params['board_id'])) {
            unset($params['board_id']);
        }

        return $params;
    }

    /**
     * Ajax controller.
     */
    public function ajaxHtml(Request $request)
    {
        switch ($request->action) {
            case 'new_board':
                $board = new KnBoard();
                return view('kanban::ajax_html/update_board', [
                    'mode' => 'create',
                    'board' => $board,
                ]);
                break;

            case 'update_board':
                $board = KnBoard::find($request->board_id);
                if (!$board || !$board->userCanUpdate(auth()->user())) {
                    \Helper::denyAccess();
                }
                return view('kanban::ajax_html/update_board', [
                    'mode' => 'update',
                    'board' => $board,
                ]);
                break;

            case 'new_card':
                $card = new KnCard();
                $card->kn_board_id = $request->kn_board_id;
                $card->kn_column_id = $request->kn_column_id;
                $card->kn_swimlane_id = $request->kn_swimlane_id;

                $board = null;
                $boards = [];
                if ($request->kn_board_id) {
                    $board = KnBoard::find($request->kn_board_id);
                } else {
                    $boards = KnBoard::boardsUserCanView(auth()->user());
                }

                return view('kanban::ajax_html/update_card', [
                    'mode' => 'create',
                    'card' => $card,
                    'board' => $board,
                    'boards' => $boards,
                    'group_by' => $request->group_by,
                    'group_by_id' => $request->group_by_id,
                    'conversation_number' => $request->conversation_number ?? '',
                ]);
                break;

            case 'update_card':
                $card = KnCard::find($request->card_id);
                if (!$card || !$card->userCanUpdate(auth()->user())) {
                    \Helper::denyAccess();
                }
                return view('kanban::ajax_html/update_card', [
                    'mode' => 'update',
                    'card' => $card,
                ]);
                break;

            case 'filter':
                $filters = [];
                $mailbox = null;
                $mailbox_id = null;
                $board = null;
                $user = auth()->user();
                $selected = $request->selected ?: [];
                $custom_fields = [];
                if (!empty($request->mailbox_id)) {
                    if ($request->mailbox_id != \Kanban::ALL_MAILBOXES) {
                        $mailbox = Mailbox::find($request->mailbox_id);
                        if (!$mailbox || !$mailbox->userHasAccess($user->id, $user)) {
                            \Helper::denyAccess();
                        }
                        $mailbox_id = $mailbox->id;
                    }
                } else {
                    $board = KnBoard::find($request->board_id);
                    if (!$board || !$board->userCanView($user)) {
                        \Helper::denyAccess();
                    }
                    $mailbox_id = $board->mailbox_id;
                }
                switch ($request->filter) {
                    case \Kanban::FILTER_BY_STATUS:
                        $filters = [
                            Conversation::STATUS_ACTIVE => Conversation::statusCodeToName(Conversation::STATUS_ACTIVE),
                            Conversation::STATUS_PENDING => Conversation::statusCodeToName(Conversation::STATUS_PENDING),
                            Conversation::STATUS_AWAITING_CUSTOMER => Conversation::statusCodeToName(Conversation::STATUS_AWAITING_CUSTOMER),
                            Conversation::STATUS_AWAITING_SUPPLIER => Conversation::statusCodeToName(Conversation::STATUS_AWAITING_SUPPLIER),
                            Conversation::STATUS_AWAITING_TODO => Conversation::statusCodeToName(Conversation::STATUS_AWAITING_TODO),
                            Conversation::STATUS_AWAITING_PROJECT => Conversation::statusCodeToName(Conversation::STATUS_AWAITING_PROJECT),
                            Conversation::STATUS_AWAITING_DUPLICATE => Conversation::statusCodeToName(Conversation::STATUS_AWAITING_DUPLICATE),
                            Conversation::STATUS_CLOSED => Conversation::statusCodeToName(Conversation::STATUS_CLOSED),
                            Conversation::STATUS_SPAM => Conversation::statusCodeToName(Conversation::STATUS_SPAM),
                        ];
                        break;

                    case \Kanban::FILTER_BY_ASSIGNEE:
                        $users = [];
                        if ($request->mailbox_id) {
                            if ($request->mailbox_id == \Kanban::ALL_MAILBOXES) {
                                // All accessible users.
                                $users = auth()->user()->whichUsersCanView();
                            } else {
                                $users = $mailbox->usersHavingAccess();
                            }
                        } elseif ($board && $board->mailbox) {
                            $users = $board->mailbox->usersHavingAccess();
                        }
                        foreach ($users as $user_item) {
                            $filters[$user_item->id] = $user_item->getFullName();
                        }
                        break;

                    case \Kanban::FILTER_BY_STATE:
                        $filters = [
                            Conversation::STATE_PUBLISHED => Conversation::stateCodeToName(Conversation::STATE_PUBLISHED),
                            Conversation::STATE_DRAFT => Conversation::stateCodeToName(Conversation::STATE_DRAFT),
                            Conversation::STATE_DELETED => Conversation::stateCodeToName(Conversation::STATE_DELETED),
                        ];
                        break;

                    case \Kanban::FILTER_BY_TAG:
                        if (\Module::isActive('tags')) {
                            $tags = \Modules\Tags\Entities\Tag::select(['id', 'name'])
                                ->where('counter', '>', 0)
                                ->get();
                            $tags = $tags->sortBy('name');
                            foreach ($tags as $tag) {
                                $filters[$tag->id] = $tag->name;
                            }
                        }
                        break;

                    case \Kanban::FILTER_BY_COLUMN:
                        if ($board) {
                            foreach ($board->columns as $column) {
                                $filters[$column['id']] = $column['name'];
                            }
                            if (!$selected) {
                                $selected = array_keys($filters);
                            }
                        }
                        break;

                    case \Kanban::FILTER_BY_CF:
                        if (\Module::isActive('customfields')) {
                            $custom_fields = \Modules\CustomFields\Entities\CustomField::getMailboxCustomFields($mailbox_id);
                            foreach ($custom_fields as $i => $custom_field) {
                                $custom_field->value = null;
                                foreach ($selected as $selected_item) {
                                    $selected_data = \Kanban::parseSelectedCf($selected_item);
                                    if (isset($selected_data['id']) 
                                        && $selected_data['id'] == $custom_field->id
                                        && isset($selected_data['op']) 
                                        && isset($selected_data['value']) 
                                    ) {
                                        $custom_field->op = $selected_data['op'];
                                        $custom_field->value = $selected_data['value'];
                                    }
                                }
                            }
                        }
                        break;
                }
                return view('kanban::ajax_html/filter', [
                    'filter' => $request->filter,
                    'filters' => $filters,
                    'custom_fields' => $custom_fields,
                    'selected' => $selected,
                ]);
                break;
        }

        abort(404);
    }

    public function processColumns($columns, $board_id = null)
    {
        if (!is_array($columns)) {
            $columns = [];
        }
        foreach ($columns as $i => $column) {
            if ($column['id'] == \Kanban::PATTERN_ID) {
                unset($columns[$i]);
            }
        }
        // Remove cards for deleted columns.
        if ($board_id) {
            $board = KnBoard::find($board_id);
            if ($board && $board->columns) {
                // Check which columns were removed.
                $deleted_column_ids = [];
                foreach ($board->columns as $column) {
                    $found = false;
                    foreach ($columns as $new_column) {
                        if ($new_column['id'] == $column['id']) {
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) {
                        $deleted_column_ids[] = $column['id'];
                    }
                }
                if ($deleted_column_ids) {
                    KnCard::where('kn_board_id', $board_id)
                        ->whereIn('kn_column_id', $deleted_column_ids)
                        ->delete();
                }
            }
        }

        return $columns;
    }

    public function processSwimlanes($swimlanes, $board_id = null)
    {
        if (!is_array($swimlanes)) {
            $swimlanes = [];
        }
        foreach ($swimlanes as $i => $swimlane) {
            if ($swimlane['id'] == \Kanban::PATTERN_ID) {
                unset($swimlanes[$i]);
            }
        }
        // Remove cards for deleted swimlanes.
        if ($board_id) {
            $board = KnBoard::find($board_id);
            if ($board && $board->swimlanes) {
                // Check which swimlanes were removed.
                $deleted_swimlane_ids = [];
                foreach ($board->swimlanes as $swimlane) {
                    $found = false;
                    foreach ($swimlanes as $new_swimlane) {
                        if ($new_swimlane['id'] == $swimlane['id']) {
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) {
                        $deleted_swimlane_ids[] = $swimlane['id'];
                    }
                }
                if ($deleted_swimlane_ids) {
                    KnCard::where('kn_board_id', $board_id)
                        ->whereIn('kn_swimlane_id', $deleted_swimlane_ids)
                        ->delete();
                }
            }
        }

        return $swimlanes;
    }
}
