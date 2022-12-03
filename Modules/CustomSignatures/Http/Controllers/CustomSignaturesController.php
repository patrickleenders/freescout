<?php

namespace Modules\CustomSignatures\Http\Controllers;

use App\Conversation;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class CustomSignaturesController extends Controller
{
    /**
     * Conversations ajax controller.
     */
    public function ajax(Request $request)
    {
        $response = [
            'status' => 'error',
            'msg'    => '', // this is error message
        ];

        $user = auth()->user();

        switch ($request->action) {

            // Update saved reply
            case 'load_signature':

                $signature = \CustomSignature::find($request->signature_id);
                if (!$signature) {
                    $response['msg'] = __('Signature not found');
                }
                if (!$response['msg'] && !$user->hasAccessToMailbox($signature->mailbox_id)) {
                    $response['msg'] = __('Not enough permissions');
                }
                if (!$response['msg']) {
                    $conversation = Conversation::find($request->conversation_id);
                    if (!$conversation) {
                        $conversation = new Conversation();
                        $conversation->mailbox_id = $signature->mailbox_id;
                    }
                    // Temporary substitute mailbox signature.
                    // Causes "Indirect modification of overloaded property App\Conversation::$mailbox has no effect"
                    // on some installations.
                    //$conversation->mailbox->signature = $signature->text;
                    $mailbox = $conversation->mailbox;
                    $mailbox->signature = $signature->text;
                    $response['html'] = $conversation->getSignatureProcessed([], true);

                    $response['status'] = 'success';
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
    }
}
