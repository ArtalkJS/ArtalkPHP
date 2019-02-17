<?php
return [
  // 支持跨域请求的域名
  'allow_origin' => [
    'http://localhost:8080'
  ],
  // 管理员用户
  'admin_users' => [
    ['nick' => 'admin', 'email' => 'admin@example.com', 'password' => '']
  ],
  // 验证码
  'captcha' => [
    'limit' => 3, // 评论次数（超过则需验证码）（设置为 0 一直需要验证码）
    'timeout' => 4*60, // 超时（x 秒内，提交超过 限制评论次数 则需要验证码）
  ]
];
