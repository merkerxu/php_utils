<?php
/*
 * session db handler, based on pdo
 * session table sql:
 * --------------sessions------------------------------
 *
 *  CREATE TABLE `sessions` (
 *      `session_id` varchar(40) NOT NULL DEFAULT '',
 *      `session_data` text NOT NULL,
 *      `expire_at` int(11) unsigned NOT NULL,
 *      UNIQUE KEY `session_id` (`session_id`)
 *      ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
 *
 * --------------sessions------------------------------
 */
class SessionDBHandler
{
    /*
     * session table name
     */
    const SESSION_TABLE = 'sessions';

    /*
     * session data structure
     */
    const FIELD_SESSION_ID      = 'session_id';
    const FIELD_SESSION_DATA    = 'session_data';
    const FIELD_EXPIRE_AT       = 'expire_at';

    /*
     * session db config
     */
    private $config    = null;

    /*
     * session pdo
     */
    private $sess_pdo  = null;

    /*
     * session lifetime
     */
    private $sess_lifetime = 0;

    /*
     * default session max lifetime in seconds
     * max_lifetime  604800=3600*24*7
     */
    const SESSION_MAX_LIFETIME = 604800;

    /*
     * init db_conf and session maxlifetime
     * @db_conf array
     * @max_lifetime  int default 604800=3600*24*7
     */
    public function __construct($db_conf = array(), $max_lifetime = self::SESSION_MAX_LIFETIME)
    {/*{{{*/
        $this->config = $db_conf;
        $this->sess_lifetime = $max_lifetime;

        session_set_save_handler(
            array($this, 'sess_open'),
            array($this, 'sess_close'),
            array($this, 'sess_read'),
            array($this, 'sess_write'),
            array($this, 'sess_destroy'),
            array($this, 'sess_gc')
            );
        register_shutdown_function('session_write_close');
    }/*}}}*/

    /*
     * the first callback function executed when the session is started
     * automatically or manually with session_start()
     * return TURE for success, FALSE for failure
     */
    public function sess_open($save_path, $session_name)
    {/*{{{*/
        if($this->sess_pdo == null)
        {/*{{{*/
            if($this->config['unix_socket'])
            {/*{{{*/
                $dsn = "mysql:dbname={$this->config['database']};" .
                    "unix_socket={$this->config['unix_socket']}";
            }/*}}}*/
            else
            {/*{{{*/
                $dsn = "mysql:host={$this->config['host']};port={$this->config['port']};" .
                    "dbname={$this->config['database']}";
            }/*}}}*/

            $username = $this->config['username'];
            $password = $this->config['password'];

            $persist = array(PDO::ATTR_PERSISTENT => $this->config['persistent']);
            $options = $persist + $this->config['options'];

            try
            {/*{{{*/
                $this->sess_pdo = new PDO($dsn, $username, $password, $options);
                $this->sess_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->sess_pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
                $this->sess_pdo->exec("SET NAMES {$this->config['charset']}");
                $this->sess_pdo->exec("SET character_set_client=binary");
            }/*}}}*/
            catch(PDOException $e)
            {/*{{{*/
                $this->sess_pdo = null;
                die('error in ' . __METHOD__ . ' : ' . $e->getMessage());
            }/*}}}*/
        }/*}}}*/

        return true;
    }/*}}}*/

    /*
     * executed after the session write callback has been called,
     * also invoked when session_write_close() is called
     * TRUE for success, FALSE for failure
     */
    public function sess_close()
    {/*{{{*/
        $this->sess_pdo = null;

        return true;
    }/*}}}*/

    /*
     * must always return a session encoded string, or an empty string if no data to read
     * called internally by PHP where session start, before this callback is invoked PHP
     * will invoke the open callback
     */
    public function sess_read($session_id)
    {/*{{{*/
        try
        {/*{{{*/
            $query = 'SELECT %s FROM %s WHERE %s=? AND %s>=?';
            $query = sprintf($query, self::FIELD_SESSION_DATA, self::SESSION_TABLE,
                self::FIELD_SESSION_ID, self::FIELD_EXPIRE_AT);
            $stmt = $this->sess_pdo->prepare($query);
            $stmt->bindValue(1, $session_id);
            $stmt->bindValue(2, time());
            $stmt->execute();

            if($stmt->rowCount() == 1)
            {
                list($sess_data) = $stmt->fetch();

                return $sess_data;
            }

            return '';
        }/*}}}*/
        catch(PDOException $e)
        {/*{{{*/
            $this->sess_pdo = null;
            die('error in ' . __METHOD__ . ' : ' . $e->getMessage());
        }/*}}}*/
    }/*}}}*/

