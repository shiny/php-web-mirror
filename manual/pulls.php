<?php
// $docDir = "manual";
// chdir($docDir);

/**
 * 读取环境变量中的 Github token
 */
function tokenFromENv() {
    return getenv('TOKEN');
}

/**
 * 请求 pull request API
 * 这里会有频率限制
 * 考虑到请求频次（每分钟一次）不高，暂时忽略该问题
 */
function requestForPulls($token): array {
    $url = 'https://api.github.com/repos/php/doc-zh/pulls';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_USERAGENT => 'phpdoc-mirror/1.0',
        CURLOPT_HTTPHEADER => [
            'Authorization: token '.$token
        ],
        CURLOPT_RETURNTRANSFER => true
    ]);
    $text = curl_exec($ch);
    if (!$text) {
        return [];
    }
    $pulls = json_decode($text);
    if (empty($pulls)) {
        return [];
    }
    return $pulls;
}

function scheduleBuildPulls($docDir) {
    $token = tokenFromENv();
    $pulls = requestForPulls($token);
    foreach($pulls as $pull) {
        if (shouldBuildPull($pull)) {
            echo "build ".$pull->merge_commit_sha, "\n";
            $patchFile = 'pr-'.$pull->number.'.patch';
            downloadPatchFile($patchFile, $pull->patch_url);
            createPullRepo($pull->number, $patchFile);
            buildPull($pull->number, $docDir);
            setMergeCommitSha($pull->number, $pull->merge_commit_sha);
            cleanPullBuild($pull->number);
        }
    }
}


/**
 * 是否应该构建该 pull request
 * 触发条件：
 * - 全新
 * - hash 发生变化
 */
function shouldBuildPull($pull): bool {
    if (empty($pull)) {
        throw new \Exception("Pull Request 数据异常，为空");
    }
    $hash = getMergeCommitSha($pull->number);
    if (empty($hash)) {
        return true;
    }
    if ($hash !== $pull->merge_commit_sha) {
        return true;
    } else {
        return false;
    }
}

/**
 * 获取当前的 merge commit sha
 */
function getMergeCommitSha($number): string | false {
    $hashLogFile = "hash-pr-${number}.txt";
    if (file_exists($hashLogFile)) {
        $hash = file_get_contents($hashLogFile);
    } else {
        return false;
    }
    return $hash;
}
/**
 * 设置当前的 commit sha
 * 用于构建完成后记录历史
 */
function setMergeCommitSha($number, $sha): void {
    $hashLogFile = "hash-pr-${number}.txt";
    file_put_contents($hashLogFile, $sha);
}

function downloadPatchFile($fileName, $patchUrl) {
    $ch = curl_init($patchUrl);
    curl_setopt_array($ch, [
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_RETURNTRANSFER => true
    ]);
    $patch = curl_exec($ch);
    file_put_contents($fileName, $patch);
}

/**
 * 创建 Pull Request 代码库
 */
function createPullRepo($number, $patchFile) {
    $pullRepoDir = "zh-pr-${number}";
    exec("cp -a ./zh ${pullRepoDir}");
    exec("git -C ${pullRepoDir} apply ../${patchFile}");
}

function buildPull($number, $docDir) {
    $pullRepoDir = "zh-pr-${number}";
    ob_start();
    exec("php doc-base/configure.php --with-lang=zh --with-lang-dir=${pullRepoDir} --output=doc-base/${pullRepoDir}.manual.xml", $result, $returnCode);
    $content = ob_get_clean();
    $dest = '../web-php/manual/'.$pullRepoDir;
    /**
     * 如果 returnCode 是1，代表发生错误
     * 是 0 则为成功
     */
    if ($returnCode === 1) {
        throw new Exception("Can not create ${pullRepoDir} manual:".$content);
    } else if($returnCode === 0) {
        echo $pullRepoDir." manual validated\n";
    }
    
    exec("php phd/render.php --docbook doc-base/${pullRepoDir}.manual.xml --package PHP --format php --output=output-${pullRepoDir}", $result, $returnCode);
    if (file_exists($dest)) {
        // watch out!
        exec("rm -rf ${dest}");
    }
    symlink("../../${docDir}/output-${pullRepoDir}/php-web", $dest);
}

/**
 * 清理指定的 pull request 构建缓存文件
 */
function cleanPullBuild($number) {
    $pullRepoDir = "zh-pr-${number}";
    if (file_exists($pullRepoDir)) {
        // watch out!
        exec("rm -rf ${pullRepoDir}");
    }
    exec("rm -f doc-base/${pullRepoDir}.manual.xml");
    exec("rm -f pr-${number}.patch");
}
