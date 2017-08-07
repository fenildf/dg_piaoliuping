<?php
/**
 * 漂流瓶模块处理程序
 *
 * @author 冬瓜
 * @url http://bbs.we7.cc/
 */
defined('IN_IA') or exit('Access Denied');

class Dg_piaoliupingModuleProcessor extends WeModuleProcessor
{
    public function respond()
    {
        $content = $this->message['content'];
        if ($content == "退出") {
            $this->endContext();
            return $this->respText("漂流瓶>>>\n\n【你已退出漂流瓶活动】\n\n\n\n获取新的漂流瓶请点击菜单");
        }
        //这里定义此模块进行消息处理时的具体过程, 请查看微擎文档来编写你的代码
        $openid = $this->message['from'];//用户的openid
        if ($this->inContext) {//处于上下文状态
            $cache = cache_load($openid . '_drifting');
            if (!$cache['count']) {
                cache_write($openid . '_drifting', ['list' => '+++++', 'count' =>(int)20, 'lao' => 1], 3600);
                $cache = cache_load($openid . '_drifting');
            }
            if ($content == '扔漂流瓶') {
                //标记着用户为扔漂流瓶状态
                cache_write($openid . '_drifting', ['list' => $cache['list'], 'count' => (int)$cache['count'], 'lao' => 0], 3600);

                $this->beginContext(60 * 5);//启动上下文


                return $this->respText("漂流瓶>>>\n\n【请编辑你要扔的漂流瓶内容,或者直接语音发送】\n\n\n\n获取新的漂流瓶请点击菜单");
            }
            if ($content == '捞漂流瓶') {
                //标记着用户为扔漂流瓶状态
                //todo 发送一个随机漂流瓶
                $list = self::getRandList();
                cache_write($openid . '_drifting', ['list' => $list['id'], 'count' => (int)$cache['count'], 'lao' => 1], 3600);
                //判断是否获取了20次
                if (10<rand(0,30)) return $this->respText($cache['lao'].$cache['list']."漂流瓶>>>\n\n【恭喜捞到禽兽湖的虾米】\n\n\n\n收获不错哦");
                //-----------------------
                $this->beginContext(60 * 5);//启动上下文
                if ($list['type'] == 1) {//文本数据
                    return $this->respText("漂流瓶>>>\n\n陌生人:" . $list['media_id'] . "\n\n\n\n可直接回复该漂流瓶,支持文字、语音回复 [5分钟内回复有效] \n获取新的漂流瓶请点击菜单");
                } else {//语音消息
                    if (time() - $list['created_at'] > 60 * 60 * 24 * 3)//三天的语音会消失
                    {
                        pdo_update('drifting_list', ['status' => 2], ['id' => $list['id']]); //更新过期了的语音漂流瓶
                        return $this->respText("漂流瓶>>>\n\n【该漂流瓶为语音消息,可惜过期了】\n\n\n\n可直接回复该漂流瓶,支持文字、语音回复 [5分钟内回复有效] \n获取新的漂流瓶请点击菜单");
                    } else
                        return $this->respVoice($list['media_id']);
                }
            }

            $this->endContext();
            //回复一个漂流瓶的逻辑

            //为该回复创建一条聊天记录
            if ($this->message['type'] == "voice") {
                $media = $this->message['mediaid'];
                $type = 2;
            } else {
                $type = 1;
                $media = $this->message['content'];
            }

            if ($cache['lao']==0){
                $sql_data = [
                    'to_openid' => null,
                    'openid' => $openid,
                    'media_id' => $media,
                    'type' => $type,
                    'status' => 0,
                    'created_at' => time(),
                    'updated_at' => time(),
                ];
                pdo_insert('drifting_list',$sql_data);
                return $this->respText("漂流瓶>>>\n\n【成功扔出去一个漂流瓶,期待有人捡到~】\n\n\n\n获取最新的漂流瓶消息请点击菜单");

            }else if ($cache['lao']==1){
                if (empty($cache['list'])) return $this->respText("漂流瓶>>>\n\n【alskjdlauow0r2381#@%#WRGG$^UWY@#R@】\n\n\n\n获取新的漂流瓶请点击菜单");
                //先获取当前用户看到的是谁的漂流瓶,将该漂流瓶的状态设置为已读可回复:status=1;
                $list_detail = $this->getStatus($cache['list']);

                if ($list_detail['code'] != 204) return $this->respText("漂流瓶>>>\n\n【alskjdlauow0r2381#@%#WRGG$^UWY@#R@】\n\n\n\n获取新的漂流瓶请点击菜单");
                //更新该漂流瓶状态

                self::updateList($cache['list'], $openid);

                //为该回复创建一条聊天记录
                if ($this->message['type'] == "voice") {
                    $media = $this->message['mediaid'];
                    $type = 2;
                } else {
                    $type = 1;
                    $media = $this->message['content'];
                }

                $sql_data = [
                    'to_openid' => $list_detail['openid'],
                    'list_id' => $cache['list'],
                    'openid' => $openid,
                    'media_id' => $media,
                    'type' => $type,
                    'status' => 0,
                    'created_at' => time(),
                    'updated_at' => time(),
                ];
                pdo_insert('drifting_detail',$sql_data);
                return $this->respText("漂流瓶>>>\n\n【回复成功~】\n\n\n\n获取最新的漂流瓶消息请点击菜单");
            }
        }
        $code = self::UserIsSend($openid);
        switch ($code['code']) {
            case 200: {
            $has=self::getUnReadList($openid);
                if (empty($has)){
                    //发送一个漂流瓶
                    $list = self::getRandList();
                    //判断是否获取了20次
                    if (10<rand(0,30)) return $this->respText("漂流瓶>>>\n\n【恭喜捞到禽兽湖的虾米】\n\n\n\n收获不错哦");
                    //-----------------------
                    $this->beginContext(60 * 5);//启动上下文
                    if ($list['type'] == 1) {//文本数据
                        return $this->respText("漂流瓶>>>\n\n陌生人:" . $list['media_id'] . "\n\n\n\n可直接回复该漂流瓶,支持文字、语音回复 [5分钟内回复有效] \n获取新的漂流瓶请点击菜单");
                    } else {//语音消息
                        if (time() - $list['created_at'] > 60 * 60 * 24 * 3)//三天的语音会消失
                        {
                            pdo_update('drifting_list', ['status' => 2], ['id' => $list['id']]); //更新过期了的语音漂流瓶
                            return $this->respText("漂流瓶>>>\n\n【该漂流瓶为语音消息,可惜过期了】\n\n\n\n可直接回复该漂流瓶,支持文字、语音回复 [5分钟内回复有效] \n获取新的漂流瓶请点击菜单");
                        } else
                            return $this->respVoice($list['media_id']);
                    }

                }else{
                    $data = cache_load($openid . '_drifting');
                    //发送最新的消息
                    if (!$data)
                    cache_write($openid . '_drifting', ['list' => $has['list_id'], 'count' => 19, 'lao' => 1], 3600);
                    else
                    cache_write($openid . '_drifting', ['list' => $has['list_id'], 'count' => (int)$data['count'], 'lao' => 1], 3600);
                    if ($has['type'] == 1) {//文本数据

                        pdo_update('drifting_detail',['status'=>1],['to_openid'=>$has['to_openid'],'list_id'=>$has['list_id']]);

                        return $this->respText("【漂流瓶有新的回复】>>>\n\n陌生人回复:" . $has['media_id'] . "\n\n\n\n可直接回复该漂流瓶,支持文字、语音回复 [5分钟内回复有效] \n获取新的漂流瓶请点击菜单");
                    } else {//语音消息
                        if (time() - $has['created_at'] > 60 * 60 * 24 * 3)//三天的语音会消失
                        {
                            return $this->respText("漂流瓶>>>\n\n【该漂流瓶为语音消息,可惜过期了】\n\n\n\n可直接回复该漂流瓶,支持文字、语音回复 [5分钟内回复有效] \n获取新的漂流瓶请点击菜单");
                        } else
                            return $this->respVoice($has['media_id']);
                    }
                }

                break;
            }
            case 204 :
            case 404: {

            $cache = cache_load($openid . '_drifting');
            if (!$cache['count']) {
                cache_write($openid . '_drifting', ['list' => '', 'count' => 20, 'lao' => 1], 3600);
                $cache = cache_load($openid . '_drifting');
            }
            if ($content == '扔漂流瓶') {
                //标记着用户为扔漂流瓶状态
                cache_write($openid . '_drifting', ['list' => $cache['list'], 'count' => (int)$cache['count'], 'lao' => 0], 3600);

                $this->beginContext(60 * 5);//启动上下文

                return $this->respText("漂流瓶>>>\n\n【请编辑你要扔的漂流瓶内容,或者直接语音发送】\n\n\n\n获取新的漂流瓶请点击菜单");
            }
            if ($content == '捞漂流瓶') {
                //标记着用户为扔漂流瓶状态
                //todo 发送一个随机漂流瓶
                $list = self::getRandList();
                cache_write($openid . '_drifting', ['list' => $list['id'], 'count' => (int)$cache['count'], 'lao' => 1], 3600);
                //判断是否获取了20次

                if (10<rand(0,30)) return $this->respText("漂流瓶>>>\n\n【恭喜捞到禽兽湖的虾米】\n\n\n\n收获不错哦");
                //-----------------------
                $this->beginContext(60 * 5);//启动上下文
                if ($list['type'] == 1) {//文本数据
                    return $this->respText("漂流瓶>>>\n\n陌生人:" . $list['media_id'] . "\n\n\n\n可直接回复该漂流瓶,支持文字、语音回复 [5分钟内回复有效] \n获取新的漂流瓶请点击菜单");
                } else {//语音消息
                    if (time() - $list['created_at'] > 60 * 60 * 24 * 3)//三天的语音会消失
                    {
                        pdo_update('drifting_list', ['status' => 2], ['id' => $list['id']]); //更新过期了的语音漂流瓶
                        return $this->respText("漂流瓶>>>\n\n【该漂流瓶为语音消息,可惜过期了】\n\n\n\n可直接回复该漂流瓶,支持文字、语音回复 [5分钟内回复有效] \n获取新的漂流瓶请点击菜单");
                    } else
                        return $this->respVoice($list['media_id']);
                }
            }
            break;
            }
        }
    }

