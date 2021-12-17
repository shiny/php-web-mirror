# PHP.net 中文文档镜像

## 功能

1. 分钟级更新 Github 上的中英文文档
2. 为打开的 php/doc-zh Pull Request 自动构建一个 https://pr-[number].phpdoc.u301.com 的中文文档镜像

## 镜像地址：

- PHP 镜像地址： https://phpdoc.u301.com
- 中文文档 Pull Request 构建后的效果预览，例如 #131 的地址是：https://pr-131.phpdoc.u301.com

## 安装指南

1. 为 Nginx 配置 SSL 证书；同时可以在 `nginx-www.conf` 中更换域名
2. 设置 .env 内的 Github Token（用于提高 pull request 检测频率限制）
3. `docker-compose up`
4. 在容器内运行 `init-web-php.php` 初始化 php web 站点
5. 运行一遍 `manual/schedule.php`，能够拉下手册相关仓库；为 `doc-base/configure.php` 打上补丁（manual/configure.diff），支持 Pull Request 的构建
6. 设置定时运行 `manual/schedule.php`，能够自动刷新，并按需构建

## 如何强制刷新

移除 `manual/` 内 `hash-` 开通的 txt 即可在定时到后，重新生成相关文件

- hash-en.txt 英文版
- hash-zh.txt 中文版
- hash-pr-131.txt 中文手册 Pull Request #131 的 merge_commit_sha

## 构建通知

每次构建后，会在长毛象中发布 @php@tea.codes

