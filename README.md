# PHP.net 中文文档镜像

## 功能

1. 分钟级更新 Github 上的中英文文档
2. 为打开的 doc-zh Pull Request 自动构建一个 https://pr-[number].phpdoc.u301.com 的中文文档镜像

## 安装指南

1. 为 Nginx 配置 SSL 证书；同时可以在 `nginx-www.conf` 中更换域名
2. 设置 .env 内的 Github Token（用于提高 pull request 检测频率限制）
3. `docker-compose up`
4. 在容器内运行 `init-web-php.php` 初始化 php web 站点
5. 设置定时运行 `manual/schedule.php`，能够自动刷新，并按需构建
