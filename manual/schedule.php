<?php
/**
 * 自动同步手册 DocBook 源码
 * 并生成 php 版本手册
 */
$docDir = "manual";
chdir($docDir);
require('pulls.php');

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
        exec("git -C $targetDir pull --quiet --rebase --autostash", $result, $returnCode);
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

        if (shouldSkipBuild($langCode)) {
            continue;
        }

        ob_start();
        exec("php doc-base/configure.php --with-lang=${langCode} --with-lang-dir=${langCode} --output=doc-base/${langCode}.manual.xml", $result, $returnCode);
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
        $outputDir = "output-${langCode}";
        exec("rm -rf ${outputDir}");
        exec("php phd/render.php --docbook doc-base/${langCode}.manual.xml --package PHP --format php --output=${outputDir}", $result, $returnCode);
        if (file_exists($dest)) {
            // watch out!
            exec("rm -rf ${dest}");
        }
        symlink("../../${docDir}/${outputDir}/php-web", $dest);
        $hash = getManualHash($langCode);
        // send notification for doc-zh
        if ($langCode === 'zh') {
            $hashInShort = substr($hash, 0, 7);
            sendNotification("已构建最新 master 分支 ${hashInShort}\n预览 https://phpdoc.u301.com/manual/zh/");
        }

        setManualHashLog($langCode, $hash);
    }
    scheduleBuildPulls($docDir);
} catch (\Exception $e) {
    echo $e->getMessage();
} finally {
    unlink($lockFile);
}


/**
 * 是否应该跳过该手册编译
 */
function shouldSkipBuild($langCode) {
    $previousHash = getManualHashLog($langCode);
    $hash = getManualHash($langCode);
    return $previousHash === $hash;
}

/**
 * 获取当前的 hash
 */
function getManualHash($langCode) {
    return file_get_contents("${langCode}/.git/refs/heads/master");
}

/**
 * 获取之前的 hash
 */
function getManualHashLog($langCode) {
    $hashLogFile = "hash-${langCode}.txt";
    if (file_exists($hashLogFile)) {
        $hash = file_get_contents("hash-${langCode}.txt");
        return $hash;
    } else {
        return false;
    }
}

/**
 * 设置 hash log
 */
function setManualHashLog($langCode, $hash) {
    file_put_contents("hash-${langCode}.txt", $hash);
}

/**
 * Send Notification for mastodon
 */
function sendNotification(string $content) {
    $flag = strtoupper(getenv('MASTODON_NOTIFICATION'));
    if ($flag !== 'ON') {
        return false;
    }

    $endpoint = getenv('MASTODON_URI');
    if (empty($endpoint)) {
        return false;
    } else {
        $endpoint .= '/api/v1/statuses';
    }

    $token = getenv('MASTODON_TOKEN');

    $hashtag = getenv('MASTODON_HASHTAG');
    if ($hashtag) {
        $content .= " #${hashtag}";
    }
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer '.$token
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => [
            'status' => $content
        ]
    ]);
    $res = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($httpcode != 200) {
        echo $res;
    }
}
