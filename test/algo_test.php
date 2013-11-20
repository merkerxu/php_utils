<?php
    require '../src/algo.php'; 

    //bank card check
    //红十字会邮政卡号校验
    echo algo::luhn(substr('6210986400000444546', 0, -1)) . PHP_EOL;
    //红十字会建行帐号校验
    echo algo::luhn(substr('6227003525850100658', 0, -1)) . PHP_EOL;
    //红十字会工行卡号校验
    echo algo::luhn(substr('6222022201013723285', 0, -1)) . PHP_EOL;
