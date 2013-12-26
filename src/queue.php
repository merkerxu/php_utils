<?php
/*
 * message queue, based on pdo
 * queue table sql:
 *
 * CREATE TABLE `msg_queue` (
 *   `msg_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '消息id',
 *   `msg_topic` varchar(16) NOT NULL COMMENT '对列主题',
 *   `msg_prior` tinyint(4) NOT NULL DEFAULT '5' COMMENT '消息优先级',
 *   `msg_status` tinyint(4) unsigned NOT NULL COMMENT '消息状态',
 *   `msg_content` varchar(255) NOT NULL COMMENT '消息内容',
 *   `msg_producer` varchar(64) NOT NULL COMMENT '生产者名称',
 *   `msg_create_time` int(10) unsigned NOT NULL COMMENT '创建unix时间戳',
 *   `msg_consumer` varchar(64) NOT NULL COMMENT '消费者名称',
 *   `msg_update_time` int(10) unsigned NOT NULL COMMENT '消息更新unix时间戳',
 *   `msg_update_cnt` int(11) unsigned NOT NULL COMMENT '累积消费次数',
 *   PRIMARY KEY (`msg_id`)
 * ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
 *
 * CREATE TABLE `msg_queue_his` (
 *   `msg_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '消息id',
 *   `msg_topic` varchar(16) NOT NULL COMMENT '对列主题',
 *   `msg_prior` tinyint(4) NOT NULL DEFAULT '5' COMMENT '消息优先级',
 *   `msg_status` tinyint(4) unsigned NOT NULL COMMENT '消息状态',
 *   `msg_content` varchar(255) NOT NULL COMMENT '消息内容',
 *   `msg_producer` varchar(64) NOT NULL COMMENT '生产者名称',
 *   `msg_create_time` int(10) unsigned NOT NULL COMMENT '创建unix时间戳',
 *   `msg_consumer` varchar(64) NOT NULL COMMENT '消费者名称',
 *   `msg_update_time` int(10) unsigned NOT NULL COMMENT '消息更新unix时间戳',
 *   `msg_update_cnt` int(11) unsigned NOT NULL COMMENT '累积消费次数',
 *   PRIMARY KEY (`msg_id`)
 * ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
 */
class MsgQueueDBDriver
{/*{{{*/
    //queue table
    const QUEUE_TABLE       = 'msg_queue';
    const QUEUE_HIS_TABLE   = 'msg_queue_his';

    //queue data structure
    const FIELD_MSG_ID          = 'msg_id';
    const FIELD_MSG_TOPIC       = 'msg_topic';
    const FIELD_MSG_PRIOR       = 'msg_prior';
    const FIELD_MSG_STATUS      = 'msg_status';
    const FIELD_MSG_CONTENT     = 'msg_content';
    const FIELD_MSG_PRODUCER    = 'msg_producer';
    const FIELD_MSG_CREATE_TIME = 'msg_create_time';
    const FIELD_MSG_CONSUMER    = 'msg_consumer';
    const FIELD_MSG_UPDATE_TIME = 'msg_update_time';
    const FIELD_MSG_UPDATE_CNT  = 'msg_update_cnt';

    //prior list: 1-9, the bigger the higher priority
    private static $prior_list = array(
        1, 2, 3, 4, 5, 6, 7, 8, 9
        );
    const MSG_PRIOR_DEFAULT     = 5;

    //msg status
    const MSG_STATUS_NEW        = 1;
    const MSG_STATUS_PENDING    = 2;
    const MSG_STATUS_SUCCESS    = 3;
    const MSG_STATUS_FAILED     = 4;

    //process mode: single or batch
    const MSG_CONSUME_MODE_SINGLE = 1;
    const MSG_CONSUME_MODE_BATCH  = 2;
    //queue pdo
    private $queue_pdo  = null;

    //init db connection
    public function __construct($db_conf = array())
    {/*{{{*/
        if($db_conf['unix_socket'])
        {/*{{{*/
            $dsn = "mysql:dbname={$db_conf['database']};" .
                "unix_socket={$db_conf['unix_socket']}";
        }/*}}}*/
        else
        {/*{{{*/
            $dsn = "mysql:host={$db_conf['host']};" .
                "port={$db_conf['port']};" .
                "dbname={$db_conf['database']}";
        }/*}}}*/

        $username = $db_conf['username'];
        $password = $db_conf['password'];

        $persist = array(PDO::ATTR_PERSISTENT => $db_conf['persistent']);
        $options = $persist + $db_conf['options'];

        try
        {/*{{{*/
            $this->queue_pdo = new PDO($dsn, $username, $password, $options);
            $this->queue_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->queue_pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            $this->queue_pdo->exec("SET NAMES {$db_conf['charset']}");
            $this->queue_pdo->exec("SET character_set_client=binary");
        }/*}}}*/
        catch(PDOException $e)
        {/*{{{*/
            $this->queue_pdo = null;
            die('error in ' . __METHOD__ . ' : ' . $e->getMessage());
        }/*}}}*/
    }/*}}}*/

