<?php
    require '../src/logp.php';

    //add logpath and app_name
    logp::config('/tmp', 'test', logp::LOG_DEBUG);
    logp::debug(__FILE__, __LINE__, 'test logp debug');
    usleep(10);
    logp::info(__FILE__, __LINE__, 'test logp info');
    usleep(10);
    logp::warn(__FILE__, __LINE__, 'test logp warn');
    usleep(10);
    logp::error(__FILE__, __LINE__, 'test logp error');
