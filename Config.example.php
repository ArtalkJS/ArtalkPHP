<?php
return [
  // 网站名
  'site_name' => 'XXX 的博客',
  // 支持跨域访问的域名
  'allow_origin' => [
    'http://localhost:8080' // 或 '*' 跨域无限制
  ],
  // 管理员用户
  'admin_users' => [
    ['nick' => 'admin', 'email' => 'admin@example.com', 'password' => '', 'badge_name' => '管理员', 'badge_color' => '#ffa928']
  ],
  // 验证码
  'captcha' => [
    'limit' => 3, // 评论次数（超过则需验证码；设置为 0 总是需要验证码）
    'timeout' => 4*60, // 超时（x 秒内，提交超过 限制评论次数 则需要验证码）
  ],
  // 邮件通知
  'email' => [
    'on' => true, // 总开关
    'admin_addr' => 'example@example.com', // 管理员邮箱地址（文章收到评论时通知）
    'sender_type' => 'smtp', // 发送方式（ali_dm or smtp）
    'mail_title' => '您在 [站名] 收到了新的回复',
    'mail_title_to_admin' => '您的文章收到了新的回复',
    'mail_tpl_name' => 'default.html', // 邮件模板文件（/email-tpl 目录下存放）
    'ali_dm' => [
      // https://help.aliyun.com/document_detail/29414.html
      'AccessKeyId' => '', // 阿里云颁发给用户的访问服务所用的密钥 ID
      'AccessKeySecret' => '', // 用于加密的密钥
      'AccountName' => 'example@example.com', // 管理控制台中配置的发信地址
    ],
    'smtp' => [
      'Host' => 'smtp.qq.com',
      'Port' => 465,
      'SMTPAuth' => true,
      'Username' => 'example@qq.com',
      'Password' => '',
      'SMTPSecure' => 'ssl',
      'FromAddr' => 'example@qq.com', // 发件人邮箱
      'FromName' => '站名', // 发件人显示的名称
    ]
  ]
];