    //enqueue
    public function enqueue($content, $producer, $topic = 'default',
        $prior = self::MSG_PRIOR_DEFAULT)
    {/*{{{*/
        if(is_object($producer))
            $producer = get_class($producer);
        if(!is_string($content) || !is_string($producer) ||
           !is_string($topic))
        {/*{{{*/
            return false;
        }/*}}}*/

        try
        {/*{{{*/
            $query = "INSERT INTO %s (%s, %s, %s, %s, %s, %s)" .
                " VALUES(?, ?, ?, ?, ?, ?)";
            $query = sprintf($query,self::QUEUE_TABLE,self::FIELD_MSG_TOPIC,
                self::FIELD_MSG_PRIOR, self::FIELD_MSG_STATUS,
                self::FIELD_MSG_CONTENT, self::FIELD_MSG_PRODUCER,
                self::FIELD_MSG_CREATE_TIME);
            $stmt = $this->queue_pdo->prepare($query);
            $stmt->bindValue(1, $topic);
            $stmt->bindValue(2, $prior);
            $stmt->bindValue(3, self::MSG_STATUS_NEW);
            $stmt->bindValue(4, $content);
            $stmt->bindValue(5, $producer);
            $stmt->bindValue(6, time());
            $stmt->execute();

            return (bool)$stmt->rowCount();
        }/*}}}*/
        catch(PDOException $e)
        {/*{{{*/
            $this->queue_pdo = null;
            return false;
        }/*}}}*/
    }/*}}}*/
    
    //msg processing:
    //mode: single or batch
    public function process($callback, $topic = 'default', $mode=self::MSG_CONSUME_MODE_BATCH)
    {/*{{{*/
        if(!is_string($topic) || !is_callable($callback, false, $consumer_name))
        {/*{{{*/
            return false;
        }/*}}}*/

        try
        {/*{{{*/
            $query = "SELECT * FROM %s WHERE %s=? and %s in (?,?)";
            if($mode == self::MSG_CONSUME_MODE_SINGLE)
            {/*{{{*/
                $query .= " limit 1";
            }/*}}}*/
            $query = sprintf($query, self::QUEUE_TABLE, self::FIELD_MSG_TOPIC,
                self::FIELD_MSG_STATUS);
            $stmt = $this->queue_pdo->prepare($query);
            $stmt->bindValue(1, $topic);
            $stmt->bindValue(2, self::MSG_STATUS_NEW);
            $stmt->bindValue(3, self::MSG_STATUS_FAILED);
            $stmt->execute();
            while($row = $stmt->fetch(PDO::FETCH_ASSOC))
            {/*{{{*/
                $pre_query = "UPDATE %s SET %s=? WHERE %s=? AND %s=?";
                $pre_query = sprintf($pre_query, self::QUEUE_TABLE,
                    self::FIELD_MSG_STATUS, self::FIELD_MSG_ID,
                    self::FIELD_MSG_STATUS);
                $pend_stmt = $this->queue_pdo->prepare($pre_query);
                $pend_stmt->bindValue(1, self::MSG_STATUS_PENDING);
                $pend_stmt->bindValue(2, $row[self::FIELD_MSG_ID]);
                $pend_stmt->bindValue(3, $row[self::FIELD_MSG_STATUS]);
                $pend_stmt->execute();

                if($pend_stmt->rowCount() == 0)
                {/*{{{*/
                    //others is dealing the msg now
                    continue;
                }/*}}}*/

                if(is_array($callback))
                {/*{{{*/
                    $obj    = $callback[0];
                    $method = $callback[1];
                    $process_result = $obj->$method($row[self::FIELD_MSG_CONTENT]);
                }/*}}}*/
                else
                {/*{{{*/
                    $process_result = $callback($row[self::FIELD_MSG_CONTENT]);
                }/*}}}*/

                $row[self::FIELD_MSG_CONSUMER]    = $consumer_name;
                $row[self::FIELD_MSG_UPDATE_TIME] = time();
                $row[self::FIELD_MSG_UPDATE_CNT]  += 1;

                if($process_result)
                {/*{{{*/
                    //move msg to queue history
                    $mv_query = "INSERT IGNORE INTO %s values(%s)";
                    $row[self::FIELD_MSG_STATUS]    = self::MSG_STATUS_SUCCESS;
                    $mv_query = sprintf($mv_query, self::QUEUE_HIS_TABLE,
                        implode(',', array_fill(0, count($row), '?')));
                    $mv_stmt  = $this->queue_pdo->prepare($mv_query);
                    $cnt = 1;
                    foreach($row as $field => $value)
                    {/*{{{*/
                        $mv_stmt->bindValue($cnt++, $value);
                    }/*}}}*/
                    $mv_stmt->execute();

                    //delete msg from queue
                    $del_query = "DELETE FROM %s WHERE %s=?";
                    $del_query = sprintf($del_query, self::QUEUE_TABLE, self::FIELD_MSG_ID);
                    $del_stmt  = $this->queue_pdo->prepare($del_query);
                    $del_stmt->bindValue(1, $row[self::FIELD_MSG_ID]);
                    $del_stmt->execute();
                }/*}}}*/
                else
                {/*{{{*/
                    $upd_query = "UPDATE %s SET %s=?,%s=?,%s=?,%s=? WHERE %s=?";
                    $upd_query = sprintf($upd_query, self::QUEUE_TABLE,
                        self::FIELD_MSG_STATUS, self::FIELD_MSG_CONSUMER,
                        self::FIELD_MSG_UPDATE_TIME, self::FIELD_MSG_UPDATE_CNT,
                        self::FIELD_MSG_ID);
                    $upd_stmt  = $this->queue_pdo->prepare($upd_query);
                    $upd_stmt->bindValue(1, self::MSG_STATUS_FAILED);
                    $upd_stmt->bindValue(2, $row[self::FIELD_MSG_CONSUMER]);
                    $upd_stmt->bindValue(3, $row[self::FIELD_MSG_UPDATE_TIME]);
                    $upd_stmt->bindValue(4, $row[self::FIELD_MSG_UPDATE_CNT]);
                    $upd_stmt->bindValue(5, $row[self::FIELD_MSG_ID]);
                    $upd_stmt->execute();
                }/*}}}*/
            }/*}}}*/
        }/*}}}*/
        catch(PDOException $e)
        {/*{{{*/
            $this->queue_pdo = null;
            return false;
        }/*}}}*/

        return true;
    }/*}}}*/
}/*}}}*/
