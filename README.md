# Artalk Server PHP

> [Artalk](https://artalk.js.org) 的后端，PHP 版

- 轻巧（使用 JSON 文件存储数据）
- 支持 SMTP / 阿里云DM(Http API) / [qwqaq-email-server](https://github.com/qwqcode/qwqaq-email-server) 发送邮件通知
- 管理员防冒
- 提交频繁验证码

## 需求

- PHP >=7.1.3
- ~~MySql~~（无需 SQL 数据库，JSON 文件存储数据）

## 部署

```bash
git clone https://github.com/qwqcode/ArtalkServerPhp.git
composer install
php -r "copy('Config.example.php', 'Config.php');"
```

之后：

1. 打开 `/Config.php` 文件，按照注释来配置
2. 修改前端页面 Artalk 配置 `serverUrl` 为文件 `/public/index.php` 外部可访问的 URL，例如：

```js
new Artalk({
  // ...
  serverUrl: 'https://example.com/index.php'
});
```

## 注意事项

### 安全性

> 您需要阻止用户访问 `/data/` 这个目录，因为该目录下的文件中包含用户的个人信息：邮箱、IP 地址 等...

通用方法

```
若本程序存在于单独的域名下，您可以直接设置网站根目录为 /public
```

Apache 配置

```conf
RewriteEngine on
RewriteRule ^data/* - [F,L]
```

Nginx 配置

```conf
location ~ /data/.* {
   deny all;
   return 403;
}
```

## 开发

1. 命令行敲入 `composer dev`
2. 浏览器访问 http://localhost:23366
