<?php
/**
 * 自动同步手册 DocBook 源码
 * 并生成 php 版本手册
 */
$docDir = "manual";
chdir($docDir);

$lockFile = ".running.lock";
if (file_exists($lockFile)) {
    echo "上个任务还在运行中\n";
    exit;
} else {
    file_put_contents($lockFile, '');
}

$gitSources = [
    'doc-base' => 'doc-base.git',
    'en' => 'doc-en.git',
    'zh' => 'doc-zh.git',
    'phd' => 'phd.git'
];

foreach($gitSources as $name => $repo) {
    $targetDir = "${name}";
    if (file_exists($targetDir)) {
        exec("git -C $targetDir pull --rebase", $result, $returnCode);
    } else {
        # this is a github mirror in China
        exec("git clone https://github.com.cnpmjs.org/php/${repo}/ ${name}", $result, $returnCode);
    }
}

$langs = [
    'en', 'zh'
];
try {
    foreach($langs as $langCode) {
    
        ob_start();
        exec("php doc-base/configure.php --with-lang=${langCode} --output=doc-base/${langCode}.manual.xml", $result, $returnCode);
        $content = ob_get_clean();
        $dest = '../web-php/manual/'.$langCode;
        /**
         * 如果 returnCode 是1，代表发生错误
         * 是 0 则为成功
         */
        if ($returnCode === 1) {
            throw new Exception("Can not create ${langCode} manual:".$content);
        } else if($returnCode === 0) {
            echo ucfirst($langCode)." manual validated\n";
        }
        
        exec("php phd/render.php --docbook doc-base/${langCode}.manual.xml --package PHP --format php --output=output-${langCode}", $result, $returnCode);
        if (file_exists($dest)) {
            // watch out!
            exec("rm -rf ${dest}");
        }
        symlink("../../${docDir}/output-${langCode}/php-web", $dest);    
    }
} catch (\Exception $e) {
    echo $e->getMessage();
} finally {
    unlink($lockFile);
}
