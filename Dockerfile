FROM php:8-fpm
ADD sources.list /etc/apt/
RUN apt-get update && apt-get install -y git
