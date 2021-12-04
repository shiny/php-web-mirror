<?php
/**
 * 初始化 Web 主目录
 */
echo "是否确认重新初始化 web-php，相关文件将被清除，无法恢复。输入 'yes' 继续：";
$handle = fopen ("php://stdin","r");
$line = fgets($handle);
if(trim($line) != 'yes'){
    echo "退出。\n";
    exit;
}
fclose($handle);
$dir = './web-php';
// watch out!
exec("rm -rf $dir");
exec("git clone https://github.com.cnpmjs.org/php/web-php/", $result, $returnCode);
mkdir('web-php/manual');
echo "Web 目录已 clone，请继续生成手册\n";
