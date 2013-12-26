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
    
    for($i=0; $i<1000; $i++)
    {
        $ret = $queue->enqueue('test message', __FILE__);
        echo $ret .  PHP_EOL;
    }
