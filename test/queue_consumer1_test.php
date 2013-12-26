<?php
    require '../src/queue.php';

    $db_conf = array(
        'host'  => '127.0.0.1'
        'port'  => '3306',
        'database'  => 'test',
        'username'  => 'test',
        'password'  => 'test',
        'charset'   => 'UTF8',
        'persistent'=> true,
        'options'   => array()
        );

    $queue = new MsgQueueDBDriver($db_conf);

    function deal_msg($param)
    {
        echo $param  . PHP_EOL;
        return true;
    }

    $ret = $queue->process('deal_msg', 'default', MsgQueueDBDriver::MSG_CONSUME_MODE_SINGLE);
    var_dump($ret);