    /**
     * 返回用户的状态
     *
     * @param $openid
     *
     * @return array
     */
    public function UserIsSend($openid)
    {

        $result = pdo_fetch('SELECT * FROM ' . tablename('drifting_list') . ' WHERE `openid`= :OPENID ', array(':OPENID' => $openid));
        if (empty($result)) return ['code' => 404];
        if ($result['status'] == 1) return ['code' => 200];
        else return ['code' => 204];
    }

    /**
     * 获取一条漂流瓶的状态
     *
     * @param $id
     *
     * @return array
     */
    public function getStatus($id)
    {
        $result = pdo_fetch('SELECT * FROM ' . tablename('drifting_list') . ' WHERE `id`= :ID ', array(':ID' => $id));
        if (empty($result)) return ['code' => 404];
        if ($result['status'] == 1) return ['code' => 200, 'openid' => $result['openid']];
        else return ['code' => 204, 'openid' => $result['openid']];
    }

    /**
     * 随机获得一条漂流瓶数据
     *
     * @return bool
     */
    public function getRandList()
    {
        $result = pdo_fetch('SELECT * FROM ' . tablename('drifting_list') . ' WHERE `status`= 0 ORDER BY  RAND() LIMIT 1');
        return $result;
    }

    public function updateList($id, $openid)
    {
        pdo_update('drifting_list', ['status' => 1, 'to_openid' => $openid, 'updated_at' => time()], ['id' => $id]);
    }

    public function getIsNull($openid,$list){
        $data = cache_load($openid . '_drifting');
        if (!$data) {//载入缓存的用户
            cache_write($openid . '_drifting', ['list' => $list['id'], 'count' =>19, 'lao' => 1], 3600);
        } else {
            if ($data['count'] < 1) return false;

            cache_write($openid . '_drifting', ['list' => $list['id'], 'count' => (int)$data['count'] - 1], 3600);
        }
        return true;
    }

    public function getUnReadList($openid){
        $result = pdo_fetch('SELECT * FROM ' . tablename('drifting_detail') . ' WHERE `status`= 0 AND `to_openid`=:OPENID ORDER BY  `id` DESC',[':OPENID'=>$openid]);
        return $result;
    }
}
