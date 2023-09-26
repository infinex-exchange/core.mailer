<?php

class MailStorage {
    private $log;
    private $pdo;
    
    function __construct($log, $pdo) {
        $this -> log = $log;
        $this -> pdo = $pdo;
        
        $this -> log -> debug('Initialized mail storage');
    }
    
    public function insert($uid, $email, $template, $context) {
        $task = [
            ':uid' => $uid,
            ':email' => $email,
            ':template' => $template,
            ':context' => json_encode($context, JSON_UNESCAPED_SLASHES)
        ];
        
        $sql = 'INSERT INTO mails(
                    uid,
                    email,
                    template,
                    context
                )
                VALUES(
                    :uid,
                    :email,
                    :template,
                    :context
                )';
        
        $q = $this -> pdo -> prepare($sql);
        $q -> execute($task);
    }
}

?>