/*
 * Copyright 2016 dun.163.com, Inc. All rights reserved.
 */
<?php
!defined('IN_DISCUZ') && exit('ACCESS DENIED');

class plugin_yidun_dz {}
class plugin_yidun_dz_forum extends plugin_yidun_dz {
    function post_dz() {}
    function post_dz_message($params) {// 显示提示信息前的嵌入点
        global $_G;
        if (!$_G['cache']['plugin']['yidun_dz']['enable_flag']) {
            return;
        }
        list($message, $returnurl, $post) = $params['param'];

        $thread_id = $post['tid'];
        $post_id = $post['pid'];
        $ip = $_G['clientip'];
        $account = $_G['member']['uid'];
        $title = diconv($_GET['subject'], CHARSET, 'UTF-8');
        $content = diconv($_GET['message'], CHARSET, 'UTF-8');
        
        $ret = $this->check($thread_id, $post_id, $ip, $account, $title, $content);
        if ($ret["code"] == 200 && $ret["result"]["action"] == 2) {
            $this->delete($message, $thread_id, $post_id);
        }else{
            // ignore error
        }
    }

    /**
     * 删除主题/回复
     * $message 发表成功后返回的字符串
     * $thread_id 主题id
     * $post_id 回帖id
     */
    function delete($message, $thread_id, $post_id){
        global $_G;
        require_once libfile('function/delete');
        if($this->isreply($message)){
            deletepost(array($post_id), 'pid', true, false, true);// function deletepost($ids, $idtype = 'pid', $credit = false, $posttableid = false, $recycle = false)
            manage_addnotify('verifyrecyclepost', $modpostsnum);
            updatethreadcount($_G['tid'], 1);
            updateforumcount($_G['fid']);
            $_G['forum']['threadcaches'] && deletethreadcaches($_G['tid']);

            showmessage('您发布的内容包含不合适信息，请重新编辑后再次发布', NULL, NULL, array('alert' => 'error'));
        }else if($this->isthread($message)){
            deletethread(array($thread_id), true, true, true); // function deletethread($tids, $membercount = false, $credit = false, $ponly = false)
            updateforumcount($_G['fid']);

            showmessage('您发布的内容包含不合适信息，请重新编辑后再次发布', NULL, NULL, array('alert' => 'error'));
        }else{
            // ignore unkown type
        }
    }

    function isreply($message){
        $reply_msgs = array('post_edit_succeed','post_reply_succeed','post_reply_mod_succeed','edit_reply_mod_succeed');
        return in_array($message, $reply_msgs, TRUE);
    }

    /**
     * 从返回的结果字符串判定当前发表/编辑的是否是主题贴
     * $message 发表之后返回的字符串
     */
    function isthread($message){
        $thread_msgs = array('post_newthread_succeed','post_newthread_mod_succeed','edit_newthread_mod_succeed');
        return in_array($message, $thread_msgs, TRUE);
    }

    /**
     * 易盾反垃圾请求接口简单封装
     *
     * $thread_id 主贴id
     * $post_id 内容id
     * $ip 请求ip
     * $account 用户账号id
     * $title 标题
     * $content 当前发表的内容
     */
    function check($thread_id, $post_id, $ip, $account, $title, $content){
        global $_G;
        $api_url = $_G['cache']['plugin']['yidun_dz']['api_url'];
        $secret_id = $_G['cache']['plugin']['yidun_dz']['secret_id'];
        $secret_key = $_G['cache']['plugin']['yidun_dz']['secret_key'];
        $business_id = $_G['cache']['plugin']['yidun_dz']['business_id'];

        $params = array(
            "dataId"=>$post_id,
            "dataOpType"=>1, // 这里全部当做新增处理
            "content"=>$content,
            "ip"=>$ip,
            "parentDataId"=>$thread_id,
            "title"=>$title,
            "account"=>$account,
            "publishTime"=>round(microtime(true)*1000)
        );
        $params["secretId"] = $secret_id;
        $params["businessId"] = $business_id;
        $params["version"] = "v2";
        $params["timestamp"] = sprintf("%d", round(microtime(true)*1000));// time in milliseconds
        $params["nonce"] = sprintf("%d", rand()); // random int
        $params["signature"] = $this->sign($secret_key, $params);

        $options = array(
            'http' => array(
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'timeout' => 3, // read timeout in seconds
                'content' => http_build_query($params),
            ),
        );
        $context  = stream_context_create($options);
        $result = file_get_contents($api_url, false, $context);
        return json_decode($result, true);
    }

    /**
     * 计算参数签名
     * $secret_key secret_key
     * $params 请求参数
     */
    function sign($secret_key, $params){
        ksort($params);
        $buff="";
        foreach($params as $key=>$value){
            $buff .=$key;
            $buff .=$value;
        }
        $buff .= $secret_key;
        return md5($buff); // 默认已经转换成utf8，这里不需要再次转换
    }
}
?>