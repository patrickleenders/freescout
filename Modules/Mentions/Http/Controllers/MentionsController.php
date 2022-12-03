<?php

namespace Modules\Mentions\Http\Controllers;

use App\Mailbox;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class MentionsController extends Controller
{
    /**
     * Ajax.
     */
    public function ajax(Request $request)
    {
        $response = [
            'status' => 'error',
            'msg'    => '', // this is error message
        ];

        switch ($request->action) {
            case 'users':
                $auth_user_id = auth()->id();

                $mailbox = Mailbox::find($request->mailbox_id);

                if ($mailbox) {
                    $users = $mailbox->usersAssignable();

                    // Make sure that current user has access.
                    if (in_array($auth_user_id, $users->pluck('id')->toArray())) {

                        $response['users'] = [];

                        foreach ($users as $user) {
                            if ($user->id != $auth_user_id) {
                                $response['users'][] = htmlspecialchars($user->getFullName()).'|'.$user->id;
                            }
                        }
                        $response['status'] = 'success';
                    }
                }
                break;

            default:
                $response['msg'] = 'Unknown action';
                break;
        }

        if ($response['status'] == 'error' && empty($response['msg'])) {
            $response['msg'] = 'Unknown error occured';
        }

        return \Response::json($response);
        //return \Response::json($response, 200, [], JSON_UNESCAPED_UNICODE);
    }
}
