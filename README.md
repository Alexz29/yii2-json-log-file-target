# yii2-json-log-file-target
Store log file as Json

[![Latest Stable Version](https://poser.pugx.org/urbanindo/yii2-json-log-file-target/v/stable.svg)](https://packagist.org/packages/urbanindo/yii2-json-log-file-target)
[![Total Downloads](https://poser.pugx.org/urbanindo/yii2-json-log-file-target/downloads.svg)](https://packagist.org/packages/urbanindo/yii2-json-log-file-target)
[![Latest Unstable Version](https://poser.pugx.org/urbanindo/yii2-json-log-file-target/v/unstable.svg)](https://packagist.org/packages/urbanindo/yii2-json-log-file-target)
[![Build Status](https://travis-ci.org/urbanindo/yii2-json-log-file-target.svg)](https://travis-ci.org/urbanindo/yii2-json-log-file-target)
[![codecov](https://codecov.io/gh/urbanindo/yii2-json-log-file-target/branch/master/graph/badge.svg)](https://codecov.io/gh/urbanindo/yii2-json-log-file-target)

## Usage

```php
'components' => [
    'log' => [
        'targets' => [
            [
                'class' => JsonFileTarget::class,
                'levels' => ['error', 'warning'],
                'decodeMessage' => false,
                'maskVars' =>[
                    'context._COOKIE.PHPSESSID',
                    'context._SERVER.HTTP_COOKIE',
                    'sessionId'
                ]
            ]
        ]
    ]
]
```

Yii version 2.0.16
Support [$maskVars](https://www.yiiframework.com/doc/api/2.0/yii-log-target#$maskVars-detail)

