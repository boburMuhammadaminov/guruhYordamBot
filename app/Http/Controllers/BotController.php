<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupWarning;
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
            isset($message->text) ? $text = $message->text : $text = null;
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
                $group = $check;
                $n = $check->add_required_member;
                if (!$check->send_by_channel){
                    if (isset($message->sender_chat)){
                        $this->sendChatAction($chat_id);
                        $this->deleteMessage($chat_id, $message_id);
                        $txt = "[{$message->sender_chat->title}](https://t.me/{$message->sender_chat->username}) *iltimos, shaxsiy akkauntingizdan xabar yozing*";
                        $this->sendMessage($chat_id, $txt, [
                            'parse_mode' => 'markdown',
                        ]);

                        exit();
                    }
                }
                if (!empty($check->channel)){
                    $channel = $this->bot('getChat', ['chat_id' => $check->channel]);
                    if ($channel->ok){
                        if (!isset($message->sender_chat)){
                            $check = $this->bot('getChatMember', [
                                'chat_id' => $channel->result->id,
                                'user_id' => $from_id,
                            ]);
                            if ($check->result->status == 'left'){
                                $this->sendChatAction($chat_id);
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
                if (isset($message->text)){
                    if ($n > 0){
                        if(!$this->isGroupAdmin($chat_id, $from_id)){
                            $count = GroupMember::where('group_id', $chat_id)->where('user_id', $from_id)->get()->count();
                            if ($n != $count){
                                $this->sendChatAction($chat_id);
                                $count = $n - $count;
                                $this->deleteMessage($chat_id, $message_id);
                                $txt = "<b><a href='tg://user?{$from_id}'>{$fname}</a> guruhda yozish uchun {$count}/{$n} ta odam qoshishingiz kerak</b>";
                                $this->sendMessage($chat_id, $txt, ['parse_mode' => 'html']);
                                exit();
                            }
                        }
                    }
                }
                if ($text == '/channel'){
                    $this->sendChatAction($chat_id);
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
                if ($text == "/id"){
                    $this->sendChatAction($chat_id);
                    $this->sendMessage($chat_id, "*Sizning id raqamingiz: *`{$from_id}`", [
                       'parse_mode' => 'markdown',
                       'reply_to_message_id' => $message_id,
                    ]);
                }
                if ($text == "/gid"){
                    $this->sendChatAction($chat_id);
                    $this->sendMessage($chat_id, "*Guruhning id raqami: *`{$chat_id}`", [
                       'parse_mode' => 'markdown',
                       'reply_to_message_id' => $message_id,
                    ]);
                }
                if ($text == "/mymembers"){
                    $this->sendChatAction($chat_id);
                    $count = GroupMember::where('group_id', $chat_id)->where('user_id', $from_id)->get()->count();
                    $txt = "<b>Siz guruhga {$count} ta odam qoshigansiz</b>";
                    $this->sendMessage($chat_id, $txt, [
                        'parse_mode' => 'html',
                        'reply_to_message_id' => $message_id,
                    ]);
                }
                if (isset($message->from) and isset($message->new_chat_participant) and isset($message->new_chat_member) and isset($message->new_chat_members)){
                    $this->deleteMessage($chat_id, $message_id);
                    if (!$message->new_chat_participant->is_bot){
                        $this->sendChatAction($chat_id);
                        $check = GroupMember::where('group_id', $chat_id)->where('added_user', $message->new_chat_participant->id)->first();
                        if (!$check){
                            $check = GroupMember::create([
                                'group_id' => $chat_id,
                                'user_id' => $from_id,
                                'added_user' => $message->new_chat_participant->id,
                            ]);
                            $count = GroupMember::where('group_id', $chat_id)->where('user_id', $from_id)->get()->count();
                            if ($count != $n){
                                $txt = "<b> <a href='tg://user?id={$from_id}'>{$fname}</a> guruhga a'zo qoshdingiz. Yana {$count}/{$n} ta odam qoshishingiz kerak</b>";
                                $this->sendMessage($chat_id, $txt, ['parse_mode' => 'html']);
                            }else{
                                $txt = "<b> <a href='tg://user?id={$from_id}'>{$fname}</a> guruhga {$n} ta odam qoshitingiz, endi guruhda yozishingiz mumkin</b>";
                                $this->sendMessage($chat_id, $txt, ['parse_mode' => 'html']);
                            }
                        }
                    }
                }
                if (isset($message->from) and isset($message->left_chat_participant) and isset($message->left_chat_member)){
                    $this->deleteMessage($chat_id, $message_id);
                    if (!$message->left_chat_participant->is_bot){
                        $this->sendChatAction($chat_id);
                        $check = GroupMember::where('group_id', $chat_id)->where('added_user', $message->left_chat_participant->id)->first();
                        if($check){
                            $check->delete();
                            $result = $this->bot('getChatMember', [
                                'chat_id' => $chat_id,
                                'user_id' => $check->user_id,
                            ]);
                            if ($result->result->status != "left"){
                                $count = $n - GroupMember::where('group_id', $chat_id)->where('user_id', $check->user_id)->get()->count();
                                $txt = "<b> <a href='tg://user?id={$check->user_id}'>{$result->result->user->first_name}</a> siz qoshgan a'zo guruhni tark etdi. Yana {$count}/{$n} ta odam qoshishingiz kerak</b>";
                                $this->sendMessage($chat_id, $txt, ['parse_mode' => 'html']);
                            }
                        }
                    }
                }
                if($this->isGroupAdmin($chat_id, $from_id)){
                    if ($text == "/offchannel"){
                        $this->deleteMessage($chat_id, $message_id);
                        $this->sendChatAction($chat_id);
                        $this->updateGroup($chat_id, ['send_by_channel' => false]);
                        $txt = "*Guruhda kanal nomi orqali yozishni taqiqlandi!*";
                        $this->sendMessage($chat_id, $txt, ['parse_mode' => 'markdown']);
                    }
                    if ($text == "/onchannel"){
                        $this->deleteMessage($chat_id, $message_id);
                        $this->sendChatAction($chat_id);
                        $this->updateGroup($chat_id, ['send_by_channel' => true]);
                        $txt = "*Guruhda kanal nomi orqali yozishga ruxsat berildi!*";
                        $this->sendMessage($chat_id, $txt, ['parse_mode' => 'markdown']);
                    }
                    if ($text == "/unsetchannel"){
                        $this->deleteMessage($chat_id, $message_id);
                        $this->sendChatAction($chat_id);
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
                    if (isset($message->reply_to_message)){
                        $reply_to_message = $message->reply_to_message;
                        $rfrom = $reply_to_message->from;
                        $rchat = $reply_to_message->chat;
                        if ($text == "/warn"){
                            $this->sendChatAction($chat_id);
                            $warning = GroupWarning::where('group_id', $chat_id)->where('user_id', $rfrom->id)->get()->first();
                            if (empty($warning)){
                                $warning = GroupWarning::create([
                                    'group_id' => $chat_id,
                                    'user_id' => $rfrom->id,
                                    'amount' => 1
                                ]);
                                $txt = "<b><a href='tg://user?id={$rfrom->id}'>{$rfrom->first_name}</a> ogohlantirish oldi</b>\nEndi undagi ogohlantirishlar soni <b>{$warning->amount}</b>/{$group->warning}";
                                $this->sendMessage($chat_id, $txt, ['parse_mode' => 'html']);
                            }else{
                                if ($group->warning == $warning->amount + 1){
                                    $time = strtotime("+10800000 minutes");
                                    $this->bot('kickChatMember', [
                                        'chat_id' => $chat_id,
                                        'user_id' => $rfrom->id,
                                        'until_date' => $time,
                                    ]);
                                    $txt = "<a href='tg://user?id={$rfrom->id}'>{$rfrom->first_name}</a> shu vaqtgacha unga berilgan ogohlantirishlarga <b>befarq bo'ldi</b>, jazo sifatida esa guruhdan <b>chiqarib yuborildi.</b>";
                                    $this->sendMessage($chat_id, $txt, ['parse_mode' => 'html']);
                                    GroupWarning::find($warning->id)->delete();
                                }else{
                                    GroupWarning::where('group_id', $chat_id)->where('user_id', $rfrom->id)->increment('amount');
                                    $amount = $warning->amount + 1;
                                    $txt = "<b><a href='tg://user?id={$rfrom->id}'>{$rfrom->first_name}</a> ogohlantirish oldi</b>\nEndi undagi ogohlantirishlar soni <b>{$amount}</b>/{$group->warning}";
                                    $this->sendMessage($chat_id, $txt, ['parse_mode' => 'html']);
                                }
                            }
                        }
                        if ($text == "/unwarn"){
                            $this->sendChatAction($chat_id);
                            $warning = GroupWarning::where('group_id', $chat_id)->where('user_id', $rfrom->id)->get()->first();
                            if (!empty($warning)){
                                GroupWarning::where('group_id', $chat_id)->where('user_id', $rfrom->id)->delete();
                            }
                            $txt = "<a href='tg://user?id={$rfrom->id}'>{$rfrom->first_name}</a> dan barcha <b>ogohlantirishlar</b> olib tashlandi.\nEndi undagi ogohlantirishlar soni <b>0</b>/{$group->warning}";
                            $this->sendMessage($chat_id, $txt, ['parse_mode' => 'html']);
                        }

                    }

                    if(mb_stripos($text,"/setchannel") !== false){
                        $ex = explode(" ", $text);
                        if (count($ex) == 2){
                            $this->deleteMessage($chat_id, $message_id);
                            $this->sendChatAction($chat_id);
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
                            $this->sendChatAction($chat_id);
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
                    if(mb_stripos($text,"/addmember") !== false) {
                        $ex = explode(" ", $text);
                        if (count($ex) == 2) {
                            $ex = $ex[1];
                            $this->deleteMessage($chat_id, $message_id);
                            $this->sendChatAction($chat_id);
                            if (is_numeric($ex)){
                                $this->updateGroup($chat_id, ['add_required_member' => $ex]);
                                $txt = "*Guruhda yozish uchun majburiy odam qo'shish {$ex} ga o'zgartirildi!*";
                                $this->sendMessage($chat_id, $txt, ['parse_mode' => 'markdown']);
                            }else{
                                $this->sendMessage($chat_id, "Raqam yuboring!");
                            }
                        }
                    }
                    if(mb_stripos($text,"/warning") !== false) {

                        $ex = explode(" ", $text);
                        if (count($ex) == 2) {
                            $ex = $ex[1];
                            $this->deleteMessage($chat_id, $message_id);
                            $this->sendChatAction($chat_id);
                            if (is_numeric($ex)){
                                $this->updateGroup($chat_id, ['warning' => $ex]);
                                $txt = "*Guruhda guruhda ogohlantirishlar soni {$ex} ga o'zgartirildi!*";
                                $this->sendMessage($chat_id, $txt, ['parse_mode' => 'markdown']);
                            }else{
                                $this->sendMessage($chat_id, "Raqam yuboring!");
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
        $group = Group::where('group_id', $group_id)->first();
        if ($group) {
            return $group;
        }else{
            return Group::create(['group_id' => $group_id]);
        }
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
    public function sendChatAction($chat_id, $action = 'typing')
    {
        $this->bot('sendChatAction', [
           'chat_id' => $chat_id,
           'action' => $action,
        ]);
    }

    public function json($update)
    {
        $this->sendMessage($this->admin, json_encode($update, JSON_PRETTY_PRINT));
    }
}