    /*
     * called when the session needs to be saved and closed
     * @session_id session ID
     * @data    serialized version of the $_SESSION
     * invoked when php shuts down or explicitly session_write_close is called
     */
    public function sess_write($session_id, $data)
    {/*{{{*/
        try
        {/*{{{*/
            /*
             * the session_id field must be defined as UNIQUE
             */
            $query = 'INSERT INTO %s (%s, %s, %s) VALUES (?, ?, ?)'.
                " ON DUPLICATE KEY UPDATE %s=VALUES(%s), %s=VALUES(%s)";
            $query = sprintf($query, self::SESSION_TABLE, self::FIELD_SESSION_ID,
                self::FIELD_SESSION_DATA, self::FIELD_EXPIRE_AT,
                self::FIELD_SESSION_DATA, self::FIELD_SESSION_DATA,
                self::FIELD_EXPIRE_AT, self::FIELD_EXPIRE_AT);
            $stmt = $this->sess_pdo->prepare($query);
            $stmt->bindValue(1, $session_id);
            $stmt->bindValue(2, $data);
            $stmt->bindValue(3, time() + $this->sess_lifetime);
            $stmt->execute();

            return (bool)$stmt->rowCount();
        }/*}}}*/
        catch(PDOException $e)
        {/*{{{*/
            $this->sess_pdo = null;
            die('error in ' . __METHOD__ . ' : ' . $e->getMessage());
        }/*}}}*/
    }/*}}}*/

    /*
     * executed when a session is destroyed with session_destroy or session_regenerate_id
     * return TRUE for success, FALSE for failure
     */
    public function sess_destroy($session_id)
    {/*{{{*/
        try
        {/*{{{*/
            /*
            $trace_arr = debug_backtrace();
            $trace_str = '';
            foreach($trace_arr as $index => $trace)
            {
                $trace_str .= '#' . $index . '|file=' . $trace['file'] . '|line=' . $trace['line'] .
                    '|class=' . $trace['class'] . '|function=' . $trace['function'];
            }
            $trace_str .= "\n";
            error_log($trace_str, 3, '/home/xuwei3/projects/fankui/logs/logout.log');
            */

            $query = 'DELETE FROM %s WHERE %s=? LIMIT 1';
            $query = sprintf($query, self::SESSION_TABLE, self::FIELD_SESSION_ID);
            $stmt = $this->sess_pdo->prepare($query);
            $stmt->bindValue(1, $session_id);
            $stmt->execute();
            return (bool)$stmt->rowCount();
        }/*}}}*/
        catch(PDOException $e)
        {/*{{{*/
            $this->sess_pdo = null;
            die('error in ' . __METHOD__ . ': ' . $e->getMessage());
        }/*}}}*/
    }/*}}}*/

    /*
     * invoked internally by PHP periodically in order to purge old session data.
     * frequency session.gc_probability/session.gc_divisor, the value of lifetime is passed to
     * this call back be set in session.gc_maxlifetime
     * return TRUE for success, FALSE for failure
     */
    public function sess_gc($lifetime)
    {/*{{{*/
        try
        {
            $query = "DELETE FROM %s WHERE %s <= UNIX_TIMESTAMP(NOW())";
            $query = sprintf($query, self::SESSION_TABLE, self::FIELD_EXPIRE_AT);
            $count = $this->sess_pdo->exec($query);
            //error_log("sess_gc:sql=" . $query . "|count=" . $count . "\n", 3, '/home/xuwei3/projects/fankui/logs/logout.log');

            return true;
        }
        catch(PDOException $e)
        {
            $this->sess_pdo = null;
            die('error in ' . __METHOD__ . ' : ' . $e->getMessage());
        }
    }/*}}}*/
}
