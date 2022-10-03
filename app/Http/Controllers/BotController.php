<?php

namespace App\Http\Controllers;

use App\Models\Group;
use Illuminate\Http\Request;
use function PHPUnit\Framework\isEmpty;
use function PHPUnit\Framework\isNull;

class BotController extends Controller
{
    public $token = '5538558009:AAF3eNJQRlXR9TfXKHsoewPAyVbGEOxJum0';
    public $admin = '716294792';
    public function bot($method, $datas = []){
        $url = "https://api.telegram.org/bot{$this->token}/{$method}";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $datas);
        $res = curl_exec($ch);
        if (curl_error($ch)) {
            var_dump(curl_error($ch));
        } else {
            http_response_code(200);
            return json_decode($res);
        }
    }

    public function index()
    {
        date_default_timezone_set("Asia/Tashkent");
        $update = json_decode(file_get_contents('php://input'));
        if (isset($update->my_chat_member)){
            $my_chat_member = $update->my_chat_member;
            $chat = $my_chat_member->chat;
            $chat_id = $chat->id;
            $chat_title = $chat->title;
            $chat_type = $chat->type;
            $from = $my_chat_member->from;
            if ($chat_type == 'supergroup'){
                $new_chat_member = $my_chat_member->new_chat_member;
                if($new_chat_member->status == 'administrator'){
                    $group = Group::where('group_id', $chat_id)->get()->first();
                    if (!$group){
                        $group = Group::create([
                            'group_id' => $chat_id,
                        ]);
                        $this->sendMessage($from->id, "Siz botni {$chat_title} guruhiga admin qildingiz!");
                    }
                }else{
                    Group::where('group_id', $chat_id)->get()->first()->delete();
                    $this->sendMessage($from->id, "Siz botni {$chat_title} guruhida adminlikdan chiqardingiz va guruh ma'lumotlar bazasidan o'chirildi!");
                }
            }
        }
        if ($update){
            $this->json($update);
        }
        if (isset($update->message)){
            $message = $update->message;
            $message_id = $message->message_id;
            $text = $message->text;
            $chat = $message->chat;
            $chat_id = $chat->id;
            isset($chat->title) ? $chat_title = $chat->title : $chat_title = null;
            $chat_username = $chat->username;
            $chat_type = $chat->type;
            $from = $message->from;
            $from_id = $from->id;
            $fname = $from->first_name;

            if ($chat_type == 'supergroup'){
                $check = $this->getGroup($chat_id);
                if (!$check->send_by_channel){
                    if (isset($message->sender_chat)){
                        $this->deleteMessage($chat_id, $message_id);
                        $txt = "[{$message->sender_chat->title}](https://t.me/{$message->sender_chat->username}) *\niltimos, shaxsiy akkauntingizdan xabar yozing*";
                        $this->sendMessage($chat_id, $txt, [
                            'parse_mode' => 'markdown',
                        ]);

                        exit();
                    }
                }
                if (!empty($check->channel)){
                    $channel = $this->bot('getChat', ['chat_id' => $check->channel]);
                    if (!$channel->ok){
                        $check = $this->bot('getChatMember', [
                            'chat_id' => $channel->result->id,
                            'user_id' => $from_id,
                        ]);
                        if ($check->result->status == 'left'){
                            $this->deleteMessage($chat_id, $message_id);
                            $txt = "*Salom* [{$fname}](tg://user?id=$from_id) *\nKanalga A'zo bo'ling bo'lmasangiz guruhimizga yoza olmaysiz*";
                            $btn = json_encode([
                                'inline_keyboard'=>[
                                    [['text' => "📡Kanalimiz" , 'url' =>"https://t.me/{$channel->result->username}"]],
                                ]
                            ]);
                            $this->sendMessage($chat_id, $txt, [
                                'parse_mode' => 'markdown',
                                'reply_markup' => $btn
                            ]);
                            exit();
                        }
                    }else{
                        $check = $this->bot('getChatAdministrators', ['chat_id' => $chat_id]);
                        foreach ($check->result as $result){
                            if(!$result->user->is_bot){
                                $txt = "<b><a href='https://t.me/{$chat_username}'>{$chat_title}</a> guruhga bo'glangan kanalda xatolik bor!</b>";
                                $this->sendMessage($result->user->id, $txt, ['parse_mode' => 'html']);
                            }
                        }

                    }
                }
                if ($text == '/channel'){
                    $channel = Group::where('group_id', $chat_id)->get()->first()->channel;
                    if (empty($channel)){
                        $txt = "*Guruhga kanal bog'lanmagan. Agar kanalga obuna bo'lmasa, guruhga yoza olmaydigan qilish:* `/setchannel @username`";
                        $this->sendMessage($chat_id, $txt, [
                            'parse_mode' => 'markdown',
                        ]);
                    }else{
                        $this->sendMessage($chat_id, "Guruhga biriktirilgan kanal: $channel");
                    }
                }
                if($this->isGroupAdmin($chat_id, $from_id)){
                    if ($text == "/unsetchannel"){
                        $this->deleteMessage($chat_id, $message_id);
                        $channel = Group::where('group_id', $chat_id)->get()->first()->channel;
                        if (empty($channel)){
                            $txt = "*Guruhga kanal bog'lanmagan. Agar kanalga obuna bo'lmasa, guruhga yoza olmaydigan qilish:* `/setchannel @username`";
                            $this->sendMessage($chat_id, $txt, [
                                'parse_mode' => 'markdown',
                            ]);
                        }else{
                            $this->updateGroup($chat_id, ['channel' => NULL]);
                            $this->sendMessage($chat_id, "*Guruhga biriktirilgan kanal $channel o'chirildi*", ['parse_mode' => 'markdown']);
                        }
                    }
                    if(mb_stripos($text,"/setchannel") !== false){
                        $this->deleteMessage($chat_id, $message_id);
                        $ex = explode(" ", $text);
                        if (count($ex) == 2){
                            $ex = $ex[1];
                            $check = $this->bot('getChat', ['chat_id' => $ex]);
                            if (!$check->ok){
                                $this->sendMessage($chat_id, "*Kanal topilmadi, tekshirib qaytadan yuboring!*", ['parse_mode' => 'markdown']);
                            }else{
                                $channel = $check->result->username;
                                $check = $this->bot('getChatAdministrators', ['chat_id' => $ex]);
                                if (!$check->ok){
                                    $this->sendMessage($chat_id, "*Botni kanalga admin qilib, qaytadan yuboring!*", ['parse_mode' => 'markdown']);
                                }else{
                                    $this->updateGroup($chat_id, [
                                        'channel' => "@{$channel}"
                                    ]);
                                    $txt = "*Guruhga @{$channel} kanali bo'glandi!*";
                                    $this->sendMessage($chat_id, $txt, ['parse_mode' => 'markdown']);
                                }

                            }
                        }
                    }
                    if(mb_stripos($text,"/message") !== false) {
                        $ex = explode(" ", $text);
                        if (count($ex) == 2) {
                            $ex = $ex[1];
                            if ($ex == 'off') {
                                $this->deleteMessage($chat_id, $message_id);
                                $this->bot('setChatPermissions', [
                                    'chat_id' => $chat_id,
                                    'permissions' => json_encode(['can_send_messages' => false]),
                                ]);
                                $this->sendMessage($chat_id, "Guruhda xabar yozish o'chirildi!");
                            } elseif ($ex == "on") {
                                $this->deleteMessage($chat_id, $message_id);
                                $this->bot('setChatPermissions', [
                                    'chat_id' => $chat_id,
                                    'permissions' => json_encode(['can_send_messages' => true]),
                                ]);
                                $this->sendMessage($chat_id, "Guruhda xabar yozish yoqildi, yozishingiz mumkin!");
                            }
                        }
                    }

                }
            }
        }
    }


    public function updateGroup($group_id, $datas)
    {
        return Group::where('group_id', $group_id)->first()->update($datas);
    }
    public function getGroup($group_id)
    {
        return Group::where('group_id', $group_id)->first();
    }
    public function isGroupAdmin($chat_id, $user_id)
    {
        $result = $this->bot('getChatMember', [
            'chat_id' => $chat_id,
            'user_id' => $user_id,
        ])->result->status;
        if($result == "administrator" or $result == "creator"){
            return true;
        }else{
            return false;
        }
    }

    public function sendMessage($chat_id, $text, $others = [])
    {
        if (empty($others)){
            return $this->bot('sendMessage', [
                'chat_id' => $chat_id,
                'text' => $text
            ]);
        }else{
            return $this->bot('sendMessage', array_merge(
                [
                    'chat_id' => $chat_id,
                    'text' => $text,
                ], $others
            ));
        }
    }
    public function deleteMessage($chat_id, $message_id = [])
    {
        $this->bot('deleteMessage', [
            'chat_id' => $chat_id,
            'message_id' => $message_id,
        ]);
    }

    public function json($update)
    {
        $this->sendMessage($this->admin, json_encode($update, JSON_PRETTY_PRINT));
    }
}
